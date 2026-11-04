import assert from 'node:assert/strict';
import test from 'node:test';
import {createStockLoader, initStockBrowser} from '../../resources/js/export-stock/stock-browser.mjs';

const reply = (html, count = 1) => ({ok: true, json: async () => ({html, count})});
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return {promise, resolve}; };

test('only the latest filter response renders even if abort is ignored', async () => {
    const first = deferred(), second = deferred(), requests = [], rendered = [], states = [];
    const loader = createStockLoader({fetcher: (url, options) => { requests.push({url, options}); return requests.length === 1 ? first.promise : second.promise; },
        render: (data, url) => rendered.push({data, url}), state: value => states.push(value)});
    const a = loader.load('/stock?q=old'), b = loader.load('/stock?q=new');
    assert.equal(requests[0].options.signal.aborted, true);
    assert.equal(requests[1].options.headers.Accept, 'application/json');
    second.resolve(reply('NEW', 20)); await b;
    first.resolve(reply('OLD')); await a;
    assert.deepEqual(rendered, [{data: {html: 'NEW', count: 20}, url: '/stock?q=new'}]);
    assert.deepEqual(states.at(-1), {busy: false});
});

test('cancelling during the search debounce prevents an earlier response rendering', async () => {
    const pending = deferred(), rendered = [], states = [];
    const loader = createStockLoader({fetcher: () => pending.promise, render: value => rendered.push(value), state: value => states.push(value)});
    const loading = loader.load('/stock'); loader.cancel();
    pending.resolve(reply('STALE')); await loading;
    assert.deepEqual(rendered, []);
    assert.deepEqual(states, [{busy: true}]);
});

test('network errors preserve old results and a retry can recover', async () => {
    let calls = 0; const rendered = [], states = [];
    const loader = createStockLoader({fetcher: async () => { if (++calls === 1) throw new Error('Offline'); return reply('RECOVERED'); },
        render: value => rendered.push(value), state: value => states.push(value)});
    assert.equal(await loader.load('/stock'), false);
    assert.deepEqual(rendered, []); assert.equal(states.at(-1).error, 'Offline');
    assert.equal(await loader.load('/stock'), true);
    assert.equal(rendered[0].html, 'RECOVERED'); assert.equal(states.at(-1).busy, false);
});

test('invalid filters and expired sessions show errors without replacing stock with a login page', async () => {
    const states = [], rendered = [];
    const invalid = createStockLoader({fetcher: async () => ({ok: false, status: 422, json: async () => ({errors: {mode: ['Mode tidak valid.']}})}),
        render: value => rendered.push(value), state: value => states.push(value)});
    await invalid.load('/stock'); assert.equal(states.at(-1).error, 'Mode tidak valid.');
    const session = createStockLoader({fetcher: async () => ({ok: true, json: async () => ({message: 'Login'})}),
        render: value => rendered.push(value), state: value => states.push(value)});
    await session.load('/stock'); assert.match(states.at(-1).error, /Respons tidak dapat dibaca/);
    assert.deepEqual(rendered, []);
});

test('a stale failed request does not overwrite the success state of newer filters', async () => {
    const stale = deferred(), rendered = [], states = [];
    const loader = createStockLoader({fetcher: url => url.includes('old') ? stale.promise : Promise.resolve(reply('CURRENT')),
        render: value => rendered.push(value), state: value => states.push(value)});
    const old = loader.load('/old'); await loader.load('/new');
    stale.resolve({ok: false, status: 403}); await old;
    assert.equal(rendered[0].html, 'CURRENT'); assert.deepEqual(states.at(-1), {busy: false});
});

test('unified search and radio grouping apply filters and restore them through reset and browser history', async t => {
    const fields = Object.fromEntries(['q', 'part_number', 'status', 'location_id', 'mode'].map(name => [name, {value: name === 'mode' ? 'box' : ''}]));
    const formEvents = {}, windowEvents = {}, requests = [];
    const partLabel = {textContent: ''};
    const partChip = {hidden: true, querySelector: () => partLabel};
    let scheduled, resetFilter;
    const form = {
        action: 'http://localhost/export/stock',
        elements: {namedItem: name => fields[name]},
        querySelector: () => partChip,
        addEventListener: (name, handler) => { formEvents[name] = handler; },
        reset: () => {},
    };
    const elements = {
        'export-stock-filters': form,
        'export-stock-results': {setAttribute() {}, addEventListener() {}, innerHTML: ''},
        'stock-request-status': {textContent: ''},
        'stock-request-retry': {addEventListener() {}, hidden: true},
        'stock-result-count': {textContent: '0'},
    };
    const windowRef = {
        location: {href: form.action},
        history: {replaceState: (_state, _title, url) => { windowRef.location.href = url; }},
        setTimeout: callback => { scheduled = callback; return 1; },
        clearTimeout: () => { scheduled = null; },
        addEventListener: (name, handler) => { windowEvents[name] = handler; },
    };
    t.mock.method(globalThis, 'FormData', function () {
        return Object.entries(fields).map(([name, input]) => [name, input.value]);
    });
    initStockBrowser({
        documentRef: {getElementById: id => elements[id], querySelector: () => ({addEventListener: (_name, handler) => { resetFilter = handler; }})},
        windowRef,
        fetcher: async url => { requests.push(new URL(url)); return reply('STOCK', 2); },
    });
    const flush = () => new Promise(resolve => setImmediate(resolve));

    fields.q.value = '7105-3578';
    formEvents.input({target: {name: 'q'}});
    assert.equal(requests.length, 0);
    await scheduled();
    assert.equal(requests.at(-1).searchParams.get('q'), '7105-3578');
    assert.equal(elements['stock-result-count'].textContent, '2');

    fields.mode.value = 'pallet';
    formEvents.change({target: {name: 'mode', matches: () => false}});
    await flush();
    assert.equal(requests.at(-1).searchParams.get('mode'), 'pallet');
    assert.equal(requests.at(-1).searchParams.get('q'), '7105-3578');

    windowRef.location.href = form.action + '?q=12000001&part_number=7105&mode=box&status=reserved';
    windowEvents.popstate();
    await flush();
    assert.equal(fields.q.value, '12000001');
    assert.equal(fields.mode.value, 'box');
    assert.equal(fields.status.value, 'spare');
    assert.equal(partChip.hidden, false);
    assert.equal(partLabel.textContent, '7105');
    assert.equal(windowRef.location.href, form.action + '?q=12000001&part_number=7105&mode=box&status=reserved');

    formEvents.click({target: {closest: () => ({})}});
    await flush();
    assert.equal(requests.at(-1).searchParams.has('part_number'), false);
    assert.equal(partChip.hidden, true);

    resetFilter({preventDefault() {}});
    await flush();
    assert.equal(fields.q.value, '');
    assert.equal(fields.mode.value, 'box');
    assert.equal(fields.status.value, '');
    assert.equal(requests.at(-1).searchParams.has('q'), false);
});
