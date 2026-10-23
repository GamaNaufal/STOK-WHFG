import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

test('status navigation does not fetch scan audio, while scan controls prepare it', () => {
    const source = readFileSync(new URL('../../public/js/scan-feedback.js', import.meta.url), 'utf8');
    const listeners = new Map();
    const requests = [];
    class AudioContext {
        state = 'running';
        destination = {};
        createGain() { return { gain: { value: 0 }, connect() {} }; }
        createDynamicsCompressor() {
            return { threshold: {}, knee: {}, ratio: {}, attack: {}, release: {}, connect() {} };
        }
        decodeAudioData() { return Promise.resolve({}); }
    }
    vm.runInNewContext(source, {
        document: {
            currentScript: { dataset: { ok: '/audio/OK.mp3', ng: '/audio/NG.mp3' } },
            addEventListener(name, handler) { listeners.set(name, handler); },
        },
        window: { AudioContext },
        fetch(url) {
            requests.push(url);
            return Promise.resolve({ ok: true, arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)) });
        },
    });

    const navigation = { closest: () => null };
    listeners.get('pointerdown')({ target: navigation });
    listeners.get('keydown')({ target: navigation });
    assert.deepEqual(requests, []);

    listeners.get('pointerdown')({ target: { closest: () => ({}) } });
    assert.deepEqual(requests, ['/audio/OK.mp3', '/audio/NG.mp3']);
});
