// Real locked CLI controls. Keep the MVP integration small: Redocly validates,
// Git shows contract changes, and any changed contract needs human review.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { copyFileSync, existsSync, lstatSync, mkdirSync, mkdtempSync, readFileSync, readlinkSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { preflightCheckout, preflightContracts } from './validate.mjs';

const tools = dirname(fileURLToPath(import.meta.url));
const repo = realpathSync(join(tools, '../../..'));
const contract = 'specs/002-api-foundation/contracts/openapi.json';
const out = realpathSync(process.argv[2]);
const lockHash = 'a987d3b6bef34c77a4d1acc7bdbdeb163e82f3a87aef0dfa15abd004765466a0';
const summary = {
    schemaVersion: 1, suite: 'T021-redocly', status: 'BLOCKED',
    node: process.version, results: [],
    runId: process.env.GITHUB_RUN_ID, runAttempt: process.env.GITHUB_RUN_ATTEMPT,
};
const scratch = mkdtempSync(join(out, 'redocly-'));
let clean = false;
const env = {
    PATH: process.env.PATH, HOME: scratch, TMPDIR: scratch, CI: 'true',
    NO_COLOR: '1', REDOCLY_TELEMETRY: 'off', REDOCLY_SUPPRESS_NOTIFICATIONS: 'true',
    npm_config_cache: join(scratch, 'npm-cache'),
    npm_config_userconfig: join(scratch, 'user.npmrc'),
    npm_config_globalconfig: join(scratch, 'global.npmrc'),
    npm_config_registry: 'https://registry.npmjs.org/',
};
function command(bin, args, cwd = scratch, timeout = 60000) {
    const result = spawnSync(bin, args, { cwd, env, encoding: 'utf8', timeout, maxBuffer: 1024 * 1024 });
    assert.equal(result.error, undefined);
    assert.equal(result.signal, null);
    assert.ok(Number.isInteger(result.status));
    return result;
}
function git(args) {
    const result = command('git', args, repo);
    assert.equal(result.status, 0);
    return result.stdout.trim();
}
function sha(path) {
    return createHash('sha256').update(readFileSync(path)).digest('hex');
}
function json(name, value) {
    writeFileSync(join(scratch, name), JSON.stringify(value));
}
function control(id, callback) {
    try {
        callback();
        summary.results.push({ id, status: 'PASSED' });
    } catch {
        summary.results.push({ id, status: 'FAILED' });
    }
}
function fixture(schema) {
    return {
        openapi: '3.1.0', info: { title: 'Synthetic control', version: '1.0.0' },
        paths: {}, components: { schemas: { Value: schema } },
    };
}

