// Test-first synthetic local graph controls. No real contract or credential reads.
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { syncBuiltinESMExports } from 'node:module';
import net from 'node:net';
import http from 'node:http';
import https from 'node:https';
import tls from 'node:tls';
import dns from 'node:dns';

const ROOT = 'specs/001-synthetic/contracts/openapi.json';
const FRAGMENT = 'specs/001-synthetic/contracts/types.json';
const SECOND = 'specs/002-synthetic/contracts/openapi.json';
const UNREGISTERED = 'specs/001-synthetic/contracts/hidden.json';
const TYPE = '#/components/schemas/Value';
const results = [];
let networkAttempts = 0;
let deniedReads = 0;
let permittedReads = 0;
const blocked = () => {
    networkAttempts++;
    throw new Error('SYNTHETIC_NETWORK_DENIED');
};
for (const [target, names] of [
    [net, ['connect', 'createConnection']],
    [net.Socket.prototype, ['connect']],
    [http, ['request', 'get']],
    [https, ['request', 'get']],
    [tls, ['connect']],
    [dns, ['lookup', 'lookupService', 'resolve', 'resolve4', 'resolve6', 'resolveAny', 'reverse']],
    [dns.promises, ['lookup', 'lookupService', 'resolve', 'resolve4', 'resolve6', 'resolveAny', 'reverse']],
]) {
    for (const name of names) {
        if (typeof target[name] === 'function') target[name] = blocked;
    }
}
globalThis.fetch = blocked;
syncBuiltinESMExports();

function document(schema = { type: 'string' }) {
    return {
        openapi: '3.1.0',
        info: { title: 'Synthetic fixture', version: '1.0.0' },
        paths: {},
        components: { schemas: { Value: schema } },
    };
}

function fixture() {
    const inventory = { roots: [ROOT], fragments: [] };
    const tracked = [{ path: ROOT, mode: '100644' }];
    const contents = new Map([[ROOT, JSON.stringify(document())]]);
    const reads = [];
    const add = (path, value, mode = '100644') => {
        tracked.push({ path, mode });
        contents.set(path, typeof value === 'string' ? value : JSON.stringify(value));
    };
    const fragment = (value, mode = '100644') => {
        inventory.fragments.push(FRAGMENT);
        add(FRAGMENT, value, mode);
    };
    const root = (value) => contents.set(ROOT, typeof value === 'string' ? value : JSON.stringify(value));
    const ref = (value) => root(document({ $ref: value }));
    const readTracked = (path) => {
        const entry = tracked.find((item) => item.path === path);
        if (!entry || !['100644', '100755'].includes(entry.mode)
            || ![...inventory.roots, ...inventory.fragments].includes(path)
            || !contents.has(path)) {
            deniedReads++;
            throw new Error('SYNTHETIC_READ_DENIED');
        }
        permittedReads++;
        reads.push(path);
        return contents.get(path);
    };
    return { inventory, tracked, contents, reads, add, fragment, root, ref, readTracked };
}

function record(id, callback) {
    try {
        callback();
        results.push({ id, status: 'PASSED' });
    } catch (error) {
        results.push({
            id,
            status: 'FAILED',
            reason: error?.code === 'MISSING_PREFLIGHT' ? 'MISSING_PREFLIGHT' : 'ASSERTION_FAILED',
        });
    }
}
record('fixture-control', () => {
    const f = fixture();
    assert.deepEqual(JSON.parse(f.readTracked(ROOT)), document());
    assert.equal(f.reads.length, 1);
    assert.equal(f.contents.size, 1);
    assert.equal(f.tracked[0].mode, '100644');
});

const moduleUrl = new URL('./validate.mjs', import.meta.url);
const implementationPresent = existsSync(moduleUrl);
let preflightContracts;
if (implementationPresent) {
    try {
        ({ preflightContracts } = await import(moduleUrl));
    } catch {
        // Existing but broken module is not the intended missing-policy RED.
    }
}
function run(f) {
    if (!implementationPresent) {
        const error = new Error('Preflight implementation is not present yet.');
        error.code = 'MISSING_PREFLIGHT';
        throw error;
    }
    assert.equal(typeof preflightContracts, 'function');
    // Test seam. T022 supplies independent Git index and canonical file reads.
    const result = preflightContracts({
        inventory: f.inventory, tracked: f.tracked, readTracked: f.readTracked,
    });
    assert.ok(result && typeof result.then !== 'function');
    return result;
}
function positive(id, setup, files = [ROOT]) {
    record(id, () => {
        const f = fixture();
        setup(f);
        assert.deepEqual(run(f), { roots: [ROOT], files: [...files].sort() });
        assert.deepEqual([...new Set(f.reads)].sort(), [...files].sort());
        assert.equal(f.reads.length, files.length);
    });
}
function negative(id, code, setup) {
    record(id, () => {
        const f = fixture();
        setup(f);
        assert.throws(() => run(f), (error) => {
            if (error?.code === 'MISSING_PREFLIGHT') throw error;
            return error?.code === code;
        });
    });
}

