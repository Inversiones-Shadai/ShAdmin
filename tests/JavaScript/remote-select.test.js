import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/remote-select.js', import.meta.url), 'utf8');
function selector(previousRegistration) {
    let factory;
    const window = Object.assign(new EventTarget(), { innerWidth: 1024, innerHeight: 768, location: { href: 'http://localhost:8001/form' }, Alpine: { data: (_, value) => { factory = value; } } });
    window.__remoteSelectRegistered = previousRegistration;
    const document = new EventTarget();
    vm.runInNewContext(source, { window, document, URL, AbortController, fetch: () => { throw new Error('Unexpected request'); } });
    const ui = factory({ value: '', source: 'test', url: '/ui/selectors/test', cache: 'options' });
    ui.$nextTick = (action) => action();
    ui.$refs = {};
    ui.$watch = () => {};
    ui.$wire = { rememberSelectorChoice: async () => ({ ok: true }) };
    ui.testWindow = window;
    ui.testDocument = document;
    return ui;
}
const page = (number, items) => ({ data: items, meta: { total: 3914, current_page: number, last_page: 392 } });

test('opening queries just one page and paging keeps the existing selection', async () => {
    const ui = selector();
    const requests = [];
    ui.request = async (params) => { requests.push(params); return page(params.page, [{ id: String(params.page), label: 'Option' }]); };
    ui.value = '999';
    await ui.show();
    await ui.load(2);
    assert.equal(requests.length, 2);
    assert.equal(ui.page, 2);
    assert.equal(ui.value, '999');
    assert.equal(ui.items.length, 1);
});

test('a slow previous page cannot overwrite newer search results', async () => {
    const ui = selector();
    let finish;
    ui.request = () => new Promise((resolve) => { finish = resolve; });
    const old = ui.load(1);
    ui.request = async () => page(2, [{ id: '2', label: 'New' }]);
    await ui.load(2);
    finish(page(1, [{ id: '1', label: 'Old' }]));
    await old;
    assert.equal(ui.items[0].id, '2');
    assert.equal(ui.loading, false);
});

test('selection waits for the server to validate and remember the record', async () => {
    const ui = selector();
    ui.value = '1';
    ui.open = true;
    ui.$wire.rememberSelectorChoice = async () => ({ ok: false, message: 'Unavailable' });
    await ui.select({ id: '2', label: 'Second' });
    assert.equal(ui.value, '1');
    assert.equal(ui.open, true);
    assert.equal(ui.error, 'Unavailable');
    ui.$wire.rememberSelectorChoice = async () => ({ ok: true });
    await ui.select({ id: '9007199254740999', label: 'Precise ID' });
    assert.equal(ui.value, '9007199254740999');
    assert.equal(ui.open, false);
});

test('failed searches can retry without clearing a chosen value', async () => {
    const ui = selector();
    ui.value = '12';
    ui.request = async () => { throw new Error('Offline'); };
    await ui.load();
    assert.equal(ui.error, 'Offline');
    assert.equal(ui.value, '12');
    ui.request = async () => page(1, []);
    await ui.load();
    assert.equal(ui.error, '');
    assert.equal(ui.loading, false);
    assert.equal(ui.value, '12');
});

test('resolving an off-page selection does not enumerate other pages', async () => {
    const ui = selector();
    const requests = [];
    ui.value = '3914';
    ui.request = async (params) => { requests.push(params); return page(1, [{ id: '3914', label: 'Selected' }]); };
    await ui.resolve();
    assert.equal(requests.length, 1);
    assert.equal(requests[0].selected, '3914');
    assert.equal(ui.label, 'Selected');
});

test('closing during verification cancels the pending selection and duplicate clicks are ignored', async () => {
    const ui = selector();
    let finish;
    let calls = 0;
    ui.value = '1';
    ui.open = true;
    ui.$wire.rememberSelectorChoice = () => { calls++; return new Promise((resolve) => { finish = resolve; }); };
    const selecting = ui.select({ id: '2', label: 'Second' });
    await ui.select({ id: '3', label: 'Third' });
    ui.close();
    finish({ ok: true });
    await selecting;
    assert.equal(calls, 1);
    assert.equal(ui.value, '1');
    assert.equal(ui.selecting, false);
    assert.equal(ui.open, false);
});

