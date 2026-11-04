import test from 'node:test';
import assert from 'node:assert/strict';
import {createOpnameQueue} from '../../resources/js/stock-opname/scan-queue.mjs';

test('fast scans are sent sequentially and an NG does not block subsequent boxes', async () => {
    let release;
    const first = new Promise(resolve => { release = resolve; });
    const sent = [], saved = [], states = [];
    const queue = createOpnameQueue({
        send: async payload => { sent.push(payload); if (sent.length === 1) await first; return {outcome: payload.box_number === 'bad' ? 'ng' : 'ok'}; },
        acknowledge: data => saved.push(data.outcome), state: state => states.push(state),
    });
    const processing = queue.enqueue({box_number: 'bad', request_token: 'one'});
    queue.enqueue({box_number: '00000001', request_token: 'two'});
    assert.equal(sent.length, 1);
    assert.equal(queue.size(), 2);
    release();
    await processing;
    assert.deepEqual(saved, ['ng', 'ok']);
    assert.deepEqual(sent.map(row => row.request_token), ['one', 'two']);
    assert.equal(queue.size(), 0);
    assert.equal(states.at(-1).running, false);
});

test('a network failure retains the same token for retry and keeps newly scanned boxes in order', async () => {
    let fail = true;
    const requests = [], states = [], saved = [];
    const queue = createOpnameQueue({
        send: async payload => { requests.push({...payload}); if (fail) throw new Error('Offline'); return {outcome: 'ok'}; },
        acknowledge: data => saved.push(data), state: state => states.push(state),
    });
    await queue.enqueue({box_number: '00000001', request_token: 'original'});
    await queue.enqueue({box_number: '00000002', request_token: 'next'});
    assert.equal(states.at(-1).paused, true);
    assert.equal(requests.length, 1);
    assert.equal(queue.size(), 2);
    fail = false;
    await queue.retry();
    assert.deepEqual(requests.map(row => row.request_token), ['original', 'original', 'next']);
    assert.equal(queue.size(), 0);
    assert.equal(saved.length, 2);
    assert.equal(states.at(-1).error, '');
});
