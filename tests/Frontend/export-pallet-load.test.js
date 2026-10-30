import assert from 'node:assert/strict';
import test from 'node:test';
import {palletLoadLevel, updatePalletLoad, initDestinationLoads} from '../../resources/js/export-stock/pallet-load.mjs';
import {analyzePallets} from '../../resources/js/export-delivery/pallet-planning.mjs';

function badge() {
    const count = {}, note = {};
    return {count, note, dataset: {warningAt: '36', dangerAt: '72'}, querySelector: selector => selector === '[data-load-count]' ? count : note};
}

test('thresholds are inclusive and clear again when the operator reduces a pallet', () => {
    const ui = badge();
    for (const [count, expected] of [[0,'normal'],[35,'normal'],[36,'warning'],[71,'warning'],[72,'danger'],[73,'danger'],[36,'warning'],[35,'normal']]) {
        assert.equal(palletLoadLevel(count,36,72),expected);
        assert.equal(updatePalletLoad(ui,count),expected);
        assert.equal(ui.note.hidden,expected === 'normal');
        assert.equal(ui.count.textContent,count + ' box');
    }
});

test('mixed parts are summed per pallet and warning counts do not block valid finalization', () => {
    const catalog = [{part_setting_id:1,required:36},{part_setting_id:2,required:72}];
    const analysis = analyzePallets([
        {master_location_id:1,items:[{part_setting_id:1,box_quantity:10},{part_setting_id:2,box_quantity:26}]},
        {master_location_id:2,items:[{part_setting_id:1,box_quantity:26},{part_setting_id:2,box_quantity:46}]},
    ],catalog,[{id:1},{id:2}]);
    assert.equal(analysis.finalValid,true);
    assert.deepEqual(analysis.pallets.map(p => palletLoadLevel(p.boxes,36,72)),['warning','danger']);
});

test('existing pallet preview includes all incoming boxes and supports dynamically loaded modal controls', () => {
    const ui = badge(), current = {}, incoming = {};
    const panel = {hidden:true,querySelector: selector => ({'[data-load-current]':current,'[data-load-incoming]':incoming,'[data-pallet-load]':ui}[selector])};
    const select = {value:'pallet:1',dataset:{incomingCount:'2'},selectedOptions:[{dataset:{boxCount:'34'}}],
        closest: () => ({querySelector: () => panel}),matches: () => true};
    const events = {}, windowEvents = {};
    // No controls exist until the modal is loaded; the delegated listener must still work.
    initDestinationLoads({documentRef:{addEventListener:(name,fn) => events[name] = fn,querySelectorAll:() => []},
        windowRef:{addEventListener:(name,fn) => windowEvents[name] = fn}});
    events.change({target:select});
    assert.equal(panel.hidden,false); assert.equal(ui.dataset.level,'warning'); assert.equal(ui.count.textContent,'36 box');
    assert.equal(current.textContent,'34'); assert.equal(incoming.textContent,'2');
    select.selectedOptions = [{dataset:{boxCount:'70'}}]; events.change({target:select});
    assert.equal(ui.dataset.level,'danger'); assert.equal(ui.count.textContent,'72 box');
    select.value = 'location:2'; select.selectedOptions = [{dataset:{boxCount:'0'}}]; events.change({target:select});
    assert.equal(ui.dataset.level,'normal'); assert.equal(ui.count.textContent,'2 box');
    select.value = ''; events.change({target:select}); assert.equal(panel.hidden,true);
});
