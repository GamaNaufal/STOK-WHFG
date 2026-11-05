import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/expired-box-workspace.js', import.meta.url), 'utf8');
const flush = () => new Promise(setImmediate);

function setup(fetcher = null) {
    const requests = [], pushes = [], timers = new Map(), windowEvents = {};
    let tick = 0;
    const location = {href: 'http://app.test/expired-box', origin: 'http://app.test'};
    const form = {action: 'http://app.test/expired-box', values: {tab: 'expired', search: '', location_id: ''}};
    const field = {id: 'expired-search', name: 'search', selectionStart: 3, selectionEnd: 3, focus() {}, setSelectionRange() {}};
    const feedback = {textContent: '', append(link) { this.retry = link; }};
    const scope = {dataset: {scope: 'domestic'}, removeAttribute(name) { if (name === 'data-pending-scope') delete this.dataset.pendingScope; }};
    const root = {innerHTML: 'initial', events: {}, busy: false, contains: element => element === field,
        setAttribute() { this.busy = true; }, removeAttribute() { this.busy = false; },
        addEventListener(name, callback) { this.events[name] = callback; },
        querySelector(selector) { return selector === '[data-expired-filter]' ? form : selector === '[data-expired-feedback]' ? feedback : scope; },
    };
    const document = {activeElement: field, getElementById(id) { return id === 'expired-workspace' ? root : field; }, createElement() { return {}; }};
    const context = vm.createContext({document, location, URL, AbortController,
        history: {pushState(state, title, url) { pushes.push(url); location.href = url; }},
        window: {addEventListener(name, callback) { windowEvents[name] = callback; }},
        FormData: class {constructor(input) { this.input = input; } forEach(callback) { Object.entries(this.input.values).forEach(([key, value]) => callback(value, key)); }},
        setTimeout(callback) { timers.set(++tick, callback); return tick; }, clearTimeout(id) { timers.delete(id); },
        fetch: async (url, options) => { requests.push({url, options}); return fetcher ? fetcher(url, options) : {ok: true, json: async () => ({html: 'updated'})}; },
    });
    vm.runInContext(source, context);
    const click = (url, nextScope = null) => {
        const link = {href: url, dataset: nextScope ? {warehouseScope: nextScope} : {}, closest: () => scope};
        const event = {target: {closest: () => link}, button: 0, preventDefault() { this.prevented = true; }};
        root.events.click(event); return event;
    };
    const input = () => root.events.input({target: field});
    const runTimers = () => { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(fn => fn()); };
    return {root, form, field, scope, feedback, location, requests, pushes, click, input, runTimers, windowEvents};
}

test('tabs and scope changes fetch partial HTML and update history without page navigation', async () => {
    const ui = setup();
    assert.equal(ui.click('http://app.test/expired-box?tab=process').prevented, true);
    await flush();
    assert.equal(ui.root.innerHTML, 'updated');
    assert.equal(ui.requests[0].options.headers.Accept, 'application/json');
    ui.click('http://app.test/export/expired-box', 'export');
    assert.equal(ui.scope.dataset.pendingScope, 'export');
    await flush();
    assert.equal(ui.pushes.length, 2);
    assert.equal(ui.scope.dataset.pendingScope, undefined);
});

test('filter changes preserve current tab and apply the search and location together', async () => {
    const ui = setup();
    ui.form.values = {tab: 'warning', search: 'PART 80', location_id: '7'};
    ui.input(); assert.equal(ui.requests.length, 0);
    ui.runTimers(); await flush();
    const url = new URL(ui.requests[0].url);
    assert.equal(url.searchParams.get('search'), 'PART 80');
    assert.equal(url.searchParams.get('location_id'), '7');
    assert.equal(url.searchParams.get('tab'), 'warning');
});

test('typing invalidates an in-flight response immediately before the next debounce fires', async () => {
    let release;
    const pending = new Promise(resolve => { release = resolve; });
    const ui = setup(async () => { await pending; return {ok: true, json: async () => ({html: 'stale result'})}; });
    ui.click('http://app.test/expired-box?search=old');
    ui.form.values.search = 'new text'; ui.input();
    assert.equal(ui.requests[0].options.signal.aborted, true);
    release(); await flush();
    assert.equal(ui.root.innerHTML, 'initial');
    assert.equal(ui.pushes.length, 0);
    ui.runTimers(); await flush();
    assert.match(ui.pushes[0], /new\+text/);
});

test('browser back refreshes the workspace without creating another history entry', async () => {
    const ui = setup(); ui.windowEvents.popstate(); await flush();
    assert.equal(ui.requests.length, 1);
    assert.equal(ui.pushes.length, 0);
    assert.equal(ui.root.busy, false);
});

test('failed responses retain current results and provide a normal reload link', async () => {
    const ui = setup(async () => ({ok: false, json: async () => ({message: 'No access'})}));
    ui.click('http://app.test/export/expired-box'); await flush();
    assert.equal(ui.root.innerHTML, 'initial');
    assert.match(ui.feedback.textContent, /belum diperbarui/);
    assert.equal(ui.feedback.retry.href, 'http://app.test/export/expired-box');
    assert.equal(ui.pushes.length, 0);
});

test('external targets are not fetched by the workspace', async () => {
    const ui = setup(); ui.click('https://outside.test/expired-box'); await flush();
    assert.equal(ui.requests.length, 0);
});
