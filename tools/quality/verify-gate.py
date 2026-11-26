"""Fail-closed F0-04 aggregate over verified Git selection and safe artifacts.

The caller supplies real workflow needs results, not conclusions read from
reports. No artifact content is executed. Hosted download/extraction and
owned directory cleanup are separate mandatory workflow steps.
"""
from collections import Counter
from datetime import datetime, timedelta, timezone
import hashlib
import json
import math
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import xml.etree.ElementTree as ET

sys.path.insert(0, str(Path(__file__).resolve().parent))
from classify import LANES, ROOT, CONTRACT, classify, git, git_text, hosted_identity
# check-docs.py has a hyphen, so load the reviewed local helper explicitly.
import importlib.util
_spec = importlib.util.spec_from_file_location("f04_docs_gate", Path(__file__).with_name("check-docs.py"))
_docs = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_docs)

MAX_FILE = 16 * 1024 * 1024
MAX_SUMMARY = 1024 * 1024
MAX_TOTAL = 80 * 1024 * 1024
SELECTIONS = {"bootstrap": ("foundation", "fast-all", "architecture"),
              "native-integration": ("integration",)}
MIN_TESTS = {"foundation": 31, "fast-all": 98, "architecture": 5, "integration": 34}
PAYLOADS = {
    "docs": ("docs.summary.json",),
    "format": ("format-diagnostic.json",),
    "analysis": ("analysis-diagnostic.json",),
    "contracts": ("preflight.summary.json", "redocly.summary.json"),
    "dependency-audit": ("composer-audit.summary.json", "npm-audit.summary.json"),
    "image-scan": ("scan.summary.json",),
    "spec-kit": ("spec-kit.summary.json",),
    **{lane: tuple(name for selection in selections
                   for name in (selection + ".summary.json", selection + "-safe.xml"))
       for lane, selections in SELECTIONS.items()},
}
VERSIONS = {"docs": {"git": None}, "format": {"pint": "1.32.3"},
            "analysis": {"phpstan": "2.3.7", "larastan": "3.15.0"},
            "contracts": {"redocly": "2.83.0", "node": "24.22.3"},
            "dependency-audit": {"composer": "2.11.3", "npm": None},
            "image-scan": {"trivy": "0.76.2"}, "bootstrap": {"php": None},
            "native-integration": {"php": None}, "spec-kit": {"python": None, "pyyaml": "6.0.2"}}
VERSION = re.compile(r"v?[0-9]{1,8}(?:\.[0-9]{1,8}){1,3}(?:[-+][A-Za-z0-9.-]{1,32})?\Z")
HEX = re.compile(r"[a-f0-9]{64}\Z")
COUNT_KEYS = {"tests", "assertions", "failures", "errors", "skipped"}
FINDING_KEYS = {"errors", "warnings", "violations", "advisories", "critical", "high", "medium", "low", "unknown", "info"}
AUDIT_PINS = {
    "composer": ("composer.lock", "9028f6191bf4f725c84890c28a0113c62b5ba0d9fc548559b8b33341ed4520f5"),
    "npm": ("tools/quality/openapi/package-lock.json", "a987d3b6bef34c77a4d1acc7bdbdeb163e82f3a87aef0dfa15abd004765466a0"),
}
IMAGES = (
    "docker.io/library/postgres:17.12-bookworm@sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03",
    "docker.io/library/redis:8.2.11-bookworm@sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87",
)
PREFLIGHT_CASES = """fixture-control components-only-root valid-local-pointer valid-escaped-pointer valid-local-fragment valid-recursive-pointer valid-cross-file-cycle empty-root-registry missing-tracked-root extra-tracked-root renamed-tracked-root duplicate-root symlink-root untracked-fragment symlink-fragment wrong-oas-version malformed-json duplicate-json-key escaped-duplicate-json-key malformed-root-shape missing-info non-string-ref absolute-ref network-path-ref remote-ref file-scheme-ref encoded-path-ref backslash-ref escaping-traversal-ref unregistered-tracked-ref unresolved-pointer unresolved-anchor unsupported-id-rebasing unsupported-dynamic-ref unsupported-schema-dialect file-size-bound depth-bound document-count-bound""".split()
REDOCLY_CASES = """canonical-lint local-ref-lint structural-negative unresolved-ref-negative canonical-bundle-revalidate local-bundle-revalidate missing-bundle-negative malformed-bundle-negative integration-review-policy""".split()


