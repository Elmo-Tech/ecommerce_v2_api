"""Small, fail-closed report policy. Native tools perform the security scans."""
import json
import re
from collections import Counter

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


def token(value, pattern=r"[A-Za-z0-9@./_+:-]+", max_length=200):
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
