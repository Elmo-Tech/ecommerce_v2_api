"""Actual read-only scans of the two approved test-image manifests, no containers."""
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import platform
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile

from policy import (IMAGES, CANDIDATES, TRIVY_ARCHIVE_SHA, TRIVY_VERSION, fresh_database,
                    require, scan_result, summary)

ROOT = Path(__file__).resolve().parents[3]
ASSET = "trivy_0.76.2_Linux-64bit.tar.gz"
CHECKSUMS = "trivy_0.76.2_checksums.txt"
CHECKSUM_SHA = "802e4ac1418ed967cd3c4b901f4f71ca00b7363ede67778665a22d9f13f58c83"
RELEASE = "https://github.com/aquasecurity/trivy/releases/download/v0.76.2/"


def sha(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def run(args, cwd, env, timeout=180, capture=False):
    return subprocess.run(args, cwd=cwd, env=env, timeout=timeout, check=False,
                          stdout=subprocess.PIPE if capture else subprocess.DEVNULL,
                          stderr=subprocess.DEVNULL, text=capture)


def identity():
    require(os.environ.get("GITHUB_ACTIONS") == "true"
            and os.environ.get("RUNNER_ENVIRONMENT") == "github-hosted"
            and os.environ.get("GITHUB_REPOSITORY") == "Elmo-Tech/ecommerce_v2_api"
            and platform.system() == "Linux" and platform.machine() == "x86_64")
    event = json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
    pr = event["pull_request"]
    source, base = pr["head"]["sha"], pr["base"]["sha"]
    require(type(event["number"]) is int and event["number"] > 0
            and pr["base"]["ref"] == "development" and pr["base"]["repo"]["full_name"] == "Elmo-Tech/ecommerce_v2_api"
            and os.environ.get("GITHUB_EVENT_NAME") == "pull_request"
            and os.environ.get("GITHUB_REF") == "refs/pull/" + str(event["number"]) + "/merge")
    git = lambda *args: subprocess.check_output(["git", *args], cwd=ROOT, text=True,
                                                 stderr=subprocess.DEVNULL, timeout=30)
    checkout = git("rev-parse", "HEAD").strip()
    parents = re.findall(r"^parent ([a-f0-9]{40})$", git("cat-file", "-p", "HEAD").split("\n\n", 1)[0], re.M)
    require(checkout == os.environ.get("GITHUB_SHA") and parents == [base, source] and all(re.fullmatch(r"[a-f0-9]{40}", v)
            for v in (source, base, checkout)))
    require(not git("status", "--porcelain", "--untracked-files=no"))
    return dict(sourceSha=source, baseSha=base, checkoutSha=checkout,
                runId=os.environ["GITHUB_RUN_ID"], runAttempt=os.environ["GITHUB_RUN_ATTEMPT"])


def read_json(path, maximum):
    require(path.is_file() and not path.is_symlink() and 0 < path.stat().st_size <= maximum)
    return path.read_text(encoding="utf-8")


def resolve_candidate(tag, scratch, env):
    """Resolve only fixed official tags to one immutable linux/amd64 manifest."""
    require(tag in CANDIDATES)
    result = run(["docker", "buildx", "imagetools", "inspect", "--raw", tag],
                 scratch, env, 90, True)
    require(result.returncode == 0 and 0 < len(result.stdout) <= 1024 * 1024)
    index = json.loads(result.stdout)
    require(index["schemaVersion"] == 2
            and index["mediaType"] in ("application/vnd.oci.image.index.v1+json",
                                       "application/vnd.docker.distribution.manifest.list.v2+json"))
    manifests = [m for m in index["manifests"]
                 if m.get("platform", {}).get("os") == "linux"
                 and m.get("platform", {}).get("architecture") == "amd64"]
    require(len(manifests) == 1 and manifests[0]["mediaType"] in (
        "application/vnd.oci.image.manifest.v1+json",
        "application/vnd.docker.distribution.manifest.v2+json"))
    digest = manifests[0]["digest"]
    require(re.fullmatch(r"sha256:[a-f0-9]{64}", digest))
    return tag + "@" + digest


def scan_all(compare=False):
    output = {"schemaVersion": 1, "task": "T032-T034", "status": "BLOCKED",
              "stage": "identity", "images": [], "candidateImages": [],
              "candidateApproval": "NOT_APPROVED"}
    try:
        output.update(identity())
        require(json.loads((Path(__file__).parent / "exceptions.json").read_text()) == [])
        output["stage"] = "scanner-acquisition"
        with tempfile.TemporaryDirectory(prefix="f04-trivy-") as directory:
            scratch = Path(directory)
            env = {"PATH": os.environ["PATH"], "HOME": directory, "TMPDIR": directory,
                   "CI": "true", "NO_COLOR": "1", "XDG_CACHE_HOME": str(scratch / "xdg-cache"),
                   "DOCKER_CONFIG": str(scratch / "docker-config")}
            for filename, expected, limit in ((ASSET, TRIVY_ARCHIVE_SHA, 500 * 1024 * 1024),
                                               (CHECKSUMS, CHECKSUM_SHA, 1024 * 1024)):
                target = scratch / filename
                result = run(["curl", "--disable", "--proto", "=https", "--proto-redir", "=https",
                              "--fail", "--silent", "--location", "--max-time", "120",
                              "--max-filesize", str(limit), "--output", str(target),
                              RELEASE + filename], scratch, env)
                require(result.returncode == 0 and target.stat().st_size <= limit and sha(target) == expected)
            checksum_rows = [row.split() for row in (scratch / CHECKSUMS).read_text().splitlines()]
            require(sum(len(row) == 2 and row[0] == TRIVY_ARCHIVE_SHA
                        and row[1].lstrip("*") == ASSET for row in checksum_rows) == 1)
            binary = scratch / "trivy"
            # Extract only the expected executable from the verified archive.
            with tarfile.open(scratch / ASSET, "r:gz") as archive:
                members = [item for item in archive if item.name in ("trivy", "./trivy")]
                require(len(members) == 1 and members[0].isfile()
                        and 0 < members[0].size <= 500 * 1024 * 1024)
                with archive.extractfile(members[0]) as source, binary.open("xb") as dest:
                    shutil.copyfileobj(source, dest)
            binary.chmod(0o700)
            version = run([str(binary), "--version"], scratch, env, 30, True)
            require(version.returncode == 0 and re.search(r"^Version:\s+0\.76\.2\s*$", version.stdout, re.M))
            output.update(scannerVersion=TRIVY_VERSION, archiveSha256=TRIVY_ARCHIVE_SHA,
                          checksumFileSha256=CHECKSUM_SHA)
            config = scratch / "trivy.yaml"
            config.write_text("{}\n")
            ignore = scratch / ".trivyignore"
            ignore.touch()
            cache = scratch / "cache"
            base = [str(binary), "--config", str(config), "--cache-dir", str(cache), "--timeout", "5m", "image"]
            common = ["--skip-version-check", "--disable-telemetry", "--no-progress"]
            output["stage"] = "database"
            result = run(base + common + ["--download-db-only", "--db-repository",
                                          "ghcr.io/aquasecurity/trivy-db:2"], scratch, env, 300)
            require(result.returncode == 0)
            meta = cache / "db/metadata.json"
            database = cache / "db/trivy.db"
            before = json.loads(read_json(meta, 64 * 1024))
            fresh_database(before, datetime.now(timezone.utc))
            require(database.is_file() and not database.is_symlink() and database.stat().st_size > 0)
            db_hash = sha(database)
            output["databaseSha256"] = db_hash
            output["stage"] = "scan"
            # Use the same verified scanner/database for a fair comparison.
            # Only the two approved entries may determine the required gate.
            for index, name in enumerate((*IMAGES, *(CANDIDATES if compare else ()))):
                candidate = index >= len(IMAGES)
                target = name
                item = summary("BLOCKED", reason="CANDIDATE_RESOLUTION" if candidate else "SCANNER_FAILURE",
                               target=name)
                try:
                    if candidate:
                        target = resolve_candidate(name, scratch, env)
                        item = summary("BLOCKED", reason="SCANNER_FAILURE", target=target)
                    fresh_database(before, datetime.now(timezone.utc))
                    report = scratch / ("image-" + str(index) + ".json")
                    command = base + common + [
                        "--skip-db-update", "--image-src", "remote", "--platform", "linux/amd64",
                        "--scanners", "vuln", "--list-all-pkgs",
                        "--severity", "UNKNOWN,LOW,MEDIUM,HIGH,CRITICAL",
                        "--ignorefile", str(ignore), "--exit-code", "0", "--format", "json",
                        "--output", str(report), target,
                    ]
                    scanned = run(command, scratch, env, 300)
                    after = json.loads(read_json(meta, 64 * 1024))
                    require(sha(database) == db_hash)
                    item = scan_result(target=target, raw=read_json(report, 16 * 1024 * 1024),
                                       exit_code=scanned.returncode, scanner_version=TRIVY_VERSION,
                                       archive_sha=TRIVY_ARCHIVE_SHA, db_before=before, db_after=after,
                                       now=datetime.now(timezone.utc), exceptions=[], approved_exceptions=[],
                                       candidate=candidate)
                    item["target"] = target
                    item["nativeExit"] = scanned.returncode
                except (OSError, ValueError, TypeError, KeyError, subprocess.SubprocessError):
                    pass
                if candidate:
                    item.update(candidateOnly=True, requestedTag=name, approval="NOT_APPROVED")
                    output["candidateImages"].append(item)
                else:
                    output["images"].append(item)
        require(not scratch.exists())
        output["ownedCleanup"] = True
        require(identity() == {key: output[key] for key in
                               ("sourceSha", "baseSha", "checkoutSha", "runId", "runAttempt")})
        output["trackedSourceUnchanged"] = True
        states = [item["status"] for item in output["images"]]
        output["status"] = ("BLOCKED" if len(states) != len(IMAGES) or "BLOCKED" in states
                            else "FAILED" if "FAILED" in states else "PASSED")
        output["stage"] = "complete"
    except (OSError, ValueError, TypeError, KeyError, AttributeError, tarfile.TarError, subprocess.SubprocessError):
        output["status"] = "BLOCKED"
    return output


if __name__ == "__main__":
    # Comparisons are explicit diagnostics only; normal CI scans approved pins.
    if sys.argv[1:] not in ([], ["--compare"]):
        sys.exit(2)
    result = scan_all(compare=sys.argv[1:] == ["--compare"])
    print(json.dumps(result, sort_keys=True))
    sys.exit({"PASSED": 0, "FAILED": 1, "BLOCKED": 2}[result["status"]])
