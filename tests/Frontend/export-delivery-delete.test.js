import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFileSync} from 'node:fs';

const source=readFileSync(new URL('../../public/js/pages/export-delivery-delete.js',import.meta.url),'utf8');

function setup(changes={}) {
    const state={review:true,final:true,valid:true,canDelete:true,networkFailure:false,...changes};
    const events={},dialogs=[],requests=[],appended=[];
    let submitted=0;
    const reason={value:'Permintaan customer dibatalkan'},clonedReason={};
    const clone={querySelector:()=>clonedReason,submit:()=>submitted++};
    const form={querySelector:()=>reason,reportValidity:()=>state.valid,cloneNode:()=>clone};
    const trigger={dataset:{previewUrl:'/export/delivery-plans/7/deletion-preview'},setAttribute(){},removeAttribute(){}};
    const document={addEventListener:(name,callback)=>{events[name]=callback;},body:{appendChild:node=>appended.push(node)}};
    const fetch=async (url,options)=>{
        requests.push({url,options});
        if(state.networkFailure)throw new Error('Koneksi terputus');
        return {ok:true,json:async()=>({can_delete:state.canDelete,plan_code:'<RD&01>',html:'<p>Daftar box terverifikasi</p>'})};
    };
    const Swal={getHtmlContainer:()=>({querySelector:()=>form}),fire:async options=>{
        dialogs.push(options);
        if(options.preConfirm)return {isConfirmed:state.review && state.canDelete && options.preConfirm()!==false};
        return {isConfirmed:options.icon==='error' ? false : state.final};
    }};
    vm.runInNewContext(source,{document,fetch,Swal});
    return {state,dialogs,requests,appended,clonedReason,get submitted(){return submitted;},
        click:()=>events.click({preventDefault(){},target:{closest:()=>trigger}})};
}

test('first reviews box details then warns about deletion and submits only after both confirmations',async()=>{
    const ui=setup();await ui.click();
    assert.equal(ui.requests.length,1);assert.equal(ui.requests[0].options.headers.Accept,'application/json');
    assert.equal(ui.dialogs.length,2);assert.match(ui.dialogs[0].title,/Tinjauan/);
    assert.match(ui.dialogs[0].html,/Daftar box/);assert.equal(ui.dialogs[0].confirmButtonText,'Konfirmasi tinjauan');
    assert.match(ui.dialogs[1].text,/<RD&01>/);assert.equal(ui.dialogs[1].html,undefined);
    assert.equal(ui.submitted,1);assert.equal(ui.appended.length,1);
    assert.equal(ui.clonedReason.value,'Permintaan customer dibatalkan');
    await ui.click();assert.equal(ui.submitted,1);assert.equal(ui.requests.length,1);
});

test('cancelling the review never opens deletion warning or submits',async()=>{
    const ui=setup({review:false});await ui.click();
    assert.equal(ui.dialogs.length,1);assert.equal(ui.submitted,0);
    ui.state.review=true;await ui.click();assert.equal(ui.submitted,1);
});

test('cancelling final warning preserves data and another click fetches a fresh review',async()=>{
    const ui=setup({final:false});await ui.click();
    assert.equal(ui.dialogs.length,2);assert.equal(ui.submitted,0);assert.equal(ui.appended.length,0);
    ui.state.final=true;await ui.click();assert.equal(ui.requests.length,2);assert.equal(ui.submitted,1);
});

test('missing required reason or a blocked physical return cannot reach final confirmation',async()=>{
    for(const options of [{valid:false},{canDelete:false}]){
        const ui=setup(options);await ui.click();assert.equal(ui.dialogs.length,1);assert.equal(ui.submitted,0);
    }
});

test('preview connection errors stop deletion and permit retry',async()=>{
    const ui=setup({networkFailure:true});await ui.click();
    assert.equal(ui.dialogs[0].icon,'error');assert.equal(ui.submitted,0);
    ui.state.networkFailure=false;await ui.click();assert.equal(ui.submitted,1);
});

test('double click while preview is loading sends one request and one deletion',async()=>{
    const ui=setup();await Promise.all([ui.click(),ui.click()]);
    assert.equal(ui.requests.length,1);assert.equal(ui.submitted,1);assert.equal(ui.dialogs.length,2);
});
