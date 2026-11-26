// MVP: only a bounded local JSON reference preflight. Redocly owns OAS validation.
import { execFileSync } from 'node:child_process';
import { lstatSync, realpathSync } from 'node:fs';
import { posix, resolve, dirname } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const MAX_BYTES = 1024 * 1024;
const MAX_DEPTH = 64;
const MAX_FILES = 128;
const object = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const own = (value, key) => Object.hasOwn(value, key);
const reject = (code) => { throw Object.assign(new Error(code), { code }); };
const rootName = /^specs\/[^/]+\/contracts\/openapi\.json$/;
const boundary = (path) => path.split('/').slice(0, 3).join('/');
const validPath = (path) => typeof path === 'string'
    && /^specs\/[A-Za-z0-9_-][A-Za-z0-9._-]*\/contracts\/(?:[A-Za-z0-9_-][A-Za-z0-9._-]*\/)*[A-Za-z0-9_-][A-Za-z0-9._-]*\.json$/.test(path);

// JSON.parse checks the grammar. Scan its valid token stream only to reject
// duplicate object members (including escaped spellings) and bound nesting.
function strictJson(raw) {
    if (typeof raw !== 'string') reject('JSON_INVALID');
    if (Buffer.byteLength(raw) > MAX_BYTES) reject('LIMIT');
    let value;
    try { value = JSON.parse(raw); } catch { reject('JSON_INVALID'); }
    const stack = [];
    for (const token of raw.matchAll(/"(?:\\[\s\S]|[^"\\])*"(\s*:)?|[{}\[\]]/g)) {
        const text = token[0];
        if (text === '{' || text === '[') {
            stack.push(text === '{' ? new Set() : null);
            if (stack.length > MAX_DEPTH) reject('LIMIT');
        } else if (text === '}' || text === ']') {
            stack.pop();
        } else if (token[1]) {
            const key = JSON.parse(text.slice(0, -token[1].length));
            const keys = stack.at(-1);
            if (!(keys instanceof Set)) reject('JSON_INVALID');
            if (keys.has(key)) reject('JSON_DUPLICATE');
            keys.add(key);
        }
    }
    return value;
}

export function preflightContracts({ inventory, tracked, readTracked }) {
    if (!object(inventory) || Object.keys(inventory).sort().join(',') !== 'fragments,roots'
        || !Array.isArray(inventory.roots) || !Array.isArray(inventory.fragments)
        || inventory.roots.length === 0 || !Array.isArray(tracked)) reject('INVENTORY');
    const { roots, fragments } = inventory;
    const registered = [...roots, ...fragments];
    if (registered.length > MAX_FILES) reject('LIMIT');
    if (new Set(registered).size !== registered.length
        || !roots.every((path) => validPath(path) && rootName.test(path))
        || !fragments.every((path) => validPath(path) && !rootName.test(path)
            && roots.some((root) => boundary(root) === boundary(path)))) reject('INVENTORY');
    const entries = new Map();
    for (const entry of tracked) {
        if (typeof entry?.path !== 'string' || entries.has(entry.path)) reject('INVENTORY');
        entries.set(entry.path, entry.mode);
    }
    const discovered = [...entries.keys()].filter((path) => rootName.test(path)).sort();
    if (JSON.stringify(discovered) !== JSON.stringify([...roots].sort())
        || !registered.every((path) => ['100644', '100755'].includes(entries.get(path)))) reject('INVENTORY');

    const allowed = new Set(registered);
    const documents = new Map();
    const pending = [];
    function load(path) {
        if (documents.has(path)) return documents.get(path);
        if (!allowed.has(path)) reject('REF_UNREGISTERED');
        if (documents.size >= MAX_FILES) reject('LIMIT');
        const value = strictJson(readTracked(path));
        if (roots.includes(path)) {
            if (!object(value) || value.openapi !== '3.1.0') reject('ROOT_VERSION');
            if (!object(value.info) || typeof value.info.title !== 'string'
                || typeof value.info.version !== 'string' || !object(value.paths)
                || (own(value, 'components') && !object(value.components))) reject('ROOT_STRUCTURE');
        } else if (!object(value) && typeof value !== 'boolean') {
            reject('ROOT_STRUCTURE');
        }
        documents.set(path, value);
        pending.push(path);
        return value;
    }
    function checkRef(from, ref) {
        // No URI decoding, schemes, queries, parent traversal or remote reads.
        // Named anchors/dialects are deferred; internal JSON pointers suffice now.
        if (typeof ref !== 'string' || ref.length === 0
            || /[%\\:\s?\x00-\x1f\x7f]/.test(ref) || ref.startsWith('/')) reject('REF_INVALID');
        const parts = ref.split('#');
        if (parts.length > 2) reject('REF_INVALID');
        const [file, pointer = ''] = parts;
        let target = from;
        if (file) {
            if (file.split('/').includes('..') || file.includes('//') || !file.endsWith('.json')) reject('REF_INVALID');
            target = posix.normalize(posix.join(posix.dirname(from), file));
            if (!validPath(target) || boundary(target) !== boundary(from)) reject('REF_INVALID');
        }
        let node = load(target);
        if (!pointer) return;
        if (!pointer.startsWith('/')) reject('REF_UNRESOLVED');
        for (const segment of pointer.slice(1).split('/')) {
            if (/~(?![01])/.test(segment)) reject('REF_INVALID');
            const key = segment.replaceAll('~1', '/').replaceAll('~0', '~');
            if (Array.isArray(node)) {
                if (!/^(0|[1-9][0-9]*)$/.test(key) || Number(key) >= node.length) reject('REF_UNRESOLVED');
            } else if (!object(node) || !own(node, key)) {
                reject('REF_UNRESOLVED');
            }
            node = node[key];
        }
    }
    function walk(path, node) {
        if (Array.isArray(node)) {
            for (const item of node) walk(path, item);
        } else if (object(node)) {
            for (const [key, value] of Object.entries(node)) {
                // Deliberately narrow MVP subset; revisit only for a real contract need.
                if (['$id', '$schema', '$dynamicRef', '$dynamicAnchor', '$recursiveRef',
                    '$recursiveAnchor', '$anchor', 'jsonSchemaDialect', 'externalValue',
                    'operationRef'].includes(key)
                    || (key === 'discriminator' && object(value) && own(value, 'mapping'))) reject('REF_UNSUPPORTED');
                if (key === '$ref') checkRef(path, value);
                else walk(path, value);
            }
        }
    }
    for (const path of roots) load(path);
    for (let i = 0; i < pending.length; i++) walk(pending[i], documents.get(pending[i]));
    return { roots: [...roots].sort(), files: [...documents.keys()].sort() };
}

