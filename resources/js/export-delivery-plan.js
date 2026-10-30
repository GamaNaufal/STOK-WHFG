import { parseCount, checkRows } from './export-delivery/document-parser.mjs';

const page=document.getElementById('delivery-plan-editor');
if(page) {
    const initial=JSON.parse(document.getElementById('plan-bootstrap').textContent);
    const byId=id=>document.getElementById(id);
    const form=byId('delivery-plan-form'),body=byId('plan-items'),template=byId('plan-row-template');
    const planCode=byId('plan-code'),customer=byId('plan-customer'),deadline=byId('plan-deadline'),sourceType=byId('plan-source-type'),sourceName=byId('plan-source-name');
    const reviewed=byId('plan-reviewed'),reviewPanel=byId('plan-review'),save=byId('plan-save');
    const status=byId('plan-import-status');
    const duplicateReview=byId('plan-duplicate-review'),duplicateReason=byId('plan-duplicate-reason'),duplicateConfirmed=byId('plan-duplicate-confirmed');
    const resetDuplicateReview=()=>{
        if(!duplicateReview || duplicateReview.hidden)return;
        duplicateReview.hidden=true;
        duplicateReview.querySelectorAll('input,textarea').forEach(field=>{field.disabled=true;field.required=false;});
        duplicateConfirmed.checked=false;
    };
    const format=n=>Number(n).toLocaleString('id-ID');
    let busy=false,undo=null,dirty=false,submitting=false;
    const rows=()=>[...body.querySelectorAll('tr')].map(tr=>({
        part_number:tr.querySelector('.plan-part').value.trim(),
        pcs_quantity:tr.querySelector('.plan-quantity').value,
    }));
    const announce=(text,error=false)=>{status.textContent=text;status.classList.toggle('is-error',error);};
    let importWarnings=[];
    const displayWarnings=warnings=>{
        importWarnings=Array.isArray(warnings)?warnings:[];
        const list=byId('plan-warning-list');list.replaceChildren();
        importWarnings.forEach(message=>{const item=document.createElement('li');item.textContent=message;list.append(item);});
        byId('plan-import-warnings').hidden=importWarnings.length===0;
    };
    const refresh=()=>{
        const data=checkRows(rows(),initial.parts,initial.snapshots);
        [...body.children].forEach((tr,i)=>{
            const part=tr.querySelector('.plan-part'),qty=tr.querySelector('.plan-quantity'),result=data.rows[i];
            part.name='items['+i+'][part_number]';qty.name='items['+i+'][pcs_quantity]';
            part.setAttribute('aria-label','Nomor part baris '+(i+1));qty.setAttribute('aria-label','Jumlah PCS baris '+(i+1));
            tr.querySelector('.plan-row-number').textContent=i+1;
            tr.querySelector('.plan-capacity').textContent=result.capacity ? format(result.capacity) : '—';
            tr.querySelector('.plan-boxes').textContent=result.boxes===null ? '—' : format(result.boxes);
            const error=tr.querySelector('.plan-row-error');error.id='plan-row-error-'+i;
            error.textContent=tr.dataset.touched==='1' ? result.error : '';
            part.setAttribute('aria-describedby',error.id);qty.setAttribute('aria-describedby',error.id);
            part.setCustomValidity(result.error && !initial.parts.some(p=>p.part_number===result.part_number) ? result.error : '');
            qty.setCustomValidity(result.error && initial.parts.some(p=>p.part_number===result.part_number) ? result.error : '');
        });
        byId('plan-row-count').textContent=data.rows.length;
        byId('plan-total-pcs').textContent=data.valid ? format(data.totalPcs) : '—';
        byId('plan-total-boxes').textContent=data.valid ? format(data.totalBoxes) : '—';
        reviewPanel.hidden=sourceType.value==='manual';reviewed.required=sourceType.value!=='manual';
        const duplicateValid=!duplicateReview || duplicateReview.hidden || (duplicateConfirmed.checked && duplicateReason.value.trim().length>0);
        save.disabled=submitting || busy || !data.valid || !duplicateValid || (reviewed.required && !reviewed.checked);
        if(!submitting)save.textContent=duplicateReview && !duplicateReview.hidden ? 'Tetap simpan dengan alasan' : 'Simpan rencana';
        byId('plan-add-item').disabled=busy || data.rows.length>=500;
    };
    const addRow=(row={},touched=false)=>{
        const fragment=template.content.cloneNode(true),tr=fragment.querySelector('tr');
        tr.querySelector('.plan-part').value=row.part_number ?? '';
        const quantity=parseCount(row.pcs_quantity);
        tr.querySelector('.plan-quantity').value=quantity ?? (row.pcs_quantity ?? '');
        tr.dataset.touched=touched ? '1':'0';body.append(fragment);
    };
    const setBusy=value=>{
        busy=value;
        for(const id of ['plan-read-excel','plan-excel-file']) byId(id).disabled=value;
        form.setAttribute('aria-busy',String(value));
        refresh();
    };
    const capture=()=>({plan_code:planCode.value,customer_name:customer.value,delivery_deadline:deadline.value,items:rows(),source_type:sourceType.value,source_name:sourceName.value,reviewed:reviewed.checked,warnings:[...importWarnings]});
    const apply=(data,type,name)=>{
        resetDuplicateReview();
        undo=capture();
        planCode.value=data.plan_code ?? '';
        if(data.customer_name) customer.value=data.customer_name;
        if(data.delivery_deadline) deadline.value=data.delivery_deadline;
        body.replaceChildren();data.items.forEach(row=>addRow(row,true));
        sourceType.value=type;sourceName.value=String(name).slice(0,255);reviewed.checked=false;dirty=true;
        byId('plan-undo-import').hidden=false;
        displayWarnings(data.warnings);
        refresh();
        announce(data.items.length+' baris dibaca. Cocokkan kode rencana, semua baris, customer dan tanggal dengan dokumen sumber.');
    };
    body.addEventListener('input',event=>{
        const tr=event.target.closest('tr');if(!tr)return;
        resetDuplicateReview();tr.dataset.touched='1';dirty=true;
        if(sourceType.value!=='manual')reviewed.checked=false;
        refresh();
    });
    body.addEventListener('click',event=>{
        const button=event.target.closest('.plan-remove');if(!button || busy)return;
        resetDuplicateReview();
        button.closest('tr').remove();if(!body.children.length)addRow();
        dirty=true;if(sourceType.value!=='manual')reviewed.checked=false;refresh();
    });
    byId('plan-add-item').addEventListener('click',()=>{
        if(body.children.length>=500)return;
        resetDuplicateReview();
        addRow();dirty=true;reviewed.checked=false;refresh();body.lastElementChild.querySelector('.plan-part').focus();
    });
    [planCode,customer,deadline].forEach(input=>input.addEventListener('input',()=>{resetDuplicateReview();dirty=true;if(sourceType.value!=='manual')reviewed.checked=false;refresh();}));
    duplicateReason?.addEventListener('input',refresh);duplicateConfirmed?.addEventListener('change',refresh);
    reviewed.addEventListener('change',refresh);
    byId('plan-undo-import').addEventListener('click',()=>{
        resetDuplicateReview();
        if(!undo)return;planCode.value=undo.plan_code;customer.value=undo.customer_name;deadline.value=undo.delivery_deadline;
        body.replaceChildren();undo.items.forEach(row=>addRow(row,true));
        sourceType.value=undo.source_type;sourceName.value=undo.source_name;reviewed.checked=undo.reviewed;
        displayWarnings(undo.warnings);
        undo=null;byId('plan-undo-import').hidden=true;announce('Hasil impor dibatalkan.');refresh();
    });
    document.querySelectorAll('[data-input-mode]').forEach(button=>button.addEventListener('click',()=>{
        document.querySelectorAll('[data-input-mode]').forEach(item=>{
            const active=item===button;item.classList.toggle('is-selected',active);item.setAttribute('aria-pressed',String(active));
        });
        byId('plan-excel-panel').hidden=button.dataset.inputMode!=='excel';
    }));
    byId('plan-read-excel').addEventListener('click',async()=>{
        const file=byId('plan-excel-file').files[0];
        if(!file)return announce('Pilih file Excel terlebih dahulu.',true);
        if(file.size>5*1024*1024)return announce('Maksimal ukuran Excel 5 MB.',true);
        setBusy(true);announce('Membaca file Excel…');
        try {
            const data=new FormData();data.append('file',file);
            const response=await fetch(page.dataset.importUrl,{method:'POST',body:data,headers:{
                Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value,
            }});
            if(response.status===419)throw new Error('Sesi berakhir. Muat ulang halaman sebelum mengimpor.');
            const result=await response.json();
            if(!response.ok)throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || 'File Excel belum dapat dibaca.');
            apply(result,'excel',file.name);
        } catch(error){announce(error.message || 'File Excel belum dapat dibaca.',true);}
        finally{setBusy(false);}
    });
    form.addEventListener('submit',event=>{
        if(submitting){event.preventDefault();return;}
        refresh();if(save.disabled || !form.reportValidity()){event.preventDefault();return;}
        submitting=true;save.disabled=true;save.textContent='Menyimpan…';form.setAttribute('aria-busy','true');
    });
    window.addEventListener('pageshow',()=>{submitting=false;save.textContent='Simpan rencana';form.setAttribute('aria-busy',String(busy));refresh();});
    window.addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue='';}});
    initial.rows.forEach(row=>addRow(row,Boolean(row.part_number)));refresh();
}
