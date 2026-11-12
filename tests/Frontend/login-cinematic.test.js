import test from 'node:test';
import assert from 'node:assert/strict';
import {initLoginCinema} from '../../public/js/pages/login-cinematic.js';

function events(extra = {}) {
    const listeners = new Map();
    return {...extra,
        addEventListener(name, callback) {
            const handlers = listeners.get(name) ?? [];
            handlers.push(callback);
            listeners.set(name, handlers);
        },
        emit(name) { for (const callback of listeners.get(name) ?? []) callback(); },
    };
}

function ui({desktop = true, reduced = false, saveData = false, effectiveType = '4g', rejectPlay = false, hidden = false} = {}) {
    const attributes = new Map();
    const classes = new Set();
    const classList = {
        add: value => classes.add(value), remove: value => classes.delete(value),
        contains: value => classes.has(value),
        toggle(value, enabled) { if (enabled) classes.add(value); else classes.delete(value); },
    };
    const video = events({dataset: {src: '/media/warehouse-login/warehouse_fg_login_desktop.mp4'},
        paused: true, loads: 0, plays: 0, muted: false,
        getAttribute: key => attributes.get(key) ?? null,
        setAttribute: (key, value) => attributes.set(key, value),
        removeAttribute: key => attributes.delete(key),
        hasAttribute: key => attributes.has(key),
        load() { this.loads++; },
        play() {
            this.plays++;
            if (rejectPlay) return Promise.reject(new Error('Autoplay blocked'));
            this.paused = false;
            this.emit('playing');
            return Promise.resolve();
        },
        pause() {
            const changed = !this.paused;
            this.paused = true;
            if (changed) this.emit('pause');
        },
    });
    const label = {textContent: ''};
    const icon = {classList};
    const toggle = events({hidden: true, attributes: {},
        querySelector: selector => selector.includes('label') ? label : icon,
        setAttribute(key, value) { this.attributes[key] = value; },
    });
    const cinema = {classList, querySelector: selector => selector === '[data-login-video]' ? video : toggle};
    const documentRef = events({hidden, querySelector: () => cinema});
    const queries = {
        '(min-width: 1024px)': events({matches: desktop}),
        '(prefers-reduced-motion: reduce)': events({matches: reduced}),
    };
    const connection = events({saveData, effectiveType});
    const windowRef = events({navigator: {connection}, matchMedia: query => queries[query]});
    const controller = initLoginCinema({documentRef, windowRef});
    return {video, toggle, label, cinema, documentRef, windowRef, connection, controller,
        desktop: queries['(min-width: 1024px)'], reduced: queries['(prefers-reduced-motion: reduce)']};
}

const settle = () => new Promise(resolve => setImmediate(resolve));

test('mobile, reduced motion, data saver, slow connections and background tabs never load video', () => {
    for (const options of [{desktop: false}, {reduced: true}, {saveData: true}, {effectiveType: '2g'}, {effectiveType: 'slow-2g'}, {hidden: true}]) {
        const page = ui(options);
        assert.equal(page.video.getAttribute('src'), null, JSON.stringify(options));
        assert.equal(page.video.loads, 0);
        assert.equal(page.video.plays, 0);
        assert.equal(page.toggle.hidden, true);
    }
});

test('eligible desktop loads only the optimized asset and exposes a pause control', async () => {
    const page = ui();
    await settle();
    assert.equal(page.video.getAttribute('src'), '/media/warehouse-login/warehouse_fg_login_desktop.mp4');
    assert.equal(page.video.muted, true);
    assert.equal(page.cinema.classList.contains('has-video-frame'), true);
    assert.equal(page.toggle.hidden, false);
    assert.equal(page.toggle.attributes['aria-label'], 'Jeda animasi gudang');
    page.toggle.emit('click');
    assert.equal(page.video.paused, true);
    assert.equal(page.label.textContent, 'Putar animasi');
    page.documentRef.hidden = true;
    page.documentRef.emit('visibilitychange');
    page.documentRef.hidden = false;
    page.documentRef.emit('visibilitychange');
    assert.equal(page.video.paused, true, 'manual pause survives a background tab');
    page.toggle.emit('click');
    await settle();
    assert.equal(page.video.paused, false);
});

test('background tabs pause and resume automatically without reloading the asset', async () => {
    const page = ui();
    await settle();
    page.documentRef.hidden = true;
    page.documentRef.emit('visibilitychange');
    assert.equal(page.video.paused, true);
    page.documentRef.hidden = false;
    page.documentRef.emit('visibilitychange');
    await settle();
    assert.equal(page.video.paused, false);
    assert.equal(page.video.loads, 1);
});

test('a changed motion or data preference releases the video and restores the poster', async () => {
    const page = ui();
    await settle();
    page.reduced.matches = true;
    page.reduced.emit('change');
    assert.equal(page.video.getAttribute('src'), null);
    assert.equal(page.video.paused, true);
    assert.equal(page.cinema.classList.contains('has-video-frame'), false);
    assert.equal(page.toggle.hidden, true);
    page.reduced.matches = false;
    page.reduced.emit('change');
    await settle();
    assert.equal(page.video.paused, false);
    page.connection.saveData = true;
    page.connection.emit('change');
    assert.equal(page.video.getAttribute('src'), null);
    assert.equal(page.toggle.hidden, true);
});

test('autoplay rejection preserves the poster and offers explicit playback', async () => {
    const page = ui({rejectPlay: true});
    await settle();
    assert.equal(page.cinema.classList.contains('has-video-frame'), false);
    assert.equal(page.toggle.hidden, false);
    assert.equal(page.label.textContent, 'Putar animasi');
    page.documentRef.emit('visibilitychange');
    assert.equal(page.video.plays, 1, 'blocked autoplay is not retried by passive events');
    page.toggle.emit('click');
    await settle();
    assert.equal(page.video.plays, 2);
});

test('manual pause survives a mobile resize and can be resumed after returning to desktop', async () => {
    const page = ui();
    await settle();
    page.toggle.emit('click');
    page.desktop.matches = false;
    page.desktop.emit('change');
    assert.equal(page.video.getAttribute('src'), null);
    page.desktop.matches = true;
    page.desktop.emit('change');
    assert.equal(page.toggle.hidden, false);
    assert.equal(page.video.getAttribute('src'), null, 'paused video is not downloaded again');
    page.toggle.emit('click');
    await settle();
    assert.equal(page.video.paused, false);
    assert.ok(page.video.getAttribute('src'));
});

test('decode or network failure returns to a stable poster without retry loops', async () => {
    const page = ui();
    await settle();
    const plays = page.video.plays;
    page.video.emit('error');
    assert.equal(page.video.paused, true);
    assert.equal(page.cinema.classList.contains('has-video-frame'), false);
    assert.equal(page.toggle.hidden, true);
    page.windowRef.emit('pageshow');
    assert.equal(page.video.plays, plays);
});
