"""Non-gating local-image compatibility diagnostic; never an approved-pin lane.

Only a private detached worktree gets an exact local image-identity adapter.
The real native test files, grants, ACLs, safety guards and cleanup are retained.
No registry, no manifest fabrication, no arbitrary user-supplied image or host.
"""
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import stat
import subprocess
import tempfile
import xml.etree.ElementTree as ET

from policy import require

ROOT=Path(__file__).resolve().parents[3]
MIB=1024*1024
ORIGINALS={
    "tests/Support/OwnedTestRun.php":"f6ffe407560a8c591f6b6bdf059bbb7028cf6bf3",
    "tests/Support/LocalDocker.php":"c329459417dda68b070a96f755e9151d457325ed",
    "tests/Support/IntegrationContext.php":"5772a6eaba9e5487ed2cd50d340169605d4c6a89",
    "tests/Support/ci/OwnedServicePlan.php":"09c7e2a91f2974c901e28035d4fdd7543672e05f",
    "tests/Support/ci/OwnedSetupFiles.php":"3d972fe5a157acfcae92de9ac56d796e1ff8a0fc",
}
REFERENCE_RUN="45823121639"
REFERENCE_CASE_DIGEST="cd007b304626ba697999c6729ae7af353b8039998b0a5b6d338ae6989e78af19"
SERVICE_IDS={
    "postgres":("sha256:850a2f036953dbe09006295666ee46737e4a15f9ede370bf3bf5718ec0a9e087",
                "sha256:c7a16500f728a9c09731e772f228ee7a29615585e8019772f05b620522f62d03"),
    "redis":("sha256:443f407d5d5a3ab81be78ebb136791c5d90f4e469231bc922dd187f55660fac6",
             "sha256:0e125214188816ac08e22435c7a5f58c61b8cbd3251d62e380d14fdf7659cd87"),
}

def execute(argv,cwd,env,timeout=120,capture=False,check=True):
    result=subprocess.run(argv,cwd=cwd,env=env,stdin=subprocess.DEVNULL,
                          stdout=subprocess.PIPE if capture else subprocess.DEVNULL,
                          stderr=subprocess.DEVNULL,timeout=timeout,check=False)
    if capture:
        require(len(result.stdout)<=16*MIB)
    if check:
        require(result.returncode==0)
    return result

def sha(data):
    return hashlib.sha256(data).hexdigest()

def git_blob(data):
    return hashlib.sha1(b"blob "+str(len(data)).encode()+b"\0"+data).hexdigest()

def replace_once(text,old,new):
    require(text.count(old)==1)
    return text.replace(old,new,1)

def candidate_env(scratch,report):
    actual=os.environ
    keys=("GITHUB_ACTIONS","GITHUB_REPOSITORY","GITHUB_JOB","GITHUB_RUN_ID","GITHUB_RUN_ATTEMPT",
          "GITHUB_SHA","GITHUB_EVENT_NAME","RUNNER_ENVIRONMENT","RUNNER_OS","RUNNER_ARCH","RUNNER_TEMP")
    env={k:actual[k] for k in keys}
    require(env["GITHUB_ACTIONS"]=="true" and env["GITHUB_REPOSITORY"]=="Elmo-Tech/ecommerce_v2_api"
            and env["GITHUB_JOB"]=="candidate-build" and env["RUNNER_ENVIRONMENT"]=="github-hosted"
            and env["RUNNER_OS"]=="Linux" and env["RUNNER_ARCH"]=="X64"
            and env["GITHUB_EVENT_NAME"]=="pull_request"
            and env["GITHUB_SHA"]==report["checkoutSha"] and env["GITHUB_RUN_ID"]==report["runId"]
            and env["GITHUB_RUN_ATTEMPT"]==report["runAttempt"]
            and re.fullmatch(r"[1-9][0-9]{0,19}",env["GITHUB_RUN_ID"])
            and re.fullmatch(r"[1-9][0-9]{0,8}",env["GITHUB_RUN_ATTEMPT"])
            and Path(env["RUNNER_TEMP"]).resolve()==scratch.parent and os.geteuid()!=0)
    for key in ("DOCKER_HOST","DOCKER_CONTEXT","DOCKER_TLS_VERIFY","DOCKER_CERT_PATH","DOCKER_CONFIG","DOCKER_API_VERSION"):
        require(not actual.get(key))
    home=scratch/"native-home"
    home.mkdir(mode=0o700)
    env.update(PATH=actual["PATH"],HOME=str(home),TMPDIR=str(scratch),
               COMPOSER_HOME=str(home/"composer"),COMPOSER_CACHE_DIR=str(home/"cache"),
               GIT_CONFIG_NOSYSTEM="1",GIT_CONFIG_GLOBAL=os.devnull,GIT_NO_REPLACE_OBJECTS="1",
               GIT_TERMINAL_PROMPT="0",CI="true",APP_ENV="testing",APP_DEBUG="false",
               F03_MODE="integration",DB_CONNECTION="sqlite",DB_DATABASE=":memory:",DB_URL="",
               CACHE_STORE="array",SESSION_DRIVER="array",MAIL_MAILER="array",
               QUEUE_CONNECTION="sync",XDEBUG_MODE="off")
    return env

