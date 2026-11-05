import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/js/expired-box-receiving.js', import.meta.url), 'utf8');
const flush = () => new Promise(setImmediate);
const key = name => name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());

class Element {
    constructor() { this.children = []; this.dataset = {}; this.events = {}; this.value = ''; this.textContent = ''; this.disabled = false; this.hidden = false; this.focuses = 0; }
    get isConnected() { return this.root || !!this.parent?.isConnected; }
    append(...children) { children.forEach(child => { child.parent = this; this.children.push(child); }); }
    remove() { this.parent.children = this.parent.children.filter(child => child !== this); this.parent = null; }
    setAttribute() {}
    addEventListener(name, callback) { this.events[name] = callback; }
    emit(name, extras = {}) { const event = {prevented: false, preventDefault() { this.prevented = true; }, ...extras}; this.events[name]?.(event); return event; }
    focus() { this.focuses++; }
    select() { this.focus(); }
    querySelectorAll(selector) {
        const attribute = selector.slice(1, -1);
        return this.children.flatMap(child => [...(Object.hasOwn(child.dataset, key(attribute)) ? [child] : []), ...child.querySelectorAll(selector)]);
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

function setup({remaining = 3, fetcher = null} = {}) {
    const form = new Element(); form.root = true; form.dataset.packingReceipt = ''; form.dataset.scanUrl = '/packing/scan'; form.elements = {_token: {value: 'csrf'}};
    const controls = {};
    for (const name of ['packing-scan', 'receipt-group', 'packing-box-list', 'packing-feedback', 'receipt-count', 'save-receipt', 'add-packing-box']) {
        const element = new Element(); element.dataset[key(`data-${name}`)] = ''; form.append(element); controls[name] = element;
    }
    controls['receipt-group'].value = 'group-a';
    controls['receipt-group'].selectedOptions = [{dataset: {remaining}}];
    const requests = [], sounds = [], events = {};
    const window = {addEventListener(name, callback) { events[name] = callback; }, ScanFeedback: {success() { sounds.push('ok'); }, error() { sounds.push('ng'); }}};
    const context = vm.createContext({window, AbortController, setTimeout, clearTimeout, document: {querySelector: () => form, createElement: () => new Element()}, fetch: async (url, options) => {
        const data = JSON.parse(options.body); requests.push(data);
        return fetcher ? fetcher(data, requests.length) : {ok: true, json: async () => ({outcome: 'ok', box_number: data.box_number})};
    }});
    vm.runInContext(script, context);
    const scan = number => { controls['packing-scan'].value = number; return controls['packing-scan'].emit('keydown', {key: 'Enter'}); };
    return {form, controls, requests, sounds, events, scan, rows: () => controls['packing-box-list'].children};
}

test('rapid scanner input is validated sequentially and saving waits for all results', async () => {
    let release;
    const waiting = new Promise(resolve => { release = resolve; });
    const ui = setup({fetcher: async (data, index) => {
        if (index === 1) await waiting;
        return {ok: true, json: async () => ({outcome: 'ok', box_number: data.box_number})};
    }});
    assert.equal(ui.scan('00123456').prevented, true);
    ui.scan('00123457');
    await flush();
    assert.equal(ui.requests.length, 1);
    assert.equal(ui.rows().length, 2);
    assert.equal(ui.controls['save-receipt'].disabled, true);
    assert.equal(ui.controls['packing-scan'].value, '');
    release(); await flush(); await flush();
    assert.deepEqual(ui.requests.map(row => row.box_number), ['00123456', '00123457']);
    assert.deepEqual(ui.rows().map(row => row.dataset.state), ['ok', 'ok']);
    assert.equal(ui.controls['save-receipt'].disabled, false);
    assert.deepEqual(ui.sounds, ['ok', 'ok']);
});

test('malformed, repeated and over-capacity scans do not enter the batch', async () => {
    const ui = setup({remaining: 1});
    ui.scan('123'); await flush(); assert.equal(ui.requests.length, 0);
    ui.scan('00123456'); await flush();
    ui.scan('00123456'); ui.scan('00123457'); await flush();
    assert.equal(ui.rows().length, 1);
    assert.equal(ui.requests.length, 1);
    assert.deepEqual(ui.sounds, ['ng', 'ok', 'ng', 'ng']);
});

test('NG remains visible and blocks save until retry succeeds or the row is removed', async () => {
    let failing = true;
    const ui = setup({fetcher: async data => failing
        ? {ok: false, json: async () => ({errors: {box_number: ['ID Box sudah pernah digunakan.']}})}
        : {ok: true, json: async () => ({outcome: 'ok', box_number: data.box_number})}});
    ui.scan('00123456'); await flush();
    const row = ui.rows()[0];
    assert.equal(row.dataset.state, 'ng');
    assert.match(row.querySelector('[data-box-state]').textContent, /sudah pernah/);
    assert.equal(ui.controls['save-receipt'].disabled, true);
    failing = false; row.querySelector('[data-retry-box]').emit('click'); await flush();
    assert.equal(row.dataset.state, 'ok');
    assert.equal(ui.controls['save-receipt'].disabled, false);
    row.querySelector('[data-remove-box]').emit('click');
    assert.equal(ui.rows().length, 0);
    assert.equal(ui.controls['save-receipt'].disabled, true);
});

test('HTML login responses and mismatched barcode responses cannot be accepted as OK', async () => {
    for (const fetcher of [async () => ({ok: true, json: async () => { throw new SyntaxError('HTML'); }}),
        async () => ({ok: true, json: async () => ({outcome: 'ok', box_number: 'WRONG'})})]) {
        const ui = setup({fetcher}); ui.scan('00123456'); await flush();
        assert.equal(ui.rows()[0].dataset.state, 'ng');
        assert.equal(ui.controls['save-receipt'].disabled, true);
    }
});

test('changing groups cannot relabel an existing scanned batch', async () => {
    const ui = setup(); ui.scan('00123456'); await flush();
    const group = ui.controls['receipt-group'];
    group.value = 'group-b'; group.emit('change');
    assert.equal(group.value, 'group-a');
    ui.rows()[0].querySelector('[data-remove-box]').emit('click');
    group.value = 'group-b'; group.emit('change');
    assert.equal(group.value, 'group-b');
});

test('unsubmitted input and pending checks prevent submission, and unsaved scans warn before leaving', async () => {
    const ui = setup();
    assert.equal(ui.form.emit('submit').prevented, true);
    ui.scan('00123456'); await flush();
    const leave = {prevented: false, preventDefault() { this.prevented = true; }};
    ui.events.beforeunload(leave); assert.equal(leave.prevented, true);
    ui.controls['packing-scan'].value = '00123457';
    assert.equal(ui.form.emit('submit').prevented, true);
    ui.controls['packing-scan'].value = '';
    assert.equal(ui.form.emit('submit').prevented, false);
    assert.equal(ui.form.emit('submit').prevented, true);
    const afterSubmit = {preventDefault() { throw new Error('A saved form must be allowed to leave.'); }};
    ui.events.beforeunload(afterSubmit);
    ui.events.pageshow({persisted: true});
    assert.equal(ui.controls['save-receipt'].disabled, false);
});
