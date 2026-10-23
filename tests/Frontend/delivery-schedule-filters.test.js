import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/delivery-schedule-filters.js', import.meta.url), 'utf8');
function setup(data = [['12 Customer Alfa PART-A', 'overdue'], ['25 Customer Beta PART-B', 'today'], ['38 Customer Gamma PART-C', 'upcoming']]) {
    const elements = Object.fromEntries(['filters', 'results', 'search', 'timing', 'filter-count', 'no-results'].map(key => [key, { value: '', hidden: true, events: {}, addEventListener(name, fn) { this.events[name] = fn; }, focus() { this.focused = true; } }]));
    const rows = data.map(([text, timing]) => ({ hidden: false, dataset: { scheduleSearch: text, scheduleTiming: timing } }));
    elements.results.querySelectorAll = () => rows;
    elements.timing.value = 'all';
    vm.runInNewContext(source, { document: { getElementById: id => elements[id.replace('delivery-schedule-', '')] } });
    return { ...elements, rows, event: { preventDefault() {} } };
}
test('search matches customer, part and hash-prefixed order without reordering rows', () => {
    const h = setup();
    for (const query of ['  BETA ', 'part-b', '#25']) {
        h.search.value = query; h.search.events.input();
        assert.deepEqual(h.rows.map(row => row.hidden), [true, false, true]);
    }
    assert.equal(h.filters.hidden, false);
});
test('timing combines with search, reports no match, reset restores all and focuses search', () => {
    const h = setup(); h.search.value = 'Alfa'; h.timing.value = 'today'; h.timing.events.change();
    assert.equal(h['no-results'].hidden, false);
    assert.match(h['filter-count'].textContent, /0 dari 3/);
    h.filters.events.reset(h.event);
    assert.equal(h.search.value, ''); assert.equal(h.search.focused, true);
    assert.equal(h.rows.every(row => !row.hidden), true);
    assert.equal(h['no-results'].hidden, true);
});
test('each timing value selects its existing group; Enter applies without navigation', () => {
    const h = setup();
    for (const [index, timing] of ['overdue', 'today', 'upcoming'].entries()) {
        h.timing.value = timing; h.filters.events.submit(h.event);
        assert.deepEqual(h.rows.map(row => row.hidden), [0, 1, 2].map(i => i !== index));
    }
});
test('empty dataset retains server empty state instead of claiming filtered results', () => {
    const h = setup([]); h.search.value = 'missing'; h.search.events.input();
    assert.equal(h['no-results'].hidden, true);
    assert.match(h['filter-count'].textContent, /0 dari 0/);
});