def docker_preflight(scratch,env):
    require(stat.S_ISSOCK(Path("/var/run/docker.sock").stat().st_mode))
    docker="/usr/bin/docker"
    for argv,expected in (
        ([docker,"context","show"],b"default"),
        ([docker,"context","inspect","default","--format","{{.Endpoints.docker.Host}}"],b"unix:///var/run/docker.sock"),
        ([docker,"--host","unix:///var/run/docker.sock","version","--format","{{.Server.Os}}/{{.Server.Arch}}"],b"linux/amd64"),
    ):
        require(execute(argv,scratch,env,30,True).stdout.strip()==expected)

def image_identity(scratch,env,built,report):
    require(set(built)==set(SERVICE_IDS) and set(report["scans"])==set(SERVICE_IDS))
    pins={}
    for service,(tag,image) in built.items():
        ident=image["Id"]
        expected_tag="f04-t034-"+service+":"+report["runId"]+"-"+report["runAttempt"]
        require(tag==expected_tag and re.fullmatch(r"sha256:[a-f0-9]{64}",ident)
                and report["scans"][service]["status"]=="PASSED"
                and report["scans"][service]["imageId"]==ident
                and report["scans"][service]["target"]==tag
                and report["builds"][service]["imageId"]==ident
                and report["builds"][service]["status"]=="BUILT"
                and report["builds"][service]["imagePublished"] is False)
        observed=json.loads(execute(["/usr/bin/docker","--host","unix:///var/run/docker.sock",
                                     "image","inspect",tag],scratch,env,30,True).stdout)
        require(len(observed)==1 and observed[0]["Id"]==ident
                and observed[0]["RootFS"]==image["RootFS"]
                and observed[0]["Os"]=="linux" and observed[0]["Architecture"]=="amd64"
                and observed[0].get("RepoDigests") in (None,[])
                and observed[0].get("RepoTags")==[tag])
        pins[service]={"localRef":tag,"imageId":ident,"diffIds":image["RootFS"]["Layers"]}
    return pins

