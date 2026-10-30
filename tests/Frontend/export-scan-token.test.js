import assert from 'node:assert/strict';
import test from 'node:test';
import {scanToken} from '../../resources/js/export-stock/scan-token.mjs';

test('scan tokens use native UUID support when available', () => {
    assert.equal(scanToken({randomUUID: () => '09cf86c4-0f28-4a19-ad4d-829a4eb4acb9'}), '09cf86c4-0f28-4a19-ad4d-829a4eb4acb9');
});

test('HTTP intranet fallback generates a version 4 UUID from secure random bytes', () => {
    const result = scanToken({getRandomValues(bytes) { bytes.fill(255); return bytes; }});
    assert.equal(result, 'ffffffff-ffff-4fff-bfff-ffffffffffff');
    assert.match(scanToken(), /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
});
