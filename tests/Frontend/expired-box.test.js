import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/expired-box.js', import.meta.url), 'utf8');

function element(extra = {}) {
    return {
        events: {},
        addEventListener(name, handler) { this.events[name] = handler; },
        focus() { this.focused = true; },
        ...extra,
    };
}

function setup({ withLocation = false, rows = true } = {}) {
    const searchUi = element({ hidden: true });
    const searchInput = element({ value: '' });
    const clearSearch = element();
    const counts = { expired: element(), warning: element() };
    const empty = { expired: element({ hidden: true }), warning: element({ hidden: true }) };
    const lists = {
        expired: rows ? [element({ hidden: false, dataset: { search: '99110001 PART-A PALLET-A R1' } })] : [],
        warning: rows ? [element({ hidden: false, dataset: { search: '99220002 PART-B PALLET-B R2' } })] : [],
    };
    const button = element({ dataset: { boxId: '7', boxNumber: '<Box&7>', age: '13 bulan', handleUrl: '/expired-box/7/handle' }, disabled: false });
    const location = withLocation ? element({ value: '', selectedOptions: [{ textContent: ' R-01 ' }] }) : null;
    const alerts = [];
    const toasts = [];
    const submitted = [];
    const page = {
        dataset: { csrfToken: 'csrf-test' },
        querySelector(selector) {
            if (selector === '[data-search-ui]') return searchUi;
            if (selector === '[data-box-search]') return searchInput;
            if (selector === '[data-clear-search]') return clearSearch;
            if (selector.includes('data-visible-count')) return counts[selector.includes('expired') ? 'expired' : 'warning'];
            if (selector.includes('data-filter-empty')) return empty[selector.includes('expired') ? 'expired' : 'warning'];
            if (selector.startsWith('.js-handle-location')) return location;
            return null;
        },
        querySelectorAll(selector) {
            if (selector === '.js-handle-btn') return [button];
            if (selector.includes('data-filter-row')) return lists[selector.includes('expired') ? 'expired' : 'warning'];
            return [];
        },
    };
    const document = {
        readyState: 'complete',
        getElementById: id => id === 'expired-box-page' ? page : null,
        body: { appendChild() {} },
        createElement(tag) {
            const node = { tag, children: [], appendChild(child) { this.children.push(child); } };
            if (tag === 'form') node.submit = () => submitted.push(node);
            return node;
        },
    };
    vm.runInNewContext(source, {
        document,
        window: { showToast: (...args) => toasts.push(args) },
        WarehouseAlert: { confirm: options => alerts.push(options) },
    });
    return { searchUi, searchInput, clearSearch, counts, empty, lists, button, location, alerts, toasts, submitted };
}

test('search scopes to live expired and warning lists, then reset restores rows', () => {
    const h = setup();
    assert.equal(h.searchUi.hidden, false);
    h.searchInput.value = 'part-b';
    h.searchInput.events.input();
    assert.equal(h.lists.expired[0].hidden, true);
    assert.equal(h.lists.warning[0].hidden, false);
    assert.equal(h.empty.expired.hidden, false);
    assert.match(h.counts.expired.textContent, /0 dari 1/);
    h.clearSearch.events.click();
    assert.equal(h.lists.expired[0].hidden, false);
    assert.equal(h.empty.expired.hidden, true);
    assert.equal(h.searchInput.focused, true);
});

test('empty source lists keep their server empty state during search', () => {
    const h = setup({ rows: false });
    h.searchInput.value = 'missing';
    h.searchInput.events.input();
    assert.equal(h.empty.expired.hidden, true);
    assert.equal(h.empty.warning.hidden, true);
});

test('Handle requires a location, escapes box text, and submits once after confirmation', () => {
    const h = setup({ withLocation: true });
    h.button.events.click();
    assert.equal(h.alerts.length, 0);
    assert.equal(h.location.focused, true);
    assert.equal(h.toasts.length, 1);

    h.location.value = '4';
    h.button.events.click();
    assert.equal(h.alerts.length, 1);
    assert.match(h.alerts[0].message, /&lt;Box&amp;7&gt;/);
    assert.match(h.alerts[0].infoText, /R-01/);
    h.alerts[0].onConfirm();
    h.alerts[0].onConfirm();
    assert.equal(h.submitted.length, 1);
    assert.equal(h.submitted[0].action, '/expired-box/7/handle');
    assert.deepEqual(h.submitted[0].children.map(input => [input.name, input.value]), [['_token', 'csrf-test'], ['location_id', '4']]);
});