def require(condition):
    if not condition:
        raise ValueError("INVALID_GATE_EVIDENCE")


def exact_keys(value, keys):
    require(isinstance(value, dict) and set(value) == set(keys))


def count(value, minimum=0, maximum=1000000):
    require(type(value) is int and minimum <= value <= maximum)
    return value


def number(value, maximum=86400):
    require(type(value) in (int, float) and math.isfinite(value) and 0 <= value <= maximum)
    return value


def digest(data):
    return hashlib.sha256(data).hexdigest()


def equal(actual, expected):
    # Python considers True == 1; JSON equality must preserve scalar types.
    require(json.dumps(actual, sort_keys=True, allow_nan=False) ==
            json.dumps(expected, sort_keys=True, allow_nan=False))


def no_canary(text, canaries):
    require(isinstance(text, str) and not any(marker in text for marker in canaries))


def json_value(raw, canaries=()):
    def unique(pairs):
        result = {}
        for key, value in pairs:
            require(key not in result)
            result[key] = value
        return result

    def walk(value, depth=0):
        require(depth <= 16)
        if isinstance(value, dict):
            for key, child in value.items():
                no_canary(key, canaries)
                walk(child, depth + 1)
        elif isinstance(value, list):
            for child in value:
                walk(child, depth + 1)
        elif isinstance(value, str):
            no_canary(value, canaries)
        elif type(value) is float:
            require(math.isfinite(value))

    text = raw.decode("utf-8")
    no_canary(text, canaries)
    result = json.loads(text, object_pairs_hook=unique, parse_constant=lambda _: require(False))
    walk(result)
    require(isinstance(result, dict))
    return result


def artifact_name(lane, identity):
    prefix = {"bootstrap": "f03-fast", "native-integration": "f03-integration"}.get(lane, "f04-" + lane)
    return prefix + "-" + identity["runId"] + "-" + str(identity["runAttempt"])


def directory(path, private=False):
    s = path.lstat()
    require(stat.S_ISDIR(s.st_mode) and not path.is_symlink() and path.resolve() == path
            and s.st_uid == os.geteuid() and s.st_mode & 0o022 == 0)
    if private:
        require(stat.S_IMODE(s.st_mode) == 0o700)
    return path


def read_file(path, limit):
    before = path.lstat()
    require(stat.S_ISREG(before.st_mode) and before.st_nlink == 1
            and before.st_uid == os.geteuid() and before.st_mode & 0o022 == 0
            and 0 < before.st_size <= limit and path.resolve() == path)
    fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_NONBLOCK)
    with os.fdopen(fd, "rb") as stream:
        current = os.fstat(stream.fileno())
        require((current.st_dev, current.st_ino, current.st_size, current.st_mtime_ns) ==
                (before.st_dev, before.st_ino, before.st_size, before.st_mtime_ns))
        raw = stream.read(limit + 1)
        after = os.fstat(stream.fileno())
        require(len(raw) == before.st_size and after.st_mtime_ns == before.st_mtime_ns
                and after.st_size == before.st_size)
    return raw


def read_artifact(path, lane, canaries):
    directory(path)
    expected = {"lane.summary.json", *PAYLOADS[lane]}
    require({child.name for child in path.iterdir()} == expected)
    raw = {name: read_file(path / name, MAX_SUMMARY if name == "lane.summary.json" else MAX_FILE)
           for name in expected}
    require(sum(map(len, raw.values())) <= MAX_TOTAL)
    for value in raw.values():
        no_canary(value.decode("utf-8"), canaries)
    return raw


