"""Small structural workflow policy; reuse the existing PyYAML dependency."""
from pathlib import Path
import json
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
    """Check the approved workflow policy without executing workflow commands."""
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


def main():
    paths = (
        (".github/workflows/f0-01-laravel-bootstrap.yml", True),
        (".github/workflows/fnd-01-spec-kit-review.yml", False),
    )
    results = {}
    for name, primary in paths:
        try:
            results[name] = policy_issues(parse((ROOT / name).read_text()), primary)
        except (OSError, ValueError, TypeError, AttributeError, yaml.YAMLError):
            results[name] = ["WORKFLOW_SHAPE"]
    print(json.dumps(results, sort_keys=True))
    return int(any(results.values()))


if __name__ == "__main__":
    raise SystemExit(main())
