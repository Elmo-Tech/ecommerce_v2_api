"""T045 bounded hosted negative diagnostic, not the production PR gate."""
import hashlib
import io
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "tests/spec_kit"))
import test_gate_quality as fixtures
import test_aggregate_gate as aggregate
import test_security_quality as security

PINS = {
    "tools/quality/classify.py": "5fddbe10a1957e04f1adbab377bc859941cb2ed7",
    "tools/quality/check-docs.py": "5eb8d8c812c5a5cc0da4a9e7a328ec6c9eaa4c91",
    "tools/quality/verify-gate.py": "da4d705416d98cb5f16989bc2611d3d245b6b4ac",
    "tools/quality/artifact-io.py": "ce443cc0379fffda758d54aba2cadc4ce0c7fd66",
    "tests/spec_kit/test_aggregate_gate.py": "f3ae67765c5cc0795f9240d37c1ae0b5e31a62b5",
    "tests/spec_kit/test_gate_quality.py": "18c7634169359464707f7dd4d14a4bc52c5120c7",
    "tests/spec_kit/test_security_quality.py": "ddb327a2cdb361a4c43d45159a51b5b184f9ee8d"
}

def require(value):
    if not value:
        raise ValueError("T045_CONTROL_REJECTED")

def git(*args):
    result = subprocess.run(["git", *args], cwd=ROOT, capture_output=True, check=True, timeout=30)
    return result.stdout.decode().strip()

def identity():
    env = os.environ
    event = json.loads(Path(env["GITHUB_EVENT_PATH"]).read_text())
    pr = event["pull_request"]
    require(env.get("GITHUB_ACTIONS") == "true" and env.get("RUNNER_ENVIRONMENT") == "github-hosted"
            and env.get("GITHUB_REPOSITORY") == "Elmo-Tech/ecommerce_v2_api"
            and env.get("GITHUB_EVENT_NAME") == "pull_request"
            and env.get("GITHUB_JOB") == "negative-proof"
            and event["number"] == 9 and pr["head"]["repo"]["full_name"] == env["GITHUB_REPOSITORY"]
            and pr["head"]["ref"] == "feature/f0-04-code-quality-ci"
            and pr["base"]["ref"] == "development"
            and pr["base"]["sha"] == "24ce00231dec35a089bb70c081b257f9b5d234e7"
            and git("rev-parse","HEAD") == env["GITHUB_SHA"]
            and git("rev-list","--parents","-n","1","HEAD").split() ==
                [env["GITHUB_SHA"],pr["base"]["sha"],pr["head"]["sha"]]
            and not git("status","--porcelain","--untracked-files=no"))
    for key in ("GITHUB_SHA", "GITHUB_RUN_ID", "GITHUB_RUN_ATTEMPT"):
        require(re.fullmatch(r"[a-f0-9]{40}" if key == "GITHUB_SHA" else r"[1-9][0-9]{0,19}",env[key]))
    for path, expected in PINS.items():
        raw = (ROOT/path).read_bytes()
        require(hashlib.sha1(b"blob "+str(len(raw)).encode()+b"\0"+raw).hexdigest()==expected)
    return {"repository":env["GITHUB_REPOSITORY"],"sourceSha":pr["head"]["sha"],
            "baseSha":pr["base"]["sha"],"checkoutSha":env["GITHUB_SHA"],
            "runId":env["GITHUB_RUN_ID"],"runAttempt":env["GITHUB_RUN_ATTEMPT"],"pr":9}

