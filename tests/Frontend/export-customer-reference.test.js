import assert from 'node:assert/strict';
import test from 'node:test';
import {createCustomerReference} from '../../resources/js/export-delivery/customer-reference.mjs';

const catalog = [
    {id:1, name:'PT Alpha', active:true, labels:['a','pt alpha','alpha excel']},
    {id:2, name:'PT Beta', active:true, labels:['b','pt beta','beta excel']},
    {id:3, name:'Inactive', active:false, labels:['inactive']},
];
function setup({name='', id='', required='1', legacyName='', lookup, customers=catalog} = {}) {
    const input={value:name}, idInput={value:id,dataset:{required,legacyName}}, sourceInput={value:''}, status={textContent:''};
    const reference=createCustomerReference({input,idInput,sourceInput,status,catalog:customers,lookup});
    return {input,idInput,sourceInput,status,reference};
}

test('initial and browser-restored official names and aliases populate the submitted master ID', () => {
    for (const name of ['PT Alpha','ALPHA EXCEL','  alpha   excel  ','A']) {
        const ui=setup({name});
        ui.reference.sync();
        assert.equal(ui.idInput.value,'1');
        assert.equal(ui.status.textContent,'Terhubung ke PT Alpha');
        ui.idInput.value='';
        ui.reference.sync();
        assert.equal(ui.idInput.value,'1');
    }
});

test('typing another customer clears the old identity and keeps the entered source alias', () => {
    const ui=setup({name:'PT Alpha',id:'1'});
    ui.input.value='beta excel';
    ui.reference.sync({edited:true});
    assert.equal(ui.idInput.value,'2');
    assert.equal(ui.sourceInput.value,'beta excel');
    ui.input.value='Unknown';
    ui.reference.sync({edited:true});
    assert.equal(ui.idInput.value,'');
    ui.input.value='Inactive';
    ui.reference.sync({edited:true});
    assert.equal(ui.idInput.value,'');
});

test('a customer added after the form opened is resolved by a live lookup and survives submit sync', async () => {
    const ui=setup({name:'NEW EXCEL ALIAS',lookup:async()=>({status:'matched',customer_id:9,customer_name:'New customer'})});
    await ui.reference.sync({lookupMissing:true,immediate:true});
    assert.equal(ui.idInput.value,'9');
    assert.equal(ui.input.value,'NEW EXCEL ALIAS');
    ui.reference.sync();
    assert.equal(ui.idInput.value,'9');
});

test('a slow reply for a previous name cannot overwrite a newer customer selection', async () => {
    const pending=[];
    const ui=setup({lookup:(name,{signal})=>new Promise(resolve=>pending.push({name,signal,resolve}))});
    ui.input.value='New A';
    const first=ui.reference.sync({edited:true,lookupMissing:true,immediate:true});
    ui.input.value='New B';
    const second=ui.reference.sync({edited:true,lookupMissing:true,immediate:true});
    assert.equal(pending[0].signal.aborted,true);
    pending[1].resolve({status:'matched',customer_id:12,customer_name:'New B'});
    await second;
    pending[0].resolve({status:'matched',customer_id:11,customer_name:'New A'});
    await first;
    assert.equal(ui.idInput.value,'12');
    assert.equal(ui.sourceInput.value,'New B');
});

test('an Excel code/name conflict stays blocked until the operator edits the customer', () => {
    const ui=setup();
    ui.reference.applyImport({customer_name:'PT Alpha',customer_match:{status:'conflict',message:'Kode dan nama customer perlu diperiksa.'}});
    ui.reference.sync();
    assert.equal(ui.idInput.value,'');
    assert.match(ui.status.textContent,/perlu diperiksa/);
    const conflict=ui.reference.capture();
    ui.input.value='PT Beta';
    ui.reference.sync({edited:true});
    assert.equal(ui.idInput.value,'2');
    ui.reference.restore(conflict);
    assert.equal(ui.idInput.value,'');
    assert.match(ui.status.textContent,/perlu diperiksa/);
});

test('an imported fresh master keeps its canonical identity and undo restores the original source', () => {
    const ui=setup({name:'ALPHA EXCEL'});
    ui.reference.sync({edited:true});
    const before=ui.reference.capture();
    ui.reference.applyImport({customer_name:'NEW ALIAS',customer_match:{status:'matched',customer_id:9,customer_name:'New customer'}});
    assert.equal(ui.input.value,'New customer');
    assert.equal(ui.sourceInput.value,'NEW ALIAS');
    ui.reference.sync();
    assert.equal(ui.idInput.value,'9');
    ui.reference.restore(before);
    assert.equal(ui.input.value,'ALPHA EXCEL');
    assert.equal(ui.idInput.value,'1');
    assert.equal(ui.sourceInput.value,'ALPHA EXCEL');
});

test('opening an unlinked historical plan does not map it automatically, while an existing link keeps its snapshot', () => {
    const legacy=setup({name:'PT Alpha',required:'0',legacyName:'PT Alpha'});
    legacy.reference.sync({lookupMissing:true,immediate:true});
    assert.equal(legacy.idInput.value,'');
    assert.match(legacy.status.textContent,/historis belum dipetakan/);
    legacy.reference.sync({edited:true});
    assert.equal(legacy.idInput.value,'1');
    const linked=setup({name:'Historical Alpha',id:'1',customers:[{id:1,name:'Renamed inactive',active:false,labels:['renamed inactive']}]});
    linked.reference.sync();
    assert.equal(linked.input.value,'Historical Alpha');
    assert.equal(linked.idInput.value,'1');
});

test('a failed lookup leaves text intact and the customer unresolved for a deliberate retry', async () => {
    const ui=setup({name:'New alias',lookup:async()=>{throw new Error('offline');}});
    await ui.reference.sync({lookupMissing:true,immediate:true});
    assert.equal(ui.input.value,'New alias');
    assert.equal(ui.idInput.value,'');
    assert.match(ui.status.textContent,/belum dapat diperiksa/);
});