def lane_summary(raw, lane, identity, decision, canaries):
    summary = json_value(raw["lane.summary.json"], canaries)
    expected = {key: value for key, value in identity.items() if key != "baseRef"}
    expected.update(job=lane, required=True, selectionReason=decision["selectionReason"])
    exact_keys(summary, set(expected) | {"status", "runnerStarted", "exitCode", "versions", "durationSeconds",
                                         "selectedCount", "validatedCount", "artifacts", "findingCounts"})
    for key, value in expected.items():
        equal(summary[key], value)
    require(summary["status"] == "PASSED" and summary["runnerStarted"] is True
            and type(summary["exitCode"]) is int and summary["exitCode"] == 0)
    number(summary["durationSeconds"], 3600)
    versions = summary["versions"]
    exact_keys(versions, VERSIONS[lane])
    for tool, pin in VERSIONS[lane].items():
        require(isinstance(versions[tool], str) and VERSION.fullmatch(versions[tool])
                and (pin is None or versions[tool] == pin))
    if summary["selectedCount"] is None:
        require(summary["validatedCount"] is None)
    else:
        require(count(summary["selectedCount"], 1) == count(summary["validatedCount"], 1))
    findings = summary["findingCounts"]
    require(isinstance(findings, dict) and set(findings) <= FINDING_KEYS)
    for key, value in findings.items():
        count(value)
        if key in {"errors", "violations", "advisories", "critical", "high", "unknown"}:
            require(value == 0)
    equal(summary["artifacts"], {name: digest(raw[name]) for name in PAYLOADS[lane]})
    return versions


def report_identity(report, identity):
    for key in ("sourceSha", "baseSha", "checkoutSha", "runId", "runAttempt"):
        expected = str(identity[key]) if key == "runAttempt" else identity[key]
        equal(report[key], expected)


def php_inventory(repo, revision):
    raw = git(repo, "ls-tree", "-r", "-z", revision)
    require(raw.endswith(b"\0"))
    paths = []
    for row in raw[:-1].split(b"\0"):
        meta, name = row.split(b"\t", 1)
        path = name.decode("utf-8")
        if path == "artisan" or path.endswith(".php"):
            require(meta.startswith((b"100644 blob ", b"100755 blob ")))
            paths.append(path)
    require(len(paths) <= 10000 and len(paths) == len(set(paths)))
    return sorted(paths)


def tool_report(report, lane, repo, identity):
    report_identity(report, identity)
    paths = php_inventory(repo, identity["checkoutSha"])
    require(paths)
    inventory_hash = digest(json.dumps(paths, ensure_ascii=False, separators=(",", ":")).encode())
    common = {"schemaVersion", "task", "runId", "runAttempt", "sourceSha", "baseSha", "checkoutSha",
              "status", "stage", "toolExit", "selectedCount", "inventorySha256", "selectedPaths",
              "findingCount", "trackedSourceUnchanged", "durationSeconds"}
    extra = {"pint", "newStrictTypesPaths", "findingPaths"} if lane == "format" else {
        "level", "phpstan", "larastan", "launcher", "resultValidation", "findings", "ownedChildCleanupVerified"}
    exact_keys(report, common | extra)
    require(type(report["schemaVersion"]) is int and report["schemaVersion"] == 1
            and report["status"] == "PASSED" and report["stage"] == "complete"
            and type(report["toolExit"]) is int and report["toolExit"] == 0
            and report["trackedSourceUnchanged"] is True and count(report["findingCount"]) == 0
            and count(report["selectedCount"], 1) == len(paths))
    equal(report["selectedPaths"], paths)
    equal(report["inventorySha256"], inventory_hash)
    number(report["durationSeconds"], 3600)
    if lane == "format":
        require(report["task"] == "T016" and report["pint"] == "1.32.3" and report["findingPaths"] == [])
        old = set(php_inventory(repo, identity["baseSha"]))
        equal(report["newStrictTypesPaths"], [path for path in paths if path not in old])
    else:
        require(report["task"] == "T019-launcher" and report["level"] == 6
                and report["phpstan"] == "2.3.7" and report["larastan"] == "3.15.0"
                and report["launcher"] == "tools/quality/run.php analyse"
                and report["resultValidation"] == "VERIFIED" and report["findings"] == []
                and report["ownedChildCleanupVerified"] is True)


