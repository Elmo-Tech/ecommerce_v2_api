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
        if len(gates) != 1 or gates[0].get("if") not in ("always()", "${{ always() }}"):
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
            if ref.startswith("actions/setup-node@") and (
                step.get("with", {}).get("node-version") != "24.22.3"
                or step.get("with", {}).get("package-manager-cache") != "false"
            ):
                issues.add("NODE_PIN_OR_CACHE")
            if ref.startswith("actions/upload-artifact@") and (
                step.get("with", {}).get("retention-days") != "7"
            ):
                issues.add("ARTIFACT_RETENTION")
    return sorted(issues)


def native_issues(doc):
    """Protect the existing native harness across the future CI migration."""
    jobs = doc.get("jobs", {})
    native_ids = [key for key in ("f0-03-integration", "native-integration") if key in jobs]
    if len(native_ids) != 1 or "bootstrap" not in jobs:
        return ["NATIVE_JOBS"]
    native = jobs[native_ids[0]]
    fast = jobs["bootstrap"]
    issues = set()
    needs = native.get("needs")
    if not (needs == "bootstrap" or isinstance(needs, list) and "bootstrap" in needs):
        issues.add("NATIVE_DEPENDENCY")
    prefix = "php -d zend.exception_ignore_args=1 tests/Support/ci/"
    def guarded_steps(job, commands, condition=None):
        steps = job.get("steps", [])
        previous = -1
        for command in commands:
            matches = [(i, s) for i, s in enumerate(steps) if s.get("run") == prefix + command]
            if (len(matches) != 1 or matches[0][0] <= previous
                    or matches[0][1].get("if") != condition):
                return False
            previous = matches[0][0]
        return True
    fast_condition = "$" + "{{ !cancelled() && steps.fast_ready.outcome == 'success' }}"
    if not guarded_steps(fast, ("run-report.php foundation", "run-report.php fast-all", "run-report.php architecture"), fast_condition):
        issues.add("FAST_GUARD")
    if not guarded_steps(native, ("start-owned.php", "setup-owned.php", "run-report.php integration")):
        issues.add("NATIVE_GUARD")
    steps = native.get("steps", [])
    cleanup = [s for s in steps if s.get("id") == "owned_cleanup"]
    cleanup_command = prefix + "cleanup-owned.php"
    if (len(cleanup) != 1
            or cleanup[0].get("if") != ("$" + "{{ always() && steps.run_identity.outcome == 'success' }}")
            or cleanup[0].get("run", "").splitlines() != ["set -euo pipefail", cleanup_command, cleanup_command]):
        issues.add("NATIVE_CLEANUP")
    expected = ("integration-safe.xml", "integration.summary.json")
    uploads = [s for s in steps if s.get("id") == "integration_upload"]
    if len(uploads) != 1:
        issues.add("NATIVE_REPORTS")
    else:
        options = uploads[0].get("with", {})
        paths = options.get("path", "").splitlines()
        if (paths != ["$" + "{{ env.F03_REPORT_DIR }}/" + name for name in expected]
                or options.get("if-no-files-found") != "error"
                or options.get("retention-days") != "7"):
            issues.add("NATIVE_REPORTS")
    for job, step_id in ((fast, "fast_report_cleanup"), (native, "integration_report_cleanup")):
        matches = [s for s in job.get("steps", []) if s.get("id") == step_id]
        if (len(matches) != 1 or matches[0].get("run") != prefix + "cleanup-reports.php"
                or "always()" not in matches[0].get("if", "")):
            issues.add("REPORT_CLEANUP")
    return sorted(issues)


def main():
    paths = (
        (".github/workflows/f0-01-laravel-bootstrap.yml", True),
        (".github/workflows/fnd-01-spec-kit-review.yml", False),
    )
    results = {}
    for name, primary in paths:
        try:
            doc = parse((ROOT / name).read_text())
            results[name] = policy_issues(doc, primary)
            if primary:
                results[name] = sorted(set(results[name] + native_issues(doc)))
        except (OSError, ValueError, TypeError, AttributeError, yaml.YAMLError):
            results[name] = ["WORKFLOW_SHAPE"]
    print(json.dumps(results, sort_keys=True))
    return int(any(results.values()))


if __name__ == "__main__":
    raise SystemExit(main())
