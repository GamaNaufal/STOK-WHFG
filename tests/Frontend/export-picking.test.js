import test from 'node:test';
import assert from 'node:assert/strict';
import {initExportPicking} from '../../resources/js/export-stock/picking.mjs';

const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return {promise, resolve}; };
function setup(fetcher, value = '00000001') {
    const makeRows = () => new Map(['00000001', '00000002'].map(number => {
        const classes = new Set(), marker = {hidden: true};
        return [number, {classList: {add: name => classes.add(name), contains: name => classes.has(name)},
            marker, querySelector: () => marker}];
    }));
    const input = {value, focus() {this.focused = true;}, select() {this.selected = true;}}, button = {};
    const form = {action: '/pick/scan', isConnected: true, attributes: {}, querySelector: () => input, querySelectorAll: () => [input, button],
        setAttribute(k,v) {this.attributes[k]=v;}, removeAttribute(k) {delete this.attributes[k];}};
    const root = {html: 'OLD', rows: makeRows(),
        querySelector(selector) {return selector === '[name="box_number"]' ? input : this.rows.get(selector.match(/data-pick-box-number="([0-9]+)"/)?.[1]) ?? null;},
        set innerHTML(value) {this.html=value;this.rows=makeRows();form.isConnected=false;}, get innerHTML() {return this.html;}};
    const outcome = {hidden: true, classList: {toggle() {}}}, sounds = [];
    const doc = {getElementById: id => ({'export-pick-workbench': root, 'export-pick-feedback': outcome}[id]), addEventListener(k, callback) {this[k]=callback;}};
    initExportPicking({documentRef: doc, fetcher, feedback: {success: () => sounds.push('OK'), error: () => sounds.push('NG')},
        formData: () => Object.entries({_token: 'csrf', request_token: 'original-token', box_number: input.value})});
    const submit = () => doc.submit({target: {closest: () => form}, preventDefault() {}});
    return {input, button, form, root, outcome, sounds, submit};
}

test('human input 123 shows NG without sending a request or creating a blocking workflow', async () => {
    let calls = 0; const ui = setup(async () => {calls++;}, '123');
    await ui.submit();
    assert.equal(calls, 0); assert.deepEqual(ui.sounds, ['NG']);
    assert.equal(ui.root.innerHTML, 'OLD'); assert.equal(ui.input.selected, true);
    assert.match(ui.outcome.textContent, /tepat 8 angka/);
    assert.equal(ui.root.rows.get('00000001').classList.contains('table-success'), false);
});

test('helper NG leaves searching usable and does not replace the page with a blocked form', async () => {
    const ui = setup(async () => ({ok: true, json: async () => ({success: false, blocked: false, message: 'NG · coba lagi'})}));
    await ui.submit();
    assert.equal(ui.root.innerHTML, 'OLD'); assert.equal(ui.button.disabled, false);
    assert.deepEqual(ui.sounds, ['NG']); assert.equal(ui.outcome.hidden, false);
    assert.equal(ui.root.rows.get('00000001').marker.hidden, true);
});

test('successful helper scans highlight only matching rows and retain earlier scan markers', async () => {
    const ui = setup(async () => ({ok: true, json: async () => ({success: true, message: 'OK'})}));
    await ui.submit();
    assert.equal(ui.root.rows.get('00000001').classList.contains('table-success'), true);
    assert.equal(ui.root.rows.get('00000001').marker.hidden, false);
    assert.equal(ui.root.rows.get('00000002').classList.contains('table-success'), false);
    ui.input.value = '00000002'; await ui.submit();
    for (const row of ui.root.rows.values()) {
        assert.equal(row.classList.contains('table-success'), true);
        assert.equal(row.marker.hidden, false);
    }
    assert.deepEqual(ui.sounds, ['OK', 'OK']);
    assert.equal(ui.root.innerHTML, 'OLD');
});

test('NG after an OK keeps the previous scan marker and leaves the rejected row unchanged', async () => {
    let calls = 0;
    const ui = setup(async () => ({ok: true, json: async () => ({success: ++calls === 1, message: 'Result'})}));
    await ui.submit(); ui.input.value = '00000002'; await ui.submit();
    assert.equal(ui.root.rows.get('00000001').classList.contains('table-success'), true);
    assert.equal(ui.root.rows.get('00000002').classList.contains('table-success'), false);
    assert.equal(ui.root.rows.get('00000002').marker.hidden, true);
    assert.deepEqual(ui.sounds, ['OK', 'NG']);
});

test('successful mandatory scan highlights the newly rendered recommendation row', async () => {
    const ui = setup(async () => ({ok: true, json: async () => ({success: true, message: 'OK', html: 'VERIFIED'})}));
    const oldRow = ui.root.rows.get('00000001');
    await ui.submit();
    assert.notEqual(ui.root.rows.get('00000001'), oldRow);
    assert.equal(ui.root.rows.get('00000001').classList.contains('table-success'), true);
    assert.equal(ui.root.rows.get('00000001').marker.hidden, false);
    assert.equal(ui.root.rows.get('00000002').classList.contains('table-success'), false);
    assert.deepEqual(ui.sounds, ['OK']);
});

test('mandatory NG renders the persisted block instructions even with HTTP 423', async () => {
    const ui = setup(async () => ({ok: false, status: 423, json: async () => ({success: false, blocked: true, message: 'Kembalikan ke A1', html: 'WAITING ADMIN AND RETURN'})}));
    await ui.submit();
    assert.equal(ui.root.innerHTML, 'WAITING ADMIN AND RETURN');
    assert.equal(ui.outcome.textContent, 'Kembalikan ke A1'); assert.deepEqual(ui.sounds, ['NG']);
});

test('in-flight scans cannot be submitted twice and retain the complete eight-digit ID', async () => {
    const pending = deferred(), requests = [];
    const ui = setup((url, options) => {requests.push({url, options}); return pending.promise;}, '00000123');
    const first = ui.submit(); await ui.submit();
    assert.equal(requests.length, 1); assert.equal(ui.button.disabled, true);
    assert.equal(JSON.parse(requests[0].options.body).box_number, '00000123');
    pending.resolve({ok: true, json: async () => ({success: true, message: 'OK', html: 'VERIFIED'})}); await first;
    assert.equal(ui.root.innerHTML, 'VERIFIED'); assert.deepEqual(ui.sounds, ['OK']);
});

test('network failure preserves the idempotency token and typed box for retry', async () => {
    const requests = []; const ui = setup(async (url, options) => {requests.push(JSON.parse(options.body)); throw new Error('Offline');});
    await ui.submit(); await ui.submit();
    assert.equal(ui.root.innerHTML, 'OLD'); assert.equal(ui.input.value, '00000001'); assert.equal(ui.button.disabled, false);
    assert.equal(requests[0].request_token, requests[1].request_token);
    assert.equal(ui.root.rows.get('00000001').classList.contains('table-success'), false);
    assert.deepEqual(ui.sounds, ['NG', 'NG']);
});
