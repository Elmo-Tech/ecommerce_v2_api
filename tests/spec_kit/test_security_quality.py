"""MVP security evidence controls; no registry calls or vulnerable installs."""
from copy import deepcopy
from datetime import datetime, timedelta, timezone
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / "tools/quality/security/policy.py"
LOCK = "a" * 64
ARCHIVE = "bc0fc726e017b8927a62b2d3531d7a84ee01769daf5c99ed866dced54089fdbf"
IMAGES = (
    "docker.io/library/postgres:17.12-bookworm@sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03",
    "docker.io/library/redis:8.2.11-bookworm@sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87",
)
NOW = datetime(2026, 11, 26, 10, 0, tzinfo=timezone.utc)
CANARY = "synthetic-private-message"


def composer():
    # Composer 2.11.3 Auditor JSON: empty arrays are normal for these maps.
    return {"advisories": [], "abandoned": [], "filter": [], "cooldown": []}


def npm():
    return {
        "auditReportVersion": 2,
        "vulnerabilities": {},
        "metadata": {
            "vulnerabilities": dict(info=0, low=0, moderate=0, high=0, critical=0, total=0),
            "dependencies": dict(prod=1, dev=1, optional=0, peer=0, peerOptional=0, total=2),
        },
    }


def db(updated=None, next_update=None):
    return {
        "Version": 2,
        "UpdatedAt": (updated or NOW - timedelta(hours=1)).isoformat(),
        "NextUpdate": (next_update or NOW + timedelta(hours=1)).isoformat(),
        "DownloadedAt": (NOW - timedelta(minutes=1)).isoformat(),
    }


def image(target=IMAGES[0]):
    repo = target.split(":17.12" if "postgres:" in target else ":8.2.11")[0]
    digest = target.split("@")[1]
    return {
        "SchemaVersion": 2, "Trivy": {"Version": "0.76.2"},
        "ArtifactName": target, "ArtifactType": "container_image",
        "Metadata": {
            "OS": {"Family": "debian", "Name": "12"},
            "RepoDigests": [repo + "@" + digest],
            "ImageConfig": {"architecture": "amd64", "os": "linux"},
        },
        "Results": [{
            "Target": target + " (debian 12)", "Class": "os-pkgs", "Type": "debian",
            "Packages": [{"Name": "synthetic-lib", "Version": "1.0"}],
            "Vulnerabilities": [],
        }],
    }