// Any changed contract gets human review, not an invented compatibility score.
export const contractReviewRequired = (before, after) => before !== after;

// Git index blobs are the only input source for the real checkout. Never
// follow a file reference into the working directory or read an untracked file.
export function preflightCheckout(repo) {
    const registryPath = 'tools/quality/openapi/inventory.json';
    const git = (args, limit = 4 * MAX_BYTES) => execFileSync('git', args, {
        cwd: repo, maxBuffer: limit, timeout: 30000, stdio: ['ignore', 'pipe', 'ignore'],
        env: { PATH: process.env.PATH, LC_ALL: 'C', GIT_CONFIG_NOSYSTEM: '1',
            GIT_CONFIG_GLOBAL: '/dev/null', GIT_OPTIONAL_LOCKS: '0', GIT_NO_REPLACE_OBJECTS: '1' },
    });
    const decode = (bytes) => new TextDecoder('utf-8', { fatal: true }).decode(bytes);
    if (realpathSync(repo) !== repo || decode(git(['rev-parse', '--show-toplevel'])).trim() !== repo) reject('INVENTORY');
    const index = new Map();
    for (const row of decode(git(['ls-files', '--stage', '-z'])).split('\0').filter(Boolean)) {
        const match = /^(\d{6}) ([a-f0-9]{40}) 0\t([\s\S]+)$/.exec(row);
        if (!match || index.has(match[3])) reject('INVENTORY');
        index.set(match[3], { path: match[3], mode: match[1], oid: match[2] });
    }
    const readTracked = (path) => {
        const entry = index.get(path);
        if (!entry || !['100644', '100755'].includes(entry.mode)
            || (path !== registryPath && !validPath(path))) reject('INVENTORY');
        const absolute = resolve(repo, path);
        const stat = lstatSync(absolute);
        if (!stat.isFile() || stat.nlink !== 1 || realpathSync(absolute) !== absolute) reject('INVENTORY');
        const size = Number(decode(git(['cat-file', '-s', entry.oid])).trim());
        if (!Number.isSafeInteger(size) || size < 0 || size > MAX_BYTES) reject('LIMIT');
        return decode(git(['cat-file', 'blob', entry.oid], MAX_BYTES + 1));
    };
    const inventory = strictJson(readTracked(registryPath));
    const inputs = new Map();
    const result = preflightContracts({ inventory, tracked: [...index.values()], readTracked(path) {
        const raw = readTracked(path);
        inputs.set(path, raw);
        return raw;
    } });
    return { ...result, inputs };
}

if (process.argv[1] && pathToFileURL(resolve(process.argv[1])).href === import.meta.url) {
    try {
        const repo = realpathSync(resolve(dirname(fileURLToPath(import.meta.url)), '../../..'));
        const { roots, files } = preflightCheckout(repo);
        console.log(JSON.stringify({ status: 'PASSED', roots, files }));
    } catch (error) {
        console.error(JSON.stringify({ status: 'FAILED', category: [
            'INVENTORY', 'ROOT_VERSION', 'ROOT_STRUCTURE', 'JSON_INVALID', 'JSON_DUPLICATE',
            'REF_INVALID', 'REF_UNREGISTERED', 'REF_UNRESOLVED', 'REF_UNSUPPORTED', 'LIMIT',
        ].includes(error?.code) ? error.code : 'PREFLIGHT_ERROR' }));
        process.exitCode = 1;
    }
}
