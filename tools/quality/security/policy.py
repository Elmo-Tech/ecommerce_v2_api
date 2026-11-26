"""Small, fail-closed report policy. Native tools perform the security scans."""
import json
import re
from collections import Counter
from datetime import datetime, timedelta, timezone

MAX_REPORT = 4 * 1024 * 1024
MAX_FINDINGS = 10000


def require(condition):
    if not condition:
        raise ValueError("INVALID_EVIDENCE")


def unique_pairs(pairs):
    result = {}
    for key, value in pairs:
        require(key not in result)
        result[key] = value
    return result


def read_report(raw):
    require(isinstance(raw, str) and 0 < len(raw.encode("utf-8")) <= MAX_REPORT)
    value = json.loads(raw, object_pairs_hook=unique_pairs,
                       parse_constant=lambda value: require(False))
    require(isinstance(value, dict))
    return value


def token(value, pattern=r"[A-Za-z0-9@./_+:~-]+", max_length=200):
    require(isinstance(value, str) and 0 < len(value) <= max_length
            and re.fullmatch(pattern, value) is not None)
    return value


def versions(locked, name):
    token(name, r"@?[A-Za-z0-9._-]+(?:/[A-Za-z0-9._-]+)?")
    require(name in locked)
    values = locked[name]
    if isinstance(values, str):
        values = [values]
    require(isinstance(values, (list, tuple)) and 0 < len(values) <= 100)
    return sorted({token(value) for value in values})


def summary(status, findings=(), **extra):
    return {"status": status, "findingCount": len(findings),
            "findings": list(findings), **extra}


def audit_result(*, tool, raw, exit_code, lock_sha, expected_lock_sha,
                 installed_versions_match, locked_versions):
    """Check bounded native JSON and exit consistency; never expose raw prose."""
    try:
        require(tool in ("composer", "npm") and type(exit_code) is int and exit_code in (0, 1))
        require(isinstance(lock_sha, str) and re.fullmatch(r"[a-f0-9]{64}", lock_sha)
                and lock_sha == expected_lock_sha and installed_versions_match is True)
        require(isinstance(locked_versions, dict) and locked_versions)
        report = read_report(raw)
        findings = []
        if tool == "composer":
            required = {"advisories", "abandoned", "filter", "cooldown"}
            require(required <= report.keys() <= required | {"ignored-advisories", "unreachable-repositories"})
            # Non-advisory policy results or unreachable/ignored sources are
            # not a verified advisory-free result. Escalate for review.
            for key in ("abandoned", "filter", "cooldown", "ignored-advisories", "unreachable-repositories"):
                require(report.get(key, []) in ([], {}))
            advisories = report["advisories"]
            require(isinstance(advisories, dict) or advisories == [])
            if isinstance(advisories, dict):
                for name, rows in advisories.items():
                    installed = versions(locked_versions, name)
                    require(isinstance(rows, list) and rows)
                    for row in rows:
                        require(isinstance(row, dict) and row.get("packageName") == name)
                        advisory = token(row["advisoryId"], r"[A-Za-z0-9-]+")
                        severity = row.get("severity") or "unknown"
                        require(severity in ("critical", "high", "medium", "moderate", "low", "unknown"))
                        findings.append({"package": name, "lockedVersions": installed,
                                         "id": advisory, "severity": severity.upper()})
                        require(len(findings) <= MAX_FINDINGS)
        else:
            require(report.keys() == {"auditReportVersion", "vulnerabilities", "metadata"}
                    and type(report["auditReportVersion"]) is int and report["auditReportVersion"] == 2)
            vulnerabilities = report["vulnerabilities"]
            metadata = report["metadata"]
            require(isinstance(vulnerabilities, dict) and isinstance(metadata, dict)
                    and len(vulnerabilities) <= MAX_FINDINGS)
            counts = metadata["vulnerabilities"]
            labels = ("info", "low", "moderate", "high", "critical")
            require(isinstance(counts, dict) and counts.keys() == set(labels) | {"total"}
                    and all(type(value) is int and value >= 0 for value in counts.values()))
            dependencies = metadata["dependencies"]
            require(isinstance(dependencies, dict) and dependencies
                    and all(type(value) is int and value >= 0 for value in dependencies.values())
                    and dependencies.get("total", 0) > 0)
            observed = Counter()
            for name, row in vulnerabilities.items():
                installed = versions(locked_versions, name)
                require(isinstance(row, dict) and row.get("name") == name
                        and row.get("severity") in labels and type(row.get("isDirect")) is bool
                        and isinstance(row.get("via"), list) and row["via"]
                        and isinstance(row.get("nodes"), list) and row["nodes"])
                ids, causes = set(), set()
                for via in row["via"]:
                    if isinstance(via, dict):
                        require(type(via.get("source")) is int and via["source"] > 0)
                        ids.add(str(via["source"]))
                    else:
                        versions(locked_versions, via)
                        causes.add(via)
                severity = row["severity"]
                observed[severity] += 1
                findings.append({"package": name, "lockedVersions": installed,
                                 "advisoryIds": sorted(ids), "viaPackages": sorted(causes),
                                 "severity": severity.upper()})
            require(counts["total"] == len(findings)
                    and all(counts[label] == observed[label] for label in labels))
        require((exit_code == 0) == (len(findings) == 0))
        findings.sort(key=lambda row: (row["package"], row.get("id", "")))
        return summary("FAILED" if findings else "PASSED", findings)
    except (ValueError, TypeError, KeyError, AttributeError, OverflowError, RecursionError):
        return summary("BLOCKED", reason="INVALID_OR_INCOMPLETE_AUDIT")


