"""Bounded changed-Markdown checks against committed trees, never external URLs."""
from collections import Counter
import json
from pathlib import Path
import posixpath
import re
import subprocess
import sys
import unicodedata
from urllib.parse import unquote, urlsplit

sys.path.insert(0, str(Path(__file__).resolve().parent))
from classify import ROOT, SHA, INLINE, blob_text, git, hosted_identity, require, safe_path, verified_diff

LEDGER = "specs/004-code-quality-ci/tasks.md"
DECISIONS = "docs/decisions/OPEN_DECISIONS_AND_MVP_GATES.md"
REFERENCE = re.compile(r"(?m)^\s{0,3}\[[^\]\n]+\]:[ \t]*(<[^>\n]+>|[^\s]+)")
HEADING = re.compile(r"(?m)^ {0,3}#{1,6}[ \t]+(.+?)\s*$")


def tree_entries(repo, oid):
    require(SHA.fullmatch(oid))
    entries = {}
    raw = git(repo, "ls-tree", "-r", "-t", "-z", oid)
    require(not raw or raw.endswith(b"\0"))
    for row in raw.split(b"\0")[:-1]:
        meta, path = row.split(b"\t", 1)
        mode, kind, blob = meta.decode("ascii").split()
        path = path.decode("utf-8", errors="surrogateescape")
        require(path not in entries and SHA.fullmatch(blob))
        entries[path] = (mode, kind, blob)
    return entries


def prose(body):
    """Ignore fenced examples and inline code, not document links."""
    lines, fence = [], None
    for line in body.splitlines():
        match = re.match(r"^\s{0,3}(["+chr(96)+r"~]{3,})", line)
        if match and len(set(match[1])) == 1:
            mark = match[1]
            if fence is None:
                fence = mark
            elif mark[0] == fence[0] and len(mark) >= len(fence):
                fence = None
            continue
        if fence is None:
            lines.append(line)
    return "\n".join(lines)


def destinations(body):
    text = re.sub(chr(96)+r"+[^"+chr(96)+r"\n]*"+chr(96)+r"+", "", prose(body))
    return [match[1].strip("<>") for pattern in (INLINE, REFERENCE) for match in pattern.finditer(text)]


def anchors(body):
    found = set()
    for match in HEADING.finditer(prose(body)):
        title = re.sub(r"\s+#+$", "", match[1])
        title = re.sub(r"\[([^\]]+)\]\([^)]+\)", r"\1", title).lower()
        slug = "".join("-" if c.isspace() else c for c in title
                       if c in "_-" or c.isspace() or unicodedata.category(c)[0] in ("L", "N", "M"))
        slug = slug.strip("-")
        if slug:
            candidate, suffix = slug, 1
            while candidate in found:
                candidate = slug + "-" + str(suffix)
                suffix += 1
            found.add(candidate)
    return found


def link_issue(repo, tree, document, destination, cache):
    try:
        if re.search(r"%(?![a-fA-F0-9]{2})", destination):
            return "UNSAFE_LINK_TARGET"
        url = urlsplit(destination)
        if url.scheme in ("http", "https", "mailto") or (not url.scheme and url.netloc):
            return "EXTERNAL_UNVERIFIED"
        if url.scheme or url.netloc or url.query:
            return "UNSAFE_LINK_TARGET"
        path = unquote(url.path, errors="strict")
        fragment = unquote(url.fragment, errors="strict")
        if ("\\" in path or any(ord(c) < 32 for c in path + fragment)
                or path.startswith("//")):
            return "UNSAFE_LINK_TARGET"
        target = (posixpath.normpath(path.lstrip("/")) if path.startswith("/")
                  else posixpath.normpath(posixpath.join(posixpath.dirname(document), path)) if path
                  else document)
        if not safe_path(target):
            return "UNSAFE_LINK_TARGET"
        parts = target.split("/")
        for i in range(1, len(parts) + 1):
            entry = tree.get("/".join(parts[:i]))
            if entry and (entry[0] == "120000" or entry[1] == "commit"):
                return "UNSAFE_LINK_TARGET"
        if target not in tree:
            return "MISSING_LINK_TARGET"
        if fragment:
            mode, kind, oid = tree[target]
            if kind != "blob" or mode not in ("100644", "100755") or not target.lower().endswith(".md"):
                return "MISSING_LINK_FRAGMENT"
            if oid not in cache:
                cache[oid] = anchors(blob_text(repo, oid))
            if fragment not in cache[oid]:
                return "MISSING_LINK_FRAGMENT"
        return None
    except (OSError, ValueError, UnicodeError, subprocess.SubprocessError):
        return "UNSAFE_LINK_TARGET"


