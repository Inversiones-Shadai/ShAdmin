import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/navigation-recovery.js', import.meta.url), 'utf8');

function interfaceHarness(fetch = async () => new Response('<html>Complete</html>')) {
    const document = new EventTarget();
    const window = new EventTarget();
    const visits = [];
    window.fetch = fetch;

    window.location = {
        href: 'http://localhost:8001/farma/productos',
        origin: 'http://localhost:8001',
        assign: (url) => visits.push(['assign', url]),
        replace: (url) => visits.push(['replace', url]),
    };

    vm.runInNewContext(source, { document, window, URL, Headers, queueMicrotask });

    const navigate = async (options = {}, cancel = false) => {
        const event = new Event('livewire:navigate', { cancelable: true });
        event.detail = {
            url: new URL('/farma/productos/principios-activos', window.location.href),
            cached: false,
            history: false,
            ...options,
        };
        document.dispatchEvent(event);
        if (cancel) event.preventDefault();
        await Promise.resolve();
    };

    const fail = (name = 'TypeError', frame = 'sendNavigateRequest') => {
        const event = new Event('unhandledrejection', { cancelable: true });
        event.reason = { name, stack: `${name}: Failed to fetch\n    at ${frame} (livewire.js:5696:33)` };
        window.dispatchEvent(event);
        return event;
    };

    return { document, window, navigate, fail, visits };
}

test('a failed page download opens the intended page once with a full load', async () => {
    const ui = interfaceHarness();
    await ui.navigate();
    assert.equal(ui.fail().defaultPrevented, true);
    ui.fail();
    assert.deepEqual(ui.visits, [['assign', 'http://localhost:8001/farma/productos/principios-activos']]);
});

test('back/forward recovery replaces the current history entry', async () => {
    const ui = interfaceHarness();
    await ui.navigate({ history: true });
    ui.fail();
    assert.equal(ui.visits[0][0], 'replace');
});

test('successful navigation does not leave a stale recovery destination', async () => {
    for (const phase of ['livewire:navigating', 'livewire:navigated']) {
        const ui = interfaceHarness();
        await ui.navigate();
        ui.document.dispatchEvent(new Event(phase));
        assert.equal(ui.fail().defaultPrevented, false);
        assert.deepEqual(ui.visits, []);
    }
});

test('an already prefetched page can finish swapping before the navigation microtask', async () => {
    const ui = interfaceHarness();
    const navigation = ui.navigate();
    ui.document.dispatchEvent(new Event('livewire:navigating'));
    await navigation;
    assert.equal(ui.fail().defaultPrevented, false);
    assert.deepEqual(ui.visits, []);
});

test('cancelled, cached, external and unrequested prefetch failures do not change pages', async () => {
    for (const options of [{ cancel: true }, { cached: true }, { url: 'https://example.test/' }, { prefetch: true }]) {
        const ui = interfaceHarness();
        if (! options.prefetch) await ui.navigate(options, options.cancel);
        assert.equal(ui.fail().defaultPrevented, false);
        assert.deepEqual(ui.visits, []);
    }
});

test('component failures and intentionally aborted requests remain outside recovery', async () => {
    const ui = interfaceHarness();
    await ui.navigate();
    assert.equal(ui.fail('TypeError', 'sendComponentRequest').defaultPrevented, false);
    assert.equal(ui.fail('AbortError').defaultPrevented, false);
    assert.deepEqual(ui.visits, []);
});

test('a failed page fetch recovers even when Livewire catches the rejection', async () => {
    const ui = interfaceHarness(async () => { throw new TypeError('Failed to fetch'); });
    await ui.navigate();
    await ui.window.fetch('/farma/productos/principios-activos', { headers: { 'X-Livewire-Navigate': '1' } }).catch(() => {});
    assert.deepEqual(ui.visits, [['assign', 'http://localhost:8001/farma/productos/principios-activos']]);
    assert.equal(ui.fail().defaultPrevented, false);
});

test('a 200 response with an interrupted body recovers without replacing or consuming other response methods', async () => {
    const response = new Response('<html>Truncated', { status: 200 });
    response.text = async () => { throw new TypeError('Failed to fetch'); };
    const ui = interfaceHarness(async () => response);
    await ui.navigate({ history: true });
    const result = await ui.window.fetch('/farma/productos/principios-activos', { headers: new Headers({ 'X-Livewire-Navigate': '1' }) });
    assert.equal(result, response);
    assert.equal(result.status, 200);
    assert.equal(result.bodyUsed, false);
    await result.text().catch(() => {});
    assert.deepEqual(ui.visits, [['replace', 'http://localhost:8001/farma/productos/principios-activos']]);
});

test('an in-flight prefetch failure only recovers after its matching navigation is requested', async () => {
    let rejectFetch;
    const ui = interfaceHarness(() => new Promise((resolve, reject) => { rejectFetch = reject; }));
    const pending = ui.window.fetch('/farma/productos/principios-activos', { headers: { 'X-Livewire-Navigate': '1' } });
    assert.deepEqual(ui.visits, []);
    await ui.navigate();
    rejectFetch(new TypeError('Failed to fetch'));
    await pending.catch(() => {});
    assert.equal(ui.visits.length, 1);
});

test('unrequested, mismatched, cancelled and aborted page downloads do not navigate', async () => {
    for (const scenario of ['prefetch', 'mismatch', 'cancelled', 'aborted', 'abort-error']) {
        const controller = new AbortController();
        const ui = interfaceHarness(async () => { throw scenario === 'abort-error' ? new DOMException('Cancelled', 'AbortError') : new TypeError('Failed to fetch'); });
        if (scenario !== 'prefetch') await ui.navigate({}, scenario === 'cancelled');
        if (scenario === 'aborted') controller.abort();
        await ui.window.fetch(scenario === 'mismatch' ? '/other' : '/farma/productos/principios-activos', {
            headers: { 'X-Livewire-Navigate': '1' }, signal: controller.signal,
        }).catch(() => {});
        assert.deepEqual(ui.visits, [], scenario);
    }
});

test('API, component, external and non-GET failures remain outside recovery', async () => {
    for (const [url, options] of [
        ['/farma/productos/principios-activos', {}],
        ['/farma/productos/principios-activos', { method: 'POST', headers: { 'X-Livewire-Navigate': '1' } }],
        ['https://example.test/farma/productos/principios-activos', { headers: { 'X-Livewire-Navigate': '1' } }],
    ]) {
        const ui = interfaceHarness(async () => { throw new TypeError('Failed to fetch'); });
        await ui.navigate();
        await ui.window.fetch(url, options).catch(() => {});
        assert.deepEqual(ui.visits, []);
    }
});

test('successful page fetches keep their body and result intact', async () => {
    const ui = interfaceHarness();
    await ui.navigate();
    const response = await ui.window.fetch(new Request('http://localhost:8001/farma/productos/principios-activos', { headers: { 'X-Livewire-Navigate': '1' } }));
    assert.equal(await response.text(), '<html>Complete</html>');
    assert.deepEqual(ui.visits, []);
});