try {
    assert.equal(process.version, 'v24.22.3');
    const event = JSON.parse(readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8'));
    assert.equal(event.number, 9);
    assert.equal(event.pull_request.base.ref, 'development');
    summary.sourceSha = event.pull_request.head.sha;
    summary.baseSha = event.pull_request.base.sha;
    summary.checkoutSha = git(['rev-parse', 'HEAD']);
    assert.equal(summary.baseSha, '24ce00231dec35a089bb70c081b257f9b5d234e7');
    assert.deepEqual(git(['rev-list', '--parents', '-n', '1', 'HEAD']).split(' '),
        [summary.checkoutSha, summary.baseSha, summary.sourceSha]);
    assert.equal(git(['status', '--porcelain', '--untracked-files=no']), '');
    // At this stage only the previously reviewed, unchanged real contract may
    // enter the CLI. All other inputs are local synthetic fixtures.
    const baseBlob = git(['rev-parse', summary.baseSha + ':' + contract]);
    const currentBlob = git(['rev-parse', 'HEAD:' + contract]);
    assert.equal(currentBlob, baseBlob);
    summary.contractBlob = currentBlob;
    summary.canonicalUnchanged = true;
    // Run preflight on the exact tracked bytes and stage only approved inputs.
    const checked = preflightCheckout(repo);
    assert.deepEqual(checked.roots, [contract]);
    summary.checkedRoots = checked.roots;
    summary.checkedFiles = checked.files;
    for (const [path, raw] of checked.inputs) {
        // Any change to the current graph is review-required for this MVP lane.
        assert.equal(git(['rev-parse', summary.baseSha + ':' + path]),
            git(['rev-parse', 'HEAD:' + path]));
        const staged = join(scratch, path);
        mkdirSync(dirname(staged), { recursive: true });
        writeFileSync(staged, raw, { flag: 'wx' });
    }
    const canonicalPath = join(scratch, contract);
    for (const name of ['package.json', 'package-lock.json']) {
        copyFileSync(join(tools, name), join(scratch, name));
    }
    assert.equal(sha(join(scratch, 'package-lock.json')), lockHash);
    summary.lockSha256 = lockHash;
    writeFileSync(env.npm_config_userconfig, '');
    writeFileSync(env.npm_config_globalconfig, '');
    const npm = command('npm', ['--version']);
    assert.equal(npm.status, 0);
    summary.npm = npm.stdout.trim();
    const [major, minor, patch] = summary.npm.split('.').map(Number);
    assert.ok(major > 10 || (major === 10 && (minor > 9 || (minor === 9 && patch >= 2))));
    assert.equal(command('npm', ['ci', '--ignore-scripts', '--no-audit', '--no-fund', '--no-progress'], scratch, 120000).status, 0);
    assert.equal(sha(join(scratch, 'package-lock.json')), lockHash);
    // Reuse the locked install rather than acquiring a second npm graph.
    const audit = command('python3', ['-B', join(repo, 'tools/quality/security/audit.py'), 'npm', scratch], scratch, 180000);
    assert.ok([0, 1, 2].includes(audit.status));
    summary.npmAudit = JSON.parse(audit.stdout);
    assert.equal(audit.status, 0);
    assert.equal(summary.npmAudit.status, 'PASSED');
    const cli = join(scratch, 'node_modules/@redocly/cli/bin/cli.js');
    assert.equal(JSON.parse(readFileSync(join(scratch, 'node_modules/@redocly/cli/package.json'), 'utf8')).version, '2.83.0');
    // GitHub-hosted Ubuntu disables unprivileged user namespaces.
    // Elevate only the fixed namespace setup, then drop UID/GID, all groups
    // and capabilities before the locked CLI starts; no privileged Node.
    assert.equal(process.env.F04_RUNNER_ENVIRONMENT, 'github-hosted');
    const uid = process.getuid();
    const gid = process.getgid();
    assert.ok(uid > 0 && gid > 0);
    const hostNamespace = readlinkSync('/proc/self/ns/net');
    const offline = ['-n', '--', '/usr/bin/unshare', '--net', '--',
        '/usr/bin/setpriv', '--reuid=' + uid, '--regid=' + gid, '--clear-groups',
        '--no-new-privs', '--bounding-set=-all', '--inh-caps=-all', '--ambient-caps=-all',
        '--', '/usr/bin/env', '-i', ...Object.entries(env).map(([k, v]) => k + '=' + v), process.execPath];
    const offlineRun = (args) => command('/usr/bin/sudo', [...offline, ...args]);
    const probe = offlineRun(['-e', "const os=require('node:os'),fs=require('node:fs');console.log(JSON.stringify({uid:process.getuid(),gid:process.getgid(),namespace:fs.readlinkSync('/proc/self/ns/net'),interfaces:Object.keys(os.networkInterfaces()),status:fs.readFileSync('/proc/self/status','utf8').split('\\n').filter(s=>/^(CapEff|CapPrm|CapBnd|NoNewPrivs):/.test(s))}))"]);
    if (probe.status !== 0) {
        summary.blockedReason = 'NETWORK_NAMESPACE_UNAVAILABLE';
        throw new Error('Offline namespace unavailable');
    }
    const observed = JSON.parse(probe.stdout);
    assert.equal(observed.uid, uid);
    assert.equal(observed.gid, gid);
    assert.notEqual(observed.namespace, hostNamespace);
    assert.ok(observed.interfaces.every((name) => name === 'lo'));
    for (const key of ['CapEff', 'CapPrm', 'CapBnd']) {
        assert.ok(observed.status.some((line) => new RegExp('^' + key + ':\\s+0+$').test(line)));
    }
    assert.ok(observed.status.some((line) => /^NoNewPrivs:\s+1$/.test(line)));
    summary.networkIsolation = 'linux-netns-unprivileged-child';
    summary.offlineProbePassed = true;
    const cliRun = (args) => offlineRun([
        '--permission', '--allow-fs-read=' + scratch, '--allow-fs-read=' + scratch + '/*',
        '--allow-fs-write=' + scratch, '--allow-fs-write=' + scratch + '/*', cli, ...args,
    ]);
    const version = cliRun(['--version']);
    assert.equal(version.status, 0);
    assert.match(version.stdout + version.stderr, /\b2\.83\.0\b/);
    summary.redocly = '2.83.0';
    // Structural/ref errors are mandatory. Use the built-in spec ruleset;
    // no fake production paths, skip rules or unused-component removal.
    copyFileSync(join(tools, 'redocly.yaml'), join(scratch, 'redocly.yaml'));
    assert.equal(readFileSync(join(scratch, 'redocly.yaml'), 'utf8'),
        'extends:\n  - spec\nrules:\n  struct: error\n  no-unresolved-refs: error\n');
    const lint = (name) => cliRun(['lint', name, '--config', 'redocly.yaml', '--format=json']);
    json('bad-shape.json', fixture({ type: 17 }));
    json('missing-ref.json', fixture({ $ref: '#/components/schemas/Missing' }));
    json('local-ref.json', fixture({ $ref: './types.json#/$defs/Value' }));
    json('types.json', { $defs: { Value: { type: 'integer' } } });
    control('canonical-lint', () => assert.equal(lint(contract).status, 0));
    control('local-ref-lint', () => assert.equal(lint('local-ref.json').status, 0));
    control('structural-negative', () => {
        const result = lint('bad-shape.json');
        assert.equal(result.status, 1);
        assert.match(result.stdout + result.stderr, /"ruleId"\s*:\s*"struct"/);
    });
    control('unresolved-ref-negative', () => {
        const result = lint('missing-ref.json');
        assert.equal(result.status, 1);
        assert.match(result.stdout + result.stderr, /"ruleId"\s*:\s*"no-unresolved-refs"/);
    });
    function checkedBundle(name, root) {
        const path = join(scratch, name);
        const stat = lstatSync(path);
        assert.ok(stat.isFile() && stat.nlink === 1 && stat.size > 0 && stat.size <= 1024 * 1024);
        assert.equal(realpathSync(path), path);
        const raw = new TextDecoder('utf-8', { fatal: true }).decode(readFileSync(path));
        // A bundle must stand alone: no reference to another file, local or remote.
        preflightContracts({
            inventory: { roots: [root], fragments: [] },
            tracked: [{ path: root, mode: '100644' }],
            readTracked: () => raw,
        });
        return JSON.parse(raw);
    }
    control('canonical-bundle-revalidate', () => {
        assert.equal(cliRun(['bundle', contract, '--config', 'redocly.yaml', '--output', 'bundle.json', '--ext', 'json']).status, 0);
        assert.deepEqual(checkedBundle('bundle.json', contract),
            JSON.parse(readFileSync(canonicalPath, 'utf8')));
        assert.equal(lint('bundle.json').status, 0);
        summary.canonicalBundleEquivalent = true;
    });
    control('local-bundle-revalidate', () => {
        assert.equal(cliRun(['bundle', 'local-ref.json', '--config', 'redocly.yaml', '--output', 'local-bundle.json', '--ext', 'json']).status, 0);
        const bundled = checkedBundle('local-bundle.json', 'specs/001-synthetic/contracts/openapi.json');
        assert.equal(bundled.openapi, '3.1.0');
        assert.equal(lint('local-bundle.json').status, 0);
    });

    control('missing-bundle-negative', () => {
        assert.throws(() => checkedBundle('missing-bundle.json', contract), { code: 'ENOENT' });
    });
    control('malformed-bundle-negative', () => {
        writeFileSync(join(scratch, 'malformed-bundle.json'), '{"openapi":');
        assert.throws(() => checkedBundle('malformed-bundle.json', contract), { code: 'JSON_INVALID' });
    });

    // One conservative compatibility policy, not a compatibility engine.
    // The integrated entrypoint must exist and require review for ANY contract
    // change, including wire semantics not understood by a structural linter.
    const integration = join(tools, 'validate.mjs');
    const policyPresent = existsSync(integration);
    summary.integrationPresent = policyPresent;
    let reviewRequired;
    if (policyPresent) {
        try {
            ({ contractReviewRequired: reviewRequired } = await import(fileURLToPath(new URL('./validate.mjs', import.meta.url))));
        } catch {
            // A broken implementation is a failure, not intended RED.
        }
    }
    control('integration-review-policy', () => {
        assert.equal(typeof reviewRequired, 'function');
        const original = readFileSync(canonicalPath, 'utf8');
        assert.equal(reviewRequired(original, original), false);
        const changes = [
            { required: ['id'] }, { type: 'integer' }, { properties: { amount: { type: 'number' } } },
            { responses: { 403: { description: 'Changed permission' } } },
            { headers: { 'X-Request-Id': { required: true } } },
            { properties: { id: { type: 'string' } } },
        ];
        for (const value of changes) {
            assert.equal(reviewRequired(original, JSON.stringify(fixture(value))), true);
        }
    });
    assert.equal(git(['status', '--porcelain', '--untracked-files=no']), '');
    summary.status = summary.results.every((item) => item.status === 'PASSED') ? 'PASSED' : 'FAILED';
} catch {
    summary.status = 'BLOCKED';
} finally {
    // Only the exact private directory created above is removed.
    rmSync(scratch, { recursive: true, force: false });
    clean = !existsSync(scratch);
    summary.ownedCleanup = clean;
    writeFileSync(join(out, 'redocly.summary.json'), JSON.stringify(summary, null, 2) + '\n', { flag: 'wx', mode: 0o600 });
}
console.log(JSON.stringify({ suite: summary.suite, status: summary.status, cases: summary.results }));
process.exitCode = summary.status === 'PASSED' && clean ? 0 : summary.status === 'FAILED' ? 1 : 2;