def blob(repo, revision, path):
    return git(repo, "cat-file", "blob", revision + ":" + path, limit=MAX_FILE)


def audit_report(report, tool, repo, identity, version):
    exact_keys(report, {"status", "findingCount", "findings", "tool", "version", "lockSha256",
                        "installedCount", "nativeExit", "ownedCleanup"})
    path, approved_hash = AUDIT_PINS[tool]
    raw_lock = blob(repo, identity["checkoutSha"], path)
    require(digest(raw_lock) == approved_hash == report["lockSha256"])
    require(report["status"] == "PASSED" and report["tool"] == tool
            and report["version"] == version and report["ownedCleanup"] is True
            and count(report["nativeExit"]) == 0 and count(report["findingCount"]) == 0
            and report["findings"] == [])
    lock = json_value(raw_lock)
    expected = len(lock["packages"]) + len(lock["packages-dev"]) if tool == "composer" else len(lock["packages"]) - 1
    require(count(report["installedCount"], 1) == expected)
    if tool == "composer":
        require(version == "2.11.3" and expected == 122)
    else:
        require(re.fullmatch(r"\d+\.\d+\.\d+", version) and tuple(map(int, version.split("."))) >= (10, 9, 2))


def controls(rows, expected):
    equal(rows, [{"id": name, "status": "PASSED"} for name in expected])


def contract_reports(pre, red, repo, identity):
    report_identity(pre, identity)
    report_identity(red, identity)
    require(pre["status"] == "PASSED" and pre["stage"] == "complete"
            and pre["task"] == "T020" and pre["suite"] == "T020-preflight"
            and pre["node"] == "24.22.3" and pre["implementationPresent"] is True
            and pre["trackedSourceUnchanged"] is True and count(pre["toolExit"]) == 0
            and count(pre["total"]) == count(pre["passed"]) == len(PREFLIGHT_CASES)
            and all(count(pre[key]) == 0 for key in ("failed", "networkAttempts", "deniedReads"))
            and count(pre["permittedReads"], 1) > 0
            and pre["testSha256"] == digest(blob(repo, identity["checkoutSha"], "tools/quality/openapi/preflight.test.mjs")))
    controls(pre["results"], PREFLIGHT_CASES)
    require(red["status"] == "PASSED" and red["suite"] == "T021-redocly"
            and red["node"] == "v24.22.3" and red["redocly"] == "2.83.0"
            and red["networkIsolation"] == "linux-netns-unprivileged-child"
            and red["offlineProbePassed"] is True and red["integrationPresent"] is True
            and red["ownedCleanup"] is True and red["canonicalBundleEquivalent"] is True
            and red["lockSha256"] == AUDIT_PINS["npm"][1])
    controls(red["results"], REDOCLY_CASES)
    current = git_text(repo, "rev-parse", identity["checkoutSha"] + ":" + CONTRACT)
    previous = git_text(repo, "rev-parse", identity["baseSha"] + ":" + CONTRACT)
    require(red["contractBlob"] == current and red["canonicalUnchanged"] is (current == previous))
    # Current approved inventory contains one canonical root, no fragments.
    equal(red["checkedRoots"], [CONTRACT])
    equal(red["checkedFiles"], [CONTRACT])
    audit_report(red["npmAudit"], "npm", repo, identity, red["npm"])


