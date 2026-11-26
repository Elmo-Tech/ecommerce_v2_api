"""T037/T038 test-first aggregate and artifact boundaries.

These are owned scratch fixtures, not evidence of hosted artifact transport.
All candidates are real local Git histories; the final verifier must recompute
selection, use actual job conclusions, and independently inspect evidence.
"""
from datetime import datetime, timedelta, timezone
import hashlib
import json
from pathlib import Path
import shutil
import tempfile
import unittest

import test_gate_quality as fixtures

LANES = fixtures.LANES
SELECTIONS = {"bootstrap": ("foundation", "fast-all", "architecture"),
              "native-integration": ("integration",)}
TOOLS = {"docs": {"git": "2.56.1"}, "format": {"pint": "1.32.3"},
         "analysis": {"phpstan": "2.3.7", "larastan": "3.15.0"},
         "contracts": {"redocly": "2.83.0", "node": "24.22.3"}, "dependency-audit": {"composer": "2.11.3", "npm": "11.0.0"},
         "image-scan": {"trivy": "0.76.2"}, "bootstrap": {"php": "8.4.1"},
         "native-integration": {"php": "8.4.1"}, "spec-kit": {"python": "3.12.0", "pyyaml": "6.0.2"}}
CANARY = "F04_TEST_ONLY_CREDENTIAL_CANARY"


