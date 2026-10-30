import assert from 'node:assert/strict';
import test from 'node:test';
import {initBoxModal} from '../../resources/js/export-stock/box-modal.mjs';

function element(extra = {}) {
    return {events: {}, dataset: {}, attributes: {}, children: [], innerHTML: '', isConnected: true,
        addEventListener(name, listener) { this.events[name] = listener; },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        replaceChildren(...children) { this.children = children; this.innerHTML = ''; },
        append(...children) { this.children.push(...children); },
        contains(target) { return !!target.inModal; },
        focus() { this.focused = true; }, ...extra};
}
function setup(fetcher, initial = false) {
    const root = element({dataset: {openOnLoad: String(initial)}}), content = element(), search = element(), title = element(), backButton = element({hidden: true});
    const documentRef = element({getElementById(id) { return {'export-stock-box-modal': root, 'export-stock-box-content': content, 'receiving-stock-search': search, 'export-stock-box-title': title, 'export-stock-modal-back': backButton}[id]; }, createElement() { return element(); }});
    const modal = {shows: 0, show() { this.shows++; }};
    const api = initBoxModal({documentRef, fetcher, Modal: {getOrCreateInstance() { return modal; }}});
    return {root, content, search, title, backButton, documentRef, modal, ...api};
}
const reply = html => ({ok: true, json: async () => ({html})});
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return {promise, resolve}; };

test('view box fetches only its detail in a dialog and returns focus on close', async () => {
    const requests = [], ui = setup(async (url, options) => { requests.push({url, options}); return reply('BOX-1'); });
    const trigger = element({dataset: {exportStockBox: '/export/stock/1'}});
    let prevented = false;
    await ui.documentRef.events.click({target: {closest: () => trigger}, preventDefault() { prevented = true; }});
    assert.equal(prevented, true); assert.equal(ui.modal.shows, 1);
    assert.equal(requests[0].url, '/export/stock/1'); assert.equal(requests[0].options.headers.Accept, 'application/json');
    assert.equal(ui.content.innerHTML, 'BOX-1'); assert.equal(ui.content.attributes['aria-busy'], undefined);
    ui.root.events['hidden.bs.modal']();
    assert.equal(trigger.focused, true); assert.equal(ui.content.innerHTML, '');
});

test('a late response cannot replace the currently requested box even if abort is ignored', async () => {
    const first = deferred(), second = deferred(), requests = [];
    const ui = setup((url, options) => { requests.push(options); return requests.length === 1 ? first.promise : second.promise; });
    const a = ui.open('/export/stock/1', element()), b = ui.open('/export/stock/2', element());
    assert.equal(requests[0].signal.aborted, true);
    second.resolve(reply('BOX-2')); await b; first.resolve(reply('BOX-1')); await a;
    assert.equal(ui.content.innerHTML, 'BOX-2');
});

test('closing a loading dialog prevents its response from filling a hidden or newer dialog', async () => {
    const pending = deferred(); const ui = setup(() => pending.promise);
    const loading = ui.open('/export/stock/1', element());
    ui.root.events['hidden.bs.modal'](); pending.resolve(reply('STALE')); await loading;
    assert.equal(ui.content.innerHTML, ''); assert.deepEqual(ui.content.children, []);
});

test('a network failure keeps the list usable and offers a retry for the same box', async () => {
    let calls = 0;
    const ui = setup(async () => { if (++calls === 1) throw new Error('Koneksi bermasalah'); return reply('RECOVERED'); });
    await ui.open('/export/stock/1', element());
    const alert = ui.content.children[0]; assert.equal(alert.attributes.role, 'alert');
    assert.equal(alert.children[0].textContent, 'Koneksi bermasalah');
    await alert.children[1].events.click(); assert.equal(ui.content.innerHTML, 'RECOVERED');
});

test('permission failures show an error, and direct box links open their server-rendered dialog without a fetch', async () => {
    const ui = setup(async () => ({ok: false, status: 403}));
    await ui.open('/export/stock/1', element());
    assert.match(ui.content.children[0].children[0].textContent, /tidak memiliki akses/);
    let requests = 0; const initial = setup(async () => { requests++; }, true);
    assert.equal(initial.modal.shows, 1); assert.equal(requests, 0);
});

test('pallet list action loads boxes in one dialog and allows returning from a box detail', async () => {
    const requests = [], ui = setup(async url => { requests.push(url); return reply(url); });
    const pallet = element({dataset: {exportStockPallet: '/stock/pallets/1/boxes?q=PART'}});
    await ui.documentRef.events.click({target: {closest: selector => selector === '[data-export-stock-pallet]' ? pallet : null}, preventDefault() {}});
    assert.equal(ui.title.textContent, 'Detail pallet ekspor');
    assert.equal(ui.content.innerHTML, '/stock/pallets/1/boxes?q=PART');
    assert.equal(ui.backButton.hidden, true);
    const box = element({inModal: true, dataset: {exportStockBox: '/stock/9'}});
    await ui.documentRef.events.click({target: {closest: selector => selector === '[data-export-stock-box]' ? box : null}, preventDefault() {}});
    assert.equal(ui.title.textContent, 'Detail box ekspor');
    assert.equal(ui.backButton.hidden, false);
    await ui.backButton.events.click();
    assert.equal(ui.title.textContent, 'Detail pallet ekspor');
    assert.equal(ui.backButton.hidden, true);
    assert.deepEqual(requests, ['/stock/pallets/1/boxes?q=PART', '/stock/9', '/stock/pallets/1/boxes?q=PART']);
    ui.root.events['hidden.bs.modal']();
    assert.equal(pallet.focused, true);
});

test('pallet pagination stays in the dialog and back from box detail returns to the same box page', async () => {
    const requests = [], ui = setup(async url => { requests.push(url); return reply(url); });
    const trigger = element();
    await ui.open('/stock/pallets/1/boxes', trigger, {kind: 'pallet'});
    const link = element({inModal: true, href: '/stock/pallets/1/boxes?box_page=2', closest: selector => selector === '.pagination' ? {} : null});
    await ui.documentRef.events.click({target: {closest: selector => selector === 'a[href]' ? link : null}, preventDefault() {}});
    assert.equal(ui.content.innerHTML, '/stock/pallets/1/boxes?box_page=2');
    const box = element({inModal: true, dataset: {exportStockBox: '/stock/99'}});
    await ui.documentRef.events.click({target: {closest: selector => selector === '[data-export-stock-box]' ? box : null}, preventDefault() {}});
    await ui.backButton.events.click();
    assert.equal(requests.at(-1), '/stock/pallets/1/boxes?box_page=2');
    assert.equal(ui.title.textContent, 'Detail pallet ekspor');
});

test('closing a pending pallet popup cancels rendering and returns focus to the list action', async () => {
    const pending = deferred(), ui = setup(() => pending.promise), trigger = element();
    const loading = ui.open('/stock/pallets/1/boxes', trigger, {kind: 'pallet'});
    assert.equal(ui.content.children[0].textContent, 'Memuat isi pallet...');
    ui.root.events['hidden.bs.modal']();
    pending.resolve(reply('STALE PALLET')); await loading;
    assert.equal(ui.content.innerHTML, '');
    assert.equal(trigger.focused, true);
    assert.equal(ui.backButton.hidden, true);
});