# Only the two existing native integration images are in the MVP scan scope.
IMAGES = (
    "docker.io/library/postgres:17.12-bookworm@sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03",
    "docker.io/library/redis:8.2.11-bookworm@sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87",
)
# Read-only comparison scope. These are not approved integration image pins.
CANDIDATES = {
    "docker.io/library/postgres:17.12-trixie": ("debian", "13"),
    "docker.io/library/postgres:17.12-alpine3.24": ("alpine", "3.24"),
    "docker.io/library/postgres:17.12-alpine3.23": ("alpine", "3.23"),
    "docker.io/library/redis:8.2.11-alpine3.22": ("alpine", "3.22"),
}
TRIVY_VERSION = "0.76.2"
TRIVY_ARCHIVE_SHA = "bc0fc726e017b8927a62b2d3531d7a84ee01769daf5c99ed866dced54089fdbf"


def timestamp(value):
    require(isinstance(value, str) and len(value) <= 40)
    result = datetime.fromisoformat(value)
    require(result.tzinfo is not None)
    return result.astimezone(timezone.utc)


def fresh_database(metadata, now):
    require(isinstance(metadata, dict) and type(metadata.get("Version")) is int
            and metadata["Version"] == 2 and now.tzinfo is not None)
    updated = timestamp(metadata["UpdatedAt"])
    next_update = timestamp(metadata["NextUpdate"])
    downloaded = timestamp(metadata["DownloadedAt"])
    require(timedelta(0) <= now - updated <= timedelta(hours=24)
            and updated < next_update and next_update > now
            and updated <= downloaded <= now)
    return {"version": 2, "updatedAt": updated.isoformat(),
            "nextUpdate": next_update.isoformat(), "downloadedAt": downloaded.isoformat()}


def image_names(target):
    # Trivy's remote image client trims the default Docker Hub namespace.
    # Accept only the exact approved repository/digest and its known aliases.
    repository, digest = target.split("@")
    short = repository.removeprefix("docker.io/library/")
    untagged = short.split(":")[0]
    names = {target, short + "@" + digest,
             "index.docker.io/library/" + short + "@" + digest}
    digests = {prefix + untagged + "@" + digest
               for prefix in ("", "library/", "docker.io/library/", "index.docker.io/library/")}
    return names | digests, digests


def package_version(package, os_package):
    # Trivy v0.76.2's Debian detector uses scan/utils.FormatVersion:
    # the inventory stores Epoch, Version and Release separately, but the
    # vulnerability record uses the canonical complete binary version.
    version = token(package["Version"])
    if not os_package:
        return version
    epoch = package.get("Epoch", 0)
    release = package.get("Release", "")
    require(type(epoch) is int and 0 <= epoch <= 2147483647
            and isinstance(release, str))
    if release:
        version += "-" + token(release)
    if epoch:
        version = str(epoch) + ":" + version
    return token(version)


