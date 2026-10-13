import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/picking-verification-session.js', import.meta.url), 'utf8');
const flush = () => new Promise(setImmediate);

function setup() {
    let now = 1000000;
    let tick;
    let confirmation;
    const events = {};
    const calls = [];
    const elements = new Map();
    const responses = [];
    const navigations = [];
    const delayed = [];
    const element = (id) => {
        if (!elements.has(id)) elements.set(id, {
            disabled: false, classList: { add() {} },
            addEventListener(name, callback) { this[name] = callback; },
        });
        return elements.get(id);
    };
    const window = { location: { assign(url) { navigations.push(url); } } };
    vm.runInNewContext(source, {
        window,
        document: {
            currentScript: { dataset: {
                activityUrl: '/activity', cancelUrl: '/cancel', returnUrl: '/verification',
                csrf: 'test', expiresIn: '600',
            } },
            visibilityState: 'visible',
            getElementById: element,
            addEventListener(name, callback) { events[name] = callback; },
        },
        Date: class extends Date { static now() { return now; } },
        setInterval(callback) { tick = callback; },
        setTimeout(callback) { delayed.push(callback); },
        showToast() {},
        WarehouseAlert: { confirm(options) { confirmation = options; } },
        async fetch(url, options) {
            calls.push({ url, ...options });
            const result = responses.shift() || { status: 200, data: { success: true, expires_in: 600 } };
            return { status: result.status, ok: result.status === 200, json: async () => result.data };
        },
    });
    return {
        calls, events, responses, window, element, navigations, delayed,
        advance(ms) { now += ms; tick(); },
        confirm() { return confirmation.onConfirm(); },
    };
}

test('passive polling and synthetic events never count as user activity', async () => {
    const app = setup();
    app.advance(15000);
    await flush();
    assert.equal(JSON.parse(app.calls[0].body).active, false);
    app.events.keydown({ isTrusted: false });
    app.advance(15000);
    await flush();
    assert.equal(JSON.parse(app.calls[1].body).active, false);
    app.advance(5000);
    app.events.keydown({ isTrusted: true });
    await flush();
    assert.equal(JSON.parse(app.calls[2].body).active, true);
});

test('server expiry disables scanning and returns to verification list', async () => {
    const app = setup();
    app.responses.push({ status: 409, data: { success: false, message: 'Expired' } });
    app.advance(600000);
    await flush();
    assert.equal(app.window.verificationSessionClosing, true);
    assert.equal(app.element('scanInput').disabled, true);
    assert.equal(app.element('btnScan').disabled, true);
    app.delayed[0]();
    assert.deepEqual(app.navigations, ['/verification']);
});

test('manual cancellation uses confirmation before calling existing cancel endpoint', async () => {
    const app = setup();
    app.element('btnCancelVerification').click();
    assert.equal(app.calls.length, 0);
    await app.confirm();
    assert.equal(app.calls[0].url, '/cancel');
    assert.equal(app.calls[0].method, 'POST');
    assert.deepEqual(app.navigations, ['/verification']);
});

test('blocked cancellation failure does not navigate or leave scanning frozen', async () => {
    const app = setup();
    app.responses.push({ status: 423, data: { success: false, message: 'Blocked' } });
    app.element('btnCancelVerification').click();
    await app.confirm();
    assert.equal(app.window.verificationSessionClosing, false);
    assert.equal(app.element('btnCancelVerification').disabled, true);
    assert.equal(app.navigations.length, 0);
});
