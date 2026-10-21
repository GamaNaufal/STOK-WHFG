import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/ppc-approval-decision.js', import.meta.url), 'utf8');

function setup() {
    const element = (extra = {}) => ({
        events: {},
        dataset: {},
        hidden: false,
        textContent: '',
        children: [],
        classList: { add() {}, remove() {} },
        addEventListener(name, callback) { this.events[name] = callback; },
        setAttribute(name, value) { this[name] = value; },
        append(...children) { this.children.push(...children); },
        appendChild(child) { this.children.push(child); },
        replaceChildren() { this.children = []; },
        focus() { this.focused = true; },
        ...extra,
    });
    const ids = [
        'statusModal', 'statusForm', 'modalStatusInput', 'statusModalLabel',
        'statusOrderNumber', 'statusOrderCustomer', 'statusOrderDate',
        'statusConfirmationText', 'statusNotes', 'statusNotesLabel',
        'statusNoteHelp', 'statusNoteError', 'statusSubmitButton',
        'impactPanel', 'impactIcon', 'impactTitle', 'impactDescription',
        'impactList', 'impactRetry',
    ];
    const elements = Object.fromEntries(ids.map((id) => [id, element()]));
    const buttons = [];
    function button(orderId, status, customer = 'PT Test') {
        const heading = element({ textContent: customer });
        const date = element({ textContent: '22 Oct 2026', dateTime: '2026-10-22' });
        const card = element({ querySelector(selector) {
            return selector === '.ppc-order-heading h2' ? heading : date;
        } });
        const result = element({ dataset: { orderId: String(orderId), status }, closest: () => card });
        buttons.push(result);
        return result;
    }
    const requests = [];
    const document = {
        getElementById(id) { return elements[id]; },
        querySelectorAll() { return buttons; },
        createElement() { return element(); },
        addEventListener(name, callback) { if (name === 'DOMContentLoaded') callback(); },
    };
    let shown = 0;
    const fetch = (url, options) => new Promise((resolve, reject) => {
        requests.push({ url, options, resolve, reject });
    });
    function start() {
        vm.runInNewContext(source, {
            document,
            bootstrap: { Modal: { getOrCreateInstance: () => ({ show() { shown += 1; } }) } },
            fetch,
            AbortController,
            encodeURIComponent,
        });
    }
    return { elements, button, requests, start, get shown() { return shown; } };
}

const settle = () => new Promise((resolve) => setImmediate(resolve));

test('approval waits for impact check and a nonblank PPC note', async () => {
    const ui = setup();
    const approve = ui.button(7, 'approved');
    ui.start();
    approve.events.click();
    assert.equal(ui.elements.statusSubmitButton.disabled, true);
    assert.equal(ui.elements.impactPanel.dataset.state, 'checking');
    assert.equal(ui.requests[0].url, '/delivery-stock/7/approval-impact');

    ui.requests[0].resolve({ ok: true, json: async () => ({ impacted: [] }) });
    await settle();
    assert.equal(ui.elements.impactPanel.dataset.state, 'safe');
    assert.equal(ui.elements.statusSubmitButton.disabled, false);

    let prevented = false;
    ui.elements.statusForm.events.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(ui.elements.statusNoteError.hidden, false);
    ui.elements.statusNotes.value = 'Sudah ditinjau';
    ui.elements.statusNotes.events.input();
    prevented = false;
    ui.elements.statusForm.events.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
    assert.equal(ui.elements.statusForm.action, '/delivery-stock/7/status');
    assert.equal(ui.elements.modalStatusInput.value, 'approved');
});

test('failed impact check blocks approval, retry shows affected orders as text', async () => {
    const ui = setup();
    const approve = ui.button(8, 'approved');
    ui.start();
    approve.events.click();
    ui.requests[0].reject(new Error('offline'));
    await settle();
    assert.equal(ui.elements.impactPanel.dataset.state, 'error');
    assert.equal(ui.elements.statusSubmitButton.disabled, true);
    assert.equal(ui.elements.impactRetry.hidden, false);

    ui.elements.impactRetry.events.click();
    ui.requests[1].resolve({ ok: true, json: async () => ({ impacted: [{
        id: 9,
        customer_name: '<img src=x onerror=alert(1)>',
        delivery_date: '23 Oct 2026',
        short_parts: [{ part_number: 'P-001', can_fulfill: 10, required: 20 }],
    }] }) });
    await settle();
    assert.equal(ui.elements.impactPanel.dataset.state, 'warning');
    assert.equal(ui.elements.impactList.children[0].children[0].textContent,
        'Order #9 · <img src=x onerror=alert(1)>');
    assert.equal(ui.elements.statusSubmitButton.disabled, false);
});

test('a late impact response cannot overwrite another decision', async () => {
    const ui = setup();
    const first = ui.button(1, 'approved');
    const second = ui.button(2, 'approved', 'PT Kedua');
    ui.start();
    first.events.click();
    ui.elements.statusModal.events['hide.bs.modal']();
    second.events.click();
    ui.requests[1].resolve({ ok: true, json: async () => ({ impacted: [] }) });
    await settle();
    ui.requests[0].resolve({ ok: true, json: async () => ({ impacted: [{ id: 3 }] }) });
    await settle();
    assert.equal(ui.elements.statusOrderCustomer.textContent, 'PT Kedua');
    assert.equal(ui.elements.impactPanel.dataset.state, 'safe');
    assert.equal(ui.elements.statusSubmitButton.disabled, false);
});
