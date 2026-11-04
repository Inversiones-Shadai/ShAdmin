import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = (await readFile(new URL('../../resources/js/storage-image-loader.js', import.meta.url), 'utf8'))
    .replace(/^import .*;\r?\n/, '');

const setup = (loadSessionImage) => {
    const listeners = new Map();
    const root = {};
    const body = () => ({ parent: root, images: [] });
    const document = {
        documentElement: root,
        body: body(),
        readyState: 'complete',
        addEventListener: (name, callback) => listeners.set(name, callback),
        querySelectorAll: (selector) => selector.startsWith('img') ? document.body.images : [],
    };
    let observer;
    class MutationObserver {
        constructor(callback) { this.callback = callback; observer = this; }
        observe(target) { this.target = target; }
    }
    vm.runInNewContext(source, { document, MutationObserver, loadSessionImage, queueMicrotask });
    const mutate = (target) => {
        for (let node = target; node; node = node.parent) {
            if (node === observer.target) { observer.callback([]); return; }
        }
    };
    return {
        navigate() {
            document.body = body();
            listeners.get('livewire:navigated')();
        },
        addImage(url) {
            const image = {
                dataset: { sessionImageSrc: url }, isConnected: true,
                classList: { remove() {} }, getAttribute: () => null,
            };
            document.body.images.push(image);
            mutate(document.body);
            return image;
        },
        changeImage(image, url) { image.dataset.sessionImageSrc = url; mutate(document.body); },
    };
};
const flush = () => new Promise((resolve) => setImmediate(resolve));

test('loads modal images inserted after navigating to purchases and replacing the body', async () => {
    const requests = [];
    const page = setup(async (url) => { requests.push(url); return `blob:${url}`; });
    const initial = page.addImage('/farma/productos/12/preview');
    await flush();
    assert.equal(initial.src, 'blob:/farma/productos/12/preview');

    page.navigate();
    await flush(); // The purchases page has no search results until the modal opens.
    const image = page.addImage('/farma/productos/149/preview');
    await flush();
    assert.deepEqual(requests, ['/farma/productos/12/preview', '/farma/productos/149/preview']);
    assert.equal(image.src, 'blob:/farma/productos/149/preview');

    page.navigate();
    await flush();
    const next = page.addImage('/farma/productos/148/preview');
    await flush();
    assert.equal(next.src, 'blob:/farma/productos/148/preview');
});

test('a failed older request cannot overwrite an image whose source changed', async () => {
    let failFirst;
    const page = setup((url) => url === '/old'
        ? new Promise((resolve, reject) => { failFirst = reject; })
        : Promise.resolve('blob:new'));
    const image = page.addImage('/old');
    await flush();
    page.changeImage(image, '/new');
    await flush();
    assert.equal(image.src, 'blob:new');
    failFirst(new Error('Old request failed'));
    await flush();
    assert.equal(image.src, 'blob:new');
});

test('a failed current request can fall back to the original image URL', async () => {
    const page = setup(async () => { throw new Error('Cache unavailable'); });
    const image = page.addImage('/farma/productos/149/preview');
    await flush();
    assert.equal(image.src, '/farma/productos/149/preview');
});