function floatingSelector(rect = { left: 100, top: 100, bottom: 140, right: 360, width: 260 }) {
    const ui = selector();
    const dialog = new EventTarget();
    let visible = false;
    const panel = {
        style: {}, contains: () => false,
        getBoundingClientRect: () => ({ height: Math.min(400, parseFloat(panel.style.maxHeight) || 400) }),
        showPopover: () => { visible = true; }, hidePopover: () => { visible = false; },
    };
    ui.$refs = { panel, trigger: { isConnected: true, getBoundingClientRect: () => rect, closest: () => dialog,
        focus: (options) => { ui.triggerFocus = options; } }, search: { focus: (options) => { ui.searchFocus = options; } } };
    ui.request = async () => page(1, [{ id: '1', label: 'First' }]);
    return { ui, panel, rect, dialog, visible: () => visible };
}

test('the options float below the trigger without changing the trigger box', async () => {
    const { ui, panel, rect, visible } = floatingSelector();
    const before = { ...rect };
    await ui.show();
    assert.equal(visible(), true);
    assert.equal(panel.style.position, 'fixed');
    assert.equal(panel.style.top, '146px');
    assert.equal(panel.style.left, '100px');
    assert.equal(panel.style.width, '280px');
    assert.deepEqual(rect, before);
    assert.equal(ui.searchFocus.preventScroll, true);
    ui.close(true);
    assert.equal(visible(), false);
    assert.equal(ui.triggerFocus.preventScroll, true);
});

test('a trigger near the bottom opens upwards and is clamped to the right viewport edge', async () => {
    const { ui, panel } = floatingSelector({ left: 990, right: 1390, top: 700, bottom: 740, width: 400 });
    await ui.show();
    assert.equal(panel.style.top, 'auto');
    assert.equal(panel.style.bottom, '74px');
    assert.equal(panel.style.left, '616px');
    assert.equal(panel.style.maxHeight, '420px');
    ui.close();
});

test('small visual viewports constrain the floating options, including mobile keyboard offsets', async () => {
    const { ui, panel } = floatingSelector({ left: 280, right: 360, top: 230, bottom: 270, width: 80 });
    ui.testWindow.visualViewport = Object.assign(new EventTarget(), { width: 300, height: 260, offsetTop: 100, offsetLeft: 10 });
    await ui.show();
    assert.ok(parseFloat(panel.style.left) >= 18);
    assert.ok(parseFloat(panel.style.left) + parseFloat(panel.style.width) <= 302);
    assert.ok(parseFloat(panel.style.top) >= 108);
    assert.ok(parseFloat(panel.style.top) + parseFloat(panel.style.maxHeight) <= 352);
    ui.close();
});

test('loading, empty results and populated results never impose a measured panel height', async () => {
    const { ui, panel } = floatingSelector();
    panel.getBoundingClientRect = () => { throw new Error('A loading box must not constrain subsequent results'); };
    await ui.show();
    assert.equal(panel.style.height, 'auto');
    assert.equal(panel.style.maxHeight, '420px');
    ui.loading = true;
    ui.positionPanel();
    assert.equal(panel.style.maxHeight, '420px');
    for (const count of [0, 7, 10]) {
        ui.request = async () => page(1, Array.from({ length: count }, (_, i) => ({ id: String(i + 1), label: 'Option' })));
        await ui.load();
        assert.equal(ui.items.length, count);
        assert.equal(panel.style.height, 'auto');
        assert.equal(panel.style.maxHeight, '420px');
    }
    ui.close();
});

test('an older registration from Livewire navigation is upgraded once', () => {
    const ui = selector(true);
    assert.equal(ui.testWindow.__remoteSelectRegistered, 'floating-natural-v1');
    let registrations = 0;
    ui.testWindow.Alpine.data = () => { registrations++; };
    ui.testDocument.dispatchEvent(new Event('alpine:init'));
    assert.equal(registrations, 0);
});

test('scrolling repositions the panel and closing the owning dialog removes floating listeners', async () => {
    const { ui, panel, rect, dialog, visible } = floatingSelector();
    await ui.show();
    rect.top += 20; rect.bottom += 20;
    ui.testDocument.dispatchEvent(new Event('scroll'));
    assert.equal(panel.style.top, '166px');
    dialog.dispatchEvent(new Event('close'));
    assert.equal(ui.open, false);
    assert.equal(visible(), false);
    let repositioned = 0;
    ui.positionPanel = () => { repositioned++; };
    ui.testWindow.dispatchEvent(new Event('resize'));
    ui.testDocument.dispatchEvent(new Event('scroll'));
    assert.equal(repositioned, 0);
});

test('removing the selector closes its popover and aborts an outstanding request', async () => {
    const { ui, visible } = floatingSelector();
    await ui.show();
    ui.request = (_, signal) => new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(new DOMException('Closed', 'AbortError'))));
    const loading = ui.load(2);
    ui.destroy();
    await loading;
    assert.equal(visible(), false);
    assert.equal(ui.loading, false);
    assert.equal(ui.error, '');
});
