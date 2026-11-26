"""Test-first MVP classifier and changed-docs checks, using real local Git."""
import importlib.util
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
LANES = {"docs", "format", "analysis", "contracts", "dependency-audit",
         "image-scan", "bootstrap", "native-integration", "spec-kit"}
CONTRACT = "specs/002-api-foundation/contracts/openapi.json"
GUIDE = "docs/guides/reading.md"
TOPIC = "docs/guides/topic.md"
PHP = "app/Helpers/Example.php"
CORE = "app/Core/Http/Example.php"
LEDGER = "specs/004-code-quality-ci/tasks.md"


class GateQuality(unittest.TestCase):
    def helper(self, filename, missing):
        path = ROOT / "tools/quality" / filename
        if not path.is_file():
            self.fail(missing)
        spec = importlib.util.spec_from_file_location(filename.replace(".py", ""), path)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        return module

    def git(self, repo, *args):
        env = {"PATH": os.environ["PATH"], "HOME": str(repo.parent),
               "GIT_CONFIG_NOSYSTEM": "1", "GIT_CONFIG_GLOBAL": os.devnull,
               "GIT_TERMINAL_PROMPT": "0", "LC_ALL": "C"}
        result = subprocess.run(["git", "-c", "core.hooksPath=/dev/null",
                                 "-c", "user.name=Fixture", "-c", "user.email=fixture@example.invalid",
                                 *args], cwd=repo, env=env, capture_output=True, check=True, timeout=15)
        return result.stdout.decode("utf-8").strip()

    def write(self, repo, path, content):
        file = repo / path
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_text(content, encoding="utf-8")

    def history(self, change):
        owned = tempfile.TemporaryDirectory(prefix="f04-git-fixture-")
        self.addCleanup(owned.cleanup)
        repo = Path(owned.name) / "repo"
        repo.mkdir()
        self.git(repo, "init", "-q", "-b", "development")
        for path, content in {
            GUIDE: "# Reading guide\n\nAn example paragraph.\n",
            TOPIC: "# Useful topic\n\nExample text.\n",
            "docs/guides/old.md": "# Old\n\n[Historic broken](missing.md)\n",
            CONTRACT: '{"openapi":"3.1.0","info":{"title":"Fixture","version":"1"},"paths":{}}\n',
            PHP: "<?php\n// Example\n",
            CORE: "<?php\n// Example\n",
            "docs/02-standards/security-standards.md": "# Security standards\n",
            "composer.lock": "{}\n",
            ".specify/scripts/bash/example.sh": "#!/bin/sh\n",
            ".github/workflows/example.yml": "name: Fixture\n",
            LEDGER: self.ledger(0),
        }.items():
            self.write(repo, path, content)
        self.git(repo, "add", "--all")
        self.git(repo, "commit", "-qm", "base")
        base = self.git(repo, "rev-parse", "HEAD")
        self.git(repo, "switch", "-qc", "candidate")
        change(repo)
        self.git(repo, "add", "--all")
        self.git(repo, "commit", "-qm", "candidate")
        source = self.git(repo, "rev-parse", "HEAD")
        self.git(repo, "switch", "-q", "development")
        self.git(repo, "merge", "--no-ff", "-qm", "integrated", "candidate")
        checkout = self.git(repo, "rev-parse", "HEAD")
        identity = {"schemaVersion": 1, "repository": "Elmo-Tech/ecommerce_v2_api",
                    "event": "pull_request", "pr": 9, "baseRef": "development",
                    "baseSha": base, "sourceSha": source, "checkoutSha": checkout,
                    "runId": "123", "runAttempt": 1}
        return repo, identity

    def ledger(self, count):
        rows = ["# Tasks", "", "**Status:** **" + str(count) + "/56 complete.**", ""]
        rows.extend("- [ ] **T%03d** Fixture task." % number for number in range(1, 57))
        return "\n".join(rows) + "\n"

    def required(self, result):
        self.assertEqual(result["status"], "PASSED")
        self.assertEqual(set(result["lanes"]), LANES)
        for lane in result["lanes"].values():
            self.assertIs(type(lane["required"]), bool)
            self.assertIn(lane["selectionReason"], (
                "docs-only", "contract-only", "php-change", "shared-core",
                "ci-dependency-security", "spec-kit", "unknown-impact"))
        return {key for key, lane in result["lanes"].items() if lane["required"]}

    def test_owned_git_fixture_has_real_integrated_parents(self):
        repo, identity = self.history(lambda repo: self.write(repo, GUIDE, "# Reading\n\nNew prose.\n"))
        self.assertEqual(self.git(repo, "rev-list", "--parents", "-n", "1", "HEAD").split(),
                         [identity["checkoutSha"], identity["baseSha"], identity["sourceSha"]])
        self.assertEqual(self.git(repo, "diff", "--name-only", identity["baseSha"], "HEAD"), GUIDE)
        self.assertEqual(self.git(repo, "remote"), "")
        self.assertEqual(self.git(repo, "status", "--porcelain"), "")

    def test_t035_matrix_uses_actual_git_diff(self):
        classifier = self.helper("classify.py", "MISSING_CLASSIFIER")
        for path, content, required, reason in (
            (GUIDE, "# Reading guide\n\nMore examples.\n", {"docs"}, "docs-only"),
            (CONTRACT, '{"openapi":"3.1.0","info":{"title":"Updated","version":"1"},"paths":{}}\n',
             {"docs", "contracts", "dependency-audit", "bootstrap"}, "contract-only"),
            (PHP, "<?php\n// Updated\n", LANES - {"spec-kit"}, "php-change"),
            (CORE, "<?php\n// Updated Core\n", LANES - {"spec-kit"}, "shared-core"),
            ("composer.lock", '{"fixture":1}\n', LANES, "ci-dependency-security"),
            (".github/workflows/example.yml", "name: Changed\n", LANES, "ci-dependency-security"),
            (".specify/scripts/bash/example.sh", "#!/bin/sh\ntrue\n", LANES, "spec-kit"),
            ("docs/02-standards/security-standards.md", "# Security standards\n\nPolicy.\n", LANES, "unknown-impact"),
            ("docs/guides/policy.md", "# Policy\n\nSEC-06 requires review.\n", LANES, "unknown-impact"),
            ("README.md", "# Root change\n", LANES, "unknown-impact"),
            ("Docs/guides/reading.md", "# Wrong case\n", LANES, "unknown-impact"),
            ("unknown/new.md", "# Unknown scope\n", LANES, "unknown-impact"),
        ):
            with self.subTest(path=path):
                repo, identity = self.history(lambda repo: self.write(repo, path, content))
                result = classifier.classify(repo, identity)
                self.assertEqual(self.required(result), required)
                self.assertEqual(result["selectionReason"], reason)
                self.assertEqual(result["identity"], identity)

    def test_t035_ambiguous_or_mixed_changes_are_broad(self):
        classifier = self.helper("classify.py", "MISSING_CLASSIFIER")
        def mixed(repo):
            self.write(repo, GUIDE, "# New text\n")
            self.write(repo, "composer.lock", '{"fixture":2}\n')
        for name, mutate in (
            ("mixed", mixed),
            ("rename", lambda repo: (repo / "composer.lock").rename(repo / "docs/guides/moved.md")),
            ("delete", lambda repo: (repo / TOPIC).unlink()),
            ("executable", lambda repo: (repo / GUIDE).chmod(0o755)),
            ("symlink", lambda repo: (repo / GUIDE).unlink() or (repo / GUIDE).symlink_to("topic.md")),
            ("policy-prose", lambda repo: self.write(repo, GUIDE, "# Reading\n\nSEC-06 exception approved.\n")),
        ):
            with self.subTest(name=name):
                repo, identity = self.history(mutate)
                self.assertEqual(self.required(classifier.classify(repo, identity)), LANES)

    def test_t035_missing_or_mismatched_identity_blocks(self):
        classifier = self.helper("classify.py", "MISSING_CLASSIFIER")
        repo, identity = self.history(lambda repo: self.write(repo, GUIDE, "# Reading\n\nNew prose.\n"))
        for key, value in (
            ("baseSha", "a" * 40), ("sourceSha", identity["baseSha"]),
            ("checkoutSha", identity["sourceSha"]), ("baseRef", "main"),
            ("repository", "foreign/repo"), ("runAttempt", 0), ("runId", ""),
        ):
            with self.subTest(key=key):
                self.assertEqual(classifier.classify(repo, {**identity, key: value})["status"], "BLOCKED")
        missing = dict(identity)
        del missing["baseSha"]
        self.assertEqual(classifier.classify(repo, missing)["status"], "BLOCKED")
        self.write(repo, GUIDE, "dirty checkout")
        self.assertEqual(classifier.classify(repo, identity)["status"], "BLOCKED")

    def test_t036_changed_links_and_external_unverified(self):
        checker = self.helper("check-docs.py", "MISSING_DOCS_CHECKER")
        repo, identity = self.history(lambda repo: self.write(
            repo, GUIDE, "# Reading guide\n\n[Topic](topic.md#useful-topic)\n[External](https://example.invalid/never-fetch)\n"))
        result = checker.check_docs(repo, identity)
        self.assertEqual(result["status"], "PASSED")
        self.assertEqual(result["checkedDocuments"], 1)
        self.assertEqual(result["externalUnverified"], 1)
        self.assertEqual(result["issues"], [])
        # The unchanged historic missing link must not fail this changed-doc check.

    def test_t036_broken_local_links_and_unsafe_targets(self):
        checker = self.helper("check-docs.py", "MISSING_DOCS_CHECKER")
        def deleted(repo):
            (repo / TOPIC).unlink()
            self.write(repo, GUIDE, "# Reading\n\n[Deleted](topic.md)\n")
        def symlink(repo):
            (repo / TOPIC).unlink()
            (repo / TOPIC).symlink_to("/etc/passwd")
            self.write(repo, GUIDE, "# Reading\n\n[Unsafe](topic.md)\n")
        for reason, mutate in (
            ("MISSING_LINK_TARGET", lambda repo: self.write(repo, GUIDE, "# Reading\n\n[Missing](missing.md)\n")),
            ("MISSING_LINK_FRAGMENT", lambda repo: self.write(repo, GUIDE, "# Reading\n\n[Missing](topic.md#no-such-section)\n")),
            ("UNSAFE_LINK_TARGET", lambda repo: self.write(repo, GUIDE, "# Reading\n\n[Escape](../../../outside.md)\n")),
            ("MISSING_LINK_TARGET", deleted),
            ("UNSAFE_LINK_TARGET", symlink),
        ):
            with self.subTest(reason=reason):
                repo, identity = self.history(mutate)
                result = checker.check_docs(repo, identity)
                self.assertEqual(result["status"], "FAILED")
                self.assertIn(reason, {issue["code"] for issue in result["issues"]})

    def test_t036_known_ledger_count_consistency(self):
        checker = self.helper("check-docs.py", "MISSING_DOCS_CHECKER")
        for good in (True, False):
            with self.subTest(good=good):
                def change(repo):
                    text = self.ledger(1 if good else 2)
                    text = text.replace("- [ ] **T001**", "- [x] **T001**", 1)
                    self.write(repo, LEDGER, text)
                repo, identity = self.history(change)
                result = checker.check_docs(repo, identity)
                self.assertEqual(result["status"], "PASSED" if good else "FAILED")
                if not good:
                    self.assertIn("LEDGER_COUNT", {issue["code"] for issue in result["issues"]})

    def test_docs_distinguishes_existing_broken_link_from_new_broken_link(self):
        checker = self.helper("check-docs.py", "MISSING_DOCS_CHECKER")
        path = "docs/guides/old.md"
        repo, identity = self.history(lambda repo: self.write(
            repo, path, "# Old\n\n[Historic broken](missing.md)\n\nMore prose.\n"))
        result = checker.check_docs(repo, identity)
        self.assertEqual(result["status"], "PASSED")
        self.assertEqual(result["historicalIssues"], 1)
        repo, identity = self.history(lambda repo: self.write(
            repo, path, "# Old\n\n[Historic broken](missing.md)\n[New broken](missing.md)\n"))
        result = checker.check_docs(repo, identity)
        self.assertEqual(result["status"], "FAILED")
        self.assertEqual(result["historicalIssues"], 1)
        self.assertEqual([item["code"] for item in result["issues"]], ["MISSING_LINK_TARGET"])

    def test_docs_decision_marker_must_match_explicit_open_heading(self):
        checker = self.helper("check-docs.py", "MISSING_DOCS_CHECKER")
        path = "docs/decisions/OPEN_DECISIONS_AND_MVP_GATES.md"
        for good in (True, False):
            with self.subTest(good=good):
                status = "OPEN" if good else "APPROVED"
                repo, identity = self.history(lambda repo: self.write(
                    repo, path, "# Decisions\n\n**Next open:** D-201.\n\n## D-201 — Fixture (" + status + ")\n"))
                result = checker.check_docs(repo, identity)
                self.assertEqual(result["status"], "PASSED" if good else "FAILED")
                if not good:
                    self.assertIn("DECISION_STATUS", {item["code"] for item in result["issues"]})

    def test_ambiguous_guide_markup_is_not_docs_only(self):
        classifier = self.helper("classify.py", "MISSING_CLASSIFIER")
        for content in (
            "# Reading\n\n[Topic](topic.md#useful-topic)\n",
            "# Reading\n\n[Topic][t]\n[t]: topic.md\n",
            "# Reading\n\n[Unclosed](topic.md\n",
        ):
            with self.subTest(content=content):
                repo, identity = self.history(lambda repo: self.write(repo, GUIDE, content))
                expected = {"docs"} if "[Topic](topic.md#useful-topic)" in content else LANES
                self.assertEqual(self.required(classifier.classify(repo, identity)), expected)

