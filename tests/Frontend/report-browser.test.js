import assert from 'node:assert/strict';
import test from 'node:test';
import {createReportLoader, reportFormUrl} from '../../public/js/report-browser.js';

const reply = (html, url) => ({ok: true, json: async () => ({html, url, title: 'Laporan'})});
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return {promise, resolve}; };

test('switching scope cannot render an older domestic response after the export report', async () => {
    const first = deferred(), second = deferred(), calls = [], rendered = [], states = [];
    const loader = createReportLoader({fetcher: (url, options) => { calls.push({url, options}); return calls.length === 1 ? first.promise : second.promise; },
        render: (...args) => rendered.push(args), state: value => states.push(value)});
    const a = loader.load('/reports?scope=domestic'), b = loader.load('/reports?scope=export');
    second.resolve(reply('EXPORT', '/reports?scope=export')); await b;
    first.resolve(reply('DOMESTIC', '/reports?scope=domestic')); await a;
    assert.equal(calls[0].options.signal.aborted, true);
    assert.equal(calls[1].options.headers.Accept, 'application/json');
    assert.equal(rendered.length, 1); assert.equal(rendered[0][0].html, 'EXPORT');
    assert.deepEqual(states.at(-1), {busy: false});
});

test('a new search immediately invalidates pending results before its debounce finishes', async () => {
    const request = deferred(), rendered = [];
    const loader = createReportLoader({fetcher: () => request.promise, render: data => rendered.push(data), state: () => {}});
    const pending = loader.load('/reports?q=old'); loader.cancel();
    request.resolve(reply('OLD')); await pending; assert.deepEqual(rendered, []);
});

test('filter changes remove pagination and switching scope clears filters from the previous report', () => {
    assert.equal(reportFormUrl('/reports/records?page=3&q=old', [['scope','export'],['kind','stock'],['q','00000001'],['page','3'],['status','']], 'https://warehouse.test'),
        'https://warehouse.test/reports/records?scope=export&kind=stock&q=00000001');
    assert.equal(reportFormUrl('/reports/records', [['scope','domestic'],['kind','receipts']], 'https://warehouse.test/reports/records?scope=export&q=part&page=2'),
        'https://warehouse.test/reports/records?scope=domestic&kind=receipts');
});

test('pagination and browser Back keep their query and use the canonical URL after a redirect', async () => {
    const rendered = [], requests = [];
    const loader = createReportLoader({fetcher: async url => { requests.push(url); return reply('PAGE', 'https://warehouse.test/reports/stock-input?scope=domestic&page=2'); },
        render: (...args) => rendered.push(args), state: () => {}});
    await loader.load('/reports/records?scope=domestic&kind=receipts&page=2', 'none');
    assert.match(requests[0], /page=2/); assert.equal(rendered[0][1], 'https://warehouse.test/reports/stock-input?scope=domestic&page=2');
    assert.equal(rendered[0][2], 'none');
});

test('invalid filters keep old results and retry can recover without navigating away', async () => {
    let count = 0; const rendered = [], states = [];
    const loader = createReportLoader({fetcher: async () => ++count === 1
        ? {ok: false, status: 422, json: async () => ({errors: {end_date: ['Tanggal akhir harus setelah tanggal awal.']}})} : reply('RECOVERED'),
        render: data => rendered.push(data), state: data => states.push(data)});
    assert.equal(await loader.load('/reports'), false); assert.deepEqual(rendered, []);
    assert.match(states.at(-1).error, /Tanggal akhir/);
    assert.equal(await loader.load('/reports'), true); assert.equal(rendered[0].html, 'RECOVERED');
});

test('expired sessions and inaccessible reports cannot replace the page with login or error HTML', async () => {
    for (const response of [{ok: false, status: 401}, {ok: false, status: 403}, {ok: true, json: async () => ({message: 'Login'})},
        {ok: true, json: async () => { throw new SyntaxError('Unexpected token <'); }}]) {
        const rendered = [], states = [];
        const loader = createReportLoader({fetcher: async () => response, render: data => rendered.push(data), state: data => states.push(data)});
        assert.equal(await loader.load('/reports'), false); assert.deepEqual(rendered, []); assert.ok(states.at(-1).error);
        assert.doesNotMatch(states.at(-1).error, /Unexpected token/);
    }
});

test('a superseded failed request never overwrites a newer success', async () => {
    const old = deferred(), states = [];
    const loader = createReportLoader({fetcher: url => url.includes('old') ? old.promise : Promise.resolve(reply('NEW')),
        render: () => {}, state: data => states.push(data)});
    const pending = loader.load('/old'); await loader.load('/new'); old.resolve({ok: false, status: 403}); await pending;
    assert.deepEqual(states.at(-1), {busy: false});
});
