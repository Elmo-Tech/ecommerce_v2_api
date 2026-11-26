"""T034 candidate build/scan only: no registry publication or required-pin changes.

Temporary, isolated hosted PR #9 diagnostic. Actual local image IDs and layer
digests are verified instead of fabricating remote registry digests.
"""
import base64
from collections import Counter
from datetime import datetime, timezone
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tarfile
import tempfile

from policy import (TRIVY_VERSION, TRIVY_ARCHIVE_SHA, MAX_FINDINGS, fresh_database,
                    require, token, package_version, unique_pairs, summary)

SPEC = importlib.util.spec_from_file_location("f04_scan_images", Path(__file__).with_name("scan-images.py"))
scanner = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(scanner)
ASSET, CHECKSUMS, CHECKSUM_SHA, RELEASE = (scanner.ASSET, scanner.CHECKSUMS, scanner.CHECKSUM_SHA, scanner.RELEASE)
run, read_json = scanner.run, scanner.read_json

ROOT = Path(__file__).resolve().parents[3]
RECIPES = Path(__file__).with_name("test-images")
MIB = 1024 * 1024
GO_SHA = "9849f4e04ee35303714e34fbc3ac8ab44ea3e448a95aa182f91e3b9b79c710ec"
GOSU_COMMIT = "29b939aa5bd4dc588af765b8248f6e39150d2565"
GOSU_TREE = "5c69cec164871648ddb14c59192c831b3884ae65"
GOSU_BUILD_VERSION = "v1.20.0+f04.1"
BLOBS = {
    "go.mod": "6abe2ddfa1e3de4ab334463463c7c7c14cb1668f",
    "go.sum": "d8a55165cb0ba195d60a21789c187c0708e94e52",
    "main.go": "7eb3d47a9baaa8c51167fff34fe79e81999ccc6b",
    "setup-user.go": "87afcd1748da9efa4af5128452ac8fbec245a7ce",
    "version.go": "e591d6ca15329c67350c2ba3ccda1b67871b5afa",
}
BASES = {
    "postgres": ("docker.io/library/postgres:17.12-alpine3.23@sha256:833be86fa9cbc2e12ca52ecf8afa306a3f20f6f57772037caeae65075aeb4616",
                 "sha256:b4912da07fcf17d4d7db60cec9a436301114a961784299ea0e77c6b5797d8acd", "3.23"),
    "redis": ("docker.io/library/redis:8.2.11-alpine3.22@sha256:a18fce2f705d9782ccf6968f8dd94fa04566ff97e10a8d322ad72645243e4c0c",
              "sha256:b1ea8d6d7e78d187c53360013ee871f29bf9c0403f301b3fbcdcca2b10f852fa", "3.22"),
}
APK_SHA256 = {
    "libcrypto3-3.5.9-r2.apk": "931ac2f8cd696a47221b6317f650eb5e0ed5bf8f04d34ae7b9573027801d3544",
    "libssl3-3.5.9-r2.apk": "678f241a05f34858762790eac31599def62e3963702d13c62b0aa4720681fea1",
}
NEW_MOD = """module github.com/tianon/gosu

go 1.25.0

require (
	github.com/moby/sys/user v0.4.1
	golang.org/x/sys v0.44.0
)
"""
NEW_SUM = """github.com/moby/sys/user v0.4.1 h1:RgjRlaDKi/Xmyrz4t8lyzXT6v2ooFeO/7xtchmhVWE0=
github.com/moby/sys/user v0.4.1/go.mod h1:E9QsW5WRe1kUAf7kW8hXKwu1uhsZEAdPLYHYSDudF4Y=
golang.org/x/sys v0.44.0 h1:ildZl3J4uzeKP07r2F++Op7E9B29JRUy+a27EibtBTQ=
golang.org/x/sys v0.44.0/go.mod h1:4GL1E5IUh+htKOUEOaiffhrAeqysfVGipDYzABqnCmw=
"""

def sha(path):
    return scanner.sha(path)


def command(args, cwd, env, timeout=180, capture=False, checked=True):
    result = scanner.run(args, cwd, env, timeout, capture)
    if checked:
        require(result.returncode == 0)
    if capture:
        require(len(result.stdout.encode("utf-8")) <= 16 * MIB)
    return result


