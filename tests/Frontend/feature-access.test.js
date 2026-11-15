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
    const rows = ['Jadwal delivery lokal', 'Picking verifikasi lokal', 'Split delivery lokal', 'Lihat stok ekspor']
        .map(text => element({dataset: {permissionSearch: text}, hidden: false}));
    const deliveryCount = element();
    const stockCount = element();
    const groups = [
        { querySelector: () => deliveryCount, querySelectorAll: selector => selector === '[data-permission-row]' ? rows.slice(0, 3) : [schedule, picking, split] },
        { querySelector: () => stockCount, querySelectorAll: selector => selector === '[data-permission-row]' ? rows.slice(3) : [stock] },
    ];
    let submitted = 0;
    const form = element({
        dataset: { roleLabel: 'Operator Warehouse' },
        querySelectorAll(selector) { return selector === '[data-access-group]' ? groups : selector === '[data-permission-row]' ? rows : boxes; },
        submit() { submitted += 1; },
    });
    const selectedCount = element();
    const changeStatus = element();
    const saveButton = element({ disabled: false });
    const selectAll = element();
    const clearAll = element();
    const search = element({value: ''});
    const searchStatus = element();
    const expandGroups = element();
    const collapseGroups = element();
    const groupControls = element({hidden: true});
    const elements = {
        featureAccessForm: form,
        accessSelectedCount: selectedCount,
        accessChangeStatus: changeStatus,
        saveFeatureAccess: saveButton,
        selectAllFeatures: selectAll,
        clearAllFeatures: clearAll,
        accessPermissionSearch: search,
        accessSearchStatus: searchStatus,
        expandAccessGroups: expandGroups,
        collapseAccessGroups: collapseGroups,
        accessGroupControls: groupControls,
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
        selectAll, clearAll, form, alerts, saveButton, windowEvents, search, rows, groups,
        searchStatus, expandGroups, collapseGroups, groupControls,
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

test('search matches all words and hides empty groups without changing grants', () => {
    const ui = setup();
    ui.search.value = '  stok  EKSPOR ';
    ui.search.events.input();
    assert.deepEqual(ui.rows.map(row => row.hidden), [true, true, true, false]);
    assert.equal(ui.groups[0].hidden, true);
    assert.equal(ui.stock.checked, true);
    ui.search.value = '';
    ui.search.events.input();
    assert.ok(ui.rows.every(row => !row.hidden));
    assert.equal(ui.groups[0].hidden, false);
});

test('nested actions select and revoke all prerequisite levels', () => {
    const ui = setup();
    ui.split.dataset.requires = '["delivery_pick"]';
    ui.schedule.checked = false;
    ui.split.checked = true;
    ui.split.events.change();
    assert.equal(ui.picking.checked, true);
    assert.equal(ui.schedule.checked, true);
    ui.schedule.checked = false;
    ui.schedule.events.change();
    assert.equal(ui.picking.checked, false);
    assert.equal(ui.split.checked, false);
});

test('pasted full-width search and non-breaking spaces preserve the selected permissions', () => {
    const ui = setup();
    ui.search.value = '\u3000ＳＴＯＫ\u00a0ＥＫＳＰＯＲ\u3000';
    ui.search.events.input();
    assert.deepEqual(ui.rows.map(row => row.hidden), [true, true, true, false]);
    assert.equal(ui.stock.checked, true);
    assert.equal(ui.schedule.checked, true);
    assert.equal(ui.picking.checked, false);
    assert.equal(ui.changeStatus.textContent, 'Belum ada perubahan.');
    ui.search.value = '';
    ui.search.events.input();
    assert.ok(ui.rows.every(row => !row.hidden));
    assert.equal(ui.selectedCount.textContent, '2 dari 4 fitur dipilih');
});

test('search opens matching chunks and restores the chosen disclosure state when cleared', () => {
    const ui = setup();
    assert.equal(ui.groupControls.hidden, false);
    assert.deepEqual(ui.groups.map(group => group.open), [true, false]);
    ui.collapseGroups.events.click();
    assert.ok(ui.groups.every(group => !group.open));
    ui.search.value = 'stok';
    ui.search.events.input();
    assert.equal(ui.groups[1].open, true);
    assert.equal(ui.expandGroups.disabled, true);
    assert.match(ui.searchStatus.textContent, /1 dari 4/);
    ui.search.value = 'stok ekspor';
    ui.search.events.input();
    ui.search.value = '';
    ui.search.events.input();
    assert.ok(ui.groups.every(group => !group.open));
    assert.equal(ui.expandGroups.disabled, false);
    ui.expandGroups.events.click();
    assert.ok(ui.groups.every(group => group.open));
    assert.equal(ui.schedule.checked, true);
    assert.equal(ui.stock.checked, true);
    assert.equal(ui.changeStatus.textContent, 'Belum ada perubahan.');
});

test('empty search results explain recovery while hidden permissions retain dependency behavior', () => {
    const ui = setup();
    ui.search.value = 'tidak ditemukan';
    ui.search.events.input();
    assert.ok(ui.groups.every(group => group.hidden));
    assert.match(ui.searchStatus.textContent, /Tidak ada menu/);
    ui.selectAll.events.click();
    assert.equal(ui.selectedCount.textContent, '4 dari 4 fitur dipilih');
    ui.clearAll.events.click();
    assert.equal(ui.selectedCount.textContent, '0 dari 4 fitur dipilih');
    ui.picking.checked = true;
    ui.picking.events.change();
    assert.equal(ui.schedule.checked, true);
    ui.search.value = '';
    ui.search.events.input();
    assert.equal(ui.picking.checked, true);
    assert.equal(ui.schedule.checked, true);
    assert.equal(ui.stock.checked, false);
    assert.equal(ui.selectedCount.textContent, '2 dari 4 fitur dipilih');
});