class SecurityQualityPolicy(unittest.TestCase):
    def policy(self):
        if not HELPER.is_file():
            self.fail("MISSING_SECURITY_POLICY")
        spec = importlib.util.spec_from_file_location("f04_security_policy", HELPER)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module

    def audit(self, policy, tool, report, **overrides):
        raw = report if isinstance(report, str) else json.dumps(report)
        options = dict(
            tool=tool, raw=raw, exit_code=0,
            lock_sha=LOCK, expected_lock_sha=LOCK,
            installed_versions_match=True,
            locked_versions={"synthetic/package": "1.0.0", "synthetic-npm": "1.0.0"},
        )
        options.update(overrides)
        result = policy.audit_result(**options)
        self.assertNotIn(CANARY, json.dumps(result))
        return result

    def scan(self, policy, report, **overrides):
        options = dict(
            target=IMAGES[0], raw=report if isinstance(report, str) else json.dumps(report),
            exit_code=0, scanner_version="0.76.2", archive_sha=ARCHIVE,
            db_before=db(), db_after=db(), now=NOW,
        )
        options.update(overrides)
        result = policy.scan_result(**options)
        self.assertNotIn(CANARY, json.dumps(result))
        return result

    def test_synthetic_fixture_controls(self):
        self.assertEqual(json.loads(json.dumps(composer()))["advisories"], [])
        self.assertEqual(npm()["metadata"]["vulnerabilities"]["total"], 0)
        self.assertEqual(image()["Metadata"]["ImageConfig"]["architecture"], "amd64")
        self.assertEqual(len(set(IMAGES)), 2)

    def test_t027_clean_locked_audits(self):
        policy = self.policy()
        for tool, report in (("composer", composer()), ("npm", npm())):
            with self.subTest(tool=tool):
                result = self.audit(policy, tool, report)
                self.assertEqual(result["status"], "PASSED")
                self.assertEqual(result["findingCount"], 0)
                self.assertEqual(result["findings"], [])

    def test_t027_actual_advisory_exit_is_not_green(self):
        policy = self.policy()
        c = composer()
        c["advisories"] = {"synthetic/package": [{
            "advisoryId": "PKSA-aaaa-bbbb-cccc", "packageName": "synthetic/package",
            "affectedVersions": "<2", "cve": "CVE-2026-999999",
            "severity": "high", "title": CANARY,
        }]}
        n = npm()
        n["vulnerabilities"] = {"synthetic-npm": {
            "name": "synthetic-npm", "severity": "moderate", "isDirect": True,
            "via": [{"source": 1234567, "name": "synthetic-npm",
                     "dependency": "synthetic-npm", "title": CANARY,
                     "severity": "moderate", "range": "<2"}],
            "effects": [], "range": "<2", "nodes": ["node_modules/synthetic-npm"],
            "fixAvailable": True,
        }}
        n["metadata"]["vulnerabilities"].update(moderate=1, total=1)
        for tool, report in (("composer", c), ("npm", n)):
            with self.subTest(tool=tool):
                result = self.audit(policy, tool, report, exit_code=1)
                self.assertEqual(result["status"], "FAILED")
                self.assertEqual(result["findingCount"], 1)
                self.assertEqual(len(result["findings"]), 1)
                self.assertEqual(self.audit(policy, tool, report)["status"], "BLOCKED")

    def test_t027_incomplete_or_untrusted_audit_is_blocked(self):
        policy = self.policy()
        for tool, clean in (("composer", composer()), ("npm", npm())):
            for name, report, options in (
                ("missing", "", {}),
                ("empty", {}, {}),
                ("malformed", "{", {}),
                ("tool-error", clean, {"exit_code": 2}),
                ("false-failure", clean, {"exit_code": 1}),
                ("wrong-lock", clean, {"lock_sha": "b" * 64}),
                ("wrong-install", clean, {"installed_versions_match": False}),
                ("unreachable-registry", {"error": {"message": CANARY}}, {"exit_code": 1}),
            ):
                with self.subTest(tool=tool, case=name):
                    self.assertEqual(self.audit(policy, tool, report, **options)["status"], "BLOCKED")
        unreachable = composer()
        unreachable["unreachable-repositories"] = [CANARY]
        ignored = composer()
        ignored["ignored-advisories"] = {"synthetic/package": [{"title": CANARY}]}
        mismatch = npm()
        mismatch["metadata"]["vulnerabilities"]["total"] = 1
        for tool, report in (("composer", unreachable), ("composer", ignored), ("npm", mismatch)):
            self.assertEqual(self.audit(policy, tool, report)["status"], "BLOCKED")

    def test_t028_exact_image_and_fresh_database(self):
        policy = self.policy()
        for target in IMAGES:
            with self.subTest(target=target):
                result = self.scan(policy, image(target), target=target)
                self.assertEqual(result["status"], "PASSED")
                self.assertEqual(result["findingCount"], 0)
        boundary = db(updated=NOW - timedelta(hours=24))
        result = self.scan(policy, image(), db_before=boundary, db_after=boundary)
        self.assertEqual(result["status"], "PASSED")

    def test_t028_incomplete_or_wrong_image_is_blocked(self):
        policy = self.policy()
        cases = [
            ("missing", "", {}),
            ("malformed", "{", {}),
            ("tool-error", image(), {"exit_code": 2}),
            ("wrong-version", image(), {"scanner_version": "0.76.1"}),
            ("wrong-archive", image(), {"archive_sha": "b" * 64}),
            ("wrong-target", image(), {"target": IMAGES[1]}),
            ("mutable-target", image(), {"target": IMAGES[0].split("@")[0]}),
        ]
        for name, change in (
            ("wrong-platform", lambda d: d["Metadata"]["ImageConfig"].update(architecture="arm64")),
            ("wrong-source", lambda d: d.update(ArtifactName="synthetic.invalid/other@sha256:" + "b" * 64)),
            ("wrong-digest", lambda d: d["Metadata"].update(RepoDigests=["docker.io/library/postgres@sha256:" + "b" * 64])),
            ("missing-os", lambda d: d["Metadata"].pop("OS")),
            ("missing-packages", lambda d: d["Results"][0].pop("Packages")),
            ("empty-results", lambda d: d.update(Results=[])),
        ):
            candidate = image()
            change(candidate)
            cases.append((name, candidate, {}))
        for name, report, options in cases:
            with self.subTest(case=name):
                self.assertEqual(self.scan(policy, report, **options)["status"], "BLOCKED")

    def test_t028_database_freshness_before_and_after(self):
        policy = self.policy()
        for name, value in (
            ("missing", None),
            ("malformed", {"UpdatedAt": "invalid"}),
            ("stale", db(updated=NOW - timedelta(hours=24, seconds=1))),
            ("future", db(updated=NOW + timedelta(hours=1))),
            ("expired", db(next_update=NOW)),
        ):
            for position in ("db_before", "db_after"):
                with self.subTest(case=name, position=position):
                    self.assertEqual(self.scan(policy, image(), **{position: value})["status"], "BLOCKED")

    def test_t028_retains_fixed_unfixed_and_lower_severity(self):
        policy = self.policy()
        candidate = image()
        candidate["Results"][0]["Vulnerabilities"] = [
            {"VulnerabilityID": "CVE-2026-999998", "PkgName": "synthetic-lib",
             "InstalledVersion": "1.0", "FixedVersion": "1.1", "Severity": "MEDIUM",
             "Title": CANARY},
            {"VulnerabilityID": "CVE-2026-999999", "PkgName": "synthetic-lib",
             "InstalledVersion": "1.0", "Severity": "LOW", "Title": CANARY},
        ]
        result = self.scan(policy, candidate)
        self.assertEqual(result["status"], "PASSED")
        self.assertEqual(result["findingCount"], 2)
        self.assertEqual({f["severity"] for f in result["findings"]}, {"MEDIUM", "LOW"})
        self.assertEqual({f["fixedVersion"] for f in result["findings"]}, {"1.1", ""})
