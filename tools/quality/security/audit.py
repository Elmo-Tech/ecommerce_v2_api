"""Run the locked native audit against an already-installed dependency graph."""
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile

from policy import audit_result, require, summary

PINS = {
    "composer": "9028f6191bf4f725c84890c28a0113c62b5ba0d9fc548559b8b33341ed4520f5",
    "npm": "a987d3b6bef34c77a4d1acc7bdbdeb163e82f3a87aef0dfa15abd004765466a0",
}


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def command(args, cwd, env, timeout=120):
    return subprocess.run(args, cwd=cwd, env=env, capture_output=True,
                          text=True, encoding="utf-8", timeout=timeout, check=False)


def audit(tool, root):
    result = summary("BLOCKED", reason="AUDIT_SETUP_FAILED")
    stage = "lock"
    try:
        require(tool in PINS)
        name = "composer.lock" if tool == "composer" else "package-lock.json"
        lock = root / name
        digest = sha(lock)
        require(digest == PINS[tool])
        data = json.loads(lock.read_text())
        stage = "installed"
        if tool == "composer":
            expected = {p["name"]: p for p in data["packages"] + data["packages-dev"]}
            installed = json.loads((root / "vendor/composer/installed.json").read_text())
            actual = {p["name"]: p for p in installed["packages"]}
            require(len(expected) == len(actual) == 122 and expected.keys() == actual.keys()
                    and installed["dev"] is True)
            for name, item in expected.items():
                require(item["version"] == actual[name]["version"]
                        and item.get("source", {}).get("reference") == actual[name].get("source", {}).get("reference")
                        and item.get("dist", {}).get("reference") == actual[name].get("dist", {}).get("reference"))
            locked = {name: item["version"] for name, item in expected.items()}
        else:
            packages = {path: item for path, item in data["packages"].items() if path}
            installed = json.loads((root / "node_modules/.package-lock.json").read_text())["packages"]
            require(packages.keys() == installed.keys() and packages)
            locked = {}
            for path, item in packages.items():
                require(path.startswith("node_modules/") and ".." not in path.split("/"))
                manifest = root / path / "package.json"
                require(manifest.is_file() and not manifest.is_symlink()
                        and manifest.resolve().is_relative_to(root / "node_modules"))
                package = json.loads(manifest.read_text())
                require(package["version"] == item["version"] == installed[path]["version"])
                require(package["name"] == path.rsplit("node_modules/", 1)[1])
                locked.setdefault(package["name"], []).append(package["version"])
        stage = "native-audit"
        with tempfile.TemporaryDirectory(prefix="f04-audit-") as temporary:
            home = Path(temporary)
            env = {"PATH": os.environ["PATH"], "HOME": temporary, "TMPDIR": temporary,
                   "CI": "true", "NO_COLOR": "1", "COMPOSER_HOME": temporary,
                   "COMPOSER_CACHE_DIR": str(home / "cache"), "COMPOSER_NO_INTERACTION": "1",
                   "npm_config_registry": "https://registry.npmjs.org/",
                   "npm_config_cache": str(home / "npm-cache"),
                   "npm_config_userconfig": str(home / "user.npmrc"),
                   "npm_config_globalconfig": str(home / "global.npmrc")}
            (home / "user.npmrc").touch()
            (home / "global.npmrc").touch()
            ver = command([tool, "--version"], root, env, 30)
            require(ver.returncode == 0)
            if tool == "composer":
                require(re.search(r"^Composer version 2\.11\.3(?:\s|$)", ver.stdout + ver.stderr, re.M))
                version = "2.11.3"
                args = ["composer", "--no-plugins", "--no-scripts", "audit", "--locked",
                        "--no-interaction", "--no-ansi", "--format=json"]
            else:
                version = ver.stdout.strip()
                require(re.fullmatch(r"\d+\.\d+\.\d+", version)
                        and tuple(map(int, version.split("."))) >= (10, 9, 2))
                args = ["npm", "audit", "--json", "--audit-level=info"]
            native = command(args, root, env)
            result = audit_result(tool=tool, raw=native.stdout, exit_code=native.returncode,
                                  lock_sha=sha(lock), expected_lock_sha=PINS[tool],
                                  installed_versions_match=True, locked_versions=locked)
            result.update(tool=tool, version=version, lockSha256=digest,
                          installedCount=len(expected) if tool == "composer" else len(packages),
                          nativeExit=native.returncode)
        result["ownedCleanup"] = not home.exists()
        require(result["ownedCleanup"])
    except (OSError, ValueError, KeyError, TypeError, AttributeError, subprocess.SubprocessError):
        result = summary("BLOCKED", reason="AUDIT_SETUP_FAILED", stage=stage)
    return result


if __name__ == "__main__":
    output = audit(sys.argv[1], Path(sys.argv[2]).resolve()) if len(sys.argv) == 3 else summary("BLOCKED", reason="ARGUMENTS")
    print(json.dumps(output, sort_keys=True))
    sys.exit({"PASSED": 0, "FAILED": 1, "BLOCKED": 2}[output["status"]])
