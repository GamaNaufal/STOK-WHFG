import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/operator/merge/index.blade.php', import.meta.url), 'utf8');
const script = blade.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace("{{ route('merge-pallet.search') }}", '/merge-pallet/search')
    .replace("{{ route(\"merge-pallet.store\") }}", '/merge-pallet/store')
    .replace('{{ csrf_token() }}', 'test-token');
const flush = () => new Promise(setImmediate);

function setup({ confirmed = false } = {}) {
    const elements = new Map();
    const requests = [];
    const dialogs = [];
    const timers = new Map();
    let timerId = 0;
    const makeElement = () => {
        const classes = new Set();
        return {
            value: '', textContent: '', innerHTML: '', hidden: false, disabled: false,
            children: [], style: { removeProperty() {}, setProperty() {} },
            classList: {
                add(name) { classes.add(name); },
                remove(name) { classes.delete(name); },
                contains(name) { return classes.has(name); },
            },
            addEventListener(name, callback) { this[`on_${name}`] = callback; },
            emit(name, event = {}) { return this[`on_${name}`]?.(event); },
            setAttribute(name, value) { this[name] = value; },
            replaceChildren(...children) { this.children = children; },
            appendChild(child) { this.children.push(child); },
            append(...children) { this.children.push(...children); },
            querySelector(selector) { return selector === 'button' ? this.children.find(child => child.type === 'button') : null; },
            contains(target) { return this === target || this.children.includes(target); },
            focus() { this.emit('focus'); },
        };
    };
    const get = (id) => {
        if (!elements.has(id)) elements.set(id, makeElement());
        return elements.get(id);
    };
    get('locationSearchResults').hidden = true;
    get('btnProcess').disabled = true;
    const context = vm.createContext({
        document: {
            getElementById: get,
            createElement: makeElement,
            querySelectorAll() { return []; },
            addEventListener() {},
        },
        window: { location: { reload() {} } },
        Swal: { fire(options) { dialogs.push(options); return Promise.resolve({ isConfirmed: confirmed }); } },
        fetch: async (url, options = {}) => {
            requests.push({ url, options });
            if (url.startsWith('/api/locations/search')) return { ok: true, json: async () => [{ id: 30, code: 'EMPTY-30' }] };
            if (url === '/merge-pallet/store') return { ok: true, json: async () => ({ success: false, message: 'Conflict' }) };
            throw new Error(`Unexpected URL: ${url}`);
        },
        showToast() {},
        setTimeout(callback) { const id = ++timerId; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
    });
    vm.runInContext(script, context);
    return {
        get, context, dialogs, requests,
        runTimers() { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); },
    };
}

const pallet = (id, number, location, locationId) => ({
    id, pallet_number: number, location, location_id: locationId, total_box: 2, total_pcs: 100,
});

test('source location selection enables review and confirmation previews the exact merge payload', async () => {
    const app = setup({ confirmed: true });
    app.context.addFromList(pallet(1, 'PLT-<1>', 'A-01', 10));
    app.context.addFromList(pallet(2, 'PLT-002', 'B-01', 20));
    assert.equal(app.get('btnProcess').disabled, true);

    app.get('locationSearchInput').emit('focus');
    await flush();
    const sourceChoice = app.get('locationSearchResults').children[0];
    assert.equal(sourceChoice.children[0].textContent, 'A-01');
    sourceChoice.emit('click');
    assert.equal(app.get('selectedLocationCode').value, 'A-01');
    assert.equal(app.get('btnProcess').disabled, false);

    app.context.processMerge();
    await flush();
    assert.match(app.dialogs[0].html, /PLT-&lt;1&gt;/);
    assert.match(app.dialogs[0].html, /4 Box/);
    const payload = JSON.parse(app.requests.find(request => request.url === '/merge-pallet/store').options.body);
    assert.deepEqual(Array.from(payload.pallet_ids), [1, 2]);
    assert.equal(payload.location_id, 10);
    assert.equal(payload.warehouse_location, 'A-01');
});

test('typing after selection or removing its source prevents merge until a valid location is chosen again', async () => {
    const app = setup();
    app.context.addFromList(pallet(1, 'PLT-001', 'A-01', 10));
    app.context.addFromList(pallet(2, 'PLT-002', 'B-01', 20));
    app.get('locationSearchInput').emit('focus');
    await flush();
    app.get('locationSearchResults').children[0].emit('click');

    const input = app.get('locationSearchInput');
    input.value = 'UNLISTED';
    input.emit('input', { target: input });
    assert.equal(app.get('selectedLocationCode').value, '');
    assert.equal(app.get('btnProcess').disabled, true);

    app.runTimers();
    await flush();
    input.value = 'A-01';
    input.emit('input', { target: input });
    app.runTimers();
    await flush();
    app.get('locationSearchResults').children[0].emit('click');
    assert.equal(app.get('btnProcess').disabled, false);
    app.context.removeFromList(0);
    assert.equal(app.get('selectedLocationCode').value, '');
    assert.equal(app.get('btnProcess').disabled, true);
});

test('available master location can be selected without changing the merge request shape', async () => {
    const app = setup({ confirmed: true });
    app.context.addFromList(pallet(1, 'PLT-001', 'A-01', 10));
    app.context.addFromList(pallet(2, 'PLT-002', 'B-01', 20));
    app.get('locationSearchInput').emit('focus');
    await flush();
    const availableChoice = app.get('locationSearchResults').children.find(child => child.children[0]?.textContent === 'EMPTY-30');
    availableChoice.emit('click');

    assert.equal(app.get('locationSelectionStatus').textContent, 'EMPTY-30 dipilih — lokasi kosong.');
    app.context.processMerge();
    await flush();
    const payload = JSON.parse(app.requests.find(request => request.url === '/merge-pallet/store').options.body);
    assert.equal(payload.location_id, 30);
    assert.equal(payload.warehouse_location, 'EMPTY-30');
    assert.match(app.dialogs[0].html, /Lokasi kosong/);
});
