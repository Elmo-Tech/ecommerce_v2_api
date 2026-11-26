"""Run the existing real unittest suite and emit only bounded case identities."""
import json
import os
from pathlib import Path
import platform
import sys
import unittest

import yaml
from classify import ROOT, classify, git, hosted_identity, require


class RecordedResult(unittest.TextTestResult):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.cases = []

    def startTest(self, test):
        self.cases.append(test.id())
        super().startTest(test)


def run():
    identity = hosted_identity()
    require(classify(ROOT, identity)["status"] == "PASSED")
    sys.path.insert(0, str(ROOT / "tests/spec_kit"))
    suite = unittest.defaultTestLoader.discover(str(ROOT / "tests/spec_kit"), pattern="test_*.py")
    # Keep verbose diagnostics separate from the structured stdout report.
    result = unittest.TextTestRunner(stream=sys.stderr, verbosity=2, resultclass=RecordedResult).run(suite)
    cases = sorted(result.cases)
    errors = len(result.errors) + len(result.unexpectedSuccesses)
    failures = len(result.failures)
    skipped = len(result.skipped) + len(result.expectedFailures)
    clean = not git(ROOT, "status", "--porcelain", "--untracked-files=no")
    passed = (result.wasSuccessful() and not skipped and clean and
              len(cases) == len(set(cases)) == result.testsRun and result.testsRun >= 60)
    report = {key: identity[key] for key in ("sourceSha", "baseSha", "checkoutSha", "runId")}
    report.update(schemaVersion=1, runAttempt=str(identity["runAttempt"]),
                  status="PASSED" if passed else "FAILED", testCount=result.testsRun,
                  failedCount=failures, errorCount=errors, skippedCount=skipped, cases=cases,
                  python=platform.python_version(), pyyaml=yaml.__version__,
                  trackedSourceUnchanged=clean, shellSyntaxPassed=True)
    return report


if __name__ == "__main__":
    try:
        result = run()
    except (OSError, ValueError, KeyError, TypeError, AttributeError, ImportError):
        result = {"status": "BLOCKED", "reason": "SPEC_KIT_REPORT_INCOMPLETE"}
    print(json.dumps(result, sort_keys=True))
    sys.exit({"PASSED": 0, "FAILED": 1, "BLOCKED": 2}[result["status"]])
