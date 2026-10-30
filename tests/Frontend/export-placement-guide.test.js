import assert from 'node:assert/strict';
import test from 'node:test';
import {initPlacementGuide} from '../../resources/js/export-stock/placement-guide.mjs';

function setup(withSelect = true) {
    const links = [{dataset: {placementGuideLink: '/export/receiving/transactions/1/print'}}];
    const select = {value: '', selectedOptions: [{dataset: {}}], events: {}, addEventListener(name, fn) { this.events[name] = fn; }};
    const pallet = {}, location = {}, banner = {classList: {toggle(name, active) { banner.unassigned = active; }},
        querySelector(selector) { return selector === '[data-guide-pallet]' ? pallet : location; }};
    const windowRef = {events: {}, addEventListener(name, fn) { this.events[name] = fn; }};
    const documentRef = {baseURI: 'https://warehouse.test/export/receiving/transactions/1', querySelectorAll: () => links,
        getElementById: () => withSelect ? select : null, querySelector: () => withSelect ? banner : null};
    initPlacementGuide({documentRef, windowRef});
    return {links, select, pallet, location, banner, windowRef};
}

test('print target and highlighted destination follow the chosen spare location and clear stale choices', () => {
    const ui = setup();
    ui.select.value = 'location:12'; ui.select.selectedOptions = [{dataset: {guidePallet: 'Pallet cadangan', guideLocation: 'A1'}}];
    ui.select.events.change();
    assert.equal(new URL(ui.links[0].href).searchParams.get('spare_destination'), 'location:12');
    assert.equal(ui.location.textContent, 'A1'); assert.equal(ui.banner.unassigned, false);
    ui.select.value = 'pallet:8'; ui.select.selectedOptions = [{dataset: {guidePallet: 'Pallet 2', guideLocation: 'B2'}}]; ui.select.events.change();
    assert.equal(ui.pallet.textContent, 'Pallet 2'); assert.equal(ui.location.textContent, 'B2');
    ui.select.value = ''; ui.select.selectedOptions = [{dataset: {}}]; ui.select.events.change();
    assert.equal(new URL(ui.links[0].href).searchParams.has('spare_destination'), false);
    assert.equal(ui.location.textContent, 'Belum dipilih'); assert.equal(ui.banner.unassigned, true);
});

test('browser restoration updates print links, and receipts without spare controls keep native print links', () => {
    const ui = setup(); ui.select.value = 'location:9'; ui.select.selectedOptions = [{dataset: {guideLocation: 'C3'}}];
    ui.windowRef.events.pageshow();
    assert.equal(new URL(ui.links[0].href).searchParams.get('spare_destination'), 'location:9');
    const plain = setup(false);
    assert.equal(plain.links[0].href, 'https://warehouse.test/export/receiving/transactions/1/print');
});
