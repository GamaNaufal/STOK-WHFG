import test from 'node:test';
import assert from 'node:assert/strict';
import {initStockReview} from '../../resources/js/export-delivery/stock-review.mjs';

function setup(fetcher) {
    const button = {}, error = {hidden: true}, note = {}, help = {}, status = {};
    const states = [{dataset: {placed: 'false'}}, {dataset: {placed: 'true'}}];
    const form = {action: '/pallet-plans/1/stock-allocation/1/acknowledge',
        querySelector: selector => selector === 'button' ? button : {value: 'csrf'},
        setAttribute() {}, removeAttribute() {}, replaceWith(node) {this.replacement = node;},
        addEventListener(name, callback) {this[name] = callback;}};
    const element = {dataset: {autoOpen: 'true'},
        querySelector: selector => ({'[data-stock-ack-form]': form, '[data-stock-review-error]': error,
            '[data-stock-review-note]': note, '[data-stock-review-help]': help}[selector]),
        querySelectorAll: () => states};
    const modal = {shown: 0, hidden: 0, show() {this.shown++;}, hide() {this.hidden++;}, dispose() {}};
    const doc = {getElementById: () => element, createElement: () => ({setAttribute() {}}),
        querySelector: selector => selector === '[data-stock-review-status]' ? status : {focus() {}}};
    const payload = {revision: '1', _token: 'csrf'};
    initStockReview({documentRef: doc, fetcher, modalApi: {getOrCreateInstance: () => modal}, formData: () => payload});
    return {form, button, error, states, modal, element, payload, submit: () => form.submit({preventDefault() {}})};
}

test('mandatory review rejects duplicate clicks and closes locally only after acknowledgment succeeds', async () => {
    let finish; const pending = new Promise(resolve => {finish = resolve;}); const requests = [];
    const ui = setup((url, options) => {requests.push({url, options}); return pending;});
    assert.equal(requests.length, 0); // Already rendered, no load request just to open the modal.
    const first = ui.submit(); await ui.submit();
    assert.equal(requests.length, 1); assert.equal(ui.button.disabled, true); assert.equal(ui.modal.hidden, 0);
    finish({ok: true, json: async () => ({message: 'Tugas aktif di antrean.'})}); await first;
    assert.equal(ui.modal.hidden, 1); assert.equal(ui.element.dataset.bsBackdrop, 'true');
    assert.equal(ui.form.replacement.textContent, 'Tutup');
    assert.equal(ui.states[0].textContent, 'Menunggu penempatan');
    assert.equal(ui.states[1].textContent, 'Sudah ditempatkan');
    assert.equal(requests[0].options.body, ui.payload);
});

test('failed acknowledgment keeps mandatory review open and permits retry with the same revision', async () => {
    let calls = 0; const bodies = [];
    const ui = setup(async (url, options) => {
        bodies.push(options.body); calls++;
        return calls === 1 ? {ok: false, json: async () => ({errors: {revision: ['Muat ulang revisi.']}})}
            : {ok: true, json: async () => ({message: 'Berhasil.'})};
    });
    await ui.submit();
    assert.equal(ui.modal.hidden, 0); assert.equal(ui.error.hidden, false);
    assert.equal(ui.error.textContent, 'Muat ulang revisi.'); assert.equal(ui.button.disabled, false);
    assert.equal(ui.form.replacement, undefined);
    await ui.submit();
    assert.equal(ui.modal.hidden, 1); assert.equal(ui.error.hidden, true);
    assert.equal(bodies[0], bodies[1]);
});
