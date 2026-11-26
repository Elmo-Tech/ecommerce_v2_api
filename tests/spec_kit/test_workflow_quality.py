"""Focused CI policy regression checks using the existing PyYAML test dependency."""
from copy import deepcopy
from pathlib import Path
import importlib.util
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("f04_workflows", ROOT / "tools/quality/check-workflows.py")
POLICY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(POLICY)
parse = POLICY.parse
policy_issues = POLICY.policy_issues


class WorkflowQualityPolicy(unittest.TestCase):
    def fixture(self):
        return parse("""
on:
  pull_request:
    branches: [development]
  workflow_dispatch:
permissions:
  contents: read
jobs:
  check:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803
        with:
          persist-credentials: false
      - uses: actions/upload-artifact@cf430e030ddbb5b0abf93d22962f4752f3646cd9
        with:
          path: report.json
          retention-days: 7
  gate:
    name: F0-04 / Quality Gate
    if: always()
    runs-on: ubuntu-24.04
    needs: check
    steps:
      - run: echo fixture
""")

    def test_reviewed_policy_fixture(self):
        self.assertEqual(policy_issues(self.fixture(), True), [])
        reusable = self.fixture()
        reusable["on"] = {"workflow_call": ""}
        del reusable["jobs"]["gate"]
        self.assertEqual(policy_issues(reusable, False), [])

    def test_rejects_unsafe_workflow_fixtures(self):
        cases = [
            ("ACTION_PIN", lambda d: d["jobs"]["check"]["steps"][0].update(uses="actions/checkout@v6")),
            ("PRIVILEGED_TRIGGER", lambda d: d["on"].update(pull_request_target="")),
            ("PERMISSIONS", lambda d: d.update(permissions={"contents": "write"})),
            ("PERMISSIONS", lambda d: d["jobs"]["check"].update(permissions={"contents": "write"})),
            ("PERMISSIONS", lambda d: d["jobs"]["check"].update(permissions={"contents": "read", "packages": "read"})),
            ("CHECKOUT_CREDENTIALS", lambda d: d["jobs"]["check"]["steps"][0]["with"].pop("persist-credentials")),
            ("CONTINUE_ON_ERROR", lambda d: d["jobs"]["check"]["steps"][0].update({"continue-on-error": "true"})),
            ("SECRETS", lambda d: d["jobs"]["check"].update(secrets="inherit")),
            ("DEVELOPMENT_PATH_FILTER", lambda d: d["on"]["pull_request"].update(paths=["app/**"])),
            ("FINAL_GATE", lambda d: d["jobs"].pop("gate")),
            ("RUNNER", lambda d: d["jobs"]["check"].update({"runs-on": "self-hosted"})),
            ("ARTIFACT_RETENTION", lambda d: d["jobs"]["check"]["steps"][1]["with"].update({"retention-days": "30"})),
        ]
        for category, mutate in cases:
            with self.subTest(category=category):
                candidate = deepcopy(self.fixture())
                mutate(candidate)
                self.assertIn(category, policy_issues(candidate, True))

    def test_actual_development_workflow(self):
        path = ROOT / ".github/workflows/f0-01-laravel-bootstrap.yml"
        actual = parse(path.read_text())
        self.assertEqual(policy_issues(actual, True), [])
        self.assertEqual(POLICY.aggregate_issues(actual), [])
        for job_id in ("image-scan", "native-integration"):
            for permissions in ({"contents": "read"}, {"contents": "read", "packages": "write"}):
                changed = deepcopy(actual)
                changed["jobs"][job_id]["permissions"] = permissions
                self.assertIn("PERMISSIONS", policy_issues(changed, True))

    def test_actual_spec_kit_workflow(self):
        path = ROOT / ".github/workflows/fnd-01-spec-kit-review.yml"
        self.assertEqual(policy_issues(parse(path.read_text()), False), [])

    def test_native_guarded_commands_are_preserved(self):
        actual = parse((ROOT / ".github/workflows/f0-01-laravel-bootstrap.yml").read_text())
        self.assertEqual(POLICY.native_issues(actual), [])
        for job_id, step_id, field, value, expected in (
            ("native-integration", "owned_start", "run", "echo skip", "NATIVE_GUARD"),
            ("native-integration", "owned_setup", "if", "false", "NATIVE_GUARD"),
            ("native-integration", "owned_cleanup", "run", "echo skip", "NATIVE_CLEANUP"),
            ("native-integration", "integration_upload", "with", {"path": "integration-raw.xml"}, "NATIVE_REPORTS"),
            ("bootstrap", "fast_report_cleanup", "run", "echo skip", "REPORT_CLEANUP"),
        ):
            with self.subTest(step=step_id):
                candidate = deepcopy(actual)
                step = next(s for s in candidate["jobs"][job_id]["steps"] if s.get("id") == step_id)
                step[field] = value
                self.assertIn(expected, POLICY.native_issues(candidate))
        candidate = deepcopy(actual)
        candidate["jobs"]["native-integration"]["needs"] = "unrelated"
        self.assertIn("NATIVE_DEPENDENCY", POLICY.native_issues(candidate))

    def test_exact_gate_condition_and_node_toolchain(self):
        candidate = self.fixture()
        candidate["jobs"]["gate"]["if"] = "always() && false"
        self.assertIn("FINAL_GATE", policy_issues(candidate, True))
        candidate = self.fixture()
        step = {"uses": "actions/setup-node@249970729cb0ef3589644e2896645e5dc5ba9c38",
                "with": {"node-version": "24.22.3", "package-manager-cache": "false"}}
        candidate["jobs"]["check"]["steps"].append(step)
        self.assertEqual(policy_issues(candidate, True), [])
        for key, value in (("node-version", "24"), ("package-manager-cache", "true")):
            changed = deepcopy(candidate)
            changed["jobs"]["check"]["steps"][-1]["with"][key] = value
            self.assertIn("NODE_PIN_OR_CACHE", policy_issues(changed, True))

    def test_fast_guard_condition_cannot_be_removed_or_bypassed(self):
        actual = parse((ROOT / ".github/workflows/f0-01-laravel-bootstrap.yml").read_text())
        self.assertEqual(POLICY.native_issues(actual), [])
        for condition in (None, "false", "always()"):
            changed = deepcopy(actual)
            step = next(s for s in changed["jobs"]["bootstrap"]["steps"] if s.get("id") == "fast_all")
            if condition is None:
                del step["if"]
            else:
                step["if"] = condition
            self.assertIn("FAST_GUARD", POLICY.native_issues(changed))


    def test_production_aggregate_rejects_missing_or_unsafe_transport(self):
        actual = parse((ROOT / ".github/workflows/f0-01-laravel-bootstrap.yml").read_text())
        self.assertEqual(POLICY.aggregate_issues(actual), [])
        for field, value in (("digest-mismatch", "warn"), ("skip-decompress", "false"),
                             ("github-token", "forbidden"), ("pattern", "*")):
            changed = deepcopy(actual)
            step = next(s for s in changed["jobs"]["gate"]["steps"] if s.get("uses", "").startswith("actions/download-artifact@"))
            step["with"][field] = value
            self.assertIn("ARTIFACT_DOWNLOAD", POLICY.aggregate_issues(changed))
        changed = deepcopy(actual)
        changed["jobs"]["gate"]["needs"].remove("image-scan")
        self.assertIn("AGGREGATE_NEEDS", POLICY.aggregate_issues(changed))
        changed = deepcopy(actual)
        changed["jobs"]["docs"]["if"] = "always()"
        self.assertIn("LANE_SELECTION", POLICY.aggregate_issues(changed))

    def test_guarded_php_lanes_do_not_inherit_report_timing(self):
        actual = parse((ROOT / ".github/workflows/f0-01-laravel-bootstrap.yml").read_text())
        for lane, directory in (("format", "F04_T016_DIR"), ("analysis", "F04_T019_DIR")):
            scripts = [step.get("run", "") for step in actual["jobs"][lane]["steps"]]
            expected = "if k not in {'" + directory + "','F04_LANE_STARTED','ACQUISITION_STATUS'}"
            self.assertEqual(sum(expected in script for script in scripts), 1)
