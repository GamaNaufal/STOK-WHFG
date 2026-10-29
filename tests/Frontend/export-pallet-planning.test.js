import assert from 'node:assert/strict';
import test from 'node:test';
import {analyzePallets, availableForRow} from '../../resources/js/export-delivery/pallet-planning.mjs';

const catalog=[{part_setting_id:1,part_number:'P-A',pcs_per_box:10,required:10},{part_setting_id:2,part_number:'P-B',pcs_per_box:20,required:4}];
const locations=[{id:1,code:'A1',blocked:false},{id:2,code:'A2',blocked:false},{id:3,code:'A3',blocked:true}];
const row=(location,items)=>({master_location_id:location,items:items.map(([id,qty])=>({part_setting_id:id,box_quantity:qty}))});

test('fill remaining replaces this row without taking allocations from other pallets',()=>{
    const result=analyzePallets([row(1,[[1,3]]),row(2,[[1,4]])],catalog,locations);
    assert.equal(availableForRow(result.summary,1,3),6);
    assert.equal(availableForRow(result.summary,1,''),3);
    assert.equal(availableForRow(result.summary,2,''),4);
    const completed=analyzePallets([row(1,[[1,6]]),row(2,[[1,4]])],catalog,locations);
    assert.equal(availableForRow(completed.summary,1,6),6);
    assert.equal(availableForRow(completed.summary,1,''),0);
});

test('fill remaining corrects excess and refuses unknown or removed delivery parts',()=>{
    const result=analyzePallets([row(1,[[1,11]])],catalog,locations);
    assert.equal(availableForRow(result.summary,1,11),10);
    assert.equal(availableForRow(result.summary,1,''),0);
    assert.equal(availableForRow(result.summary,99,5),null);
    assert.equal(availableForRow([{part_setting_id:3,required:0,allocated:5}],3,5),null);
});

test('mixed parts can be split over sequential pallets with exact totals and free capacity',()=>{
    const result=analyzePallets([row(1,[[1,6],[2,4]]),row(2,[[1,4]])],catalog,locations);
    assert.equal(result.finalValid,true);assert.equal(result.totalBoxes,14);
    assert.deepEqual(result.summary.map(p=>p.remaining),[0,0]);
    assert.deepEqual(result.pallets.map(p=>p.boxes),[10,4]);
});
test('partial drafts and excess allocations after delivery revisions remain visible but cannot finalize',()=>{
    const partial=analyzePallets([row(null,[[1,3]])],catalog,locations);
    assert.equal(partial.draftValid,true);assert.equal(partial.finalValid,false);
    assert.equal(partial.summary[0].remaining,7);
    const excess=analyzePallets([row(1,[[1,11],[2,4]])],catalog,locations);
    assert.equal(excess.draftValid,true);assert.equal(excess.finalValid,false);
    assert.equal(excess.summary[0].remaining,-1);
});
test('reserved and duplicated locations, repeated part rows and invalid counts block saving',()=>{
    for(const rows of [[row(3,[[1,10],[2,4]])],[row(1,[[1,5]]),row(1,[[1,5],[2,4]])],
        [row(1,[[1,5],[1,5],[2,4]])],[row(1,[[99,10]])],[row(1,[[1,1.5]])]]){
        assert.equal(analyzePallets(rows,catalog,locations).draftValid,false);
    }
});
test('empty pallets and removed delivery parts can remain drafts until corrected',()=>{
    assert.equal(analyzePallets([row(null,[])],catalog,locations).draftValid,true);
    assert.equal(analyzePallets([row(null,[])],catalog,locations).finalValid,false);
    const revised=[...catalog,{part_setting_id:3,part_number:'REMOVED',pcs_per_box:30,required:0}];
    const result=analyzePallets([row(1,[[1,10],[2,4],[3,2]])],revised,locations);
    assert.equal(result.draftValid,true);assert.equal(result.finalValid,false);
    assert.equal(result.summary[2].remaining,-2);
});
