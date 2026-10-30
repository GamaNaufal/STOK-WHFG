import assert from 'node:assert/strict';
import test from 'node:test';
import {createStockLoader} from '../../resources/js/export-stock/stock-browser.mjs';

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