def fetch(url, target, maximum, scratch, env):
    command(["curl", "--disable", "--proto", "=https", "--proto-redir", "=https",
             "--fail", "--silent", "--location", "--max-time", "120",
             "--max-filesize", str(maximum), "--output", str(target), url],
            scratch, env, 130)
    require(target.is_file() and not target.is_symlink()
            and 0 < target.stat().st_size <= maximum)


def gosu_build(scratch, env, context, record):
    record["stage"] = "gosu-sdk"
    sdk = scratch / "go.tar.gz"
    fetch("https://go.dev/dl/go1.26.11.linux-amd64.tar.gz", sdk, 100*MIB, scratch, env)
    require(sha(sdk) == GO_SHA)
    with tarfile.open(sdk, "r:gz") as archive:
        archive.extractall(scratch, filter="data")
    go = scratch / "go/bin/go"
    goenv = dict(env, PATH=str(go.parent)+":"+env["PATH"], GOROOT=str(go.parent.parent),
                 GOPATH=str(scratch/"gopath"), GOCACHE=str(scratch/"go-cache"),
                 GOMODCACHE=str(scratch/"go-mod-cache"), GOENV="off", GOWORK="off",
                 GOTOOLCHAIN="local", GOOS="linux", GOARCH="amd64", CGO_ENABLED="0",
                 GOPROXY="https://proxy.golang.org", GOSUMDB="sum.golang.org",
                 GOVCS="*:off", GOFLAGS="-mod=readonly")
    require(command([str(go), "version"], scratch, goenv, 30, True).stdout.strip()
            == "go version go1.26.11 linux/amd64")
    record["goSdkSha256"] = GO_SHA
    source = scratch/"gosu-source"
    source.mkdir(mode=0o700)
    api = "https://api.github.com/repos/tianon/gosu/git/"
    record["stage"] = "gosu-source-pin-shape"
    require(len(BLOBS)==5 and all(re.fullmatch(r"[a-f0-9]{40}",v) for v in BLOBS.values())
            and re.fullmatch(r"[a-f0-9]{40}",GOSU_COMMIT) and re.fullmatch(r"[a-f0-9]{40}",GOSU_TREE))
    record["stage"] = "gosu-commit-fetch"
    meta = scratch/"source-meta.json"
    fetch(api+"commits/"+GOSU_COMMIT, meta, MIB, scratch, env)
    record["stage"] = "gosu-commit-identity"
    obj = json.loads(meta.read_text())
    require(obj["sha"] == GOSU_COMMIT and obj["tree"]["sha"] == GOSU_TREE)
    meta.unlink()
    record["stage"] = "gosu-tree-fetch"
    fetch(api+"trees/"+GOSU_TREE+"?recursive=1", meta, MIB, scratch, env)
    record["stage"] = "gosu-tree-identity"
    tree = json.loads(meta.read_text())
    require(tree["sha"] == GOSU_TREE and tree["truncated"] is False)
    found = {item["path"]:item["sha"] for item in tree["tree"]
             if item["type"] == "blob" and (item["path"].endswith(".go") or item["path"] in ("go.mod","go.sum"))}
    record["stage"] = "gosu-tree-inventory"
    record["sourceBlobCount"]=len(found)
    record["sourceBlobMatches"]={name:found.get(name)==expected for name,expected in BLOBS.items()}
    require(found == BLOBS)
    record["stage"] = "gosu-verified-blobs"
    for name, expected in BLOBS.items():
        meta.unlink()
        fetch(api+"blobs/"+expected, meta, 2*MIB, scratch, env)
        item = json.loads(meta.read_text())
        require(item["sha"] == expected and item["encoding"] == "base64")
        raw = base64.b64decode("".join(item["content"].split()), validate=True)
        require(0 < len(raw) <= MIB and len(raw) == item["size"]
                and hashlib.sha1(b"blob "+str(len(raw)).encode()+b"\0"+raw).hexdigest() == expected)
        with (source/name).open("xb") as file:
            file.write(raw)
    record["sourceCommit"] = GOSU_COMMIT
    record["sourceTree"] = GOSU_TREE
    record["sourceBlobs"] = BLOBS
    record["stage"] = "gosu-locked-modules"
    # Exact reviewed source bytes were verified above. The only local changes
    # are pinned module metadata and a version-only linker stamp. A locally
    # built (devel) root module otherwise has no detectable version in Trivy.
    (source/"go.mod").write_text(NEW_MOD, encoding="utf-8")
    (source/"go.sum").write_text(NEW_SUM, encoding="utf-8")
    version_file=source/"version.go"
    require(version_file.read_bytes()==b'package main\n\nconst Version = "1.20"\n')
    version_file.write_bytes(b'package main\n\nvar Version = "1.20"\n')
    record["versionMetadataPatchSha256"]=sha(version_file)
    record["gosuBuildVersion"]=GOSU_BUILD_VERSION
    hashes = {name:sha(source/name) for name in BLOBS}
    expected = {line.split()[0]+"@"+line.split()[1]:line.split()[2] for line in NEW_SUM.splitlines()}
    for version in ("github.com/moby/sys/user@v0.4.1","golang.org/x/sys@v0.44.0"):
        result = json.loads(command([str(go),"mod","download","-json",version],
                                    source,goenv,180,True).stdout)
        require(not result.get("Error") and result["Path"]+"@"+result["Version"] == version
                and result["Sum"] == expected[version]
                and result["GoModSum"] == expected[version+"/go.mod"])
    command([str(go),"mod","verify"],source,goenv,60)
    goenv["GOPROXY"]="off"
    require(set(command([str(go),"list","-m","all"],source,goenv,60,True).stdout.splitlines())
            == {"github.com/tianon/gosu","github.com/moby/sys/user v0.4.1","golang.org/x/sys v0.44.0"})
    record["stage"] = "gosu-compile"
    binary=context/"gosu"
    # Retain ELF .symtab for Trivy 0.76.2's actual version-symbol fallback:
    # -trimpath hides the ldflags metadata and -s would strip .symtab.
    command([str(go),"build","-trimpath","-buildvcs=false","-mod=readonly",
             "-ldflags=-w -buildid= -X main.Version="+GOSU_BUILD_VERSION,
             "-o",str(binary),"."],source,goenv,300)
    record["stage"]="gosu-version-symbol"
    nm=command([str(go),"tool","nm",str(binary)],source,goenv,30,True).stdout
    require(sum(line.split()[-1:]==["main.Version.str"] for line in nm.splitlines())==1
            and sum(line.split()[-1:]==["main.Version"] for line in nm.splitlines())==1)
    record["versionSymbolPresent"]=True
    record["stage"]="gosu-build-metadata"
    info = command([str(go),"version","-m",str(binary)],source,goenv,30,True).stdout
    require(re.search(r": go1\.26\.11$",info.splitlines()[0])
            and "\tpath\tgithub.com/tianon/gosu" in info
            and "\tdep\tgithub.com/moby/sys/user\tv0.4.1\t" in info
            and "\tdep\tgolang.org/x/sys\tv0.44.0\t" in info
            and all("\tbuild\t"+setting in info for setting in ("CGO_ENABLED=0","GOOS=linux","GOARCH=amd64"))
            and hashes == {name:sha(source/name) for name in BLOBS})
    record.update(gosuSha256=sha(binary),moduleLockSha256=hashes["go.sum"],
                  moduleGraph=["github.com/moby/sys/user@v0.4.1","golang.org/x/sys@v0.44.0"],
                  goVersion="1.26.11",gosuBuildVerified=True)


