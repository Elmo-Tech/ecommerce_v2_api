"""Focused test-first CI policy checks using the existing PyYAML test dependency."""
from copy import deepcopy
from pathlib import Path
import unittest

import yaml

ROOT = Path(__file__).resolve().parents[2]
PINS = {
    "actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803",
    "actions/upload-artifact@cf430e030ddbb5b0abf93d22962f4752f3646cd9",
    "actions/setup-node@249970729cb0ef3589644e2896645e5dc5ba9c38",
    "actions/setup-python@ece7cb06caefa5fff74198d8649806c4678c61a1",
    "shivammathur/setup-php@eb7c497e18156a6bbabfef1d3a82760b9eda3962",
    "actions/download-artifact@9000827ccba6bdab643e8b6fd33ac0654aef8333",
}
READ_ONLY = {"contents": "read"}
REUSABLE = "./.github/workflows/fnd-01-spec-kit-review.yml"


def parse(raw):
    # BaseLoader keeps GitHub's "on" key and literal booleans as strings.
    return yaml.load(raw, Loader=yaml.BaseLoader)


def policy_issues(doc, primary):
    """Test contract only; promote to the small reusable check in T030."""
    issues = set()
    if not isinstance(doc, dict):
        return ["WORKFLOW_SHAPE"]
    triggers = doc.get("on")
    jobs = doc.get("jobs")
    if not isinstance(triggers, dict) or not isinstance(jobs, dict) or not jobs:
        return ["WORKFLOW_SHAPE"]
    if "pull_request_target" in triggers:
        issues.add("PRIVILEGED_TRIGGER")
    pr = triggers.get("pull_request")
    if isinstance(pr, dict):
        branches = pr.get("branches")
        if (branches is None or "development" in branches) and (
            "paths" in pr or "paths-ignore" in pr
        ):
            issues.add("DEVELOPMENT_PATH_FILTER")
    if primary:
        if not isinstance(pr, dict) or "development" not in pr.get("branches", []):
            issues.add("DEVELOPMENT_TRIGGER")
        gates = [job for job in jobs.values()
                 if isinstance(job, dict) and job.get("name") == "F0-04 / Quality Gate"]
        if len(gates) != 1 or "always()" not in gates[0].get("if", ""):
            issues.add("FINAL_GATE")
    elif "workflow_call" not in triggers:
        issues.add("REUSABLE_SPEC_KIT")
    if doc.get("permissions") != READ_ONLY:
        issues.add("PERMISSIONS")
    for job in jobs.values():
        if not isinstance(job, dict):
            issues.add("WORKFLOW_SHAPE")
            continue
        if job.get("permissions", READ_ONLY) != READ_ONLY:
            issues.add("PERMISSIONS")
        if job.get("continue-on-error", "false") != "false":
            issues.add("CONTINUE_ON_ERROR")
        if "secrets" in job:
            issues.add("SECRETS")
        if "uses" in job:
            if job["uses"] != REUSABLE or not primary:
                issues.add("ACTION_PIN")
            continue
        if job.get("runs-on") != "ubuntu-24.04":
            issues.add("RUNNER")
        for step in job.get("steps", []):
            if not isinstance(step, dict):
                issues.add("WORKFLOW_SHAPE")
                continue
            if step.get("continue-on-error", "false") != "false":
                issues.add("CONTINUE_ON_ERROR")
            if "uses" not in step:
                continue
            ref = step["uses"]
            if ref not in PINS:
                issues.add("ACTION_PIN")
            if ref.startswith("actions/checkout@") and (
                step.get("with", {}).get("persist-credentials") != "false"
            ):
                issues.add("CHECKOUT_CREDENTIALS")
            if ref.startswith("actions/upload-artifact@") and (
                step.get("with", {}).get("retention-days") != "7"
            ):
                issues.add("ARTIFACT_RETENTION")
    return sorted(issues)


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
