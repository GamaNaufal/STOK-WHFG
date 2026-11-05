import assert from 'node:assert/strict';
import test from 'node:test';
import {receivingScanProgress} from '../../resources/js/export-stock/receiving-scan-progress.mjs';

const draft = (id, items = []) => ({id, status: 'draft', items});
const fields = {box: '00001234', part: 'PART-A', checked: true, inputDate: '2026-11-05'};

function browser() {
    const values = new Map();
    return {
        localStorage: {
            getItem: key => values.get(key) ?? null,
            setItem: (key, value) => values.set(key, value),
            removeItem: key => values.delete(key),
        },
    };
}

test('the same operator resumes a checked ID, part, and date in another page', () => {
    const windowRef = browser();
    receivingScanProgress(7, windowRef).save(draft(13), fields);
    assert.deepEqual(receivingScanProgress(7, windowRef).restore(draft(13)), fields);
    assert.equal(receivingScanProgress(8, windowRef).restore(draft(13)), null);
});

test('a box already accepted by the server is not restored as a pending scan', () => {
    const progress = receivingScanProgress(7, browser());
    progress.save(draft(13), fields);
    const items = [{box_number: fields.box}];
    assert.deepEqual(progress.restore(draft(13, items)), {...fields, box: '', part: '', checked: false});
    assert.equal(progress.restore(draft(13)).box, '');
});

test('a pending first scan can follow creation of an empty draft but not a different batch', () => {
    const progress = receivingScanProgress(7, browser());
    progress.save(draft(null), {...fields, box: '00001', checked: false});
    assert.equal(progress.restore(draft(13)).box, '00001');
    assert.equal(progress.restore(draft(14)), null);
});

test('processing or clearing a draft prevents its unfinished inputs from reappearing', () => {
    const progress = receivingScanProgress(7, browser());
    progress.save(draft(13), fields);
    assert.equal(progress.restore(draft(null)), null);
    progress.save(draft(14), fields);
    progress.save(draft(null), {});
    assert.equal(progress.restore(draft(14)), null);
});

test('blocked browser storage still allows the server-backed draft to render', () => {
    const progress = receivingScanProgress(7, {get localStorage() { throw new Error('Blocked'); }});
    assert.doesNotThrow(() => progress.save(draft(13), fields));
    assert.equal(progress.restore(draft(13)), null);
});
