import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/sales-delivery-order.js', import.meta.url), 'utf8');

function setup(initial = [{ part: 'A', quantity: '10' }], mode = 'create') {
    const element = (extra = {}) => ({
        events: {},
        addEventListener(name, callback) { this.events[name] = callback; },
        classList: { add() {}, remove() {}, toggle() {} },
        setAttribute() {},
        closest() { return null; },
        focus() { this.focused = true; },
        ...extra,
    });
    const itemRows = [];
    function makeRow(part = '', amount = '') {
        const select = element({ value: part, dataset: {}, name: '', id: '', matches: (selector) => selector === '.part-select' });
        const quantity = element({ value: amount, name: '', id: '', matches: (selector) => selector === '[name$="[quantity]"]' });
        const number = element({ textContent: '' });
        const partLabel = element({ htmlFor: '' });
        const quantityLabel = element({ htmlFor: '' });
        const remove = element({ disabled: false, closest: (selector) => selector === '[data-item-row]' ? row : remove });
        const row = element({
            querySelector(selector) {
                return {
                    '.part-select': select,
                    '[name$="[quantity]"]': quantity,
                    '[data-item-number]': number,
                    '.remove-item': remove,
                    'label[for="sales-part-template"]': partLabel,
                    'label[for="sales-quantity-template"]': quantityLabel,
                }[selector];
            },
            querySelectorAll(selector) { return selector === '.part-select' ? [select] : []; },
            remove() { itemRows.splice(itemRows.indexOf(row), 1); },
        });
        return row;
    }
    initial.forEach(({ part, quantity }) => itemRows.push(makeRow(part, quantity)));
    const container = element({
        dataset: { nextIndex: String(itemRows.length) },
        querySelectorAll(selector) { return selector === '[data-item-row]' ? itemRows : itemRows.map((row) => row.querySelector('.part-select')); },
        appendChild(row) { itemRows.push(row); },
    });
    const template = { content: { firstElementChild: { cloneNode: () => makeRow() } } };
    const addButton = element();
    const count = element({ textContent: '' });
    const total = element({ textContent: '' });
    const error = element({ textContent: '', hidden: true });
    const formError = element({ textContent: '', hidden: true });
    const submitButton = element({ disabled: false });
    const customer = element({ value: '<PT Test>' });
    const date = element({ value: '2026-10-25' });
    let submitted = 0;
    const form = element({
        dataset: { salesOrderForm: mode },
        querySelector(selector) {
            return { '[type="submit"]': submitButton, '[name="customer_name"]': customer, '[name="delivery_date"]': date }[selector];
        },
        submit() { submitted += 1; },
    });
    const elements = {
        'sales-order-form': form,
        'items-container': container,
        'sales-order-item-template': template,
        'add-item-btn': addButton,
        'sales-order-item-count': count,
        'sales-order-pcs-total': total,
        'sales-order-items-error': error,
        'sales-order-form-error': formError,
    };
    const alerts = [];
    const document = {
        getElementById(id) { return elements[id]; },
        addEventListener(name, callback) { if (name === 'DOMContentLoaded') callback(); },
        createElement() {
            let value = '';
            return {
                set textContent(text) { value = text; },
                get innerHTML() { return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;'); },
            };
        },
    };
    vm.runInNewContext(source, {
        document,
        window: {},
        WarehouseAlert: { info(options) { alerts.push(options); } },
        Intl,
    });
    return { itemRows, container, addButton, count, total, error, formError, form, alerts, submitButton,
        get submitted() { return submitted; } };
}

test('duplicate part blocks confirmation until quantities are combined into one item', () => {
    const ui = setup([{ part: 'A', quantity: '10' }, { part: 'A', quantity: '20' }]);
    ui.form.events.submit({ preventDefault() {} });
    assert.match(ui.error.textContent, /Nomor part yang sama/);
    assert.equal(ui.alerts.length, 0);

    ui.itemRows[1].querySelector('.part-select').value = 'B';
    ui.container.events.change({ target: ui.itemRows[1].querySelector('.part-select') });
    ui.form.events.submit({ preventDefault() {} });
    assert.equal(ui.alerts.length, 1);
    assert.equal(ui.alerts[0].details.Pelanggan, '&lt;PT Test&gt;');
    assert.equal(ui.submitted, 0);
    ui.alerts[0].onConfirm();
    assert.equal(ui.submitted, 1);
    assert.equal(ui.submitButton.disabled, true);
});

test('adding and removing item keeps sequential labels and PCS total readable', () => {
    const ui = setup();
    ui.addButton.events.click();
    assert.equal(ui.itemRows.length, 2);
    assert.equal(ui.itemRows[1].querySelector('.part-select').name, 'items[1][part_number]');
    assert.equal(ui.count.textContent, '2 item');
    assert.equal(ui.total.textContent, '10 PCS diminta');

    const remove = ui.itemRows[0].querySelector('.remove-item');
    ui.container.events.click({ target: { closest: () => remove } });
    assert.equal(ui.itemRows.length, 1);
    assert.equal(ui.itemRows[0].querySelector('[data-item-number]').textContent, 'Item 1');
    assert.equal(ui.itemRows[0].querySelector('.remove-item').disabled, true);
});
