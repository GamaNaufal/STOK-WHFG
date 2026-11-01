import assert from 'node:assert/strict';
import test from 'node:test';
import {parseCount,checkRows} from '../../resources/js/export-delivery/document-parser.mjs';

test('quantities accept whole counts and consistent thousands separators without rounding',()=>{
    for(const [input,output] of [['7500',7500],['7.500',7500],['7,500',7500],['7 500',7500],[4294967295,4294967295]]) assert.equal(parseCount(input),output);
    for(const input of [0,-1,1.5,'1.50','1.000,000','1e3','4294967296','=100*2','',null]) assert.equal(parseCount(input),null,String(input));
});
test('capacity snapshots stay stable and partial boxes become explicit remainder requirements',()=>{
    const parts=[{part_number:'P-1',qty_box:30}];
    const snapshot=[{part_number:'P-1',pcs_per_box:20}];
    const valid=checkRows([{part_number:'P-1',pcs_quantity:100}],parts,snapshot);
    assert.equal(valid.valid,true);assert.equal(valid.totalBoxes,5);
    const remainder=checkRows([{part_number:'P-1',pcs_quantity:100}],parts);
    assert.equal(remainder.valid,true);assert.equal(remainder.totalBoxes,4);assert.equal(remainder.rows[0].notFullPcs,10);
    assert.equal(checkRows([{part_number:'UNKNOWN',pcs_quantity:100}],parts).valid,false);
    assert.equal(checkRows([{part_number:'P-1',pcs_quantity:60},{part_number:'P-1',pcs_quantity:60}],parts).valid,false);
});
