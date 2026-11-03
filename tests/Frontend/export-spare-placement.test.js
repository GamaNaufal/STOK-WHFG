import assert from 'node:assert/strict';
import test from 'node:test';
import {distributeSpareBoxes, spareIncoming} from '../../resources/js/export-stock/spare-placement.mjs';

const boxes = Array.from({length:5},(_,i)=>({id:i+1,revision:2,request_token:`token-${i}`,box_number:`0000000${i+1}`,part_number:'A'}));
test('one target keeps all boxes of the part together and splitting preserves their FIFO order and tokens', () => {
    const single = distributeSpareBoxes(boxes,[{quantity:5,destination:'location:1'}]);
    assert.equal(single.length,5); assert.ok(single.every(row=>row.destination==='location:1'));
    const split = distributeSpareBoxes(boxes,[{quantity:3,destination:'location:1'},{quantity:2,destination:'pallet:8'}]);
    assert.deepEqual(split.map(row=>[row.id,row.destination]),[[1,'location:1'],[2,'location:1'],[3,'location:1'],[4,'pallet:8'],[5,'pallet:8']]);
    assert.deepEqual(split.map(row=>row.request_token),boxes.map(row=>row.request_token));
    assert.equal(boxes[0].destination,undefined);
});
test('underallocation, overallocation, fractions, zero and missing targets cannot be confirmed', () => {
    for (const allocations of [[{quantity:4,destination:'location:1'}],[{quantity:6,destination:'location:1'}],[{quantity:2.5,destination:'location:1'},{quantity:2.5,destination:'location:2'}],[{quantity:0,destination:'location:1'},{quantity:5,destination:'location:2'}],[{quantity:5,destination:''}],[{quantity:5,destination:'location:0'}]]) {
        assert.throws(()=>distributeSpareBoxes(boxes,allocations));
    }
});
test('printing can mark unselected targets without inventing a destination, but incomplete quantities still fail', () => {
    const rows=distributeSpareBoxes(boxes,[{quantity:3,destination:'location:1'},{quantity:2,destination:''}],{requireDestination:false});
    assert.equal(rows.filter(row=>!row.destination).length,2);
    assert.throws(()=>distributeSpareBoxes(boxes,[{quantity:3,destination:'location:1'}],{requireDestination:false}));
});
test('pallet load includes multiple parts, split targets and the existing planned incoming quantity', () => {
    const totals=spareIncoming([
        [{destination:'pallet:1'},{destination:'location:2'}],
        [{destination:'pallet:1'},{destination:'pallet:1'},{destination:''}],
    ],[{destination:'pallet:1',incoming:33}]);
    assert.equal(totals.get('pallet:1'),36);
    assert.equal(totals.get('location:2'),1);
    assert.equal(totals.has(''),false);
});