def redis_packages(scratch, env, context, record):
    record["stage"] = "redis-apk-acquisition"
    for name, expected in APK_SHA256.items():
        fetch("https://dl-cdn.alpinelinux.org/alpine/v3.22/main/x86_64/"+name,
              context/name, 16*MIB, scratch, env)
        require(sha(context/name)==expected)
    record["apkSha256"] = dict(APK_SHA256)
    # Signatures and exact versions are enforced inside the offline build
    # using Alpine's existing trusted keyring. No --allow-untrusted.


def docker_inspect(ref, scratch, env, required=True):
    result = command(["docker","image","inspect",ref],scratch,env,30,True,required)
    if result.returncode != 0:
        return None
    data = json.loads(result.stdout)
    require(isinstance(data,list) and len(data)==1)
    return data[0]


def build_image(service, context, scratch, env, report, owned):
    ref, base_id, version = BASES[service]
    record = report["builds"][service]
    tag = "f04-t034-"+service+":"+report["runId"]+"-"+report["runAttempt"]
    record.update(stage="base-acquisition",base=ref,localTag=tag)
    require(docker_inspect(ref,scratch,env,False) is None
            and docker_inspect(tag,scratch,env,False) is None)
    # Owned by this dedicated, ephemeral hosted job only.
    command(["docker","pull","--platform","linux/amd64",ref],scratch,env,180)
    owned.append(ref)
    base = docker_inspect(ref,scratch,env)
    require(base["Id"] == base_id and base["Os"]=="linux" and base["Architecture"]=="amd64"
            and any(x.split("@")[-1]==ref.split("@")[-1] for x in base["RepoDigests"]))
    recipe = RECIPES/(service+".Dockerfile")
    require(recipe.is_file() and not recipe.is_symlink())
    shutil.copyfile(recipe,context/"Dockerfile")
    record["recipeSha256"] = sha(context/"Dockerfile")
    record["stage"]="offline-image-build"
    iid = scratch/(service+".iid")
    result = command(["docker","build","--no-cache","--network=none","--platform=linux/amd64",
                      "--iidfile",str(iid),"-t",tag,str(context)],scratch,
                     dict(env,DOCKER_BUILDKIT="1"),300,False,False)
    record["buildExitCode"] = result.returncode
    if docker_inspect(tag,scratch,env,False) is not None:
        owned.append(tag)
    require(result.returncode==0 and tag in owned)
    image = docker_inspect(tag,scratch,env)
    layers = image["RootFS"]["Layers"]
    require(re.fullmatch(r"sha256:[a-f0-9]{64}",image["Id"])
            and iid.read_text().strip()==image["Id"] and image["Id"]!=base_id
            and image["Os"]=="linux" and image["Architecture"]=="amd64"
            and layers[:-1]==base["RootFS"]["Layers"] and len(layers)==len(base["RootFS"]["Layers"])+1
            and all(re.fullmatch(r"sha256:[a-f0-9]{64}",d) for d in layers))
    # No service startup or entrypoint/UID/environment change in this stage.
    for key in ("Entrypoint","Cmd","User","Env","WorkingDir","ExposedPorts","Volumes","Healthcheck","StopSignal"):
        require(image["Config"].get(key)==base["Config"].get(key))
    require(not image.get("RepoDigests"))
    record.update(stage="built",imageId=image["Id"],diffIds=layers,baseImageId=base_id,
                  configPreserved=True,imagePublished=False)
    return tag,image