def exercise(scratch, result):
    # All synthetic fixtures stay in this owned directory, never use real
    # application databases, credentials, registry or repository refs.
    tempfile.tempdir = str(scratch)
    suite = unittest.TestSuite()
    for module in (fixtures, aggregate, security):
        suite.addTests(unittest.defaultTestLoader.loadTestsFromModule(module))
    case_count = suite.countTestCases()
    output = io.StringIO()
    tests = unittest.TextTestRunner(stream=output, verbosity=0).run(suite)
    result["regressions"] = {"tests": tests.testsRun, "failures": len(tests.failures),
                             "errors": len(tests.errors), "skipped": len(tests.skipped)}
    require(case_count>=30 and tests.testsRun==case_count and tests.wasSuccessful() and not tests.skipped)
    case = fixtures.GateQuality()
    try:
        repo, synthetic = case.history(lambda root:case.write(root, fixtures.GUIDE,
                        "# Reading guide\n\nA clear example for readers.\n"))
        # Explicit synthetic PR/run markers; no actual PR result is fabricated.
        synthetic.update(pr=999999999,runId="123",runAttempt=1)
        classifier = case.helper("classify.py","classifier")
        docs = case.helper("check-docs.py","docs")
        gate = case.helper("verify-gate.py","gate")
        artifact = case.helper("artifact-io.py","artifact")
        require(case.git(repo,"remote")=="" and case.git(repo,"status","--porcelain")==""
                and case.git(repo,"diff","--name-only",synthetic["baseSha"],"HEAD")==fixtures.GUIDE)
        selection=classifier.classify(repo,synthetic)
        require(selection["status"]=="PASSED" and selection["selectionReason"]=="docs-only"
                and selection["changedCount"]==1
                and {k for k,v in selection["lanes"].items() if v["required"]}=={"docs"})
        report=docs.check_docs(repo,synthetic)
        require(report["status"]=="PASSED" and report["checkedDocuments"]==1 and report["issues"]==[])
        root=scratch/"evidence"
        root.mkdir(mode=0o700)
        folder=root/gate.artifact_name("docs",synthetic)
        folder.mkdir(mode=0o700)
        payload=folder/"docs.summary.json"
        payload.write_text(json.dumps(report,sort_keys=True),encoding="utf-8")
        artifact.seal(repo,synthetic,"docs",folder,0.01)
        outcomes={"classify":"success",**{k:("success" if k=="docs" else "skipped") for k in gate.LANES}}
        def verify(**kw):
            return gate.verify_gate(repo,synthetic,selection,outcomes,root,**kw)
        valid=verify()
        require(valid["status"]=="PASSED" and valid["lanes"]["docs"]["status"]=="PASSED"
                and sum(v["status"]=="NOT_APPLICABLE" for v in valid["lanes"].values())==8)
        result["scratchControl"]={"identityKind":"SYNTHETIC_LOCAL_GIT_NOT_A_PR_RUN",
            "identity":synthetic,"changedPath":fixtures.GUIDE,"actualClassifier":selection,
            "actualDocs":report,"actualGate":valid}
        negative={}
        def rejected(name, expected, **kw):
            observed=verify(**kw)
            require(observed["status"]==expected)
            negative[name]={"status":observed["status"],
                            "docs":observed.get("lanes",{}).get("docs",{}),
                            "reason":observed.get("reason")}
            return observed
        for value,expected in (("failure","FAILED"),("skipped","BLOCKED"),("blocked","BLOCKED"),("cancelled","BLOCKED")):
            outcomes["docs"]=value
            rejected("required-"+value,expected)
        outcomes["docs"]="success"
        summary=folder/"lane.summary.json"
        before=summary.read_bytes()
        obj=json.loads(before)
        obj["artifacts"]["docs.summary.json"]="0"*64
        summary.write_text(json.dumps(obj))
        rejected("wrong-digest","FAILED")
        obj=json.loads(before)
        obj["sourceSha"]="a"*40
        summary.write_text(json.dumps(obj))
        rejected("foreign-source","FAILED")
        summary.write_bytes(before)
        canary="F04_T045_SYNTHETIC_CANARY"
        raw=payload.read_bytes()
        payload.write_text(json.dumps({**report,"issues":[{"code":canary}]}))
        obj=json.loads(before)
        obj["artifacts"]["docs.summary.json"]=hashlib.sha256(payload.read_bytes()).hexdigest()
        summary.write_text(json.dumps(obj))
        rejected("credential-canary","FAILED",canaries=(canary,))
        payload.write_bytes(raw)
        summary.write_bytes(before)
        extra=folder/"unexpected.json"
        extra.write_text("{}")
        try:
            artifact.cleanup_downloads(root,synthetic)
        except ValueError:
            require(extra.is_file() and summary.is_file())
            negative["unsafe-cleanup"]={"status":"REJECTED","noDeletion":True}
        else:
            require(False)
        extra.unlink()
        payload.unlink()
        payload.symlink_to(repo/fixtures.GUIDE)
        rejected("unsafe-target","FAILED")
        payload.unlink()
        payload.write_bytes(raw)
        require(verify()["status"]=="PASSED")
        # Deliberately missing evidence: execute actual gate implementation,
        # retain its failure and propagate its real status-to-exit mapping.
        payload.unlink()
        red=rejected("missing-evidence","FAILED")
        require(red["lanes"]["docs"]["reason"]=="MISSING_OR_INVALID_REQUIRED_EVIDENCE")
        result["negativeControls"]=negative
        result["actualNegativeGate"]=red
        artifact.cleanup_downloads(root,synthetic)
        require(not root.exists())
        result["gateOwnedCleanup"]=True
        return {"PASSED":0,"FAILED":1,"BLOCKED":2}[red["status"]]
    finally:
        case.doCleanups()

def main():
    result={"schemaVersion":1,"task":"T045","diagnosticOnly":True,"status":"BLOCKED"}
    try:
        result["hostedIdentity"]=identity()
        require(os.environ.get("F04_MISSING_UPLOAD_OUTCOME")=="failure")
        result["actualMissingUploadOutcome"]="failure"
        parent=Path(os.environ["RUNNER_TEMP"]).resolve()
        with tempfile.TemporaryDirectory(prefix="f04-t045-scratch-",dir=parent) as directory:
            scratch=Path(directory)
            code=exercise(scratch,result)
            require(not list(scratch.iterdir()))
        require(not scratch.exists() and identity()==result["hostedIdentity"])
        result.update(status="EXPECTED_REJECTION_VERIFIED",actualGateExit=code,ownedScratchCleanup=True,trackedSourceUnchanged=True)
    except (OSError,ValueError,KeyError,TypeError,AttributeError,subprocess.SubprocessError,AssertionError) as error:
        result["errorClass"]=type(error).__name__
        code=2
    print(json.dumps(result,sort_keys=True,allow_nan=False))
    return code

if __name__=="__main__":
    sys.exit(main())
