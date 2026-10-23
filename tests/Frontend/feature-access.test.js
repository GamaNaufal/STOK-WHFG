import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/feature-access.js', import.meta.url), 'utf8');

function setup() {
    const element = (extra = {}) => ({
        events: {},
        addEventListener(name, callback) { this.events[name] = callback; },
        classList: { toggle() {} },
        ...extra,
    });
    const schedule = element({ value: 'delivery_schedule', checked: true, dataset: { requires: '[]' } });
    const picking = element({ value: 'delivery_pick', checked: false, dataset: { requires: '["delivery_schedule"]' } });
    const split = element({ value: 'delivery_split', checked: false, dataset: { requires: '["delivery_schedule"]' } });
    const stock = element({ value: 'stock_view', checked: true, dataset: { requires: '[]' } });
    const boxes = [schedule, picking, split, stock];
    const deliveryCount = element();
    const stockCount = element();
    const groups = [
        { querySelector: () => deliveryCount, querySelectorAll: () => [schedule, picking, split] },
        { querySelector: () => stockCount, querySelectorAll: () => [stock] },
    ];
    let submitted = 0;
    const form = element({
        dataset: { roleLabel: 'Operator Warehouse' },
        querySelectorAll(selector) { return selector === '[data-access-group]' ? groups : boxes; },
        submit() { submitted += 1; },
    });
    const selectedCount = element();
    const changeStatus = element();
    const saveButton = element({ disabled: false });
    const selectAll = element();
    const clearAll = element();
    const elements = {
        featureAccessForm: form,
        accessSelectedCount: selectedCount,
        accessChangeStatus: changeStatus,
        saveFeatureAccess: saveButton,
        selectAllFeatures: selectAll,
        clearAllFeatures: clearAll,
    };
    const alerts = [];
    const windowEvents = {};
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
        window: { addEventListener(name, callback) { windowEvents[name] = callback; } },
        WarehouseAlert: { confirm(options) { alerts.push(options); } },
    });

    return { schedule, picking, split, stock, selectedCount, changeStatus, deliveryCount,
        selectAll, clearAll, form, alerts, saveButton, windowEvents,
        get submitted() { return submitted; } };
}

test('dependent permissions follow their required parent and counts stay accurate', () => {
    const ui = setup();
    ui.schedule.checked = false;
    ui.schedule.events.change();
    ui.picking.checked = true;
    ui.picking.events.change();
    assert.equal(ui.schedule.checked, true);
    assert.equal(ui.deliveryCount.textContent, '2 dari 3 dipilih');

    ui.schedule.checked = false;
    ui.schedule.events.change();
    assert.equal(ui.picking.checked, false);
    assert.equal(ui.selectedCount.textContent, '1 dari 4 fitur dipilih');
    assert.equal(ui.changeStatus.textContent, '1 pilihan belum disimpan.');
});

test('empty access is explained before saving and dirty navigation stays guarded', () => {
    const ui = setup();
    ui.clearAll.events.click();
    assert.equal(ui.selectedCount.textContent, '0 dari 4 fitur dipilih');
    const leave = { preventDefault() { this.prevented = true; }, returnValue: undefined };
    ui.windowEvents.beforeunload(leave);
    assert.equal(leave.prevented, true);

    const submit = { preventDefault() { this.prevented = true; } };
    ui.form.events.submit(submit);
    assert.equal(submit.prevented, true);
    assert.equal(ui.submitted, 0);
    assert.match(ui.alerts[0].warningItems[0], /Semua fitur/);
    ui.alerts[0].onConfirm();
    assert.equal(ui.submitted, 1);
    assert.equal(ui.saveButton.disabled, true);
});