def setup_scanner(scratch, env, output):
    for filename, expected, limit in ((ASSET, TRIVY_ARCHIVE_SHA, 500 * 1024 * 1024),
                                       (CHECKSUMS, CHECKSUM_SHA, 1024 * 1024)):
        target = scratch / filename
        result = run(["curl", "--disable", "--proto", "=https", "--proto-redir", "=https",
                      "--fail", "--silent", "--location", "--max-time", "120",
                      "--max-filesize", str(limit), "--output", str(target),
                      RELEASE + filename], scratch, env)
        require(result.returncode == 0 and target.stat().st_size <= limit and sha(target) == expected)
    checksum_rows = [row.split() for row in (scratch / CHECKSUMS).read_text().splitlines()]
    require(sum(len(row) == 2 and row[0] == TRIVY_ARCHIVE_SHA
                and row[1].lstrip("*") == ASSET for row in checksum_rows) == 1)
    binary = scratch / "trivy"
    # Extract only the expected executable from the verified archive.
    with tarfile.open(scratch / ASSET, "r:gz") as archive:
        members = [item for item in archive if item.name in ("trivy", "./trivy")]
        require(len(members) == 1 and members[0].isfile()
                and 0 < members[0].size <= 500 * 1024 * 1024)
        with archive.extractfile(members[0]) as source, binary.open("xb") as dest:
            shutil.copyfileobj(source, dest)
    binary.chmod(0o700)
    version = run([str(binary), "--version"], scratch, env, 30, True)
    require(version.returncode == 0 and re.search(r"^Version:\s+0\.76\.2\s*$", version.stdout, re.M))
    output.update(scannerVersion=TRIVY_VERSION, archiveSha256=TRIVY_ARCHIVE_SHA,
                  checksumFileSha256=CHECKSUM_SHA)
    config = scratch / "trivy.yaml"
    config.write_text("{}\n")
    ignore = scratch / ".trivyignore"
    ignore.touch()
    cache = scratch / "cache"
    base = [str(binary), "--config", str(config), "--cache-dir", str(cache), "--timeout", "5m", "image"]
    common = ["--skip-version-check", "--disable-telemetry", "--no-progress"]
    output["stage"] = "database"
    result = run(base + common + ["--download-db-only", "--db-repository",
                                  "ghcr.io/aquasecurity/trivy-db:2"], scratch, env, 300)
    require(result.returncode == 0)
    meta = cache / "db/metadata.json"
    database = cache / "db/trivy.db"
    before = json.loads(read_json(meta, 64 * 1024))
    fresh_database(before, datetime.now(timezone.utc))
    require(database.is_file() and not database.is_symlink() and database.stat().st_size > 0)
    db_hash = sha(database)
    output["databaseSha256"] = db_hash

    return base, common, before, meta, database, db_hash


