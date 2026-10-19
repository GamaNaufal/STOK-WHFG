import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/dashboard-audit.js', import.meta.url), 'utf8');
const flush = () => new Promise(setImmediate);
function setup() {
    const requests = [], urls = [], timers = [];
    const element = () => ({
        events: {}, attrs: {}, innerHTML: 'original', textContent: '', hidden: false,
        classList: { toggle() {} },
        addEventListener(name, fn) { this.events[name] = fn; },
        setAttribute(name, value) { this.attrs[name] = value; },
        contains(el) { return el === this; },
        focus() { document.activeElement = this; },
    });
    const form = element(), content = element(), feedback = element(), message = element(), retry = element();
    let button = element();
    form.action = 'https://wms.test/dashboard';
    form.values = [['audit_type', 'stock_input'], ['audit_user', '7'], ['audit_action', '']];
    form.querySelector = () => button;
    form.contains = el => el === button || el === form;
    const elements = { 'audit-filter-form': form, 'audit-report-content': content, 'audit-feedback': feedback, 'audit-feedback-message': message, 'audit-retry': retry };
    const document = { getElementById: id => elements[id], activeElement: null, body: {} };
    vm.runInNewContext(source, {
        document, URL, AbortController, CSS: { escape: value => value },
        window: { history: { replaceState(a, b, url) { urls.push(url); } } },
        FormData: class { constructor(f) { this.values = f.values; } forEach(fn) { this.values.forEach(([k, v]) => fn(v, k)); } },
        DOMParser: class { parseFromString(html) { return { getElementById: id => html === 'login' ? null : { innerHTML: `${id}:${html}` } }; } },
        fetch(url, options) { return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })); },
        setTimeout(fn) { timers.push(fn); return timers.length; }, clearTimeout() {},
    });
    const event = (link) => ({ button: 0, preventDefault() {}, target: { closest: () => link } });
    return { form, content, message, retry, document, requests, urls, timers,
        submit() { form.events.submit(event()); },
        reset() { form.events.click(event({ href: 'https://wms.test/dashboard?period=week' })); },
        replaceButton() { button = element(); return button; },
        reply(index, html = 'success', ok = true) { requests[index].resolve({ ok, text: async () => html }); },
    };
}

test('submit preserves GET values; delegated Reset works after multiple replacements', async () => {
    const h = setup(); h.submit();
    assert.equal(h.requests[0].url, 'https://wms.test/dashboard?audit_type=stock_input&audit_user=7');
    assert.equal(h.content.attrs['aria-busy'], 'true');
    h.reply(0); await flush();
    h.reset(); h.reply(1); await flush(); h.reset();
    assert.equal(h.requests[2].url, 'https://wms.test/dashboard?period=week');
    assert.match(h.message.textContent, /Memuat/);
});

test('server, network and invalid markup failures preserve results and allow retry', async () => {
    for (const failure of ['server', 'network', 'markup']) {
        const h = setup(); h.submit();
        if (failure === 'network') h.requests[0].reject(new Error('offline'));
        else h.reply(0, failure === 'markup' ? 'login' : '', failure !== 'server');
        await flush();
        assert.equal(h.content.innerHTML, 'original');
        assert.equal(h.retry.hidden, false);
        assert.equal(h.content.attrs['aria-busy'], 'false');
        assert.equal(h.urls.length, 0);
        h.retry.events.click(); h.reply(1); await flush();
        assert.match(h.content.innerHTML, /success/);
        assert.equal(h.retry.hidden, true);
    }
});

test('latest request wins even if an aborted request still resolves', async () => {
    const h = setup(); h.submit(); h.reset();
    assert.equal(h.requests[0].options.signal.aborted, true);
    h.reply(1, 'new'); await flush(); h.reply(0, 'old'); await flush();
    assert.match(h.content.innerHTML, /new$/);
    assert.equal(h.urls.length, 1);
});

test('editing filters cancels pending response and clears stale retry', async () => {
    const h = setup(); h.submit(); h.form.events.change(); h.reply(0); await flush();
    assert.equal(h.content.innerHTML, 'original');
    assert.equal(h.content.attrs['aria-busy'], 'false');
    assert.match(h.message.textContent, /Pilihan filter berubah/);
    assert.equal(h.retry.hidden, true);
});

test('restores focus after replacement without stealing focus outside audit', async () => {
    const h = setup(); h.document.activeElement = h.form; h.submit();
    const replacement = h.replaceButton(); h.reply(0); await flush();
    assert.equal(h.document.activeElement, replacement);
    const outside = {}; h.document.activeElement = outside;
    h.submit(); h.reply(1); await flush();
    assert.equal(h.document.activeElement, outside);
});

test('timeout aborts and reports recoverable error', async () => {
    const h = setup(); h.submit(); h.timers[0]();
    assert.equal(h.requests[0].options.signal.aborted, true);
    h.requests[0].reject(new Error('aborted')); await flush();
    assert.match(h.message.textContent, /terlalu lama/);
    assert.equal(h.retry.hidden, false);
});

test('retry restores focus when its hidden button releases browser focus', async () => {
    const h = setup(); h.submit(); h.reply(0, '', false); await flush();
    h.document.activeElement = h.retry; h.retry.events.click();
    h.document.activeElement = h.document.body;
    const button = h.replaceButton(); h.reply(1); await flush();
    assert.equal(h.document.activeElement, button);
});
