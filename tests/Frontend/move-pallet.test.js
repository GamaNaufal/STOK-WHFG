import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/operator/move-pallet/index.blade.php', import.meta.url), 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace("{{ route('move-pallet.search') }}", '/move-pallet/search')
    .replace("{{ route('move-pallet.store') }}", '/move-pallet/store')
    .replace('{{ csrf_token() }}', 'test-token');
const flush = () => new Promise(setImmediate);

function setup({ decisions = [], locationFetch = null } = {}) {
    const elements = new Map();
    const requests = [];
    const dialogs = [];
    const timers = new Map();
    let timerId = 0;
    const makeElement = () => {
        const classes = new Set();
        return {
            value: '', textContent: '', innerHTML: '', className: '',
            dataset: {}, style: {}, disabled: false, children: [],
            classList: {
                add(name) { classes.add(name); },
                remove(name) { classes.delete(name); },
                toggle(name, force) { if (force) classes.add(name); else classes.delete(name); },
                contains(name) { return classes.has(name); },
            },
            addEventListener(name, callback) { this[`on_${name}`] = callback; },
            emit(name, event = {}) { return this[`on_${name}`]?.(event); },
            replaceChildren() { this.children = []; },
            appendChild(child) { this.children.push(child); },
            focus() {},
        };
    };
    const get = (id) => {
        if (!elements.has(id)) elements.set(id, makeElement());
        return elements.get(id);
    };
    const availableLocation = makeElement();
    availableLocation.dataset = { locationId: '2', locationCode: 'B-01', search: 'b-01' };
    const window = {
        Swal: { fire(options) {
            dialogs.push(options);
            return Promise.resolve({ isConfirmed: decisions.shift() ?? false });
        } },
        confirm() { throw new Error('Unexpected native confirmation'); },
    };
    vm.runInNewContext(script, {
        window,
        document: {
            getElementById: get,
            createElement: makeElement,
            querySelectorAll(selector) { return selector === '.available-location' ? [availableLocation] : []; },
        },
        fetch: async (url, options = {}) => {
            requests.push({ url, options });
            if (url.startsWith('/move-pallet/search')) return {
                ok: true, json: async () => ({ success: true, pallet: {
                    id: 7, pallet_number: 'PLT-<7>', location: 'A-01', location_id: 1,
                    total_box: 2, total_pcs: 100,
                } }),
            };
            if (url.startsWith('/api/locations/search')) return locationFetch
                ? locationFetch(url)
                : { ok: true, json: async () => [{ id: 2, code: 'B-01' }] };
            if (url === '/move-pallet/store') return { ok: true, json: async () => ({ success: true, message: 'Pallet berhasil dipindahkan.' }) };
            throw new Error(`Unexpected URL: ${url}`);
        },
        setTimeout(callback) { const id = ++timerId; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
    });
    return {
        get, requests, dialogs, availableLocation,
        runTimers() { const callbacks = [...timers.values()]; timers.clear(); return Promise.all(callbacks.map((callback) => callback())); },
    };
}

test('location search keeps the newest result and announces search state', async () => {
    let resolveOld;
    const app = setup({ locationFetch(url) {
        if (url.includes('q=A')) return new Promise((resolve) => { resolveOld = resolve; });
        return { ok: true, json: async () => [{ id: 2, code: 'B-01' }] };
    } });
    app.get('palletCode').value = 'PLT-<7>';
    await app.get('searchPalletButton').emit('click');
    assert.match(app.get('palletSearchStatus').textContent, /ditemukan/);

    const search = app.get('locationSearch');
    search.value = 'A';
    search.emit('input');
    const oldRequest = app.runTimers();
    await flush();
    search.value = 'B';
    search.emit('input');
    await app.runTimers();
    assert.equal(app.get('locationResults').children[0].textContent, 'B-01');
    resolveOld({ ok: true, json: async () => [{ id: 3, code: 'A-OLD' }] });
    await oldRequest;
    assert.equal(app.get('locationResults').children[0].textContent, 'B-01');
    assert.match(app.get('locationSearchStatus').textContent, /1 lokasi kosong ditemukan/);
});

test('location search failure leaves the list usable and reports the error', async () => {
    const app = setup({ locationFetch() { return { ok: false }; } });
    app.get('palletCode').value = 'PLT-<7>';
    await app.get('searchPalletButton').emit('click');
    app.get('locationSearch').value = 'B';
    app.get('locationSearch').emit('input');
    await app.runTimers();

    assert.match(app.get('locationSearchStatus').textContent, /Pencarian lokasi gagal/);
    assert.equal(app.get('locationResults').style.display, 'none');
    assert.equal(app.availableLocation.disabled, false);
});

test('location from API remains selected even when absent from the initial list', async () => {
    const app = setup({ locationFetch() {
        return { ok: true, json: async () => [{ id: 3, code: 'C-99' }] };
    } });
    app.get('palletCode').value = 'PLT-<7>';
    await app.get('searchPalletButton').emit('click');
    app.get('locationSearch').value = 'C';
    app.get('locationSearch').emit('input');
    await app.runTimers();
    const result = app.get('locationResults').children[0];
    app.get('locationResults').emit('click', { target: { closest() { return result; } } });

    assert.equal(app.get('targetLocationId').value, 3);
    assert.equal(app.get('targetLocationCode').textContent, 'C-99');
    assert.equal(app.get('noLocationMatch').classList.contains('d-none'), true);
    assert.equal(app.get('moveButton').disabled, false);
});

test('confirmation shows escaped route and only posts after approval', async () => {
    const app = setup({ decisions: [false, true] });
    app.get('palletCode').value = 'PLT-<7>';
    await app.get('searchPalletButton').emit('click');
    app.get('availableLocationList').emit('click', {
        target: { closest() { return app.availableLocation; } },
    });

    app.get('moveButton').emit('click');
    await flush();
    assert.match(app.dialogs[0].html, /PLT-&lt;7&gt;/);
    assert.doesNotMatch(app.dialogs[0].html, /PLT-<7>/);
    assert.match(app.dialogs[0].html, /A-01[\s\S]*B-01/);
    assert.match(app.dialogs[0].html, /2 Box[\s\S]*100 PCS/);
    assert.equal(app.requests.filter((request) => request.url === '/move-pallet/store').length, 0);

    app.get('moveButton').emit('click');
    await flush();
    const posts = app.requests.filter((request) => request.url === '/move-pallet/store');
    assert.equal(posts.length, 1);
    assert.equal(JSON.parse(posts[0].options.body).pallet_id, 7);
    assert.equal(JSON.parse(posts[0].options.body).location_id, 2);
});
