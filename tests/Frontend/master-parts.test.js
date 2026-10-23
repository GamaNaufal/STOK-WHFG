import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../public/js/pages/master-parts.js', import.meta.url), 'utf8');

function setup() {
    const element = (extra = {}) => ({
        events: {},
        addEventListener(name, handler) { this.events[name] = handler; },
        focus() { this.focused = true; },
        ...extra,
    });
    const page = element({ dataset: { searchUrl: '/part-settings/search', baseUrl: '/part-settings', csrfToken: 'csrf-test' } });
    const searchInput = element({ value: '' });
    const clearButton = element({ hidden: true });
    const tableBody = element({ innerHTML: '<tr><td>Daftar awal</td></tr>' });
    const pagination = element({ hidden: false });
    const status = element({ textContent: 'Menampilkan 1–10 dari 11 nomor part' });
    const elements = {
        'master-parts': page,
        'part-search': searchInput,
        'part-search-clear': clearButton,
        'part-table-body': tableBody,
        'part-pagination': pagination,
        'part-result-status': status,
    };
    const requests = [];
    const alerts = [];
    const submitted = [];
    let timer = null;
    const document = {
        getElementById(id) { return elements[id] ?? null; },
        createElement(tag) {
            const node = { tag, children: [], appendChild(child) { this.children.push(child); } };
            if (tag === 'form') node.submit = () => submitted.push(node);
            return node;
        },
        body: { appendChild() {} },
    };
    vm.runInNewContext(source, {
        document,
        AbortController,
        fetch: (url, options) => new Promise((resolve) => requests.push({ url, options, resolve })),
        WarehouseAlert: { delete: (options) => alerts.push(options) },
        setTimeout: (callback) => { timer = callback; return 1; },
        clearTimeout: () => { timer = null; },
    });
    return {
        searchInput, clearButton, tableBody, pagination, status, requests, alerts, submitted,
        runTimer() { const callback = timer; timer = null; callback?.(); },
    };
}

async function settle(request, items) {
    request.resolve({ ok: true, json: async () => ({ data: items }) });
    await new Promise((resolve) => setImmediate(resolve));
}

test('search uses PCS per box, then clear restores the original page without navigation', async () => {
    const ui = setup();
    ui.searchInput.value = 'PART';
    ui.searchInput.events.input();
    ui.runTimer();
    assert.equal(ui.requests[0].url, '/part-settings/search?q=PART');
    await settle(ui.requests[0], [{ id: 7, part_number: 'PART-&-7', qty_box: 120 }]);
    assert.match(ui.tableBody.innerHTML, /PART-&amp;-7/);
    assert.match(ui.tableBody.innerHTML, /120<\/strong> PCS \/ box/);
    assert.equal(ui.pagination.hidden, true);
    assert.equal(ui.status.textContent, '1 nomor part cocok dengan pencarian.');

    ui.clearButton.events.click();
    assert.equal(ui.tableBody.innerHTML, '<tr><td>Daftar awal</td></tr>');
    assert.equal(ui.pagination.hidden, false);
    assert.equal(ui.searchInput.focused, true);
});

test('old search responses cannot replace newer results and delete confirmation escapes part names', async () => {
    const ui = setup();
    ui.searchInput.value = 'lama';
    ui.searchInput.events.input();
    ui.runTimer();
    ui.searchInput.value = 'baru';
    ui.searchInput.events.input();
    ui.runTimer();
    await settle(ui.requests[1], [{ id: 8, part_number: 'PART-BARU', qty_box: 25 }]);
    await settle(ui.requests[0], [{ id: 9, part_number: 'PART-LAMA', qty_box: 50 }]);
    assert.match(ui.tableBody.innerHTML, /PART-BARU/);
    assert.doesNotMatch(ui.tableBody.innerHTML, /PART-LAMA/);

    const button = { dataset: { deleteUrl: '/part-settings/8', partNumber: '<PART&8>' } };
    ui.tableBody.events.click({ target: { closest: () => button } });
    assert.match(ui.alerts[0].itemName, /&lt;PART&amp;8&gt;/);
    ui.alerts[0].onConfirm();
    assert.equal(ui.submitted[0].action, '/part-settings/8');
    assert.deepEqual(ui.submitted[0].children.map((child) => [child.name, child.value]), [
        ['_token', 'csrf-test'],
        ['_method', 'DELETE'],
    ]);
});