def scan_report(report, identity):
    report_identity(report, identity)
    require(report["status"] == "PASSED" and report["stage"] == "complete"
            and report["task"] == "T032-T034" and report["scannerVersion"] == "0.76.2"
            and report["archiveSha256"] == "bc0fc726e017b8927a62b2d3531d7a84ee01769daf5c99ed866dced54089fdbf"
            and report["checksumFileSha256"] == "802e4ac1418ed967cd3c4b901f4f71ca00b7363ede67778665a22d9f13f58c83"
            and HEX.fullmatch(report["databaseSha256"]) and report["ownedCleanup"] is True
            and report["trackedSourceUnchanged"] is True and report["candidateImages"] == [])
    require(isinstance(report["images"], list) and len(report["images"]) == len(IMAGES))
    databases = []
    for item, target in zip(report["images"], IMAGES):
        require(item["target"] == target and item["status"] == "PASSED"
                and count(item["nativeExit"]) == 0 and count(item["exceptionCount"]) == 0
                and count(item["packageCount"], 1) >= count(item["osPackageCount"], 1)
                and isinstance(item["findings"], list) and len(item["findings"]) == count(item["findingCount"]))
        severities = Counter()
        for finding in item["findings"]:
            exact_keys(finding, {"id", "package", "installedVersion", "fixedVersion", "severity"})
            require(finding["severity"] in ("LOW", "MEDIUM"))
            for key in ("id", "package", "installedVersion", "fixedVersion"):
                require(isinstance(finding[key], str) and len(finding[key]) <= 400
                        and (key == "fixedVersion" or finding[key]))
            severities[finding["severity"]] += 1
        equal(item["severityCounts"], dict(severities))
        database = item["database"]
        exact_keys(database, {"version", "updatedAt", "nextUpdate", "downloadedAt"})
        require(type(database["version"]) is int and database["version"] == 2)
        updated, next_update, downloaded = (datetime.fromisoformat(database[key]) for key in
                                              ("updatedAt", "nextUpdate", "downloadedAt"))
        now = datetime.now(timezone.utc)
        require(updated <= downloaded <= now < next_update and now - updated <= timedelta(hours=24))
        databases.append(database)
    equal(databases[0], databases[1])


def native_report(raw, selection, identity, version, canaries):
    report = json_value(raw[selection + ".summary.json"], canaries)
    exact_keys(report, {"selection", "status", "exit_code", "runner_started", "tests_executed", "selection_verified",
                        "runner_exit", "source_sha", "base_sha", "checkout_sha", "run_id", "run_attempt",
                        "php", "duration_seconds", "counts"})
    for key, source in (("source_sha", "sourceSha"), ("base_sha", "baseSha"),
                        ("checkout_sha", "checkoutSha"), ("run_id", "runId"), ("run_attempt", "runAttempt")):
        equal(report[key], str(identity[source]) if source == "runAttempt" else identity[source])
    require(report["selection"] == selection and report["status"] == "PASSED"
            and count(report["exit_code"]) == count(report["runner_exit"]) == 0
            and all(report[key] is True for key in ("runner_started", "tests_executed", "selection_verified"))
            and report["php"] == version and version.startswith("8.4."))
    number(report["duration_seconds"])
    counts = report["counts"]
    exact_keys(counts, COUNT_KEYS | {"duration"})
    require(count(counts["tests"], MIN_TESTS[selection], 100000) > 0 and count(counts["assertions"], 1) > 0)
    require(all(count(counts[key]) == 0 for key in ("failures", "errors", "skipped")))
    number(counts["duration"])
    xml = raw[selection + "-safe.xml"].decode("utf-8")
    no_canary(xml, canaries)
    # Safe reports are a single flattened suite. Do not parse declarations
    # other than the optional UTF-8 XML header, external entities or PIs.
    body = re.sub(r'\A<\?xml version="1\.0" encoding="UTF-8"\?>\s*', "", xml)
    require("<!" not in body and "<?" not in body)
    root = ET.fromstring(body)
    require(root.tag == "testsuites" and not root.attrib and len(root) == 1
            and not (root.text or "").strip() and not (root.tail or "").strip())
    suite = root[0]
    require(suite.tag == "testsuite" and set(suite.attrib) == COUNT_KEYS | {"name", "time"}
            and suite.attrib["name"] == selection and not (suite.text or "").strip()
            and not (suite.tail or "").strip())
    for key in COUNT_KEYS:
        require(suite.attrib[key] == str(counts[key]))
    suite_time = number(float(suite.attrib["time"]))
    cases, assertions, duration, postgres, redis = set(), 0, 0.0, 0, 0
    for case in suite:
        require(case.tag == "testcase" and not len(case) and not (case.text or "").strip()
                and not (case.tail or "").strip() and set(case.attrib) == {"name", "classname", "assertions", "time"})
        name, klass = case.attrib["name"], case.attrib["classname"]
        no_canary(name, canaries)
        no_canary(klass, canaries)
        require(0 < len(name) <= 2048 and 0 < len(klass) <= 512
                and not re.search(r"[\x00-\x1f\x7f]", name + klass))
        klass = klass.replace(".", "\\")
        key = (klass, name)
        require(key not in cases)
        cases.add(key)
        require(re.fullmatch(r"0|[1-9][0-9]{0,7}", case.attrib["assertions"]))
        assertions += count(int(case.attrib["assertions"]))
        duration += number(float(case.attrib["time"]))
        integration = "\\Integration\\" in klass
        if selection == "integration":
            require(integration)
            postgres += int(klass.endswith("\\PostgreSqlIsolationTest"))
            redis += int(klass.endswith("\\RedisIsolationTest"))
        else:
            require(not integration and (selection != "architecture" or "\\Architecture\\" in klass))
    require(len(cases) == counts["tests"] and assertions == counts["assertions"])
    tolerance = len(cases) * 0.000001 + 0.000002
    require(abs(suite_time - counts["duration"]) <= tolerance and abs(duration - suite_time) <= tolerance)
    if selection == "integration":
        require(postgres >= 26 and redis >= 8)


