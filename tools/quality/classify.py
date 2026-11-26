"""Conservative MVP change selection using Git's actual integrated tree."""
import json
import os
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
REPOSITORY = "Elmo-Tech/ecommerce_v2_api"
LANES = ("docs", "format", "analysis", "contracts", "dependency-audit",
         "image-scan", "bootstrap", "native-integration", "spec-kit")
CONTRACT = "specs/002-api-foundation/contracts/openapi.json"
GUIDE = re.compile(r"docs/guides/[a-z0-9][a-z0-9-]*\.md\Z")
SHA = re.compile(r"[a-f0-9]{40}\Z")
SAFE_PATH = re.compile(r"[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*\Z")
POLICY_WORDS = re.compile(
    r"\b(?:policy|policies|decision|decisions|security|approval|approved|exception|exceptions|"
    r"constitution|standards?|permissions?|authorization|authentication|tenant|"
    r"release|deployment|must|shall|required|mandatory|blocked|deferred|gate|gates)\b"
    r"|\b(?:D|BA|SEC|FR|AC|SC|FBR|AUTHZ)-\d+\b", re.I)
INLINE = re.compile(r"(?<!\\)\[[^\]\n]*\]\(\s*(<[^>\n]+>|[^\s)]+)(?:[ \t]+[\"'][^\n]*?[\"'])?\s*\)")
IDENTITY_KEYS = {"schemaVersion", "repository", "event", "pr", "baseRef",
                 "baseSha", "sourceSha", "checkoutSha", "runId", "runAttempt"}


def require(condition):
    if not condition:
        raise ValueError("INVALID_GIT_EVIDENCE")


def git(repo, *args, limit=2 * 1024 * 1024):
    env = {"PATH": os.environ["PATH"], "LC_ALL": "C", "GIT_CONFIG_NOSYSTEM": "1",
           "GIT_CONFIG_GLOBAL": os.devnull, "GIT_NO_REPLACE_OBJECTS": "1",
           "GIT_TERMINAL_PROMPT": "0", "GIT_OPTIONAL_LOCKS": "0"}
    result = subprocess.run(["git", "--no-pager", *args], cwd=repo, env=env,
                            stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                            timeout=30, check=True)
    require(len(result.stdout) <= limit)
    return result.stdout


def git_text(repo, *args):
    return git(repo, *args).decode("utf-8").strip()


def safe_path(path):
    return bool(SAFE_PATH.fullmatch(path)) and all(part not in (".", "..") for part in path.split("/"))


def blob_text(repo, oid, limit=2 * 1024 * 1024):
    require(SHA.fullmatch(oid))
    size = int(git_text(repo, "cat-file", "-s", oid))
    require(0 <= size <= limit)
    raw = git(repo, "cat-file", "blob", oid, limit=limit)
    require(len(raw) == size)
    return raw.decode("utf-8")


def verified_diff(repo, identity):
    """No ref mutation, network or worktree content is used for selection."""
    repo = Path(repo).resolve()
    require(isinstance(identity, dict) and set(identity) == IDENTITY_KEYS
            and type(identity["schemaVersion"]) is int and identity["schemaVersion"] == 1
            and identity["repository"] == REPOSITORY and identity["event"] == "pull_request"
            and identity["baseRef"] == "development" and type(identity["pr"]) is int and identity["pr"] > 0
            and isinstance(identity["runId"], str) and re.fullmatch(r"[1-9][0-9]{0,19}", identity["runId"])
            and type(identity["runAttempt"]) is int and identity["runAttempt"] > 0)
    for key in ("baseSha", "sourceSha", "checkoutSha"):
        require(isinstance(identity[key], str) and SHA.fullmatch(identity[key]))
    base, source, checkout = (identity[key] for key in ("baseSha", "sourceSha", "checkoutSha"))
    require(len({base, source, checkout}) == 3)
    require(Path(git_text(repo, "rev-parse", "--show-toplevel")).resolve() == repo
            and git_text(repo, "rev-parse", "HEAD") == checkout
            and git_text(repo, "rev-list", "--parents", "-n", "1", "HEAD").split() == [checkout, base, source]
            and not git(repo, "status", "--porcelain=v1", "-z", "--untracked-files=no"))
    trees = {key: git_text(repo, "rev-parse", identity[key] + "^{tree}")
             for key in ("baseSha", "sourceSha", "checkoutSha")}
    require(all(SHA.fullmatch(oid) for oid in trees.values()))
    raw = git(repo, "diff", "--raw", "-z", "--no-abbrev", "--find-renames",
              "--no-ext-diff", "--no-textconv", base, checkout, "--")
    require(not raw or raw.endswith(b"\0"))
    parts = raw.split(b"\0")[:-1] if raw else []
    changes = []
    i = 0
    while i < len(parts):
        match = re.fullmatch(rb":([0-7]{6}) ([0-7]{6}) ([a-f0-9]{40}) ([a-f0-9]{40}) ([A-Z][0-9]*)", parts[i])
        require(match)
        old_mode, mode, old_oid, oid, status = (value.decode("ascii") for value in match.groups())
        count = 2 if status[0] in ("R", "C") else 1
        require(i + count < len(parts))
        paths = [part.decode("utf-8", errors="surrogateescape") for part in parts[i + 1:i + 1 + count]]
        require(all(paths))
        changes.append({"status": status, "oldMode": old_mode, "mode": mode,
                        "oldOid": old_oid, "oid": oid, "paths": paths})
        require(len(changes) <= 10000)
        i += count + 1
    return repo, trees, changes


