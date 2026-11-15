import assert from 'node:assert/strict';
import test from 'node:test';
import {createExcelTracker, createExcelRepeatGuard, excelCookieName, readExcelCookie, excelFormUrl} from '../../public/js/excel-download.js';

function tracker() {
    const timers = new Map(), polls = new Map(), phases = [], finished = [], cleared = [];
    let counter = 0, cookie;
    const token = 'a'.repeat(32);
    const instance = createExcelTracker({token,
        readCookie: () => cookie, clearCookie: name => cleared.push(name),
        changed: (phase, message) => phases.push({phase, message}), finished: phase => finished.push(phase),
        setTimer(callback, delay) { const id = ++counter; timers.set(id, {callback, delay}); return id; },
        clearTimer(id) { timers.delete(id); },
        setPoll(callback, delay) { const id = ++counter; polls.set(id, {callback, delay}); return id; },
        clearPoll(id) { polls.delete(id); },
    });
    return {instance, timers, polls, phases, finished, cleared, token, ready() { cookie = 'ready'; }};
}

test('native Excel preparation is acknowledged immediately; elapsed time never means the file is ready', () => {
    const ui = tracker();
    assert.equal(ui.phases[0].phase, 'preparing');
    const callbacks = [...ui.timers.values()];
    callbacks.find(item => item.delay === 1800).callback();
    assert.equal(ui.phases.at(-1).phase, 'slow');
    assert.deepEqual(ui.finished, []);
    assert.equal(ui.instance.check(), false);
    ui.ready();
    assert.equal(ui.instance.check(), true);
    assert.equal(ui.phases.at(-1).phase, 'ready');
    assert.deepEqual(ui.finished, ['ready']);
    assert.deepEqual(ui.cleared, [excelCookieName(ui.token)]);
    assert.equal(ui.timers.size, 0); assert.equal(ui.polls.size, 0);
    callbacks.forEach(item => item.callback());
    assert.equal(ui.phases.at(-1).phase, 'ready');
});

test('missing acknowledgement ends monitoring without treating an elapsed deadline as a successful download', () => {
    const ui = tracker();
    const callbacks = [...ui.timers.values()];
    callbacks.find(item => item.delay === 60000).callback();
    assert.equal(ui.phases.at(-1).phase, 'unconfirmed');
    assert.deepEqual(ui.finished, ['unconfirmed']);
    assert.equal(ui.timers.size, 0); assert.equal(ui.polls.size, 0);
    const count = ui.phases.length;
    ui.ready(); ui.instance.check();
    callbacks.forEach(item => item.callback());
    assert.equal(ui.phases.length, count);
    assert.equal(ui.phases.at(-1).phase, 'unconfirmed');
});

test('an acknowledgement arriving at the monitoring deadline still wins over an unconfirmed status', () => {
    const ui = tracker();
    const deadline = [...ui.timers.values()].find(item => item.delay === 60000).callback;
    ui.ready(); deadline();
    assert.equal(ui.phases.at(-1).phase, 'ready');
    assert.deepEqual(ui.finished, ['ready']);
});

test('fast completion still blocks a repeated activation while other files and later deliberate retries remain available', () => {
    let time = 250;
    const guard = createExcelRepeatGuard({now: () => time});
    const url = '/reports/export?scope=domestic';
    guard.hold(url);
    time = 350;
    assert.equal(guard.blocked(url), true);
    assert.equal(guard.blocked('/reports/export?scope=export'), false);
    time = 1249;
    assert.equal(guard.blocked(url), true);
    time = 1250;
    assert.equal(guard.blocked(url), false);
    guard.hold(url);
    guard.clear();
    assert.equal(guard.blocked(url), false);
});

test('failed and cancelled downloads stop monitoring and cannot become successes later', () => {
    for (const cancel of [false, true]) {
        const ui = tracker();
        if (cancel) ui.instance.cancel(); else ui.instance.fail('Akses tidak tersedia.');
        ui.ready(); ui.instance.check();
        assert.deepEqual(ui.finished, [cancel ? 'cancelled' : 'error']);
        assert.equal(ui.phases.at(-1).phase, cancel ? 'cancelled' : 'error');
        assert.equal(ui.timers.size, 0); assert.equal(ui.polls.size, 0);
    }
});

test('cookie correlation is exact and cannot confuse another tab or another request', () => {
    assert.equal(readExcelCookie('whfg_excel_abc=ready; whfg_excel_ab=waiting', 'whfg_excel_ab'), 'waiting');
    assert.equal(readExcelCookie('other=ready', 'whfg_excel_abc'), undefined);
});

test('GET export buttons preserve the current filter snapshot and their formaction', () => {
    const button = {getAttribute: name => name === 'formaction' ? '/export/stock/excel' : null};
    const url = excelFormUrl({action: '/export/stock'}, button, 'https://warehouse.test/current', [
        ['mode', 'pallet'], ['customer', '001'], ['q', 'PART A'], ['status', 'ready'], ['part_ids[]', '1'], ['part_ids[]', '2'],
    ]);
    assert.equal(url.pathname, '/export/stock/excel');
    assert.equal(url.searchParams.get('customer'), '001');
    assert.equal(url.searchParams.get('q'), 'PART A');
    assert.deepEqual(url.searchParams.getAll('part_ids[]'), ['1', '2']);
});
