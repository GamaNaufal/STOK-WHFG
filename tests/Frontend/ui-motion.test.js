import assert from 'node:assert/strict';
import test from 'node:test';
import {createSurfaceMotion, enhanceDisclosure, animateCue, initAccountMenuMotion} from '../../public/js/ui-motion.js';

class Events {
    listeners = new Map();
    addEventListener(name, callback) {
        if (!this.listeners.has(name)) this.listeners.set(name, new Set());
        this.listeners.get(name).add(callback);
    }
    removeEventListener(name, callback) { this.listeners.get(name)?.delete(callback); }
    emit(name, event = {}) { for (const callback of [...this.listeners.get(name) ?? []]) callback(event); }
}

function surface(hidden = true) {
    const media = Object.assign(new Events(), {matches: false});
    const view = Object.assign(new Events(), {
        matchMedia: () => media,
        getComputedStyle: element => element.presentation ?? {opacity: '1', transform: 'none'},
    });
    const doc = Object.assign(new Events(), {defaultView: view});
    const element = Object.assign(new Events(), {
        hidden, ownerDocument: doc, attrs: {}, animations: [],
        setAttribute(name, value) { this.attrs[name] = value; },
        animate(frames, options) {
            let resolve, reject;
            const animation = {frames, options, cancelled: false,
                finished: new Promise((yes, no) => { resolve = yes; reject = no; }),
                finish() { resolve(); }, cancel() { this.cancelled = true; reject(new Error('cancelled')); },
            };
            this.animations.push(animation); return animation;
        },
    });
    return {element, doc, view, media};
}

test('keyboard/default, reduced-motion and unsupported WAAPI keep visibility immediate', async () => {
    for (const mode of ['keyboard', 'reduced', 'fallback']) {
        const ui = surface();
        if (mode === 'reduced') ui.media.matches = true;
        if (mode === 'fallback') ui.element.animate = undefined;
        const controller = createSurfaceMotion(ui.element);
        assert.equal(await controller.setVisible(true, {animate: mode !== 'keyboard'}), true);
        assert.equal(ui.element.hidden, false);
        assert.equal(ui.element.inert, false);
        assert.equal(ui.element.animations.length, 0);
    }
});

test('a rapid reversal samples the visible pose and a stale completion cannot hide reopened content', async () => {
    const ui = surface(false), controller = createSurfaceMotion(ui.element);
    const closing = controller.setVisible(false, {animate: true});
    assert.equal(ui.element.inert, true);
    ui.element.presentation = {opacity: '.42', transform: 'matrix(1, 0, 0, 1, 0, 2)'};
    const opening = controller.setVisible(true, {animate: true});
    assert.equal(await closing, false);
    assert.deepEqual(ui.element.animations[1].frames[0], ui.element.presentation);
    ui.element.animations[0].finish();
    ui.element.animations[1].finish();
    assert.equal(await opening, true);
    assert.equal(ui.element.hidden, false);
    assert.equal(ui.element.inert, false);
});

test('keyboard, focus and preference changes settle to the requested state without waiting', async () => {
    for (const event of ['keyboard', 'focus', 'media']) {
        const ui = surface(false), controller = createSurfaceMotion(ui.element);
        const closing = controller.setVisible(false, {animate: true});
        if (event === 'keyboard') ui.doc.emit('keydown');
        if (event === 'focus') ui.element.emit('focusin');
        if (event === 'media') { ui.media.matches = true; ui.media.emit('change'); }
        assert.equal(ui.element.hidden, true);
        assert.equal(await closing, true);
    }
});

function disclosure() {
    const ui = surface(false), summary = new Events();
    const details = {open: true, dataset: {}, ownerDocument: ui.doc,
        querySelector: selector => selector === 'summary' ? summary : ui.element};
    return {...ui, summary, details, controller: enhanceDisclosure(details)};
}

test('validation can reopen a disclosure during its closing effect, keeping its native open state correct', async () => {
    const ui = disclosure();
    const closing = ui.controller.setOpen(false, {animate: true});
    assert.equal(ui.details.open, true);
    await ui.controller.setOpen(true); // The invalid handler requests an immediate reveal.
    await closing;
    assert.equal(ui.details.open, true);
    assert.equal(ui.element.hidden, false);
    assert.equal(ui.element.inert, false);
    ui.element.animations[0].finish();
    await Promise.resolve();
    assert.equal(ui.details.open, true);
});

test('keyboard summary activation closes immediately and focusable fields are hidden before default tab movement', async () => {
    const ui = disclosure();
    let prevented = false;
    ui.summary.emit('click', {detail: 0, preventDefault() { prevented = true; }});
    assert.equal(prevented, true);
    assert.equal(ui.element.hidden, true);
    assert.equal(ui.element.animations.length, 0);
    await Promise.resolve();
    assert.equal(ui.details.open, false);
});

test('completion cues can be interrupted by keyboard and remove their transient listeners', async () => {
    const ui = surface(false);
    animateCue(ui.element, {animate: true});
    assert.equal(ui.element.animations.length, 1);
    ui.doc.emit('keydown');
    assert.equal(ui.element.animations[0].cancelled, true);
    await Promise.resolve();
    assert.equal(ui.doc.listeners.get('keydown').size, 0);
    assert.equal(ui.media.listeners.get('change').size, 0);
});

test('account motion follows a pointer dispatch but never leaks into later API or keyboard opens', () => {
    const ui = surface(), trigger = new Events(), menu = {dataset: {}};
    ui.doc.querySelector = selector => selector === '.avatar-trigger' ? trigger : menu;
    initAccountMenuMotion(ui.doc);
    const click = {detail: 1, eventPhase: 3};
    ui.view.emit('click', click); trigger.emit('show.bs.dropdown');
    assert.equal(menu.dataset.uiMotion, 'true');
    click.eventPhase = 0; trigger.emit('show.bs.dropdown');
    assert.equal(menu.dataset.uiMotion, 'false');
    ui.view.emit('keydown'); trigger.emit('hide.bs.dropdown');
    assert.equal(menu.dataset.uiMotion, 'false');
    trigger.emit('hidden.bs.dropdown'); assert.equal(menu.inert, true);
    trigger.emit('shown.bs.dropdown'); assert.equal(menu.inert, false);
});
