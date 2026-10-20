import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/delivery-assign.js', import.meta.url), 'utf8');
const flush = () => new Promise(setImmediate);

function setup(storage, decisions = [], previewBoxes = [], assignResponses = []) {
    const elements = new Map();
    const requests = [];
    const dialogs = [];
    const get = (id) => {
        if (!elements.has(id)) {
            elements.set(id, {
                value: '', checked: false, disabled: false, readOnly: false,
                textContent: '', innerHTML: '', style: {}, dataset: {}, children: [],
                addEventListener(name, callback) { this[name] = callback; },
                appendChild(child) { this.children.push(child); },
                querySelector(selector) {
                    if (!this.controls) this.controls = new Map();
                    if (!this.controls.has(selector)) this.controls.set(selector, get(`${id}:${selector}`));
                    return this.controls.get(selector);
                },
                setAttribute(name, value) { this[name] = value; },
                contains() { return false; },
                focus() {},
            });
        }
        return elements.get(id);
    };
    let created = 0;
    const select = get('deliveryOrderSelect');
    select.value = '1';
    select.options = [{ value: '' }, { value: '1' }];
    select.selectedOptions = [{ textContent: '#1 - Customer' }];
    get('deliveryAssignPalletModeNew').checked = true;

    const window = {
        deliveryAssignConfig: {
            userId: 7, csrfToken: 'test', previewUrl: '/preview',
            assignUrl: '/assign', searchUrl: '/search',
            deliveryOrderPartsUrl: '/orders/__ORDER__/parts',
        },
        sessionStorage: {
            getItem(key) { return storage.get(key) ?? null; },
            setItem(key, value) { storage.set(key, value); },
            removeItem(key) { storage.delete(key); },
        },
        Swal: {
            fire(options) {
                dialogs.push(options);
                return Promise.resolve({ isConfirmed: decisions.shift() ?? false });
            },
        },
        confirm() { throw new Error('Unexpected native confirmation'); },
        clearTimeout() {}, setTimeout() {},
    };

    vm.runInNewContext(source, {
        window,
        document: {
            getElementById: get,
            querySelector() { return null; },
            addEventListener() {},
            createElement() { return get(`created-${++created}`); },
        },
        fetch: async (url, options = {}) => {
            requests.push({ url, options });
            if (url.includes('/parts')) return { ok: true, json: async () => ({ parts: [{ part_number: 'P-1', requested_quantity: 100, remaining_quantity: 100, qty_box: 50 }] }) };
            if (url === '/preview') return { ok: true, json: async () => ({ boxes: previewBoxes, missing_box_ids: [], pallet_box_count: previewBoxes.length }) };
            if (url === '/assign') return assignResponses.shift() ?? { ok: true, json: async () => ({ assigned_existing_count: 0, created_new_count: 1, skipped_count: 0 }) };
            return { ok: true, json: async () => ({ pallets: [], boxes: [] }) };
        },
    });

    return { get, requests, dialogs };
}

test('new box draft survives reload and cancelled removal/reset', async () => {
    const storage = new Map();
    const first = setup(storage);
    await flush();
    first.get('deliveryAssignLocationId').value = '5';
    first.get('deliveryAssignLocationCode').value = 'A1';
    first.get('deliveryAssignNewBoxNumber').value = '12345678';
    first.get('deliveryAssignNewBoxPart').value = 'P-1';
    first.get('deliveryAssignNewBoxQty').value = '50';
    first.get('deliveryAssignAddNewBox').click();

    const key = 'delivery-assign-draft:v1:7';
    assert.equal(JSON.parse(storage.get(key)).scannedBoxes[0].box_number, '12345678');

    const second = setup(storage, [false, true, false, true]);
    await flush();
    await flush();
    assert.match(second.get('deliveryAssignSelectedList').children[0].innerHTML, /12345678/);
    assert.equal(second.get('deliveryAssignLocationId').value, '5');

    const remove = second.get('deliveryAssignSelectedList').children[0].querySelector('[data-action="remove"]');
    remove.click();
    await flush();
    assert.equal(JSON.parse(storage.get(key)).scannedBoxes.length, 1);

    remove.click();
    await flush();
    assert.equal(JSON.parse(storage.get(key)).scannedBoxes.length, 0);

    second.get('deliveryAssignNewBoxNumber').value = '12345678';
    second.get('deliveryAssignNewBoxPart').value = 'P-1';
    second.get('deliveryAssignNewBoxQty').value = '50';
    second.get('deliveryAssignAddNewBox').click();
    assert.equal(JSON.parse(storage.get(key)).scannedBoxes.length, 1);

    second.get('deliveryAssignClearBtn').click();
    await flush();
    assert.equal(JSON.parse(storage.get(key)).scannedBoxes.length, 1);

    second.get('deliveryAssignClearBtn').click();
    await flush();
    assert.equal(JSON.parse(storage.get(key)).scannedBoxes.length, 0);
});

