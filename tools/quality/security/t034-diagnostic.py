"""One-off T034 diagnostic; never returns security acceptance or runs image code."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import tarfile
import tempfile
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from classify import ROOT, classify, hosted_identity, require

IMAGE = "docker.io/library/postgres@sha256:833be86fa9cbc2e12ca52ecf8afa306a3f20f6f57772037caeae65075aeb4616"
IMAGE_ID = "sha256:b4912da07fcf17d4d7db60cec9a436301114a961784299ea0e77c6b5797d8acd"
GOSU_SHA = "b1892636c281590f34bc63c1118f058cd9de66c4ea463772e3909672d8b302ac"
SOURCE = "29b939aa5bd4dc588af765b8248f6e39150d2565"
GO_SHA = "bbca37cc395c974ffa4893ee35819ad23ebb27426df87af92e93a9ec66ef8712"
BLOBS = {
    "go.mod": "6abe2ddfa1e3de4ab334463463c7c14cb1668f",
    "go.sum": "d8a55165cb0ba195d60a21789c187c0708e94e52",
    "main.go": "7eb3d47a9baaa8c51167fff34fe79e81999ccc6b",
    "setup-user.go": "87afcd1748da9efa4af5128452ac8fbec245a7ce",
    "version.go": "e591d6ca15329c67350c2ba3ccda1b67871b5afa",
}
CVE = """CVE-2025-61726 CVE-2025-61729 CVE-2025-68121 CVE-2026-111943 CVE-2026-25679 CVE-2026-27145 CVE-2026-32280 CVE-2026-32281 CVE-2026-32283 CVE-2026-33811 CVE-2026-33814 CVE-2026-33818 CVE-2026-39820 CVE-2026-39821 CVE-2026-39822 CVE-2026-39824 CVE-2026-39836 CVE-2026-42499 CVE-2026-42504 CVE-2026-56853 CVE-2026-56858 CVE-2026-56859 CVE-2026-56860 CVE-2026-56862 CVE-2026-78667 CVE-2026-78669 CVE-2026-97031""".split()
MIB = 1024 * 1024

def sha(path):
    with path.open("rb") as f:
        return hashlib.file_digest(f, "sha256").hexdigest()

def run(args, cwd, env, seconds=180, check=True):
    result = subprocess.run(args, cwd=cwd, env=env, text=True,
                            stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                            timeout=seconds, check=False)
    require(len(result.stdout) <= 32*MIB)
    if check:
        require(result.returncode == 0)
    return result

def fetch(url, path, maximum, cwd, env):
    run(["curl", "--disable", "--proto", "=https", "--proto-redir", "=https",
         "--fail", "--silent", "--location", "--max-time", "120",
         "--max-filesize", str(maximum), "--output", str(path), url], cwd, env, 130)
    require(path.is_file() and not path.is_symlink() and 0 < path.stat().st_size <= maximum)

def go_toolchain(scratch, env):
    archive = scratch / "go.tar.gz"
    fetch("https://go.dev/dl/go1.24.6.linux-amd64.tar.gz", archive, 100*MIB, scratch, env)
    require(sha(archive) == GO_SHA)
    with tarfile.open(archive, "r:gz") as tar:
        # This is the hash-verified official SDK, not an image layer or arbitrary PR archive.
        tar.extractall(scratch, filter="data")
    go = scratch / "go/bin/go"
    env.update(PATH=str(go.parent)+":"+env["PATH"], GOROOT=str(go.parent.parent),
               GOPATH=str(scratch/"gopath"), GOBIN=str(scratch/"bin"),
               GOCACHE=str(scratch/"go-cache"), GOMODCACHE=str(scratch/"go-mod-cache"),
               GOENV="off", GOTOOLCHAIN="local", GOOS="linux", GOARCH="amd64",
               CGO_ENABLED="0", GOPROXY="https://proxy.golang.org",
               GOSUMDB="sum.golang.org", GOWORK="off", GOFLAGS="-mod=readonly")
    require(run([str(go),"version"],scratch,env).stdout.strip()=="go version go1.24.6 linux/amd64")
    run([str(go),"install","golang.org/x/vuln/cmd/govulncheck@v1.1.4"],scratch,env,300)
    scanner = scratch/"bin/govulncheck"
    info = run([str(go),"version","-m",str(scanner)],scratch,env).stdout
    require("\tmod\tgolang.org/x/vuln\tv1.1.4\t" in info)
    require("\tpath\tgolang.org/x/vuln/cmd/govulncheck" in info)
    return go, scanner

def database(scratch, env):
    archive=scratch/"vulndb.zip"
    fetch("https://vuln.go.dev/vulndb.zip",archive,100*MIB,scratch,env)
    dest=scratch/"vulndb"
    dest.mkdir(mode=0o700)
    with zipfile.ZipFile(archive) as z:
        names=set()
        total=0
        for member in z.infolist():
            name=member.filename
            if member.is_dir():
                require(name in ("index/","ID/"))
                continue
            # Accept only the documented JSON API layout, ignore compressed
            # duplicates if the official bulk archive also contains them.
            require(re.fullmatch(r"(?:index/(?:db|modules|vulns)|ID/GO-\d{4}-\d+)\.json(?:\.gz)?",name)
                    and name not in names and not (member.flag_bits&1)
                    and stat.S_IFMT(member.external_attr>>16) in (0,stat.S_IFREG))
            names.add(name)
            require(len(names)<=30000 and 0<member.file_size<=16*MIB)
            total+=member.file_size
            require(total<=400*MIB)
            if name.endswith(".gz"):
                continue
            path=dest/name
            path.parent.mkdir(mode=0o700,exist_ok=True)
            with path.open("xb") as out:
                raw=z.read(member)
                require(len(raw)==member.file_size)
                out.write(raw)
    meta=json.loads((dest/"index/db.json").read_text())
    modified=datetime.datetime.fromisoformat(meta["modified"].replace("Z","+00:00"))
    now=datetime.datetime.now(datetime.timezone.utc)
    require(modified<=now+datetime.timedelta(minutes=5))
    require((dest/"index/modules.json").is_file() and (dest/"index/vulns.json").is_file())
    # Current retrieval and metadata recorded, not an assumption that upstream
    # publishes every day; both modes use the exact same frozen DB.
    idx=json.loads((dest/"index/vulns.json").read_text())
    aliases={c:[] for c in CVE}
    for entry in idx:
        for alias in entry.get("aliases",[]):
            if alias in aliases:
                require(re.fullmatch(r"GO-\d{4}-\d+",entry["id"]))
                aliases[alias].append(entry["id"])
    for c in aliases:
        aliases[c]=sorted(set(aliases[c]))
    return dest, {"zipSha256":sha(archive),"modified":meta["modified"],
                  "retrievedAt":now.isoformat(),"aliases":aliases}

def image_inventory(scratch, env, go, report):
    # Docker creates a stopped, network-disabled container only for the
    # flattened filesystem export. It never starts any candidate executable.
    # No shared daemon: only an ephemeral hosted runner, exact owned IDs.
    require(run(["docker","image","inspect",IMAGE],scratch,env,check=False).returncode!=0)
    owned_image=False
    container=None
    cleanup=True
    try:
        run(["docker","pull","--platform","linux/amd64",IMAGE],scratch,env,180)
        owned_image=True
        record=json.loads(run(["docker","image","inspect",IMAGE],scratch,env).stdout)
        require(len(record)==1 and record[0]["Id"]==IMAGE_ID and record[0]["Os"]=="linux"
                and record[0]["Architecture"]=="amd64"
                and any(x.split("@")[-1]==IMAGE.split("@")[-1] for x in record[0]["RepoDigests"]))
        report["imageId"]=record[0]["Id"]
        name="f04-gosu-"+os.environ["GITHUB_RUN_ID"]+"-"+os.environ["GITHUB_RUN_ATTEMPT"]
        container=run(["docker","create","--name",name,"--network","none","--read-only",
                       "--cap-drop","ALL","--security-opt","no-new-privileges",
                       "--entrypoint","/bin/false",IMAGE],scratch,env).stdout.strip()
        require(re.fullmatch(r"[0-9a-f]{64}",container))
        state=json.loads(run(["docker","container","inspect",container],scratch,env).stdout)[0]
        require(state["Id"]==container and state["Image"]==IMAGE_ID
                and state["State"]["Status"]=="created" and state["State"]["Running"] is False)
        archive=scratch/"image.tar"
        run(["docker","export","--output",str(archive),container],scratch,env,180)
        require(0<archive.stat().st_size<=1500*MIB)
        inventory=[]
        paths=set()
        elf_count=0
        regular_count=0
        total=0
        gosu=scratch/"gosu.bin"
        with tarfile.open(archive,"r:") as tar:
            for entry in tar:
                if not entry.isfile():
                    continue
                regular_count+=1
                require(regular_count<=100000)
                path=entry.name.removeprefix("./")
                require(not path.startswith("/") and ".." not in Path(path).parts
                        and path not in paths and len(path)<=1024)
                paths.add(path)
                with tar.extractfile(entry) as stream:
                    prefix=stream.read(4)
                    if prefix!=b"\x7fELF":
                        continue
                    elf_count+=1
                    total+=entry.size
                    require(elf_count<=4000 and 4<entry.size<=128*MIB and total<=1500*MIB)
                    temp=scratch/"elf.bin"
                    with temp.open("xb") as f:
                        f.write(prefix)
                        while chunk:=stream.read(MIB):
                            f.write(chunk)
                    info=run([str(go),"version","-m",str(temp)],scratch,env,30,False)
                    if re.search(r": go1\.\d+",info.stdout):
                        require(info.returncode==0 and re.fullmatch(r"[A-Za-z0-9_./+-]+",path))
                        digest=sha(temp)
                        inventory.append({"path":path,"sha256":digest})
                        if path=="usr/local/bin/gosu":
                            require(digest==GOSU_SHA)
                            require("go1.24.6" in info.stdout and "\tdep\tgolang.org/x/sys\tv0.1.0\t" in info.stdout
                                    and "\tdep\tgithub.com/moby/sys/user\tv0.4.1\t" in info.stdout
                                    and "\tbuild\tCGO_ENABLED=0" in info.stdout)
                            temp.rename(gosu)
                            report["gosuBuildInfo"]=[line.strip() for line in info.stdout.splitlines()[1:]
                                                      if line.startswith(("\tpath\t","\tmod\t","\tdep\t","\tbuild\tCGO_ENABLED=",
                                                                          "\tbuild\tGOOS=","\tbuild\tGOARCH="))]
                    elif path=="usr/local/bin/gosu":
                        require(False)
                    if temp.exists():
                        temp.unlink()
        require(gosu.is_file() and sha(gosu)==GOSU_SHA)
        state=json.loads(run(["docker","container","inspect",container],scratch,env).stdout)[0]
        require(state["State"]["Status"]=="created" and state["State"]["Running"] is False)
        report.update(elfCount=elf_count,regularFileCount=regular_count,goBinaries=inventory,
                      gosuSha256=GOSU_SHA,imageExecuted=False,
                      scope="regular ELF files in stopped-container export; mounted volume contents excluded")
        return gosu
    finally:
        if container is not None and re.fullmatch(r"[0-9a-f]{64}",container):
            cleanup &= run(["docker","rm","-v",container],scratch,env,check=False).returncode==0
            cleanup &= run(["docker","container","inspect",container],scratch,env,check=False).returncode!=0
        if owned_image:
            cleanup &= run(["docker","image","rm",IMAGE],scratch,env,check=False).returncode==0
            cleanup &= run(["docker","image","inspect",IMAGE],scratch,env,check=False).returncode!=0
        report["dockerCleanupVerified"]=bool(cleanup)
        require(cleanup)

def sources(scratch,env,go):
    dest=scratch/"source"
    dest.mkdir(mode=0o700)
    for name,expected in BLOBS.items():
        path=dest/name
        fetch("https://raw.githubusercontent.com/tianon/gosu/"+SOURCE+"/"+name,path,MIB,scratch,env)
        data=path.read_bytes()
        require(hashlib.sha1(b"blob "+str(len(data)).encode()+b"\0"+data).hexdigest()==expected)
    before={name:sha(dest/name) for name in BLOBS}
    run([str(go),"mod","download"],dest,env,180)
    require(before=={name:sha(dest/name) for name in BLOBS})
    return dest,before

def scan(scanner,db,mode,target,cwd,env):
    result=run([str(scanner),"-json","-mode="+mode,"-scan=symbol","-db",db.as_uri(),str(target)],
               cwd,env,300)
    decoder=json.JSONDecoder()
    raw=result.stdout
    pos=0
    messages=[]
    while pos<len(raw):
        while pos<len(raw) and raw[pos].isspace():
            pos+=1
        if pos==len(raw):
            break
        obj,end=decoder.raw_decode(raw,pos)
        require(isinstance(obj,dict) and len(obj)==1 and len(messages)<100000)
        messages.append(obj)
        pos=end
    require(messages and "config" in messages[0])
    cfg=messages[0]["config"]
    require(cfg["protocol_version"]=="v1.0.0" and cfg["scan_mode"]==mode and cfg["scan_level"]=="symbol"
            and cfg["scanner_version"].split("-")[0]=="v1.1.4")
    sboms=[x["SBOM"] for x in messages if "SBOM" in x]
    require(len(sboms)==1 and sboms[0]["go_version"]=="go1.24.6")
    modules={m["path"]:m.get("version","") for m in sboms[0]["modules"]}
    require(modules.get("golang.org/x/sys")=="v0.1.0" and modules.get("github.com/moby/sys/user")=="v0.4.1")
    levels={}
    symbols={}
    for obj in messages:
        if "finding" not in obj:
            continue
        f=obj["finding"]
        osv=f["osv"]
        require(re.fullmatch(r"GO-\d{4}-\d+",osv) and f["trace"])
        frame=f["trace"][0]
        level=3 if frame.get("function") else 2 if frame.get("package") else 1
        levels[osv]=max(level,levels.get(osv,0))
        if level==3:
            # No source paths, environment, raw logs, or secret-bearing strings.
            symbol=frame.get("package","")+"."+frame.get("receiver","")+"."+frame["function"]
            require(len(symbol)<=512 and "\n" not in symbol)
            symbols.setdefault(osv,set()).add(symbol)
    return {"mode":mode,"exitCode":result.returncode,"goVersion":sboms[0]["go_version"],
            "databaseModified":cfg.get("db_last_modified"),"levels":levels,
            "symbols":{k:sorted(v) for k,v in symbols.items()},
            "moduleCount":len(modules),"rawSha256":hashlib.sha256(raw.encode()).hexdigest()}

def main():
    out={"status":"BLOCKED","stage":"identity","candidateApproval":"NOT_APPROVED",
         "diagnosticOnly":True,"image":IMAGE,"sourceCommit":SOURCE,"scannerVersion":"v1.1.4"}
    try:
        identity=hosted_identity()
        require(identity["pr"]==9 and classify(ROOT,identity)["status"]=="PASSED"
                and os.environ["RUNNER_OS"]=="Linux" and os.environ["RUNNER_ARCH"]=="X64")
        out["identity"]=identity
        with tempfile.TemporaryDirectory(prefix="f04-gosu-work-",dir=os.environ["RUNNER_TEMP"]) as directory:
            scratch=Path(directory)
            env={"PATH":os.environ["PATH"],"HOME":directory,"TMPDIR":directory,
                 "CI":"true","DOCKER_CONFIG":str(scratch/"docker-config"),"GIT_CONFIG_NOSYSTEM":"1",
                 "GIT_CONFIG_GLOBAL":os.devnull,"GIT_NO_REPLACE_OBJECTS":"1","GIT_TERMINAL_PROMPT":"0"}
            out["stage"]="toolchain"
            go,scanner=go_toolchain(scratch,env)
            out.update(toolSha256=sha(scanner),goArchiveSha256=GO_SHA)
            out["stage"]="database"
            db,meta=database(scratch,env)
            out["database"]=meta
            out["stage"]="image-inventory"
            binary=image_inventory(scratch,env,go,out)
            out["stage"]="source"
            source,before=sources(scratch,env,go)
            out["stage"]="binary-analysis"
            out["binary"]=scan(scanner,db,"binary",binary,scratch,env)
            out["stage"]="source-analysis"
            out["source"]=scan(scanner,db,"source","./...",source,env)
            require(before=={name:sha(source/name) for name in BLOBS})
            require(out["source"]["databaseModified"]==out["binary"]["databaseModified"])
            out["comparison"]=[]
            for cve,ids in meta["aliases"].items():
                b=max((out["binary"]["levels"].get(i,0) for i in ids),default=0)
                s=max((out["source"]["levels"].get(i,0) for i in ids),default=0)
                out["comparison"].append({"cve":cve,"goIds":ids,"binaryLevel":b,"sourceLevel":s})
            require(len(out["comparison"])==27)
        require(not scratch.exists())
        require(classify(ROOT,identity)["status"]=="PASSED")
        out.update(status="COMPLETE_DIAGNOSTIC",stage="complete",ownedCleanup=True,
                   trackedSourceUnchanged=True)
    except (OSError,ValueError,TypeError,KeyError,AttributeError,IndexError,
            subprocess.SubprocessError,tarfile.TarError,zipfile.BadZipFile) as e:
        # Fixed stage and exception class only; never echo raw command or paths.
        out["errorClass"]=type(e).__name__
    print(json.dumps(out,sort_keys=True))
    return 0 if out["status"]=="COMPLETE_DIAGNOSTIC" else 2

if __name__=="__main__":
    sys.exit(main())