def scan_result(*, target, raw, exit_code, scanner_version, archive_sha,
                db_before, db_after, now, exceptions=(), approved_exceptions=(),
                candidate=False):
    """Validate the actual pinned scanner report, keeping all severities."""
    stage = "tool-identity"
    try:
        require(type(candidate) is bool and type(exit_code) is int and exit_code == 0
                and scanner_version == TRIVY_VERSION and archive_sha == TRIVY_ARCHIVE_SHA)
        if candidate:
            # Explicitly non-gating comparison of the two proposed official tags.
            tag, separator, digest = target.partition("@")
            require(tag in CANDIDATES and separator == "@"
                    and re.fullmatch(r"sha256:[a-f0-9]{64}", digest)
                    and not exceptions and not approved_exceptions)
            family, os_version = CANDIDATES[tag]
        else:
            require(target in IMAGES)
            family, os_version = "debian", "12"
        stage = "database"
        freshness = fresh_database(db_before, now)
        require(freshness == fresh_database(db_after, now) and db_before == db_after)
        stage = "json"
        # The full native image JSON includes package inventory and metadata.
        require(isinstance(raw, str) and 0 < len(raw.encode("utf-8")) <= 16 * 1024 * 1024)
        report = json.loads(raw, object_pairs_hook=unique_pairs,
                            parse_constant=lambda value: require(False))
        stage = "report-identity"
        names, digests = image_names(target)
        require(isinstance(report, dict) and type(report.get("SchemaVersion")) is int
                and report["SchemaVersion"] == 2 and report["Trivy"]["Version"] == scanner_version
                and report["ArtifactType"] == "container_image" and report["ArtifactName"] in names)
        stage = "platform"
        meta = report["Metadata"]
        detected = str(meta["OS"]["Name"])
        require(meta["OS"]["Family"] == family
                and re.fullmatch(re.escape(os_version) + r"(?:\.[0-9]+)*", detected)
                and meta["ImageConfig"]["architecture"] == "amd64"
                and meta["ImageConfig"]["os"] == "linux")
        stage = "digest"
        refs = meta["RepoDigests"]
        require(isinstance(refs, list) and refs and all(ref in digests for ref in refs))
        stage = "results"
        results = report["Results"]
        require(isinstance(results, list) and results)
        os_packages = 0
        package_count = 0
        findings = []
        for result in results:
            stage = "package-inventory"
            require(isinstance(result, dict) and result.get("Class") in ("os-pkgs", "lang-pkgs"))
            packages = result["Packages"]
            require(isinstance(packages, list) and packages)
            inventory = {(token(pkg["Name"]), package_version(pkg, result["Class"] == "os-pkgs"))
                         for pkg in packages}
            package_count += len(packages)
            require(package_count <= 100000)
            if result["Class"] == "os-pkgs":
                require(result["Type"] == family)
                os_packages += len(packages)
            stage = "finding-fields"
            vulnerabilities = result.get("Vulnerabilities", [])
            require(isinstance(vulnerabilities, list))
            for row in vulnerabilities:
                stage = "finding-fields"
                severity = row["Severity"]
                require(severity in ("UNKNOWN", "LOW", "MEDIUM", "HIGH", "CRITICAL"))
                name = token(row["PkgName"])
                installed = token(row["InstalledVersion"])
                stage = "finding-inventory"
                require((name, installed) in inventory)
                stage = "finding-fields"
                fixed = row.get("FixedVersion", "")
                # Native fixed versions can be a comma-separated list.
                require(isinstance(fixed, str) and len(fixed) <= 400)
                if fixed:
                    token(fixed, r"[A-Za-z0-9.+:~_, -]+", max_length=400)
                findings.append({"id": token(row["VulnerabilityID"], r"[A-Za-z0-9._-]+"),
                                 "package": name, "installedVersion": installed,
                                 "fixedVersion": fixed, "severity": severity})
                require(len(findings) <= MAX_FINDINGS)
        stage = "os-coverage"
        require(os_packages > 0)
        findings.sort(key=lambda f: (f["id"], f["package"], f["installedVersion"], f["severity"]))
        stage = "exceptions"
        # No blanket ignores; lower-risk approvals only acknowledge an exact
        # retained finding. Actual execution has no approved entries at present.
        require(isinstance(exceptions, (list, tuple)) and isinstance(approved_exceptions, (list, tuple)))
        seen = set()
        for entry in exceptions:
            require(isinstance(entry, dict) and entry in approved_exceptions
                    and set(entry) == {"image", "id", "package", "installedVersion", "owner",
                                       "reviewer", "mitigation", "followUp", "approval", "expiresAt"}
                    and entry["image"] == target and timestamp(entry["expiresAt"]) > now)
            for field in ("owner", "reviewer", "mitigation", "followUp", "approval"):
                require(isinstance(entry[field], str) and 0 < len(entry[field].strip()) <= 1000)
            require(entry["owner"].strip() != entry["reviewer"].strip())
            key = (entry["id"], entry["package"], entry["installedVersion"])
            require(key not in seen and all(isinstance(v, str) and "*" not in v for v in key))
            matching = [f for f in findings if (f["id"], f["package"], f["installedVersion"]) == key]
            require(matching and all(f["severity"] in ("LOW", "MEDIUM") for f in matching))
            seen.add(key)
        counts = dict(Counter(f["severity"] for f in findings))
        return summary("FAILED" if any(counts.get(s, 0) for s in ("HIGH", "CRITICAL", "UNKNOWN")) else "PASSED",
                       findings, exceptionCount=len(seen), severityCounts=counts,
                       target=target, osPackageCount=os_packages, packageCount=package_count,
                       database=freshness)
    except (ValueError, TypeError, KeyError, AttributeError, OverflowError, RecursionError):
        return summary("BLOCKED", reason="INVALID_OR_INCOMPLETE_SCAN", stage=stage)