def validate_local(target, image, service, raw, exit_code, before, after, now, progress):
    # Same complete inventory/finding logic as the required remote parser;
    # local candidates deliberately have no registry manifest or approval.
    progress["stage"]="native-exit-database"
    progress["nativeExit"]=exit_code
    require(type(exit_code) is int and exit_code==0 and before==after)
    freshness=fresh_database(before,now)
    require(freshness==fresh_database(after,now))
    require(isinstance(raw,str) and 0<len(raw.encode("utf-8"))<=16*MIB)
    progress["stage"]="native-json"
    progress["rawReportSha256"]=hashlib.sha256(raw.encode("utf-8")).hexdigest()
    report=json.loads(raw,object_pairs_hook=unique_pairs,parse_constant=lambda value:require(False))
    progress["stage"]="report-identity"
    names={target,"docker.io/library/"+target,"index.docker.io/library/"+target}
    require(type(report.get("SchemaVersion")) is int and report["SchemaVersion"]==2
            and report["Trivy"]["Version"]==TRIVY_VERSION and report["ArtifactType"]=="container_image"
            and report["ArtifactName"] in names)
    progress["stage"]="platform-local-image-identity"
    family,os_version="alpine",BASES[service][2]
    meta=report["Metadata"]
    require(meta["OS"]["Family"]==family
            and re.fullmatch(re.escape(os_version)+r"(?:\.[0-9]+)*",str(meta["OS"]["Name"]))
            and meta["ImageConfig"]["architecture"]=="amd64" and meta["ImageConfig"]["os"]=="linux"
            and meta["ImageID"]==image["Id"] and meta["DiffIDs"]==image["RootFS"]["Layers"]
            and isinstance(meta["RepoTags"],list) and meta["RepoTags"]
            and all(tag in names for tag in meta["RepoTags"]) and not meta.get("RepoDigests"))
    progress["stage"] = "results"
    results = report["Results"]
    require(isinstance(results, list) and results)
    os_packages = 0
    package_count = 0
    findings = []
    for result in results:
        progress["stage"] = "package-inventory"
        require(isinstance(result, dict) and result.get("Class") in ("os-pkgs", "lang-pkgs"))
        packages = result["Packages"]
        require(isinstance(packages, list) and packages)
        # Do not synthesize a version or drop any unknown package. Report
        # only safe bounded counters and the presence/absence of required
        # metadata before the original strict version validator.
        missing_name=sum(isinstance(pkg,dict) and not pkg.get("Name") for pkg in packages)
        missing_version=sum(isinstance(pkg,dict) and not pkg.get("Version") for pkg in packages)
        progress["inventoryObservation"]={"class":result["Class"],"packageCount":len(packages),
                                           "missingNameCount":missing_name,"missingVersionCount":missing_version}
        require(not missing_name and not missing_version)
        inventory = {(token(pkg["Name"]), package_version(pkg, result["Class"] == "os-pkgs"))
                     for pkg in packages}
        package_count += len(packages)
        require(package_count <= 100000)
        if result["Class"] == "os-pkgs":
            require(result["Type"] == family)
            os_packages += len(packages)
        progress["stage"] = "finding-fields"
        vulnerabilities = result.get("Vulnerabilities", [])
        require(isinstance(vulnerabilities, list))
        for row in vulnerabilities:
            progress["stage"] = "finding-fields"
            severity = row["Severity"]
            require(severity in ("UNKNOWN", "LOW", "MEDIUM", "HIGH", "CRITICAL"))
            name = token(row["PkgName"])
            installed = token(row["InstalledVersion"])
            progress["stage"] = "finding-inventory"
            require((name, installed) in inventory)
            progress["stage"] = "finding-fields"
            fixed = row.get("FixedVersion", "")
            # Native fixed versions can be a comma-separated list.
            require(isinstance(fixed, str) and len(fixed) <= 400)
            if fixed:
                token(fixed, r"[A-Za-z0-9.+:~_, -]+", max_length=400)
            findings.append({"id": token(row["VulnerabilityID"], r"[A-Za-z0-9._-]+"),
                             "package": name, "installedVersion": installed,
                             "fixedVersion": fixed, "severity": severity})
            require(len(findings) <= MAX_FINDINGS)
    progress["stage"] = "os-coverage"
    require(os_packages > 0)
    findings.sort(key=lambda f: (f["id"], f["package"], f["installedVersion"], f["severity"]))

    # Require evidence of the patched versions, even if the scanner reports no
    # findings. A missing language/OS inventory cannot count as green.
    progress["stage"]="patched-inventory"
    installed=set()
    for item in results:
        for pkg in item["Packages"]:
            installed.add((pkg["Name"],package_version(pkg,item["Class"]=="os-pkgs")))
    if service=="postgres":
        require({version for name,version in installed if name=="github.com/tianon/gosu"}
                =={GOSU_BUILD_VERSION}
                and any(name=="stdlib" and version in ("1.26.11","v1.26.11","go1.26.11")
                    for name,version in installed)
                and any(name=="golang.org/x/sys" and version in ("0.44.0","v0.44.0")
                        for name,version in installed)
                and all(version in ("1.26.11","v1.26.11","go1.26.11") for name,version in installed if name=="stdlib")
                and all(version in ("0.44.0","v0.44.0") for name,version in installed if name=="golang.org/x/sys"))
    else:
        for name in ("libcrypto3","libssl3"):
            require({version for pkg,version in installed if pkg==name}=={"3.5.9-r2"})
    progress["stage"]="complete"
    counts=dict(Counter(f["severity"] for f in findings))
    return summary("FAILED" if any(counts.get(s,0) for s in ("HIGH","CRITICAL","UNKNOWN")) else "PASSED",
                   findings,exceptionCount=0,severityCounts=counts,
                   target=target,imageId=image["Id"],osPackageCount=os_packages,packageCount=package_count,
                   database=freshness,patchedInventoryVerified=True,nativeExit=exit_code,
                   candidateOnly=True,approval="NOT_APPROVED")


