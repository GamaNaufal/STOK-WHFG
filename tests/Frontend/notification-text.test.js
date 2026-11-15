import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

test('toast shows an untrusted name literally without adding it to the markup', () => {
    const messageNode = { textContent: '' };
    const toastNode = { setAttribute() {}, addEventListener() {}, querySelector() { return messageNode; } };
    const context = {
        window: {},
        document: { getElementById() { return { appendChild() {} }; }, createElement() { return toastNode; } },
        bootstrap: { Toast: class { show() {} } },
    };
    vm.runInNewContext(readFileSync(new URL('../../public/js/toast.js', import.meta.url), 'utf8'), context);
    const name = '<img src=x onerror="throw 1">';
    context.window.showToast(name, 'danger');
    assert.equal(messageNode.textContent, name);
    assert.equal(toastNode.innerHTML.includes(name), false);
});

test('error fallback sends title and message through SweetAlert text fields', () => {
    let options;
    const context = { Swal: { fire(value) { options = value; } } };
    vm.runInNewContext(readFileSync(new URL('../../public/js/alert-helper.js', import.meta.url), 'utf8')
        + '\nWarehouseAlert.error({ title: "<b>name</b>", message: "<img src=x onerror=throw(1)>" });', context);
    assert.equal(options.titleText, '<b>name</b>');
    assert.equal(options.text, '<img src=x onerror=throw(1)>');
    assert.equal(options.html, undefined);
});

test('completion emphasis is opt-in for success, keeps untrusted text literal and removes the toast on dismissal', () => {
    for (const [type, options, emphasized] of [
        ['success', undefined, false],
        ['success', {emphasis: true}, true],
        ['danger', {emphasis: true}, false],
    ]) {
        const messageNode = {textContent: ''}, attributes = {}, events = {};
        let removed = false, appended = false, delay;
        const toastNode = {
            setAttribute(key, value) { attributes[key] = value; },
            addEventListener(key, callback) { events[key] = callback; },
            querySelector() { return messageNode; },
            remove() { removed = true; },
        };
        const context = {
            window: {},
            document: {getElementById() { return {appendChild() { appended = true; }}; }, createElement() { return toastNode; }},
            bootstrap: {Toast: class {constructor(element, config) { delay = config.delay; } show() {}}},
        };
        vm.runInNewContext(readFileSync(new URL('../../public/js/toast.js', import.meta.url), 'utf8'), context);
        const message = '<img src=x onerror="throw 1">';
        context.window.showToast(message, type, options);
        assert.equal(toastNode.className.includes('toast-completion'), emphasized);
        assert.equal(attributes.role, emphasized ? 'status' : 'alert');
        assert.equal(attributes['aria-live'], emphasized ? 'polite' : 'assertive');
        assert.equal(messageNode.textContent, message);
        assert.equal(toastNode.innerHTML.includes(message), false);
        assert.equal(delay, 3500);
        assert.equal(appended, true);
        events['hidden.bs.toast']();
        assert.equal(removed, true);
    }
});
