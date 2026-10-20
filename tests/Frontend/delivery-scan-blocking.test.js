import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const flush = () => new Promise(setImmediate);

function setup(view) {
    const source = readFileSync(new URL(`../../resources/views/operator/delivery/${view}.blade.php`, import.meta.url), 'utf8');
    const script = source.match(/<script>([\s\S]*?)<\/script>/)?.[1];
    const elements = new Map();
    const requests = [];
    const element = (id) => {
        if (!elements.has(id)) {
            const classes = new Set();
            elements.set(id, {
                disabled: false, hidden: true, value: '', textContent: '1', innerHTML: '',
                dataset: { canCancelBlocked: '0' },
                classList: {
                    add(name) { classes.add(name); },
                    remove(name) { classes.delete(name); },
                    contains(name) { return classes.has(name); },
                },
                addEventListener(name, callback) { this[name] = callback; },
                setAttribute(name, value) { this[name] = value; },
                focus() {},
            });
        }
        return elements.get(id);
    };
    vm.runInNewContext(script, {
        Promise,
        window: { ScanFeedback: { success() {}, error() {} } },
        document: {
            getElementById: element,
            querySelectorAll() { return []; },
        },
        fetch: async (_url, options) => {
            requests.push(JSON.parse(options.body));
            return {
                status: 422,
                json: async () => view === 'scan'
                    ? { success: false, message: 'Sesi diblokir.', session_status: 'blocked' }
                    : { success: false, message: 'Box tidak sesuai.', session_status: null },
            };
        },
        setTimeout() {},
        showToast() {},
    });
    return { element, requests };
}

for (const wrongCode of ['87654321', '1', 'abc', '']) {
    test(`scan: submitted ${wrongCode || 'empty'} scan blocks UI and discards queued scans`, async () => {
        const app = setup('scan');
        const input = app.element('scanInput');
        input.value = wrongCode;
        input.keydown({ key: 'Enter', preventDefault() {} });
        input.value = '12345678';
        input.keydown({ key: 'Enter', preventDefault() {} });
        await flush();
        await flush();

        assert.deepEqual(app.requests, [{ box_number: wrongCode }]);
        assert.equal(input.disabled, true);
        assert.equal(app.element('btnScan').disabled, true);
        assert.equal(app.element('sessionStatus').textContent, 'BLOCKED');
        assert.equal(app.element('btnComplete').disabled, true);
        assert.equal(app.element('scanBlockedNotice').hidden, false);
        assert.equal(app.element('btnCancelScan').disabled, true);
    });

    test(`verify-scan: submitted ${wrongCode || 'empty'} scan keeps UI active and processes queued scans`, async () => {
        const app = setup('verify-scan');
        const input = app.element('scanInput');
        input.value = wrongCode;
        input.keydown({ key: 'Enter', preventDefault() {} });
        input.value = '12345678';
        input.keydown({ key: 'Enter', preventDefault() {} });
        await flush();
        await flush();
        await flush();

        assert.deepEqual(app.requests, [{ box_number: wrongCode }, { box_number: '12345678' }]);
        assert.equal(input.disabled, false);
        assert.equal(app.element('btnScan').disabled, false);
        assert.equal(app.element('verificationBlockedNotice').hidden, true);
        assert.equal(app.element('btnCancelVerification').disabled, false);
    });
}
