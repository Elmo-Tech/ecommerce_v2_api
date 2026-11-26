"""Seal real lane reports and clean only owned, fixed artifact inventories.

No artifact content is executed. The job's successful conclusion and a
separately validated same-run download are still required by verify-gate.py.
"""
import importlib.util
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import time
import zipfile

_spec = importlib.util.spec_from_file_location("f04_gate_io", Path(__file__).with_name("verify-gate.py"))
gate = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(gate)

# Fixed existing report directories; only these safe filenames are sealed.
PREFIXES = {
    "docs": "f04-docs", "format": "f04-t016", "analysis": "f04-t019",
    "contracts": "f04-t020", "dependency-audit": "f04-audit",
    "image-scan": "f04-image-scan", "bootstrap": "f03-reports",
    "native-integration": "f03-reports", "spec-kit": "f04-spec-kit",
}


def versions_for(repo, raw, lane):
    def data(name):
        return gate.json_value(raw[name])

    if lane == "docs":
        version = gate.git_text(repo, "--version")
        gate.require(version.startswith("git version "))
        return {"git": version.removeprefix("git version ")}
    if lane == "format":
        return {"pint": data("format-diagnostic.json")["pint"]}
    if lane == "analysis":
        report = data("analysis-diagnostic.json")
        return {key: report[key] for key in ("phpstan", "larastan")}
    if lane == "contracts":
        report = data("redocly.summary.json")
        return {"node": report["node"].removeprefix("v"), "redocly": report["redocly"]}
    if lane == "dependency-audit":
        return {tool: data(tool + "-audit.summary.json")["version"] for tool in ("composer", "npm")}
    if lane == "image-scan":
        return {"trivy": data("scan.summary.json")["scannerVersion"]}
    if lane in gate.SELECTIONS:
        return {"php": data(gate.SELECTIONS[lane][0] + ".summary.json")["php"]}
    if lane == "spec-kit":
        report = data("spec-kit.summary.json")
        return {"python": report["python"], "pyyaml": report["pyyaml"]}
    raise ValueError("UNKNOWN_LANE")


def seal(repo, identity, lane, directory, duration):
    decision = gate.classify(repo, identity)
    gate.require(decision["status"] == "PASSED" and lane in gate.LANES
                 and decision["lanes"][lane]["required"] is True)
    directory = gate.directory(Path(directory).absolute(), private=True)
    names = gate.PAYLOADS[lane]
    raw = {name: gate.read_file(directory / name, gate.MAX_FILE) for name in names}
    gate.require(sum(map(len, raw.values())) <= gate.MAX_TOTAL)
    versions = versions_for(repo, raw, lane)
    gate.validate_payloads(repo, raw, lane, identity, versions)
    summary = {key: value for key, value in identity.items() if key != "baseRef"}
    counts = {"errors": 0, "violations": 0}
    selected = None
    if lane in ("format", "analysis"):
        selected = gate.json_value(raw[lane + "-diagnostic.json"])["selectedCount"]
    elif lane == "docs":
        selected = gate.json_value(raw["docs.summary.json"])["checkedDocuments"] or None
    elif lane == "contracts":
        selected = len(gate.json_value(raw["redocly.summary.json"])["checkedRoots"])
    elif lane == "dependency-audit":
        selected = sum(gate.json_value(raw[tool + "-audit.summary.json"])["installedCount"]
                       for tool in ("composer", "npm"))
        counts["advisories"] = 0
    elif lane == "image-scan":
        images = gate.json_value(raw["scan.summary.json"])["images"]
        selected = len(images)
        for severity in ("CRITICAL", "HIGH", "MEDIUM", "LOW", "UNKNOWN"):
            counts[severity.lower()] = sum(image["severityCounts"].get(severity, 0) for image in images)
    elif lane == "spec-kit":
        selected = gate.json_value(raw["spec-kit.summary.json"])["testCount"]
    summary.update(job=lane, required=True, selectionReason=decision["selectionReason"], status="PASSED",
                   runnerStarted=True, exitCode=0, versions=versions, durationSeconds=duration,
                   selectedCount=selected, validatedCount=selected,
                   artifacts={name: gate.digest(raw[name]) for name in names}, findingCounts=counts)
    encoded = (json.dumps(summary, sort_keys=True, allow_nan=False) + "\n").encode("utf-8")
    raw["lane.summary.json"] = encoded
    gate.lane_summary(raw, lane, identity, decision["lanes"][lane], ())
    target = directory / "lane.summary.json"
    fd = os.open(target, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, "wb") as stream:
        stream.write(encoded)
    return summary


def owned_path(path, parent, prefix):
    path = Path(path).absolute()
    gate.require(path.parent == Path(parent).resolve()
                 and re.fullmatch(re.escape(prefix) + r"-[A-Za-z0-9]{12}", path.name))
    return gate.directory(path, private=True)



MAX_ARCHIVE = gate.MAX_TOTAL + 1024 * 1024


def archive_file(folder):
    gate.directory(folder)
    files = list(folder.iterdir())
    gate.require(len(files) == 1)
    item = files[0]
    gate.require(re.fullmatch(r"[A-Za-z0-9_.-]{1,200}", item.name)
                 and item.name not in (".", ".."))
    info = item.lstat()
    gate.require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1
                 and info.st_uid == os.geteuid() and info.st_mode & 0o022 == 0
                 and 0 < info.st_size <= MAX_ARCHIVE and item.resolve() == item)
    return item


