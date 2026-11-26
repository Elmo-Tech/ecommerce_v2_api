"""Install and audit only the approved npm graph in an owned temporary directory."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile

from policy import require, summary
from importlib.util import spec_from_file_location, module_from_spec

ROOT = Path(__file__).resolve().parents[3]
spec = spec_from_file_location("f04_audit", Path(__file__).with_name("audit.py"))
audit = module_from_spec(spec)
spec.loader.exec_module(audit)


def run():
    result = summary("BLOCKED", reason="NPM_AUDIT_SETUP_FAILED")
    try:
        require(os.environ.get("GITHUB_ACTIONS") == "true"
                and os.environ.get("RUNNER_ENVIRONMENT") == "github-hosted"
                and os.environ.get("GITHUB_REPOSITORY") == "Elmo-Tech/ecommerce_v2_api")
        parent = Path(os.environ["RUNNER_TEMP"]).resolve()
        require(parent.is_dir())
        with tempfile.TemporaryDirectory(prefix="f04-npm-", dir=parent) as temporary:
            scratch = Path(temporary)
            env = {"PATH": os.environ["PATH"], "HOME": temporary, "TMPDIR": temporary,
                   "CI": "true", "NO_COLOR": "1", "GIT_CONFIG_NOSYSTEM": "1",
                   "GIT_CONFIG_GLOBAL": os.devnull, "GIT_NO_REPLACE_OBJECTS": "1",
                   "npm_config_registry": "https://registry.npmjs.org/",
                   "npm_config_cache": str(scratch / "npm-cache"),
                   "npm_config_userconfig": str(scratch / "user.npmrc"),
                   "npm_config_globalconfig": str(scratch / "global.npmrc")}
            for name in ("package.json", "package-lock.json"):
                raw = subprocess.check_output(
                    ["git", "cat-file", "blob", "HEAD:tools/quality/openapi/" + name],
                    cwd=ROOT, env=env, stderr=subprocess.DEVNULL, timeout=30)
                require(0 < len(raw) <= 8 * 1024 * 1024)
                (scratch / name).write_bytes(raw)
            require(hashlib.sha256((scratch / "package-lock.json").read_bytes()).hexdigest() == audit.PINS["npm"])
            (scratch / "user.npmrc").touch()
            (scratch / "global.npmrc").touch()
            version = subprocess.check_output(["node", "--version"], cwd=scratch, env=env, timeout=30).strip()
            require(version == b"v24.22.3")
            installed = subprocess.run(["npm", "ci", "--ignore-scripts", "--no-audit", "--no-fund", "--no-progress"],
                                       cwd=scratch, env=env, stdout=subprocess.DEVNULL,
                                       stderr=subprocess.DEVNULL, timeout=180, check=False)
            require(installed.returncode == 0)
            result = audit.audit("npm", scratch)
        require(not scratch.exists())
    except (OSError, ValueError, KeyError, TypeError, AttributeError, subprocess.SubprocessError):
        result = summary("BLOCKED", reason="NPM_AUDIT_SETUP_FAILED")
    return result


if __name__ == "__main__":
    result = run()
    print(json.dumps(result, sort_keys=True))
    sys.exit({"PASSED": 0, "FAILED": 1, "BLOCKED": 2}[result["status"]])