def spec_report(report, identity, versions):
    report_identity(report, identity)
    exact_keys(report, {"schemaVersion", "sourceSha", "baseSha", "checkoutSha", "runId", "runAttempt",
                        "status", "testCount", "failedCount", "errorCount", "skippedCount", "cases",
                        "python", "pyyaml", "trackedSourceUnchanged", "shellSyntaxPassed"})
    require(report["status"] == "PASSED" and type(report["schemaVersion"]) is int and report["schemaVersion"] == 1
            and report["python"] == versions["python"] and report["python"].startswith("3.12.")
            and report["pyyaml"] == "6.0.2" and report["trackedSourceUnchanged"] is True
            and report["shellSyntaxPassed"] is True
            and all(count(report[key]) == 0 for key in ("failedCount", "errorCount", "skippedCount")))
    cases = report["cases"]
    require(isinstance(cases, list) and len(cases) == count(report["testCount"], 60, 10000)
            and all(isinstance(case, str) and re.fullmatch(r"[A-Za-z0-9_.-]{1,512}", case) for case in cases)
            and cases == sorted(set(cases)))


def validate_lane(repo, path, lane, identity, decision, canaries):
    raw = read_artifact(path, lane, canaries)
    versions = lane_summary(raw, lane, identity, decision, canaries)
    validate_payloads(repo, raw, lane, identity, versions, canaries)


def validate_payloads(repo, raw, lane, identity, versions, canaries=()):
    def data(name):
        return json_value(raw[name], canaries)
    if lane == "docs":
        actual = _docs.check_docs(repo, identity)
        require(actual["status"] == "PASSED")
        equal(data("docs.summary.json"), actual)
    elif lane in ("format", "analysis"):
        tool_report(data(lane + "-diagnostic.json"), lane, repo, identity)
    elif lane == "contracts":
        contract_reports(data("preflight.summary.json"), data("redocly.summary.json"), repo, identity)
    elif lane == "dependency-audit":
        for tool in ("composer", "npm"):
            audit_report(data(tool + "-audit.summary.json"), tool, repo, identity, versions[tool])
    elif lane == "image-scan":
        scan_report(data("scan.summary.json"), identity)
    elif lane in SELECTIONS:
        for selection in SELECTIONS[lane]:
            native_report(raw, selection, identity, versions["php"], canaries)
    elif lane == "spec-kit":
        spec_report(data("spec-kit.summary.json"), identity, versions)
    else:
        require(False)