def adapt(checkout,pins):
    # All original bytes are pinned, so a changed guard cannot be silently
    # transformed. The actual integration suite and other guards stay intact.
    hashes={}
    for path,expected in ORIGINALS.items():
        file=checkout/path
        require(file.is_file() and not file.is_symlink() and file.resolve()==file.absolute())
        raw=file.read_bytes()
        require(git_blob(raw)==expected)
        s=raw.decode("utf-8")
        if path.endswith("/OwnedTestRun.php"):
            for service,(old_id,old_manifest) in SERVICE_IDS.items():
                s=replace_once(s,"'manifest' => '"+old_manifest+"'","'local_ref' => '"+pins[service]["localRef"]+"'")
                s=replace_once(s,"'image_id' => '"+old_id+"'","'image_id' => '"+pins[service]["imageId"]+"'")
            s=replace_once(s,"$job['job'] !== 'native-integration'","$job['job'] !== 'candidate-build'")
            s=s.replace("'manifest'","'local_ref'").replace("manifest: string","local_ref: string")
        elif path.endswith("/IntegrationContext.php"):
            s=replace_once(s,"'GITHUB_JOB' => 'native-integration'","'GITHUB_JOB' => 'candidate-build'")
            s=s.replace("pinned\n        // manifests","pinned\n        // local image references")
        elif path.endswith("/OwnedServicePlan.php"):
            s=replace_once(s,"""        $tag = match ($service) {
            'postgres' => 'postgres:17.12-bookworm',
            'redis' => 'redis:8.2.11-bookworm',
            default => throw new RuntimeException('Unknown owned service.'),
        };
        return 'docker.io/library/'.$tag.'@'.OwnedTestRun::image($service)['manifest'];""",
                """        // Diagnostic-only: pass the immutable scanned config ID to Docker,
        // never a mutable tag or an invented registry manifest.
        return OwnedTestRun::image($service)['image_id'];""")
        elif path.endswith("/OwnedSetupFiles.php"):
            s=replace_once(s,"'manifest' => $pin['manifest']","'local_ref' => $pin['local_ref']")
        elif path.endswith("/LocalDocker.php"):
            s=replace_once(s,r"\Tests\Support\ci\OwnedServicePlan::imageRef($service),","$pin['local_ref'],")
            s=replace_once(s,"""            || !is_array($image[3]) || (!in_array($service.'@'.$pin['manifest'], $image[3], true)
                && !in_array('docker.io/library/'.$service.'@'.$pin['manifest'], $image[3], true))) {""",
                """            || !in_array($image[3], [null, []], true)) {""")
            s=replace_once(s,"""        $tag = $service === 'postgres' ? 'postgres:17.12-bookworm' : 'redis:8.2.11-bookworm';
        $expectedRef = 'docker.io/library/'.$tag.'@'.$pin['manifest'];""",
                """        $expectedRef = $pin['image_id'];""")
            s=replace_once(s,"|| $image[0] !== $imageId || $image[1] !== 'linux' || $image[2] !== 'amd64' || !is_array($image[3])",
                "|| $image[0] !== $imageId || $image[1] !== 'linux' || $image[2] !== 'amd64' || !in_array($image[3], [null, []], true)")
            s=replace_once(s,"""        $validDigest = false;
        foreach ($image[3] as $digest) {
            if (in_array($digest, [$service.'@'.$pin['manifest'], 'docker.io/library/'.$service.'@'.$pin['manifest']], true)) {
                $validDigest = true;
            }
        }
        if (!$validDigest) {
            self::reject();
        }
""","")
            s=replace_once(s,"'manifest' => $pin['manifest']","'local_ref' => $pin['local_ref']")
            s=s.replace("manifest: string","local_ref: string")
        else:
            require(False)
        require(s!=raw.decode("utf-8") and "'manifest'" not in s)
        data=s.encode("utf-8")
        file.write_bytes(data)
        hashes[path]={"originalGitBlob":expected,"adaptedSha256":sha(data)}
    return hashes

def check_diff(checkout,env,adapted):
    changed=execute(["git","diff","--name-only","-z","HEAD","--"],checkout,env,30,True).stdout
    require(set(changed.rstrip(b"\0").decode().split("\0"))==set(ORIGINALS))
    for path,identity in adapted.items():
        require(sha((checkout/path).read_bytes())==identity["adaptedSha256"])

def read_safe(path,maximum):
    require(path.is_file() and not path.is_symlink()
            and path.resolve()==path.absolute() and 0<path.stat().st_size<=maximum)
    data=path.read_bytes()
    require(0<len(data)<=maximum)
    return data

