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
        self.assertEqual(policy_issues(parse(path.read_text()), True), [])

    def test_actual_spec_kit_workflow(self):
        path = ROOT / ".github/workflows/fnd-01-spec-kit-review.yml"
        self.assertEqual(policy_issues(parse(path.read_text()), False), [])
