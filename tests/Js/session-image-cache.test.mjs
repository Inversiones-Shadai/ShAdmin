import { test } from 'node:test';
import assert from 'node:assert/strict';

let sequence = 0;
const buckets = new Map();
const setup = async (session, fetchImage) => {
    globalThis.document = { querySelector: () => ({ content: session }) };
    globalThis.location = { href: 'https://shadmin.test/' };
    globalThis.window = globalThis;
    globalThis.fetch = fetchImage;
    globalThis.caches = {
        keys: async () => [...buckets.keys()],
        delete: async (key) => buckets.delete(key),
        open: async (name) => {
            if (!buckets.has(name)) buckets.set(name, new Map());
            const entries = buckets.get(name);
            return {
                match: async (key) => entries.get(key)?.clone(),
                put: async (key, response) => { entries.set(key, response); },
            };
        },
    };
    return import(`../../resources/js/session-image-cache.js?test=${sequence++}`);
};
const response = () => new Response(new Blob(['png'], { type: 'image/png' }), { headers: { 'Content-Type': 'image/png' } });

test('different images start concurrently and duplicate UUID requests share one fetch', async () => {
    const requests = [];
    const { loadSessionImage } = await setup('concurrent', (source) => new Promise((resolve) => requests.push({ source, resolve })));
    const first = loadSessionImage('https://storage.test/uuid-a?X-Amz-Signature=one');
    const same = loadSessionImage('https://storage.test/uuid-a?X-Amz-Signature=two');
    const second = loadSessionImage('https://storage.test/uuid-b?X-Amz-Signature=one');
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(requests.length, 2);
    requests.forEach((request) => request.resolve(response()));
    const [a, duplicate, b] = await Promise.all([first, same, second]);
    assert.equal(a, duplicate);
    assert.notEqual(a, b);
});

test('cache survives reload in the same session and new UUID fetches fresh bytes', async () => {
    let count = 0;
    const fetchImage = async () => { count++; return response(); };
    let cache = await setup('reload', fetchImage);
    await cache.loadSessionImage('https://storage.test/uuid-a?X-Amz-Signature=one');
    cache = await setup('reload', fetchImage);
    await cache.loadSessionImage('https://storage.test/uuid-a?X-Amz-Signature=two');
    assert.equal(count, 1);
    await cache.loadSessionImage('https://storage.test/uuid-b');
    assert.equal(count, 2);
});

test('new authentication session clears previous session data', async () => {
    const cache = await setup('new-login', async () => response());
    await cache.loadSessionImage('https://storage.test/uuid');
    assert.deepEqual([...buckets.keys()], ['shadmin-images-new-login']);
});

test('changing session during Livewire navigation clears memory without reloading JavaScript', async () => {
    let count = 0;
    const cache = await setup('before-navigation', async () => { count++; return response(); });
    const old = await cache.loadSessionImage('https://storage.test/uuid');
    document.querySelector = () => ({ content: 'after-navigation' });
    const next = await cache.loadSessionImage('https://storage.test/uuid');
    assert.notEqual(next, old);
    assert.equal(count, 2);
    assert.deepEqual([...buckets.keys()], ['shadmin-images-after-navigation']);
});

test('failed requests can retry and cache write failure does not prevent display', async () => {
    let count = 0;
    const cache = await setup('retry', async () => {
        if (count++ === 0) return new Response('', { status: 503 });
        return response();
    });
    await assert.rejects(cache.loadSessionImage('https://storage.test/uuid'));
    assert.match(await cache.loadSessionImage('https://storage.test/uuid'), /^blob:/);
    assert.equal(count, 2);
});
