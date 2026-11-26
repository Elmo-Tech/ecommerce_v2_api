"""T037/T038 test-first aggregate and artifact boundaries.

These are owned scratch fixtures, not evidence of hosted artifact transport.
All candidates are real local Git histories; the final verifier must recompute
selection, use actual job conclusions, and independently inspect evidence.
"""
from copy import deepcopy
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
         "analysis": {"phpstan": "2.2.4", "larastan": "3.9.12"},
         "contracts": {"redocly": "2.31.4"}, "dependency-audit": {"composer": "2.9.0", "npm": "11.0.0"},
         "image-scan": {"trivy": "0.76.2"}, "bootstrap": {"php": "8.4.1"},
         "native-integration": {"php": "8.4.1"}, "spec-kit": {"python": "3.12.0"}}
CANARY = "F04_TEST_ONLY_CREDENTIAL_CANARY"


class AggregateQuality(unittest.TestCase):
    # Reuse the existing actual-Git fixture without inheriting its test cases.
    git = fixtures.GateQuality.git
    write = fixtures.GateQuality.write
    history = fixtures.GateQuality.history
    ledger = fixtures.GateQuality.ledger
    helper = fixtures.GateQuality.helper

    def put(self, path, data):
        path.write_text(json.dumps(data, sort_keys=True), encoding="utf-8")

    def get(self, path):
        return json.loads(path.read_text(encoding="utf-8"))

    def artifact(self, root, lane, identity):
        prefix = {"bootstrap": "f03-fast", "native-integration": "f03-integration"}.get(lane, "f04-" + lane)
        return root / (prefix + "-" + identity["runId"] + "-" + str(identity["runAttempt"]))

    def native(self, directory, selection, identity):
        counts = {"tests": 1, "assertions": 2, "failures": 0, "errors": 0,
                  "skipped": 0, "duration": 0.01}
        self.put(directory / (selection + ".summary.json"), {
            "selection": selection, "status": "PASSED", "exit_code": 0,
            "runner_started": True, "tests_executed": True, "selection_verified": True,
            "runner_exit": 0, "source_sha": identity["sourceSha"], "base_sha": identity["baseSha"],
            "checkout_sha": identity["checkoutSha"], "run_id": identity["runId"],
            "run_attempt": str(identity["runAttempt"]), "php": "8.4.1",
            "duration_seconds": 0.02, "counts": counts})
        (directory / (selection + "-safe.xml")).write_text(
            '<?xml version="1.0" encoding="UTF-8"?>\n'
            '<testsuites><testsuite name="' + selection + '" tests="1" assertions="2" failures="0" '
            'errors="0" skipped="0" time="0.010000"><testcase name="testFixture" '
            'classname="FixtureCase" assertions="2" time="0.010000"/></testsuite></testsuites>\n',
            encoding="utf-8")

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
            ("composer.lock", '{"fixture":2}\n'),
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
                candidate = self.candidate("composer.lock", '{"fixture":3}\n')
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
                c = self.candidate("composer.lock", '{"fixture":4}\n')
                directory = self.artifact(c[4], "native-integration", c[1])
                xml = directory / "integration-safe.xml"
                report = directory / "integration.summary.json"
                if variant == "malformed-xml":
                    xml.write_text("<testsuites>")
                elif variant == "doctype":
                    xml.write_text('<!DOCTYPE testsuites [<!ENTITY x "unsafe">]>' + xml.read_text().split("?>", 1)[1])
                elif variant == "counts":
                    value = self.get(report)
                    value["counts"]["tests"] = 2
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