def main():
    out={"schemaVersion":1,"task":"T034","status":"BLOCKED","stage":"identity",
         "candidateApproval":"NOT_APPROVED","diagnosticOnly":True,"builds":{},"scans":{}}
    try:
        out.update(scanner.identity())
        event=json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
        require(event["number"]==9 and event["pull_request"]["head"]["repo"]["full_name"]=="Elmo-Tech/ecommerce_v2_api"
                and event["pull_request"]["head"]["ref"]=="feature/f0-04-code-quality-ci")
        require(json.loads(Path(__file__).with_name("exceptions.json").read_text())==[])
        parent=Path(os.environ["RUNNER_TEMP"]).resolve()
        with tempfile.TemporaryDirectory(prefix="f04-t034-build-",dir=parent) as directory:
            scratch=Path(directory)
            env={"PATH":os.environ["PATH"],"HOME":directory,"TMPDIR":directory,
                 "CI":"true","NO_COLOR":"1","XDG_CACHE_HOME":str(scratch/"xdg-cache"),
                 "DOCKER_CONFIG":str(scratch/"docker-config"),"GIT_CONFIG_NOSYSTEM":"1",
                 "GIT_CONFIG_GLOBAL":os.devnull,"GIT_TERMINAL_PROMPT":"0"}
            owned=[]
            built={}
            try:
                for service in BASES:
                    out["stage"]=service+"-build"
                    record={}
                    out["builds"][service]=record
                    context=scratch/(service+"-context")
                    context.mkdir(mode=0o700)
                    try:
                        if service=="postgres":
                            gosu_build(scratch,env,context,record)
                        else:
                            redis_packages(scratch,env,context,record)
                        built[service]=build_image(service,context,scratch,env,out,owned)
                    except (OSError,ValueError,TypeError,KeyError,AttributeError,subprocess.SubprocessError,tarfile.TarError) as exc:
                        record.update(status="BLOCKED",errorClass=type(exc).__name__)
                    else:
                        record["status"]="BUILT"
                if built:
                    out["stage"]="scanner-acquisition"
                    base,common,before,meta,database,db_hash=setup_scanner(scratch,env,out)
                    for service,(tag,image) in built.items():
                        out["stage"]=service+"-scan"
                        result={"status":"BLOCKED","candidateOnly":True,"approval":"NOT_APPROVED"}
                        progress={"stage":"scan-native"}
                        try:
                            fresh_database(before,datetime.now(timezone.utc))
                            native=scratch/(service+"-scan.json")
                            cmd=base+common+["--skip-db-update","--image-src","docker","--platform","linux/amd64",
                                             "--scanners","vuln","--list-all-pkgs",
                                             "--severity","UNKNOWN,LOW,MEDIUM,HIGH,CRITICAL",
                                             "--ignorefile",str(scratch/".trivyignore"),"--exit-code","0",
                                             "--format","json","--output",str(native),tag]
                            scanned=command(cmd,scratch,env,300,False,False)
                            after=json.loads(read_json(meta,64*1024))
                            require(sha(database)==db_hash)
                            result=validate_local(tag,image,service,read_json(native,16*MIB),
                                                  scanned.returncode,before,after,datetime.now(timezone.utc),progress)
                            progress["stage"]="post-scan-image-identity"
                            now_image=docker_inspect(tag,scratch,env)
                            require(now_image["Id"]==image["Id"] and now_image["RootFS"]==image["RootFS"])
                            result["rawReportSha256"]=progress["rawReportSha256"]
                        except (OSError,ValueError,TypeError,KeyError,AttributeError,subprocess.SubprocessError) as exc:
                            result={"status":"BLOCKED","errorClass":type(exc).__name__,
                                    "candidateOnly":True,"approval":"NOT_APPROVED",**progress}
                            # Fixed schema field names only; never emit arbitrary
                            # error strings or raw report data.
                            if isinstance(exc,KeyError) and exc.args and exc.args[0] in (
                                    "Version","Name","Packages","Results","Type","Class","Metadata",
                                    "OS","Family","ImageConfig","architecture","os","ImageID","DiffIDs",
                                    "RepoTags","Trivy","ArtifactType","ArtifactName","Severity","PkgName",
                                    "InstalledVersion","VulnerabilityID"):
                                result["missingSchemaField"]=exc.args[0]
                        out["scans"][service]=result
            finally:
                # Remove only exact refs created on this disposable runner.
                clean=True
                for ref in reversed(owned):
                    clean &= command(["docker","image","rm",ref],scratch,env,90,False,False).returncode==0
                    clean &= docker_inspect(ref,scratch,env,False) is None
                out["ownedImageCleanup"]=bool(clean)
                require(clean)
        require(not scratch.exists())
        require(scanner.identity()=={k:out[k] for k in ("sourceSha","baseSha","checkoutSha","runId","runAttempt")})
        out["ownedScratchCleanup"]=True
        out["trackedSourceUnchanged"]=True
        states=[out["scans"].get(service,{}).get("status","BLOCKED") for service in BASES]
        out["status"]="BLOCKED" if "BLOCKED" in states else "FAILED" if "FAILED" in states else "CANDIDATES_SCAN_PASSED"
        out["stage"]="complete"
    except (OSError,ValueError,TypeError,KeyError,AttributeError,subprocess.SubprocessError,tarfile.TarError) as exc:
        out["errorClass"]=type(exc).__name__
    print(json.dumps(out,sort_keys=True))
    return {"CANDIDATES_SCAN_PASSED":0,"FAILED":1,"BLOCKED":2}[out["status"]]


if __name__=="__main__":
    sys.exit(main())