def extract_archive(folder, target, lane):
    archive = archive_file(folder)
    expected = {"lane.summary.json", *gate.PAYLOADS[lane]}
    gate.require(not target.exists() and not target.is_symlink())
    with zipfile.ZipFile(archive, "r") as zipped:
        entries = zipped.infolist()
        gate.require(len(entries) == len(expected) and
                     {item.filename for item in entries} == expected)
        total = 0
        for item in entries:
            kind = stat.S_IFMT(item.external_attr >> 16)
            limit = gate.MAX_SUMMARY if item.filename == "lane.summary.json" else gate.MAX_FILE
            gate.require(item.orig_filename == item.filename and not item.is_dir()
                         and kind in (0, stat.S_IFREG) and not (item.flag_bits & 1)
                         and item.compress_type in (zipfile.ZIP_STORED, zipfile.ZIP_DEFLATED)
                         and 0 < item.file_size <= limit)
            total += item.file_size
            gate.require(total <= gate.MAX_TOTAL)
        # Validate the entire archive inventory before creating any extracted file.
        target.mkdir(mode=0o700)
        for item in entries:
            with zipped.open(item, "r") as source:
                payload = source.read(item.file_size + 1)
            gate.require(len(payload) == item.file_size)
            fd = os.open(target / item.filename, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
            with os.fdopen(fd, "wb") as stream:
                stream.write(payload)
    archive.unlink()
    folder.rmdir()
    gate.require(not folder.exists())


def extract_downloads(root, identity):
    root = gate.directory(Path(root).absolute(), private=True)
    allowed = {"archive-" + gate.artifact_name(lane, identity): lane for lane in gate.LANES}
    folders = list(root.iterdir())
    gate.require(len(folders) <= len(allowed) and all(folder.name in allowed for folder in folders))
    for folder in folders:
        lane = allowed[folder.name]
        extract_archive(folder, root / gate.artifact_name(lane, identity), lane)


def cleanup_downloads(root, identity):
    # Validate the entire inventory before deleting anything. On an unsafe
    # extraction, refuse cleanup and leave the job failed on its ephemeral runner.
    root = gate.directory(Path(root).absolute(), private=True)
    allowed = {gate.artifact_name(lane, identity): {"lane.summary.json", *gate.PAYLOADS[lane]}
               for lane in gate.LANES}
    raw_names = {"archive-" + name for name in allowed}
    entries = list(root.iterdir())
    gate.require(len(entries) <= 2 * len(allowed) and all(item.name in allowed or item.name in raw_names for item in entries))
    plan = []
    total = 0
    for folder in entries:
        gate.directory(folder)
        files = list(folder.iterdir())
        if folder.name in raw_names:
            gate.require(len(files) <= 1)
            if files:
                archive_file(folder)
        else:
            gate.require(len(files) <= len(allowed[folder.name])
                         and all(item.name in allowed[folder.name] for item in files))
        for item in files:
            info = item.lstat()
            limit = MAX_ARCHIVE if folder.name in raw_names else gate.MAX_FILE
            gate.require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1
                         and info.st_uid == os.geteuid() and item.resolve() == item
                         and info.st_mode & 0o022 == 0 and 0 <= info.st_size <= limit)
            total += info.st_size
            gate.require(total <= 2 * len(gate.LANES) * MAX_ARCHIVE)
            plan.append((item, info))
    for item, before in plan:
        after = item.lstat()
        gate.require((before.st_dev, before.st_ino, before.st_size, before.st_mtime_ns) ==
                     (after.st_dev, after.st_ino, after.st_size, after.st_mtime_ns))
        item.unlink()
    for folder in entries:
        gate.directory(folder)
        folder.rmdir()
    root.rmdir()
    gate.require(not root.exists() and not root.is_symlink())


def cleanup_lane(root, lane):
    root = gate.directory(Path(root).absolute(), private=True)
    names = {"lane.summary.json", *gate.PAYLOADS[lane]}
    entries = list(root.iterdir())
    gate.require(len(entries) <= len(names) and all(item.name in names for item in entries))
    for item in entries:
        gate.read_file(item, gate.MAX_FILE)
    for item in entries:
        item.unlink()
    root.rmdir()
    gate.require(not root.exists() and not root.is_symlink())


def main():
    try:
        args = sys.argv[1:]
        identity = gate.hosted_identity()
        parent = os.environ["RUNNER_TEMP"]
        if len(args) == 3 and args[0] == "seal" and args[1] in PREFIXES:
            _, lane, path = args
            duration = round(time.monotonic() - float(os.environ["F04_LANE_STARTED"]), 3)
            seal(gate.ROOT, identity, lane, owned_path(path, parent, PREFIXES[lane]), duration)
        elif len(args) == 3 and args[0] == "cleanup-lane" and args[1] in ("docs", "dependency-audit", "spec-kit"):
            _, lane, path = args
            cleanup_lane(owned_path(path, parent, PREFIXES[lane]), lane)
        elif len(args) == 2 and args[0] == "extract-downloads":
            extract_downloads(owned_path(args[1], parent, "f04-gate"), identity)
        elif len(args) == 2 and args[0] == "cleanup-downloads":
            cleanup_downloads(owned_path(args[1], parent, "f04-gate"), identity)
        else:
            gate.require(False)
        print("F0-04 artifact boundary verified.")
        return 0
    except (OSError, ValueError, KeyError, TypeError, AttributeError, OverflowError,
            RecursionError, subprocess.SubprocessError, gate.ET.ParseError, zipfile.BadZipFile):
        print("F0-04 artifact boundary rejected or incomplete.", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
