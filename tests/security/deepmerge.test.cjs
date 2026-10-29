const assert = require('node:assert/strict');
const { test } = require('node:test');
const { createRequire } = require('node:module');
const path = require('node:path');

// Exercise the dependency Jest actually resolves, including after npm ci.
const jestRequire = createRequire(require.resolve('jest-config'));
const merge = jestRequire('deepmerge');

test('Jest resolves the local security-patched dependency', () => {
    assert.equal(jestRequire.resolve('deepmerge'), path.resolve(__dirname, '../../tools/deepmerge/index.js'));
});

test('rejects prototype poisoning with null and primitive placeholders', () => {
    for (const session of [null, undefined, false, 0, '']) {
        const result = merge({ session }, JSON.parse('{"session":{"__proto__":{"admin":true},"safe":1}}'));
        assert.equal(result.session.admin, undefined);
        assert.equal(Object.getPrototypeOf(result.session), Object.prototype);
        assert.equal(Object.hasOwn(result.session, '__proto__'), false);
        assert.equal(result.session.safe, 1);
    }
    assert.equal({}.admin, undefined);
});

test('rejects prototype keys for null-prototype targets', () => {
    const result = merge(Object.create(null), JSON.parse('{"__proto__":{"admin":true},"safe":1}'));
    assert.equal(result.admin, undefined);
    assert.equal(Object.getPrototypeOf(result), Object.prototype);
    assert.equal(result.safe, 1);
});

test('rejects dangerous keys from both the target and source', () => {
    for (const key of ['__proto__', 'constructor', 'prototype']) {
        const payload = JSON.parse(`{"${key}":{"admin":true},"safe":1}`);
        for (const result of [merge(payload, {}), merge({}, payload), merge(payload, payload)]) {
            assert.equal(Object.hasOwn(result, key), false);
            assert.equal(result.admin, undefined);
            assert.equal(Object.getPrototypeOf(result), Object.prototype);
            assert.equal(result.safe, 1);
        }
    }
});

test('rejects poisoning nested inside arrays and merge.all', () => {
    const payload = JSON.parse('{"__proto__":{"admin":true},"safe":1}');
    for (const result of [merge([], [payload])[0], merge.all([payload, { other: 2 }])]) {
        assert.equal(result.admin, undefined);
        assert.equal(Object.getPrototypeOf(result), Object.prototype);
        assert.equal(result.safe, 1);
    }
});

test('preserves normal Jest configuration merging without mutating inputs', () => {
    const target = { globals: { enabled: true }, paths: ['one'] };
    const source = { globals: { language: 'en' }, paths: ['two'] };
    assert.deepEqual(merge(target, source), {
        globals: { enabled: true, language: 'en' }, paths: ['one', 'two']
    });
    assert.deepEqual(target, { globals: { enabled: true }, paths: ['one'] });
    assert.deepEqual(source, { globals: { language: 'en' }, paths: ['two'] });
    assert.deepEqual(merge.all([{ a: 1 }, { b: 2 }]), { a: 1, b: 2 });
});

test('preserves symbols, special values, and custom merge options', () => {
    const key = Symbol('config');
    const date = new Date('2026-01-01T00:00:00Z');
    const result = merge({ [key]: { a: 1 }, date }, { [key]: { b: 2 } });
    assert.deepEqual(result[key], { a: 1, b: 2 });
    assert.equal(result.date, date);
    assert.deepEqual(merge([1], [2], { arrayMerge: (_, source) => source }), [2]);
    assert.deepEqual(merge({ config: { a: 1 } }, { config: { b: 2 } }, {
        customMerge: key => key === 'config' ? (_, source) => source : undefined
    }), { config: { b: 2 } });
});

test('Jest safely merges preset globals through the patched package', async () => {
    const fs = require('node:fs/promises');
    const os = require('node:os');
    const { readConfigs } = require('jest-config');
    const rootDir = await fs.mkdtemp(path.join(os.tmpdir(), 'planner-jest-security-'));
    try {
        await fs.mkdir(path.join(rootDir, 'preset'));
        await fs.writeFile(path.join(rootDir, 'preset/jest-preset.js'),
            'module.exports = { globals: { session: null, base: true } };\n');
        const result = await readConfigs({ config: JSON.stringify({
            rootDir,
            preset: './preset/jest-preset.js',
            globals: JSON.parse('{"session":{"__proto__":{"admin":true},"language":"en"}}')
        }) }, [rootDir]);
        const globals = result.configs[0].globals;
        assert.equal(globals.base, true);
        assert.equal(globals.session.language, 'en');
        assert.equal(globals.session.admin, undefined);
        assert.equal(Object.getPrototypeOf(globals.session), Object.prototype);
    } finally {
        await fs.rm(rootDir, { recursive: true, force: true });
    }
});