def safe_result(report_dir,report,run):
    summary_raw=read_safe(report_dir/"integration.summary.json",64*1024)
    summary=json.loads(summary_raw)
    require(summary["selection"]=="integration" and summary["source_sha"]==report["sourceSha"]
            and summary["base_sha"]==report["baseSha"] and summary["checkout_sha"]==report["checkoutSha"]
            and summary["run_id"]==report["runId"] and summary["run_attempt"]==report["runAttempt"]
            and re.fullmatch(r"8\.4\.[0-9]+(?:[A-Za-z0-9.+-]*)",summary["php"]))
    run.update(selectionStatus=summary["status"],summarySha256=sha(summary_raw),
               selectionVerified=summary["selection_verified"],testsExecuted=summary["tests_executed"],
               runnerExit=summary["runner_exit"],counts=summary["counts"],php=summary["php"])
    require(summary["status"]=="PASSED" and summary["exit_code"]==0
            and summary["runner_started"] is True and summary["tests_executed"] is True
            and summary["selection_verified"] is True and summary["runner_exit"]==0)
    expected={"tests":34,"assertions":304,"failures":0,"errors":0,"skipped":0}
    require(all(type(summary["counts"][k]) is int and summary["counts"][k]==v for k,v in expected.items()))
    xml=read_safe(report_dir/"integration-safe.xml",512*1024)
    require(b"<!DOCTYPE" not in xml and b"<!ENTITY" not in xml)
    root=ET.fromstring(xml)
    require(root.tag=="testsuites" and len(root)==1 and root[0].tag=="testsuite"
            and len(root[0])==34 and all(c.tag=="testcase" and len(c)==0 for c in root[0]))
    cases=sorted((c.attrib["classname"],c.attrib["name"],int(c.attrib["assertions"])) for c in root[0])
    require(len(set((c[0],c[1]) for c in cases))==34 and sum(c[2] for c in cases)==304)
    by_class={k:sum(c[0]==k for c in cases) for k in {c[0] for c in cases}}
    require(by_class=={"Tests.Integration.Foundation.PostgreSqlIsolationTest":26,
                       "Tests.Integration.Foundation.RedisIsolationTest":8})
    case_digest=sha(json.dumps(cases,ensure_ascii=True,separators=(",",":")).encode())
    require(case_digest==REFERENCE_CASE_DIGEST)
    run.update(safeXmlSha256=sha(xml),caseInventorySha256=case_digest,
               referenceCaseInventoryMatched=True,caseCounts=by_class,
               referenceRun=REFERENCE_RUN)
    # Only safe report counts and digests are retained, not raw XML or credentials.