def prose_guide(repo, path, change):
    if not GUIDE.fullmatch(path) or POLICY_WORDS.search(path):
        return False
    for oid in (change["oldOid"], change["oid"]):
        if oid == "0" * 40:
            continue
        try:
            body = blob_text(repo, oid, limit=64 * 1024)
        except (ValueError, UnicodeError, subprocess.SubprocessError):
            return False
        # Narrow, non-governing prose only; uncertain markup/language is broad.
        if (not body.isascii() or POLICY_WORDS.search(body)
                or any(ord(c) < 32 and c not in "\n\r\t" for c in body)
                or re.search(r"(?m)^(?:---|\s{0,3}(?:~~~|"+chr(96)*3+r"))|[<>]", body)
                or chr(96) in body
                or re.search(r"[\[\]\\]", INLINE.sub("", body))):
            return False
    return True


def change_reason(repo, change):
    path = change["paths"][-1]
    if (change["status"] not in ("A", "M") or len(change["paths"]) != 1
            or change["mode"] != "100644" or change["oldMode"] not in ("000000", "100644")
            or not safe_path(path)):
        return "unknown-impact"
    if path == CONTRACT and change["status"] == "M":
        return "contract-only"
    if path.startswith((".specify/", "tests/spec_kit/")):
        return "spec-kit"
    if (path.startswith((".github/", "tools/", "tests/Support/"))
            or path in {"composer.json", "composer.lock", "package.json", "package-lock.json",
                        "phpunit.xml", "phpunit.integration.xml", "phpstan.neon", "phpstan.neon.dist", "pint.json",
                        "artisan", ".env.example"}):
        return "ci-dependency-security"
    if path.endswith(".php") and path.startswith(("app/", "config/", "bootstrap/", "routes/",
                                                 "tests/", "lang/", "database/", "public/")):
        return "shared-core" if path.startswith("app/Core/") else "php-change"
    if prose_guide(repo, path, change):
        return "docs-only"
    return "unknown-impact"


def classify(repo, identity):
    try:
        repo, trees, changes = verified_diff(repo, identity)
        reasons = {change_reason(repo, change) for change in changes}
        reason = next(iter(reasons)) if len(reasons) == 1 else "unknown-impact"
        required = {"docs-only": {"docs"},
                    "contract-only": {"docs", "contracts", "dependency-audit", "bootstrap"},
                    "php-change": set(LANES) - {"spec-kit"},
                    "shared-core": set(LANES) - {"spec-kit"}}.get(reason, set(LANES))
        return {"status": "PASSED", "identity": identity, "trees": trees, "changedCount": len(changes),
                "selectionReason": reason, "lanes": {
                    lane: {"required": lane in required, "selectionReason": reason} for lane in LANES}}
    except (OSError, ValueError, KeyError, TypeError, AttributeError, subprocess.SubprocessError):
        return {"status": "BLOCKED", "reason": "INVALID_GIT_EVIDENCE", "lanes": {}}


def hosted_identity():
    require(os.environ.get("GITHUB_ACTIONS") == "true"
            and os.environ.get("RUNNER_ENVIRONMENT") == "github-hosted"
            and os.environ.get("GITHUB_REPOSITORY") == REPOSITORY
            and os.environ.get("GITHUB_EVENT_NAME") == "pull_request")
    event = json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text(encoding="utf-8"))
    pr = event["pull_request"]
    require(pr["base"]["repo"]["full_name"] == REPOSITORY
            and os.environ.get("GITHUB_REF") == "refs/pull/" + str(event["number"]) + "/merge")
    return {"schemaVersion": 1, "repository": REPOSITORY, "event": "pull_request",
            "pr": event["number"], "baseRef": pr["base"]["ref"],
            "baseSha": pr["base"]["sha"], "sourceSha": pr["head"]["sha"],
            "checkoutSha": os.environ["GITHUB_SHA"], "runId": os.environ["GITHUB_RUN_ID"],
            "runAttempt": int(os.environ["GITHUB_RUN_ATTEMPT"])}


if __name__ == "__main__":
    try:
        result = classify(ROOT, hosted_identity())
    except (OSError, ValueError, KeyError, TypeError, AttributeError):
        result = {"status": "BLOCKED", "reason": "INVALID_PR_IDENTITY", "lanes": {}}
    print(json.dumps(result, sort_keys=True))
    sys.exit(0 if result["status"] == "PASSED" else 2)