positive('components-only-root', () => {});
positive('valid-local-pointer', (f) => {
    f.root({
        ...document(),
        components: { schemas: { Value: { $ref: '#/components/schemas/Other' }, Other: { type: 'string' } } },
    });
});
positive('valid-escaped-pointer', (f) => {
    f.root({
        ...document(),
        components: { schemas: { Value: { $ref: '#/components/schemas/A~1B~0C' }, 'A/B~C': { type: 'integer' } } },
    });
});
positive('valid-local-fragment', (f) => {
    f.fragment({ $defs: { Value: { type: 'integer' } } });
    f.ref('./types.json#/$defs/Value');
}, [ROOT, FRAGMENT]);
positive('valid-recursive-pointer', (f) => {
    f.root(document({ type: 'object', properties: { child: { $ref: TYPE } } }));
});
positive('valid-cross-file-cycle', (f) => {
    f.fragment({ $defs: { Value: { $ref: './openapi.json' + TYPE } } });
    f.ref('./types.json#/$defs/Value');
}, [ROOT, FRAGMENT]);

negative('empty-root-registry', 'INVENTORY', (f) => { f.inventory.roots = []; });
negative('missing-tracked-root', 'INVENTORY', (f) => { f.tracked.length = 0; });
negative('extra-tracked-root', 'INVENTORY', (f) => { f.add(SECOND, document()); });
negative('renamed-tracked-root', 'INVENTORY', (f) => { f.tracked[0].path = SECOND; });
negative('duplicate-root', 'INVENTORY', (f) => { f.inventory.roots.push(ROOT); });
negative('symlink-root', 'INVENTORY', (f) => { f.tracked[0].mode = '120000'; });
negative('untracked-fragment', 'INVENTORY', (f) => { f.inventory.fragments.push(FRAGMENT); f.ref('./types.json'); });
negative('symlink-fragment', 'INVENTORY', (f) => { f.fragment({}, '120000'); f.ref('./types.json'); });
negative('wrong-oas-version', 'ROOT_VERSION', (f) => { f.root({ ...document(), openapi: '3.0.3' }); });
negative('malformed-json', 'JSON_INVALID', (f) => { f.root('{"openapi":'); });
negative('duplicate-json-key', 'JSON_DUPLICATE', (f) => {
    f.root(JSON.stringify(document()).replace('"openapi":', '"openapi":"3.0.3","openapi":'));
});
negative('escaped-duplicate-json-key', 'JSON_DUPLICATE', (f) => {
    f.root(JSON.stringify(document()).replace('"openapi":', '"open\\u0061pi":"3.0.3","openapi":'));
});
negative('malformed-root-shape', 'ROOT_STRUCTURE', (f) => { f.root({ ...document(), paths: [] }); });
negative('missing-info', 'ROOT_STRUCTURE', (f) => {
    const value = document();
    delete value.info;
    f.root(value);
});
negative('non-string-ref', 'REF_INVALID', (f) => { f.ref(7); });
negative('absolute-ref', 'REF_INVALID', (f) => { f.ref('/synthetic-denied.json'); });
negative('network-path-ref', 'REF_INVALID', (f) => { f.ref('//synthetic.invalid/schema.json'); });
negative('remote-ref', 'REF_INVALID', (f) => { f.ref('https://synthetic.invalid/schema.json'); });
negative('file-scheme-ref', 'REF_INVALID', (f) => { f.ref('file:///synthetic-denied.json'); });
negative('encoded-path-ref', 'REF_INVALID', (f) => { f.ref('./%2e%2e/denied.json'); });
negative('backslash-ref', 'REF_INVALID', (f) => { f.ref('..\\denied.json'); });
negative('escaping-traversal-ref', 'REF_INVALID', (f) => { f.ref('../../../denied.json'); });
negative('unregistered-tracked-ref', 'REF_UNREGISTERED', (f) => { f.add(UNREGISTERED, {}); f.ref('./hidden.json'); });
negative('unresolved-pointer', 'REF_UNRESOLVED', (f) => { f.ref('#/components/schemas/Missing'); });
negative('unresolved-anchor', 'REF_UNRESOLVED', (f) => { f.ref('#missing-anchor'); });
negative('unsupported-id-rebasing', 'REF_UNSUPPORTED', (f) => { f.root(document({ $id: 'https://synthetic.invalid/', type: 'string' })); });
negative('unsupported-dynamic-ref', 'REF_UNSUPPORTED', (f) => { f.root(document({ $dynamicRef: '#node' })); });
negative('unsupported-schema-dialect', 'REF_UNSUPPORTED', (f) => { f.root(document({ $schema: 'https://synthetic.invalid/dialect', type: 'string' })); });
negative('file-size-bound', 'LIMIT', (f) => { f.root({ ...document(), 'x-padding': 'a'.repeat(1024 * 1024) }); });
negative('depth-bound', 'LIMIT', (f) => {
    let value = { type: 'string' };
    for (let i = 0; i < 65; i++) value = { properties: { child: value } };
    f.root(document(value));
});
negative('document-count-bound', 'LIMIT', (f) => {
    const refs = [];
    for (let i = 0; i < 128; i++) {
        const name = 'fragment-' + i + '.json';
        const path = 'specs/001-synthetic/contracts/' + name;
        f.inventory.fragments.push(path);
        f.add(path, { type: 'string' });
        refs.push({ $ref: './' + name });
    }
    f.root(document({ allOf: refs }));
});

const passed = results.filter((item) => item.status === 'PASSED').length;
const failed = results.length - passed;
const safe = networkAttempts === 0 && deniedReads === 0;
console.log(JSON.stringify({
    schemaVersion: 1, suite: 'T020-preflight', implementationPresent,
    total: results.length, passed, failed, networkAttempts, deniedReads, permittedReads, results,
}));
process.exitCode = failed === 0 && safe ? 0 : 1;