def run_candidate_native(scratch,built,report):
    result={"status":"BLOCKED","stage":"identity","diagnosticOnly":True,
            "registryManifestVerified":False,"candidateApproval":"NOT_APPROVED",
            "startExit":None,"setupExit":None,"testExit":None}
    checkout=scratch/"native-checkout"
    report_dir=None
    env=None
    worktree=False
    identity=False
    completed=False
    cleanup_ok=True
    try:
        env=candidate_env(scratch,report)
        docker_preflight(scratch,env)
        pins=image_identity(scratch,env,built,report)
        result["images"]=pins
        result["stage"]="private-worktree"
        require(not checkout.exists() and not checkout.is_symlink())
        execute(["git","-c","core.hooksPath=/dev/null","worktree","add","--detach",
                 str(checkout),report["checkoutSha"]],ROOT,env,120)
        worktree=True
        require(checkout.resolve()==checkout.absolute()
                and execute(["git","rev-parse","HEAD"],checkout,env,30,True).stdout.strip().decode()==report["checkoutSha"]
                and not execute(["git","status","--porcelain","--untracked-files=no"],checkout,env,30,True).stdout)
        result["stage"]="local-identity-adapter"
        adapted=adapt(checkout,pins)
        result["adapter"]=adapted
        check_diff(checkout,env,adapted)
        for path in ORIGINALS:
            execute(["php","-l",path],checkout,env,30)
        lock=read_safe(checkout/"composer.lock",8*MIB)
        result["composerLockSha256"]=sha(lock)
        result["stage"]="locked-php-dependencies"
        execute(["php","-r",'if (PHP_MAJOR_VERSION!==8 || PHP_MINOR_VERSION!==4 || !in_array("pgsql",PDO::getAvailableDrivers(),true) || phpversion("redis")!=="6.3.0") exit(2);'],checkout,env,30)
        require(execute(["composer","--version","--no-ansi"],checkout,env,30,True).stdout.startswith(b"Composer version 2.11.3 "))
        for argv,limit in (
            (["composer","validate","--strict","--no-check-publish"],60),
            (["composer","check-platform-reqs","--lock"],60),
            (["composer","install","--no-scripts","--no-plugins","--no-interaction","--prefer-dist","--no-progress"],300),
            (["composer","check-platform-reqs"],60),
            (["composer","audit","--locked","--no-interaction"],120),
        ):
            execute(argv,checkout,env,limit)
        require(read_safe(checkout/"composer.lock",8*MIB)==lock)
        check_diff(checkout,env,adapted)
        result["lockedDependenciesVerified"]=True
        result["stage"]="fresh-owned-run"
        dotenv=checkout/".env"
        require(not dotenv.exists() and not dotenv.is_symlink())
        with dotenv.open("xb"):
            pass
        execute(["git","check-ignore","-q",".env"],checkout,env,30)
        token=secrets.token_hex(8)
        run_dir=Path(env["RUNNER_TEMP"])/("f03-"+token)
        require(not run_dir.exists() and not run_dir.is_symlink())
        report_dir=Path(tempfile.mkdtemp(prefix="f03-reports-",dir=env["RUNNER_TEMP"]))
        env.update(F03_RUN_TOKEN=token,F03_RUN_DESCRIPTOR=str(run_dir/"runtime.json"),F03_REPORT_DIR=str(report_dir))
        identity=True
        php=["php","-d","zend.exception_ignore_args=1"]
        require(image_identity(scratch,env,built,report)==pins)
        result["stage"]="owned-start"
        started=execute(php+["tests/Support/ci/start-owned.php"],checkout,env,180,False,False)
        result["startExit"]=started.returncode
        if started.returncode==0:
            result["stage"]="owned-setup"
            setup=execute(php+["tests/Support/ci/setup-owned.php"],checkout,env,240,False,False)
            result["setupExit"]=setup.returncode
        if result["startExit"]==0 and result["setupExit"]==0:
            result["stage"]="native-test-suite"
            tests=execute(php+["tests/Support/ci/run-report.php","integration"],checkout,env,300,False,False)
            result["testExit"]=tests.returncode
            result["stage"]="native-safe-evidence"
            safe_result(report_dir,report,result)
            require(tests.returncode==0)
            completed=True
        else:
            execute(php+["tests/Support/ci/run-report.php","integration","--blocked"],checkout,env,60,False,False)
        result["stage"]="post-native-identity"
        require(image_identity(scratch,env,built,report)==pins)
        require(read_safe(checkout/"composer.lock",8*MIB)==lock)
        check_diff(checkout,env,adapted)
        result["trackedAdapterOnly"]=True
    except (OSError,ValueError,TypeError,KeyError,AttributeError,subprocess.SubprocessError,ET.ParseError) as exc:
        result["errorClass"]=type(exc).__name__
        completed=False
    finally:
        if identity:
            try:
                # The original F03 cleanup policy is used twice, including
                # complete capture revalidation and anonymous-volume absence.
                codes=[]
                for _ in range(2):
                    try:
                        codes.append(execute(["php","-d","zend.exception_ignore_args=1",
                                              "tests/Support/ci/cleanup-owned.php"],checkout,env,120,False,False).returncode)
                    except (OSError,subprocess.SubprocessError,ValueError):
                        codes.append(-1)
                result["ownedCleanupExits"]=codes
                result["ownedCleanup"]=codes==[0,0] and not run_dir.exists() and not run_dir.is_symlink()
                cleanup_ok &= result["ownedCleanup"]
                result["reportCleanup"]=execute(["php","-d","zend.exception_ignore_args=1",
                                                "tests/Support/ci/cleanup-reports.php"],checkout,env,60,False,False).returncode==0 and not report_dir.exists()
                cleanup_ok &= result["reportCleanup"]
            except (OSError,ValueError,subprocess.SubprocessError):
                cleanup_ok=False
        if worktree:
            try:
                require(checkout.resolve()==checkout.absolute() and checkout.parent==scratch
                        and not checkout.is_symlink())
                removed=execute(["git","-c","core.hooksPath=/dev/null","worktree","remove","--force",str(checkout)],ROOT,env,120,False,False)
                result["ownedWorktreeCleanup"]=removed.returncode==0 and not checkout.exists() and not checkout.is_symlink()
                cleanup_ok &= result["ownedWorktreeCleanup"]
            except (OSError,ValueError,subprocess.SubprocessError):
                cleanup_ok=False
        result["cleanupVerified"]=bool(cleanup_ok)
    if completed and cleanup_ok and result.get("trackedAdapterOnly") is True:
        result.update(status="PASSED",stage="complete")
    return result
