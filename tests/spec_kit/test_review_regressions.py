"""Focused regressions for CodeRabbit FND-01 findings; no real project writes."""

from __future__ import annotations

import json
import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASH_DIR = ROOT / ".specify/scripts/bash"
sys.path.insert(0, str(BASH_DIR))
from markdown_sections import replace_section  # noqa: E402


class MarkdownSectionRegressions(unittest.TestCase):
    def replace(self, content: str, replacement: str, selector: str) -> str:
        return replace_section(
            content,
            replacement,
            selector,
            preset="review-fixture",
            contribution="regression",
            artifact="fixture.md",
        )

    def test_next_heading_survives_non_newline_replacement(self) -> None:
        source = "## A\nold\n## B\nkeep\n"
        self.assertEqual(
            self.replace(source, "new", "a"),
            "## A\nnew\n## B\nkeep\n",
        )

    def test_next_heading_keeps_crlf(self) -> None:
        source = "## A\r\nold\r\n## B\r\nkeep\r\n"
        self.assertEqual(
            self.replace(source, "new", "a"),
            "## A\r\nnew\r\n## B\r\nkeep\r\n",
        )

    def test_empty_replacement_does_not_insert_spurious_newline(self) -> None:
        self.assertEqual(
            self.replace("## A\nold\n## B\nkeep\n", "", "a"),
            "## A\n## B\nkeep\n",
        )

    def test_html_comment_headings_do_not_cause_ambiguity(self) -> None:
        source = "## A\noriginal\n<!--\n## B\n```\n-->\n## B\nreal\n"
        self.assertEqual(
            self.replace(source, "updated", "b"),
            "## A\noriginal\n<!--\n## B\n```\n-->\n## B\nupdated",
        )

    def test_inline_comments_do_not_change_heading_count(self) -> None:
        source = "## A\n<!-- ## B -->\n## B\nreal\n"
        self.assertEqual(
            self.replace(source, "new", "b"),
            "## A\n<!-- ## B -->\n## B\nnew",
        )

    def test_unicode_line_separator_is_not_a_markdown_newline(self) -> None:
        source = "## A\nText\u2028## B\n## B\nreal\n"
        self.assertEqual(
            self.replace(source, "new", "b"),
            "## A\nText\u2028## B\n## B\nnew",
        )


class ShellRegressions(unittest.TestCase):
    def run_bash(self, command: str, root: Path) -> subprocess.CompletedProcess[str]:
        return subprocess.run(
            ["bash", "-c", command, "--", str(root)],
            cwd=ROOT,
            text=True,
            capture_output=True,
            check=False,
        )

    def test_compact_json_fallback_parses_its_own_key(self) -> None:
        with tempfile.TemporaryDirectory(prefix="spec-kit-review-") as d:
            root = Path(d)
            (root / ".specify").mkdir()
            (root / ".specify/feature.json").write_text(
                '{"version":1,"feature_directory":"specs/001-example"}\n',
                encoding="utf-8",
            )
            command = (
                f'source "{BASH_DIR / "common.sh"}"; '
                'command() { '
                'if [[ "$1" == "-v" && ( "$2" == "jq" || "$2" == "python3" ) ]]; '
                'then return 1; fi; builtin command "$@"; }; '
                'read_feature_json_feature_directory "$1"'
            )
            result = self.run_bash(command, root)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(result.stdout, "specs/001-example")

    def test_fallback_returns_empty_on_unmatched_value(self) -> None:
        with tempfile.TemporaryDirectory(prefix="spec-kit-review-") as d:
            root = Path(d)
            (root / ".specify").mkdir()
            (root / ".specify/feature.json").write_text(
                '{"version":1,"feature_directory":77}\n', encoding="utf-8"
            )
            command = (
                f'source "{BASH_DIR / "common.sh"}"; '
                'command() { '
                'if [[ "$1" == "-v" && ( "$2" == "jq" || "$2" == "python3" ) ]]; '
                'then return 1; fi; builtin command "$@"; }; '
                'read_feature_json_feature_directory "$1"'
            )
            result = self.run_bash(command, root)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(result.stdout, "")

    def test_failed_pin_writer_keeps_existing_file_byte_identical(self) -> None:
        with tempfile.TemporaryDirectory(prefix="spec-kit-review-") as d:
            root = Path(d)
            pin = root / ".specify/feature.json"
            pin.parent.mkdir()
            before = b'{"feature_directory":"specs/001-existing"}\n'
            pin.write_bytes(before)
            command = (
                f'source "{BASH_DIR / "common.sh"}"; '
                'jq() { return 42; }; '
                '_persist_feature_json "$1" specs/002-new'
            )
            result = self.run_bash(command, root)
            self.assertNotEqual(result.returncode, 0)
            self.assertEqual(pin.read_bytes(), before)
            self.assertEqual(list(pin.parent.glob(".feature.json.*")), [])

    def test_successful_pin_replacement_is_valid_json(self) -> None:
        with tempfile.TemporaryDirectory(prefix="spec-kit-review-") as d:
            root = Path(d)
            (root / ".specify").mkdir()
            cmd = (
                f'source "{BASH_DIR / "common.sh"}"; '
                '_persist_feature_json "$1" specs/002-new'
            )
            result = self.run_bash(cmd, root)
            self.assertEqual(result.returncode, 0, result.stderr)
            parsed = json.loads((root / ".specify/feature.json").read_text())
            self.assertEqual(parsed["feature_directory"], "specs/002-new")
            self.assertEqual(list((root / ".specify").glob(".feature.json.*")), [])

    def test_conflicting_override_is_rejected_before_feature_creation(self) -> None:
        with tempfile.TemporaryDirectory(prefix="spec-kit-review-") as d:
            root = Path(d)
            (root / ".specify").mkdir()
            env = os.environ.copy()
            env["SPECIFY_INIT_DIR"] = str(root)
            env["SPECIFY_FEATURE_DIRECTORY"] = "specs/001-old"
            result = subprocess.run(
                [
                    "bash",
                    str(BASH_DIR / "create-new-feature.sh"),
                    "--number", "998",
                    "--short-name", "review-new",
                    "Review new feature",
                ],
                cwd=root,
                env=env,
                text=True,
                capture_output=True,
                check=False,
            )
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("SPECIFY_FEATURE_DIRECTORY", result.stderr)
            self.assertFalse((root / "specs/998-review-new").exists())
            self.assertFalse((root / ".specify/feature.json").exists())

    def test_large_template_roundtrips_as_json_without_argument_limit(self) -> None:
        with tempfile.TemporaryDirectory(prefix="spec-kit-review-") as d:
            root = Path(d)
            override = root / ".specify/templates/overrides/tasks-template.md"
            override.parent.mkdir(parents=True)
            expected = "Template large block\n" + ("x" * 150_000) + "\n"
            override.write_text(expected, encoding="utf-8")
            env = os.environ.copy()
            env["SPECIFY_INIT_DIR"] = str(root)
            result = subprocess.run(
                ["bash", str(BASH_DIR / "resolve-template.sh"), "tasks-template", "--json"],
                cwd=root,
                env=env,
                text=True,
                capture_output=True,
                check=False,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertEqual(json.loads(result.stdout)["TEMPLATE_CONTENT"], expected)


if __name__ == "__main__":
    unittest.main()
