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

test('document aliases resolve to a scan identity and duplicate physical products are blocked',()=>{
    const catalog=[{id:7,part_number:'P71005079',qty_box:1000,document_numbers:[{document_number:'7100-5079'},{document_number:'DOC-B'}]}];
    const result=checkRows([{part_number:'7100-5079',pcs_quantity:2500}],catalog);
    assert.equal(result.valid,true);assert.equal(result.rows[0].partSettingId,7);
    assert.equal(result.rows[0].scanPartNumber,'P71005079');assert.equal(result.totalBoxes,3);
    for(const code of ['DOC-B','P71005079']) {
        assert.equal(checkRows([{part_number:'7100-5079',pcs_quantity:1000},{part_number:code,pcs_quantity:1000}],catalog).valid,false);
    }
    assert.equal(checkRows([{part_number:'7100-5079'.toLowerCase()+'x',pcs_quantity:1000}],catalog).valid,false);
});

test('historical document mapping and capacity stay stable when current alias points elsewhere',()=>{
    const catalog=[{id:7,part_number:'SCAN-OLD',qty_box:2000},{id:8,part_number:'SCAN-NEW',qty_box:3000,document_numbers:[{document_number:'DOC-A'}]}];
    const rows=[{part_number:'DOC-A',pcs_quantity:2000}];
    const result=checkRows(rows,catalog,[{part_number:'DOC-A',scan_part_number:'SCAN-OLD',part_setting_id:7,pcs_per_box:1000}]);
    assert.equal(result.valid,true);assert.equal(result.rows[0].partSettingId,7);assert.equal(result.totalBoxes,2);
    assert.equal(checkRows(rows,catalog).rows[0].partSettingId,8);
});

test('ambiguous document mappings fail and slash codes are never split automatically',()=>{
    const catalog=[{id:1,part_number:'SCAN-A',qty_box:10,document_numbers:[{document_number:'A/B'}]},
        {id:2,part_number:'SCAN-B',qty_box:10,document_numbers:[{document_number:'A/B'}]}];
    assert.match(checkRows([{part_number:'A/B',pcs_quantity:10}],catalog).rows[0].error,/ambigu/);
    assert.equal(checkRows([{part_number:'A',pcs_quantity:10}],catalog.slice(0,1)).valid,false);
    assert.equal(checkRows([{part_number:'A/B',pcs_quantity:10}],catalog.slice(0,1)).valid,true);
});
