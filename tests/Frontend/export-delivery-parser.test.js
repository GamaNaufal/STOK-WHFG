import assert from 'node:assert/strict';
import test from 'node:test';
import {parseCount,parseDocumentDate,parsePhotoResult,checkRows} from '../../resources/js/export-delivery/document-parser.mjs';

test('quantities accept whole counts and consistent thousands separators without rounding',()=>{
    for(const [input,output] of [['7500',7500],['7.500',7500],['7,500',7500],['7 500',7500],[4294967295,4294967295]]) assert.equal(parseCount(input),output);
    for(const input of [0,-1,1.5,'1.50','1.000,000','1e3','4294967296','=100*2','',null]) assert.equal(parseCount(input),null,String(input));
});
test('document dates recognize Indonesian print and reject impossible dates',()=>{
    assert.equal(parseDocumentDate('SENIN,28 SEPTEMBER 2026'),'2026-09-28');
    assert.equal(parseDocumentDate('Batas: 15/11/2026'),'2026-11-15');
    assert.equal(parseDocumentDate('2026-11-15'),'2026-11-15');
    assert.equal(parseDocumentDate('31 FEBRUARI 2026'),'');
    assert.equal(parseDocumentDate('29 FEBRUARI 2024'),'2024-02-29');
});
test('photo parsing selects only literal master codes, preserving uncertain codes for correction',()=>{
    const data=parsePhotoResult('CUSTOMER : CUSTOMER A\nSENIN,28 SEPTEMBER 2026',[
        {part:'l EXP-001',quantity:'100',partConfidence:40,quantityConfidence:80},
        {part:'EXP-OO2',quantity:'200',partConfidence:20,quantityConfidence:40},
    ],[{part_number:'EXP-001'},{part_number:'EXP-002'}]);
    assert.equal(data.customer_name,'CUSTOMER A');
    assert.equal(data.delivery_deadline,'2026-09-28');
    assert.equal(data.items[0].part_number,'EXP-001');
    assert.equal(data.items[1].part_number,'EXP-OO2');
});
test('capacity snapshots stay stable, duplicate parts and partial boxes prevent saving',()=>{
    const parts=[{part_number:'P-1',qty_box:30}];
    const snapshot=[{part_number:'P-1',pcs_per_box:20}];
    const valid=checkRows([{part_number:'P-1',pcs_quantity:100}],parts,snapshot);
    assert.equal(valid.valid,true);assert.equal(valid.totalBoxes,5);
    assert.equal(checkRows([{part_number:'P-1',pcs_quantity:100}],parts).valid,false);
    assert.equal(checkRows([{part_number:'UNKNOWN',pcs_quantity:100}],parts).valid,false);
    assert.equal(checkRows([{part_number:'P-1',pcs_quantity:60},{part_number:'P-1',pcs_quantity:60}],parts).valid,false);
});