test('assignment preview lists involved boxes and cancelling does not submit', async () => {
    const storage = new Map();
    storage.set('delivery-assign-draft:v1:7', JSON.stringify({
        orderId: '1', palletMode: 'new', locationId: '5', locationCode: 'A1',
        selectedPallets: [], selectedBoxes: [],
        scannedBoxes: [{ box_number: '12345678', part_number: 'P-1', pcs_quantity: 50 }],
        selectionOrder: [{ type: 'new_box', box_number: '12345678' }],
    }));
    const app = setup(storage, [false]);
    await flush();
    await flush();
    await app.get('deliveryAssignSubmit').click();

    assert.equal(app.requests.some((request) => request.url === '/preview'), true);
    assert.equal(app.requests.some((request) => request.url === '/assign'), false);
    assert.match(app.dialogs[0].html, /12345678/);
    assert.match(app.dialogs[0].html, /pallet bernomor otomatis/);
});

test('assignment preview includes every expanded stock box from a selected pallet', async () => {
    const storage = new Map();
    storage.set('delivery-assign-draft:v1:7', JSON.stringify({
        orderId: '1', palletMode: 'new',
        selectedPallets: [{ id: 9, pallet_number: 'PLT-009', location: 'A1' }],
        selectedBoxes: [], scannedBoxes: [],
        selectionOrder: [{ type: 'pallet', id: 9 }],
    }));
    const app = setup(storage, [false], [
        { id: 31, box_number: '30000001', part_number: 'P-1', pcs_quantity: 50 },
        { id: 32, box_number: '30000002', part_number: 'P-1', pcs_quantity: 50 },
    ]);
    await flush();
    await flush();
    await app.get('deliveryAssignSubmit').click();

    assert.match(app.dialogs[0].html, /30000001/);
    assert.match(app.dialogs[0].html, /30000002/);
    assert.match(app.dialogs[0].html, /PLT-009/);
    assert.equal(app.requests.some((request) => request.url === '/assign'), false);
});

test('overage dialog explains per-part order change and only retries after confirmation', async () => {
    const draft = {
        orderId: '1', palletMode: 'new', locationId: '5', locationCode: 'A1',
        selectedPallets: [], selectedBoxes: [],
        scannedBoxes: [{ box_number: '12345678', part_number: 'P-1', pcs_quantity: 60 }],
        selectionOrder: [{ type: 'new_box', box_number: '12345678' }],
    };
    const conflict = () => ({ ok: false, status: 409, json: async () => ({
        requires_overage_confirmation: true,
        part_overages: [{
            part_number: 'P-1 <img>', requested_quantity: 100,
            assigned_quantity: 50, remaining_quantity: 50,
            selected_quantity: 60, overage_quantity: 10,
            new_requested_quantity: 110,
        }],
    }) });

    for (const [confirmOverage, expectedRequests] of [[false, 1], [true, 2]]) {
        const storage = new Map([['delivery-assign-draft:v1:7', JSON.stringify(draft)]]);
        const app = setup(storage, [true, confirmOverage], [], [conflict()]);
        await flush();
        await flush();
        await app.get('deliveryAssignSubmit').click();
        for (let i = 0; i < 5; i++) await flush();

        const overage = app.dialogs[1];
        assert.equal(overage.title, 'Pilihan melebihi sisa order');
        assert.match(overage.html, /P-1 &lt;img&gt;/);
        assert.doesNotMatch(overage.html, /<img>/);
        assert.match(overage.html, /Sisa 50 PCS/);
        assert.match(overage.html, /Kelebihan 10 PCS/);
        assert.match(overage.html, /Dipilih 60 PCS/);
        assert.match(overage.html, /Request order:[\s\S]*100[\s\S]*110 PCS/);
        assert.match(overage.html, /quantity request order akan naik/);
        assert.match(overage.html, /Stok belum ditarik/);
        assert.equal(overage.confirmButtonText, 'Tambah request & assign');
        const assignments = app.requests.filter((request) => request.url === '/assign');
        assert.equal(assignments.length, expectedRequests);
        assert.equal(JSON.parse(assignments[0].options.body).confirm_overage, undefined);
        if (confirmOverage) {
            assert.equal(JSON.parse(assignments[1].options.body).confirm_overage, true);
        } else {
            assert.equal(JSON.parse(storage.get('delivery-assign-draft:v1:7')).scannedBoxes.length, 1);
        }
    }
});