def document_checks(path, body):
    issues = []
    if path == LEDGER:
        status = re.search(r"(?m)^\*\*Status:\*\* \*\*(\d+)/(\d+) complete\b", body)
        rows = re.findall(r"(?m)^- \[([ x])\] \*\*(T\d{3})\*\*", body)
        expected = ["T%03d" % i for i in range(1, 57)]
        if (not status or int(status[2]) != 56 or [task for _, task in rows] != expected
                or int(status[1]) != sum(checked == "x" for checked, _ in rows)):
            issues.append("LEDGER_COUNT")
        for line in body.splitlines():
            task = re.match(r"^- \[[ x]\] \*\*T(\d{3})\*\*", line)
            deps = re.search(r"\*\*Depends:\*\* (.*?)\*\*Trace:", line)
            if task and deps and any(int(n) >= int(task[1]) or int(n) < 1
                                     for n in re.findall(r"\bT(\d{3})\b", deps[1])):
                issues.append("LEDGER_DEPENDENCY")
    if path == DECISIONS:
        opened = re.search(r"\*\*Next open:\*\*\s*(D-\d+)", body)
        if opened and not re.search(r"(?m)^## " + re.escape(opened[1]) + r"\b[^\n]*\(OPEN\)\s*$", body):
            issues.append("DECISION_STATUS")
    return issues


def check_docs(repo, identity):
    output = {"status": "BLOCKED", "checkedDocuments": 0, "externalUnverified": 0,
              "historicalIssues": 0, "issues": []}
    try:
        repo, trees, changes = verified_diff(repo, identity)
        before = tree_entries(repo, trees["baseSha"])
        after = tree_entries(repo, trees["checkoutSha"])
        paths = {change["paths"][-1] for change in changes if change["status"] != "D"
                 and change["paths"][-1].lower().endswith(".md")}
        output.update(identity=identity, trees=trees, changedCount=len(changes))
        cache = {}
        for path in sorted(paths):
            if not safe_path(path) or after.get(path, ("",))[0] != "100644":
                output["issues"].append({"code": "UNSAFE_DOCUMENT"})
                continue
            body = blob_text(repo, after[path][2])
            output["checkedDocuments"] += 1
            previous = Counter()
            if before.get(path, ("",))[0] == "100644":
                old = blob_text(repo, before[path][2])
                for destination in destinations(old):
                    code = link_issue(repo, before, path, destination, cache)
                    if code:
                        previous[(destination, code)] += 1
            for destination in destinations(body):
                code = link_issue(repo, after, path, destination, cache)
                if code == "EXTERNAL_UNVERIFIED":
                    output["externalUnverified"] += 1
                elif code and previous[(destination, code)]:
                    previous[(destination, code)] -= 1
                    output["historicalIssues"] += 1
                elif code:
                    output["issues"].append({"code": code, "document": path})
            for code in document_checks(path, body):
                output["issues"].append({"code": code, "document": path})
            require(len(output["issues"]) <= 10000)
        output["status"] = "FAILED" if output["issues"] else "PASSED"
    except (OSError, ValueError, KeyError, TypeError, AttributeError, subprocess.SubprocessError):
        output["status"] = "BLOCKED"
        output["reason"] = "INVALID_DOCUMENT_EVIDENCE"
    return output


if __name__ == "__main__":
    try:
        result = check_docs(ROOT, hosted_identity())
    except (OSError, ValueError, KeyError, TypeError, AttributeError):
        result = {"status": "BLOCKED", "reason": "INVALID_PR_IDENTITY"}
    print(json.dumps(result, sort_keys=True))
    sys.exit({"PASSED": 0, "FAILED": 1, "BLOCKED": 2}[result["status"]])