def verify_gate(repo, identity, classification, outcomes, artifacts, *, canaries=(), gate_state="running"):
    result = {"status": "BLOCKED", "lanes": {}, "reason": "INVALID_GATE_INPUT"}
    try:
        require(gate_state == "running" and isinstance(canaries, (tuple, list)) and len(canaries) <= 64
                and all(isinstance(v, str) and 0 < len(v) <= 1024 for v in canaries))
        actual = classify(repo, identity)
        require(actual["status"] == "PASSED")
        equal(classification, actual)
        exact_keys(outcomes, {"classify", *LANES})
        require(outcomes["classify"] == "success")
        root = directory(Path(artifacts).absolute(), private=True)
        expected = {artifact_name(lane, identity) for lane, decision in actual["lanes"].items() if decision["required"]}
        require({p.name for p in root.iterdir()} <= expected)
        result = {"status": "PASSED", "identity": identity, "selectionReason": actual["selectionReason"], "lanes": {}}
        for lane in LANES:
            decision = actual["lanes"][lane]
            outcome = outcomes[lane]
            if not decision["required"]:
                status, reason = ("NOT_APPLICABLE", decision["selectionReason"]) if outcome == "skipped" else ("FAILED", "UNEXPECTED_OPTIONAL_JOB_RESULT")
            elif outcome != "success":
                status = "BLOCKED" if outcome in ("skipped", "cancelled", "blocked") else "FAILED"
                reason = "PREREQUISITE_NOT_PASSED" if lane == "native-integration" and outcomes["bootstrap"] != "success" else "REQUIRED_JOB_NOT_SUCCESS"
            else:
                try:
                    validate_lane(repo, root / artifact_name(lane, identity), lane, identity, decision, canaries)
                    status, reason = "PASSED", "VALIDATED_SAME_CANDIDATE_EVIDENCE"
                except (OSError, ValueError, KeyError, TypeError, AttributeError, OverflowError, RecursionError,
                        subprocess.SubprocessError, ET.ParseError):
                    status, reason = "FAILED", "MISSING_OR_INVALID_REQUIRED_EVIDENCE"
            result["lanes"][lane] = {"status": status, "reason": reason}
        if any(lane["status"] == "FAILED" for lane in result["lanes"].values()):
            result["status"] = "FAILED"
        elif any(lane["status"] == "BLOCKED" for lane in result["lanes"].values()):
            result["status"] = "BLOCKED"
    except (OSError, ValueError, KeyError, TypeError, AttributeError, OverflowError, RecursionError,
            subprocess.SubprocessError):
        return {"status": "BLOCKED", "lanes": {}, "reason": "INVALID_GATE_INPUT"}
    return result


if __name__ == "__main__":
    try:
        require(len(sys.argv) == 2)
        identity = hosted_identity()
        needs = json_value(os.environ["F04_NEEDS_JSON"].encode("utf-8"))
        exact_keys(needs, {"classify", *LANES})
        classification = json_value(needs["classify"]["outputs"]["classification"].encode("utf-8"))
        outcomes = {key: value["result"] for key, value in needs.items()}
        root = Path(sys.argv[1]).absolute()
        require(root.parent == Path(os.environ["RUNNER_TEMP"]).resolve()
                and re.fullmatch(r"f04-gate-[A-Za-z0-9]{12}", root.name))
        result = verify_gate(ROOT, identity, classification, outcomes, root)
    except (OSError, ValueError, KeyError, TypeError, AttributeError, OverflowError, RecursionError):
        result = {"status": "BLOCKED", "lanes": {}, "reason": "INVALID_HOSTED_GATE_INPUT"}
    print(json.dumps(result, sort_keys=True))
    sys.exit({"PASSED": 0, "FAILED": 1, "BLOCKED": 2}[result["status"]])