class AggregateQuality(unittest.TestCase):
    # Reuse the existing actual-Git fixture without inheriting its test cases.
    git = fixtures.GateQuality.git
    write = fixtures.GateQuality.write
    ledger = fixtures.GateQuality.ledger
    helper = fixtures.GateQuality.helper

    def put(self, path, data):
        path.write_text(json.dumps(data, sort_keys=True), encoding="utf-8")

    def get(self, path):
        return json.loads(path.read_text(encoding="utf-8"))

    def artifact(self, root, lane, identity):
        prefix = {"bootstrap": "f03-fast", "native-integration": "f03-integration"}.get(lane, "f04-" + lane)
        return root / (prefix + "-" + identity["runId"] + "-" + str(identity["runAttempt"]))

    def history(self, change):
        # Actual locked source blobs, not invented package provenance.
        extra = {name: (fixtures.ROOT / name).read_text(encoding="utf-8") for name in (
            "composer.lock", "tools/quality/openapi/package-lock.json", "tools/quality/openapi/preflight.test.mjs")}
        return fixtures.GateQuality.history(self, change, extra_base=extra)

    def native(self, directory, selection, identity):
        minimum = {"foundation": 31, "fast-all": 98, "architecture": 5, "integration": 34}[selection]
        counts = {"tests": minimum, "assertions": 2 * minimum, "failures": 0, "errors": 0,
                  "skipped": 0, "duration": 0.01 * minimum}
        self.put(directory / (selection + ".summary.json"), {
            "selection": selection, "status": "PASSED", "exit_code": 0,
            "runner_started": True, "tests_executed": True, "selection_verified": True,
            "runner_exit": 0, "source_sha": identity["sourceSha"], "base_sha": identity["baseSha"],
            "checkout_sha": identity["checkoutSha"], "run_id": identity["runId"],
            "run_attempt": str(identity["runAttempt"]), "php": "8.4.1",
            "duration_seconds": 0.02 * minimum, "counts": counts})
        cases = []
        for i in range(minimum):
            if selection == "integration":
                klass = "Tests.Integration." + ("PostgreSqlIsolationTest" if i < 26 else "RedisIsolationTest")
            elif selection == "architecture":
                klass = "Tests.Architecture.FixtureCase"
            else:
                klass = "Tests.Unit.FixtureCase"
            cases.append('<testcase name="testFixture' + str(i) + '" classname="' + klass
                         + '" assertions="2" time="0.010000"/>')
        (directory / (selection + "-safe.xml")).write_text(
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<testsuites><testsuite name="' + selection + '" tests="' + str(minimum) + '" assertions="'
            + str(2 * minimum) + '" failures="0" errors="0" skipped="0" time="'
            + format(counts["duration"], ".6f") + '">' + "".join(cases) + '</testsuite></testsuites>\n',
            encoding="utf-8")

    def lane_payload(self, lane, directory, repo, identity):
        # Synthetic structured controls model the reviewed producer schemas;
        # none claim the actual tools ran. Job conclusions are synthetic too.
        common = {key: identity[key] for key in ("sourceSha", "baseSha", "checkoutSha", "runId")}
        common.update(runAttempt=str(identity["runAttempt"]), schemaVersion=1, status="PASSED")

        def audit(tool):
            path = "composer.lock" if tool == "composer" else "tools/quality/openapi/package-lock.json"
            raw = (repo / path).read_bytes()
            lock = json.loads(raw)
            installed = len(lock["packages"]) + len(lock["packages-dev"]) if tool == "composer" else len(lock["packages"]) - 1
            return {"status": "PASSED", "findingCount": 0, "findings": [], "tool": tool,
                    "version": TOOLS["dependency-audit"][tool], "lockSha256": hashlib.sha256(raw).hexdigest(),
                    "installedCount": installed, "nativeExit": 0, "ownedCleanup": True}

        if lane in ("format", "analysis"):
            paths = sorted(path for path in self.git(repo, "ls-files").splitlines() if path == "artisan" or path.endswith(".php"))
            report = {**common, "stage": "complete", "toolExit": 0, "selectedCount": len(paths),
                      "inventorySha256": hashlib.sha256(json.dumps(paths, separators=(",", ":")).encode()).hexdigest(),
                      "selectedPaths": paths, "findingCount": 0, "trackedSourceUnchanged": True,
                      "durationSeconds": 0.01}
            if lane == "format":
                report.update(task="T016", pint="1.32.3", newStrictTypesPaths=[], findingPaths=[])
            else:
                report.update(task="T019-launcher", level=6, phpstan="2.3.7", larastan="3.15.0",
                              launcher="tools/quality/run.php analyse", resultValidation="VERIFIED",
                              findings=[], ownedChildCleanupVerified=True)
            self.put(directory / (lane + "-diagnostic.json"), report)
        elif lane == "contracts":
            preflight = """fixture-control components-only-root valid-local-pointer valid-escaped-pointer valid-local-fragment valid-recursive-pointer valid-cross-file-cycle empty-root-registry missing-tracked-root extra-tracked-root renamed-tracked-root duplicate-root symlink-root untracked-fragment symlink-fragment wrong-oas-version malformed-json duplicate-json-key escaped-duplicate-json-key malformed-root-shape missing-info non-string-ref absolute-ref network-path-ref remote-ref file-scheme-ref encoded-path-ref backslash-ref escaping-traversal-ref unregistered-tracked-ref unresolved-pointer unresolved-anchor unsupported-id-rebasing unsupported-dynamic-ref unsupported-schema-dialect file-size-bound depth-bound document-count-bound""".split()
            redocly = """canonical-lint local-ref-lint structural-negative unresolved-ref-negative canonical-bundle-revalidate local-bundle-revalidate missing-bundle-negative malformed-bundle-negative integration-review-policy""".split()
            self.put(directory / "preflight.summary.json", {**common, "stage": "complete", "task": "T020",
                     "suite": "T020-preflight", "node": "24.22.3", "implementationPresent": True,
                     "trackedSourceUnchanged": True, "toolExit": 0, "total": len(preflight),
                     "passed": len(preflight), "failed": 0, "networkAttempts": 0, "deniedReads": 0,
                     "permittedReads": 1, "testSha256": hashlib.sha256((repo / "tools/quality/openapi/preflight.test.mjs").read_bytes()).hexdigest(),
                     "results": [{"id": case, "status": "PASSED"} for case in preflight]})
            current = self.git(repo, "rev-parse", "HEAD:" + fixtures.CONTRACT)
            previous = self.git(repo, "rev-parse", identity["baseSha"] + ":" + fixtures.CONTRACT)
            npm = audit("npm")
            self.put(directory / "redocly.summary.json", {**common, "suite": "T021-redocly",
                     "node": "v24.22.3", "redocly": "2.83.0", "networkIsolation": "linux-netns-unprivileged-child",
                     "offlineProbePassed": True, "integrationPresent": True, "ownedCleanup": True,
                     "canonicalBundleEquivalent": True, "lockSha256": npm["lockSha256"],
                     "contractBlob": current, "canonicalUnchanged": current == previous,
                     "checkedRoots": [fixtures.CONTRACT], "checkedFiles": [fixtures.CONTRACT],
                     "npm": npm["version"], "npmAudit": npm,
                     "results": [{"id": case, "status": "PASSED"} for case in redocly]})
        elif lane == "dependency-audit":
            for tool in ("composer", "npm"):
                self.put(directory / (tool + "-audit.summary.json"), audit(tool))
        elif lane == "image-scan":
            now = datetime.now(timezone.utc)
            db = {"version": 2, "updatedAt": (now - timedelta(hours=1)).isoformat(),
                  "downloadedAt": (now - timedelta(minutes=1)).isoformat(),
                  "nextUpdate": (now + timedelta(hours=1)).isoformat()}
            images = []
            for target in (
                "docker.io/library/postgres:17.12-bookworm@sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03",
                "docker.io/library/redis:8.2.11-bookworm@sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87",
            ):
                images.append({"target": target, "status": "PASSED", "nativeExit": 0, "exceptionCount": 0,
                               "packageCount": 10, "osPackageCount": 10, "findings": [], "findingCount": 0,
                               "severityCounts": {}, "database": db})
            self.put(directory / "scan.summary.json", {**common, "stage": "complete", "task": "T032-T034",
                     "scannerVersion": "0.76.2", "archiveSha256": "bc0fc726e017b8927a62b2d3531d7a84ee01769daf5c99ed866dced54089fdbf",
                     "checksumFileSha256": "802e4ac1418ed967cd3c4b901f4f71ca00b7363ede67778665a22d9f13f58c83",
                     "databaseSha256": "a" * 64, "ownedCleanup": True, "trackedSourceUnchanged": True,
                     "candidateImages": [], "images": images})
        elif lane == "spec-kit":
            cases = ["test_fixture.Case.test_%03d" % i for i in range(70)]
            self.put(directory / "spec-kit.summary.json", {**common, "testCount": len(cases),
                     "failedCount": 0, "errorCount": 0, "skippedCount": 0, "cases": cases,
                     "python": "3.12.0", "pyyaml": "6.0.2", "trackedSourceUnchanged": True, "shellSyntaxPassed": True})

    def rehash(self, directory):
        path = directory / "lane.summary.json"
        summary = self.get(path)
        summary["artifacts"] = {item.name: hashlib.sha256(item.read_bytes()).hexdigest()
                                for item in directory.iterdir() if item.name != path.name}
        self.put(path, summary)

    def candidate(self, path=fixtures.GUIDE, content="# Reading guide\n\nMore examples.\n"):
        repo, identity = self.history(lambda repo: self.write(repo, path, content))
        classifier = self.helper("classify.py", "MISSING_CLASSIFIER")
        checker = self.helper("check-docs.py", "MISSING_DOCS_CHECKER")
        classification = classifier.classify(repo, identity)
        self.assertEqual(classification["status"], "PASSED")
        owned = tempfile.TemporaryDirectory(prefix="f04-gate-fixture-")
        self.addCleanup(owned.cleanup)
        root = Path(owned.name)
        outcomes = {"classify": "success"}
        for lane, decision in classification["lanes"].items():
            required = decision["required"]
            outcomes[lane] = "success" if required else "skipped"
            if not required:
                continue
            directory = self.artifact(root, lane, identity)
            directory.mkdir(mode=0o700)
            if lane == "docs":
                self.put(directory / "docs.summary.json", checker.check_docs(repo, identity))
            self.lane_payload(lane, directory, repo, identity)
            for selection in SELECTIONS.get(lane, ()):
                self.native(directory, selection, identity)
            summary = {key: value for key, value in identity.items() if key != "baseRef"}
            summary.update(job=lane, required=True, selectionReason=decision["selectionReason"],
                           status="PASSED", runnerStarted=True, exitCode=0, versions=TOOLS[lane],
                           durationSeconds=0.01, selectedCount=None, validatedCount=None,
                           artifacts={}, findingCounts={"errors": 0, "violations": 0})
            self.put(directory / "lane.summary.json", summary)
            self.rehash(directory)
        return repo, identity, classification, outcomes, root

    def verify(self, candidate, **kwargs):
        gate = self.helper("verify-gate.py", "MISSING_AGGREGATE_GATE")
        return gate.verify_gate(*candidate, **kwargs)

    def rejected(self, candidate, **kwargs):
        result = self.verify(candidate, **kwargs)
        self.assertIn(result["status"], ("FAILED", "BLOCKED"))
        self.assertNotEqual(result.get("status"), "PASSED")

    def test_t037_matrix_positive_controls(self):
        for path, content in (
            (fixtures.GUIDE, "# Reading guide\n\nMore examples.\n"),
            (fixtures.CONTRACT, '{"openapi":"3.1.0","info":{"title":"Changed","version":"1"},"paths":{}}\n'),
            (fixtures.PHP, "<?php\n// Changed\n"),
            (".github/workflows/example.yml", "name: Updated\n"),
            ("README.md", "# Unknown\n"),
        ):
            with self.subTest(path=path):
                candidate = self.candidate(path, content)
                result = self.verify(candidate)
                self.assertEqual(result["status"], "PASSED")
                self.assertEqual(set(result["lanes"]), LANES)
                for lane, decision in candidate[2]["lanes"].items():
                    self.assertEqual(result["lanes"][lane]["status"],
                                     "PASSED" if decision["required"] else "NOT_APPLICABLE")

    def test_t037_failed_skipped_cancelled_or_missing_required_job(self):
        for status in ("failure", "skipped", "cancelled", "blocked", "unknown", None):
            with self.subTest(status=status):
                candidate = self.candidate(".github/workflows/example.yml", "name: Updated\n")
                if status is None:
                    candidate[3].pop("image-scan")
                else:
                    candidate[3]["image-scan"] = status
                self.rejected(candidate)

    def test_t037_missing_classification_unknown_jobs_and_unjustified_skip(self):
        for variant in ("no-classify", "classify-failed", "unknown-job", "missing-optional",
                        "optional-failed", "forged-selection", "missing-classification"):
            with self.subTest(variant=variant):
                c = list(self.candidate())
                if variant == "no-classify":
                    c[3].pop("classify")
                elif variant == "classify-failed":
                    c[3]["classify"] = "failure"
                elif variant == "unknown-job":
                    c[3]["unexpected"] = "success"
                elif variant == "missing-optional":
                    c[3].pop("format")
                elif variant == "optional-failed":
                    c[3]["format"] = "failure"
                elif variant == "forged-selection":
                    c[2]["lanes"]["docs"]["required"] = False
                    c[3]["docs"] = "skipped"
                else:
                    c[2] = {}
                self.rejected(c)

    def test_t037_same_candidate_identity_cannot_be_forged(self):
        for field, value in (("sourceSha", "a" * 40), ("baseSha", "b" * 40),
                             ("checkoutSha", "c" * 40), ("runId", "987"),
                             ("runAttempt", 2), ("repository", "other/repo"), ("pr", 42)):
            with self.subTest(field=field):
                c = self.candidate()
                directory = self.artifact(c[4], "docs", c[1])
                summary = self.get(directory / "lane.summary.json")
                summary[field] = value
                self.put(directory / "lane.summary.json", summary)
                self.rejected(c)
        c = list(self.candidate())
        c[2]["identity"] = {**c[1], "runAttempt": 2}
        self.rejected(c)

    def test_t037_spoofed_green_does_not_replace_actual_docs_result(self):
        c = self.candidate()
        directory = self.artifact(c[4], "docs", c[1])
        payload = directory / "docs.summary.json"
        self.put(payload, {"status": "PASSED"})
        self.rehash(directory)
        self.rejected(c)
        c = self.candidate(fixtures.GUIDE, "# Reading\n\n[Missing](missing.md)\n")
        directory = self.artifact(c[4], "docs", c[1])
        report = self.get(directory / "docs.summary.json")
        self.assertEqual(report["status"], "FAILED")
        report.update(status="PASSED", issues=[])
        self.put(directory / "docs.summary.json", report)
        self.rehash(directory)
        self.rejected(c)

    def test_t038_missing_foreign_extra_or_unsafe_artifact(self):
        for variant in ("missing-dir", "foreign-attempt", "missing-file", "extra-file", "symlink",
                        "duplicate-dir", "nested", "hardlink", "oversized"):
            with self.subTest(variant=variant):
                c = self.candidate()
                directory = self.artifact(c[4], "docs", c[1])
                payload = directory / "docs.summary.json"
                if variant == "missing-dir":
                    shutil.rmtree(directory)
                elif variant == "foreign-attempt":
                    directory.rename(self.artifact(c[4], "docs", {**c[1], "runAttempt": 2}))
                elif variant == "missing-file":
                    payload.unlink()
                elif variant == "extra-file":
                    (directory / "extra.json").write_text("{}")
                elif variant == "symlink":
                    payload.unlink()
                    payload.symlink_to(c[0] / fixtures.GUIDE)
                elif variant == "duplicate-dir":
                    shutil.copytree(directory, c[4] / (directory.name + "-copy"))
                elif variant == "nested":
                    (directory / "unexpected").mkdir()
                elif variant == "hardlink":
                    (c[4] / "alias").hardlink_to(payload)
                else:
                    payload.write_bytes(b" " * (16 * 1024 * 1024 + 1))
                self.rejected(c)

    def test_t038_digest_duplicate_json_and_credential_canary(self):
        for variant in ("digest", "duplicate-json", "canary", "unknown-field"):
            with self.subTest(variant=variant):
                c = self.candidate()
                directory = self.artifact(c[4], "docs", c[1])
                path = directory / "lane.summary.json"
                summary = self.get(path)
                if variant == "digest":
                    summary["artifacts"]["docs.summary.json"] = "0" * 64
                    self.put(path, summary)
                elif variant == "duplicate-json":
                    path.write_text(path.read_text()[:-1] + ', "status": "PASSED"}')
                elif variant == "canary":
                    payload = directory / "docs.summary.json"
                    report = self.get(payload)
                    report["issues"] = [{"code": CANARY}]
                    self.put(payload, report)
                    self.rehash(directory)
                else:
                    summary["untrusted"] = True
                    self.put(path, summary)
                self.rejected(c, canaries=(CANARY,))

    def test_t038_native_reports_must_match_xml_counts_identity_and_safe_shape(self):
        for variant in ("malformed-xml", "doctype", "counts", "failure", "duplicate-case",
                        "foreign", "unverified", "missing-summary", "leak"):
            with self.subTest(variant=variant):
                c = self.candidate(".github/workflows/example.yml", "name: Updated\n")
                directory = self.artifact(c[4], "native-integration", c[1])
                xml = directory / "integration-safe.xml"
                report = directory / "integration.summary.json"
                if variant == "malformed-xml":
                    xml.write_text("<testsuites>")
                elif variant == "doctype":
                    xml.write_text('<!DOCTYPE testsuites [<!ENTITY x "unsafe">]>' + xml.read_text().split("?>", 1)[1])
                elif variant == "counts":
                    value = self.get(report)
                    value["counts"]["tests"] += 1
                    self.put(report, value)
                elif variant == "failure":
                    value = self.get(report)
                    value["counts"]["failures"] = 1
                    self.put(report, value)
                elif variant == "duplicate-case":
                    text = xml.read_text()
                    case = text[text.index("<testcase"):text.index("</testsuite>")]
                    xml.write_text(text.replace("</testsuite>", case + "</testsuite>"))
                elif variant == "foreign":
                    value = self.get(report)
                    value["run_attempt"] = "99"
                    self.put(report, value)
                elif variant == "unverified":
                    value = self.get(report)
                    value["selection_verified"] = False
                    self.put(report, value)
                elif variant == "missing-summary":
                    report.unlink()
                else:
                    xml.write_text(xml.read_text().replace("testFixture", CANARY))
                self.rehash(directory)
                self.rejected(c, canaries=(CANARY,))

    def test_t038_upload_or_cleanup_failure_cannot_be_green(self):
        c = self.candidate()
        c[3]["docs"] = "failure"
        self.rejected(c)

    def test_t037_cancelled_gate_and_missing_gate_never_success(self):
        c = self.candidate()
        for state in ("cancelled", "missing"):
            with self.subTest(state=state):
                self.rejected(c, gate_state=state)

    def test_t038_every_required_tool_has_nonempty_specific_evidence(self):
        for lane, name in (
            ("format", "format-diagnostic.json"), ("analysis", "analysis-diagnostic.json"),
            ("contracts", "redocly.summary.json"), ("dependency-audit", "npm-audit.summary.json"),
            ("image-scan", "scan.summary.json"), ("spec-kit", "spec-kit.summary.json"),
        ):
            with self.subTest(lane=lane):
                c = self.candidate(".github/workflows/example.yml", "name: Updated\n")
                self.assertEqual(self.verify(c)["status"], "PASSED")
                directory = self.artifact(c[4], lane, c[1])
                payload = directory / name
                original = payload.read_bytes()
                payload.unlink()
                self.rehash(directory)
                self.rejected(c)
                payload.write_bytes(original)
                self.rehash(directory)
                self.assertEqual(self.verify(c)["status"], "PASSED")
                self.put(payload, {"status": "PASSED"})
                self.rehash(directory)
                self.rejected(c)


    def test_lane_seal_requires_real_payload_and_cannot_overwrite(self):
        c = self.candidate()
        directory = self.artifact(c[4], "docs", c[1])
        helper = self.helper("artifact-io.py", "MISSING_ARTIFACT_IO")
        (directory / "lane.summary.json").unlink()
        helper.seal(c[0], c[1], "docs", directory, 0.5)
        self.assertEqual(self.verify(c)["status"], "PASSED")
        with self.assertRaises(FileExistsError):
            helper.seal(c[0], c[1], "docs", directory, 0.5)
        (directory / "lane.summary.json").unlink()
        self.put(directory / "docs.summary.json", {"status": "PASSED"})
        with self.assertRaises((ValueError, KeyError)):
            helper.seal(c[0], c[1], "docs", directory, 0.5)
        self.assertFalse((directory / "lane.summary.json").exists())

    def test_gate_cleanup_is_exact_and_rejects_unknown_or_linked_entries(self):
        helper = self.helper("artifact-io.py", "MISSING_ARTIFACT_IO")
        c = self.candidate()
        directory = self.artifact(c[4], "docs", c[1])
        unknown = directory / "unexpected.json"
        unknown.write_text("{}")
        with self.assertRaises(ValueError):
            helper.cleanup_downloads(c[4], c[1])
        self.assertTrue((directory / "lane.summary.json").is_file())
        unknown.unlink()
        link = directory / "docs.summary.json"
        link.unlink()
        link.symlink_to(c[0] / fixtures.GUIDE)
        with self.assertRaises(ValueError):
            helper.cleanup_downloads(c[4], c[1])
        self.assertTrue((directory / "lane.summary.json").is_file())
        self.assertTrue((c[0] / fixtures.GUIDE).is_file())
        link.unlink()
        helper.cleanup_downloads(c[4], c[1])
        self.assertFalse(c[4].exists())

    def test_archive_inventory_is_checked_before_extraction(self):
        import zipfile
        import stat
        io = self.helper("artifact-io.py", "MISSING_ARTIFACT_IO")
        for variant in ("valid", "traversal", "duplicate", "symlink", "extra", "oversize"):
            with self.subTest(variant=variant):
                c = self.candidate()
                target = self.artifact(c[4], "docs", c[1])
                payloads = {file.name: file.read_bytes() for file in target.iterdir()}
                shutil.rmtree(target)
                folder = c[4] / ("archive-" + target.name)
                folder.mkdir(mode=0o700)
                with zipfile.ZipFile(folder / "artifact.zip", "w", compression=zipfile.ZIP_DEFLATED) as archive:
                    for name, data in payloads.items():
                        if variant == "symlink" and name == "docs.summary.json":
                            member = zipfile.ZipInfo(name)
                            member.create_system = 3
                            member.external_attr = (stat.S_IFLNK | 0o777) << 16
                            archive.writestr(member, data)
                        elif variant == "oversize" and name == "docs.summary.json":
                            archive.writestr(name, b"x" * (16 * 1024 * 1024 + 1))
                        else:
                            archive.writestr(name, data)
                    if variant == "traversal":
                        archive.writestr("../outside.json", b"{}")
                    elif variant == "duplicate":
                        archive.writestr("docs.summary.json", b"{}")
                    elif variant == "extra":
                        archive.writestr("other.json", b"{}")
                if variant == "valid":
                    io.extract_downloads(c[4], c[1])
                    self.assertEqual(self.verify(c)["status"], "PASSED")
                    self.assertFalse(folder.exists())
                else:
                    with self.assertRaises(ValueError):
                        io.extract_downloads(c[4], c[1])
                    self.assertFalse(target.exists())
                    self.assertFalse((c[4] / "outside.json").exists())
