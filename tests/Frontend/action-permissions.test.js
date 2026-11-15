import test from 'node:test';
import assert from 'node:assert/strict';
import {routeRule, permits, installActionPermissions} from '../../public/js/action-permissions.mjs';

const origin = 'http://warehouse.test';
const policy = {root: '/wh', features: {revise: false, read: true}, rules: [
    {path: 'plans/{id}', methods: ['PUT'], allowed: true, where: {id: '[0-9]+'}, field: 'action', choices: {draft: true, finalize: false}},
    {path: 'plans/{id}', methods: ['DELETE'], allowed: false},
    {path: 'plans/{id}', methods: ['GET'], allowed: true},
    {path: 'review', methods: ['GET'], allowed: true, field: 'action', choices: {qa: true, packing: false}},
    {path: 'stock/pallets/{pallet}/boxes', methods: ['GET'], where: {pallet: '[0-9]+|unplaced'}, allowed: true},
]};

test('URL matching respects root, HTTP method, numeric constraints and external links', () => {
    assert.equal(routeRule(policy, origin + '/wh/plans/7', 'DELETE', origin).allowed, false);
    assert.equal(routeRule(policy, origin + '/wh/plans/7', 'GET', origin).allowed, true);
    assert.equal(routeRule(policy, origin + '/wh/plans/name', 'PUT', origin), null);
    assert.equal(routeRule(policy, origin + '/other/plans/7', 'DELETE', origin), null);
    assert.equal(routeRule(policy, 'https://external.test/wh/plans/7', 'DELETE', origin), null);
    assert.equal(routeRule(policy, origin + '/wh/stock/pallets/unplaced/boxes', 'GET', origin).allowed, true);
    assert.equal(routeRule(policy, origin + '/wh/stock/pallets/99/other', 'GET', origin), null);
});

test('dispatch distinguishes draft/finalize and reads GET action from query', () => {
    const rule = routeRule(policy, origin + '/wh/plans/7', 'PUT', origin);
    assert.equal(permits(rule, new URLSearchParams({action: 'draft'})), true);
    assert.equal(permits(rule, new URLSearchParams({action: 'finalize'})), false);
    assert.equal(permits(routeRule(policy, origin + '/wh/review?action=packing', 'GET', origin)), false);
    assert.equal(permits(null), true);
});

function setup() {
    const previous = {FormData: globalThis.FormData, MutationObserver: globalThis.MutationObserver};
    globalThis.FormData = class extends Map { constructor(form) { super(Object.entries(form.values || {})); } };
    let observe;
    globalThis.MutationObserver = class { constructor(callback) { observe = callback; } observe() {} };
    const element = (type, props = {}) => ({nodeType: 1, type, dataset: {}, hidden: false,
        matches(selector) { return selector === 'a[href]' ? type === 'link' : selector === 'form' ? type === 'form' : true; },
        querySelectorAll() { return this.buttons || []; }, ...props});
    const read = element('link', {href: origin + '/wh/plans/7', hidden: true});
    const revise = element('button', {dataset: {permission: 'revise'}, disabled: false});
    const deleteButton = element('button', {dataset: {deleteUrl: origin + '/wh/plans/7'}});
    const form = element('form', {action: origin + '/wh/plans/7', method: 'post', values: {_method: 'PUT'}});
    const draft = element('button', {name: 'action', value: 'draft', form});
    const finalize = element('button', {name: 'action', value: 'finalize', form});
    form.buttons = [draft, finalize];
    const fixed = element('form', {action: origin + '/wh/plans/7', method: 'post', values: {_method: 'PUT', action: 'finalize'}});
    const elements = [read, revise, deleteButton, form, draft, finalize, fixed], events = {};
    const doc = {nodeType: 9, documentElement: {}, querySelectorAll: () => elements,
        addEventListener: (event, callback) => { events[event] = callback; }};
    installActionPermissions(doc, policy, origin);
    return {read, revise, deleteButton, form, draft, finalize, fixed, events, element,
        add: node => observe([{addedNodes: [node]}]),
        restore() { Object.assign(globalThis, previous); }};
}

test('hides only denied controls, keeps shared forms and preserves existing business visibility', () => {
    const ui = setup();
    try {
        assert.equal(ui.revise.hidden, true);
        assert.equal(ui.revise.disabled, true);
        assert.equal(ui.deleteButton.hidden, true);
        assert.equal(ui.read.hidden, true);
        assert.equal(ui.form.hidden, false);
        assert.equal(ui.draft.hidden, false);
        assert.equal(ui.finalize.hidden, true);
        assert.equal(ui.fixed.hidden, true);
    } finally { ui.restore(); }
});

test('new modal controls are checked and capture handlers block denied clicks and submits', () => {
    const ui = setup();
    try {
        const modalButton = ui.element('button', {dataset: {permission: 'revise'}});
        ui.add(modalButton);
        assert.equal(modalButton.hidden, true);
        let blocked = 0;
        ui.events.click({target: {closest: () => modalButton}, preventDefault() { blocked++; }, stopImmediatePropagation() {}});
        ui.events.submit({target: ui.form, submitter: ui.finalize, preventDefault() { blocked++; }, stopImmediatePropagation() {}});
        assert.equal(blocked, 2);
        ui.events.submit({target: ui.form, submitter: ui.draft, preventDefault() { blocked++; }, stopImmediatePropagation() {}});
        assert.equal(blocked, 2);
    } finally { ui.restore(); }
});
