import { parseCount, checkRows, parsePhotoResult } from './export-delivery/document-parser.mjs';
import { preparePrintedTable } from './export-delivery/image-processing.mjs';

const page=document.getElementById('delivery-plan-editor');
if(page) {
    const initial=JSON.parse(document.getElementById('plan-bootstrap').textContent);
    const byId=id=>document.getElementById(id);
    const form=byId('delivery-plan-form'),body=byId('plan-items'),template=byId('plan-row-template');
    const customer=byId('plan-customer'),deadline=byId('plan-deadline'),sourceType=byId('plan-source-type'),sourceName=byId('plan-source-name');
    const reviewed=byId('plan-reviewed'),reviewPanel=byId('plan-review'),save=byId('plan-save');
    const status=byId('plan-import-status'),progress=byId('plan-photo-progress');
    const format=n=>Number(n).toLocaleString('id-ID');
    let busy=false,undo=null,photo=null,photoUrl=null,rotation=0,worker=null,cvPromise=null,dirty=false,submitting=false;
    let recognitionStage={from:0,to:100};
    const rows=()=>[...body.querySelectorAll('tr')].map(tr=>({
        part_number:tr.querySelector('.plan-part').value.trim(),
        pcs_quantity:tr.querySelector('.plan-quantity').value,
    }));
    const announce=(text,error=false)=>{status.textContent=text;status.classList.toggle('is-error',error);};
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
        save.disabled=busy || !data.valid || (reviewed.required && !reviewed.checked);
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
        for(const id of ['plan-read-excel','plan-read-photo','plan-photo-file','plan-excel-file','plan-rotate-left','plan-rotate-right']) byId(id).disabled=value;
        form.setAttribute('aria-busy',String(value));
        refresh();
    };
    const capture=()=>({customer_name:customer.value,delivery_deadline:deadline.value,items:rows(),source_type:sourceType.value,source_name:sourceName.value,reviewed:reviewed.checked});
    const apply=(data,type,name)=>{
        undo=capture();
        if(data.customer_name) customer.value=data.customer_name;
        if(data.delivery_deadline) deadline.value=data.delivery_deadline;
        body.replaceChildren();data.items.forEach(row=>addRow(row,true));
        sourceType.value=type;sourceName.value=String(name).slice(0,255);reviewed.checked=false;dirty=true;
        byId('plan-undo-import').hidden=false;
        refresh();
        announce(data.items.length+' baris dibaca. Cocokkan semua baris, customer dan tanggal dengan dokumen sumber.');
    };
    body.addEventListener('input',event=>{
        const tr=event.target.closest('tr');if(!tr)return;
        tr.dataset.touched='1';dirty=true;
        if(sourceType.value!=='manual')reviewed.checked=false;
        refresh();
    });
    body.addEventListener('click',event=>{
        const button=event.target.closest('.plan-remove');if(!button || busy)return;
        button.closest('tr').remove();if(!body.children.length)addRow();
        dirty=true;if(sourceType.value!=='manual')reviewed.checked=false;refresh();
    });
    byId('plan-add-item').addEventListener('click',()=>{
        if(body.children.length>=500)return;
        addRow();dirty=true;reviewed.checked=false;refresh();body.lastElementChild.querySelector('.plan-part').focus();
    });
    [customer,deadline].forEach(input=>input.addEventListener('input',()=>{dirty=true;if(sourceType.value!=='manual')reviewed.checked=false;refresh();}));
    reviewed.addEventListener('change',refresh);
    byId('plan-undo-import').addEventListener('click',()=>{
        if(!undo)return;customer.value=undo.customer_name;deadline.value=undo.delivery_deadline;
        body.replaceChildren();undo.items.forEach(row=>addRow(row,true));
        sourceType.value=undo.source_type;sourceName.value=undo.source_name;reviewed.checked=undo.reviewed;
        undo=null;byId('plan-undo-import').hidden=true;announce('Hasil impor dibatalkan.');refresh();
    });
    document.querySelectorAll('[data-input-mode]').forEach(button=>button.addEventListener('click',()=>{
        document.querySelectorAll('[data-input-mode]').forEach(item=>{
            const active=item===button;item.classList.toggle('is-selected',active);item.setAttribute('aria-pressed',String(active));
        });
        byId('plan-excel-panel').hidden=button.dataset.inputMode!=='excel';
        byId('plan-photo-panel').hidden=button.dataset.inputMode!=='photo';
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
    const rotatedCanvas=()=>{
        const canvas=document.createElement('canvas');
        const swapped=Math.abs(rotation%180)===90;
        const ratio=Math.min(1,2400/Math.max(photo.naturalWidth,photo.naturalHeight));
        const width=Math.round(photo.naturalWidth*ratio),height=Math.round(photo.naturalHeight*ratio);
        canvas.width=swapped?height:width;canvas.height=swapped?width:height;
        const ctx=canvas.getContext('2d',{willReadFrequently:true});
        ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);
        ctx.translate(canvas.width/2,canvas.height/2);ctx.rotate(rotation*Math.PI/180);ctx.drawImage(photo,-width/2,-height/2,width,height);
        return canvas;
    };
    const displayPhoto=()=>{
        const canvas=rotatedCanvas();byId('plan-photo-image').src=canvas.toDataURL('image/jpeg',0.9);
    };
    byId('plan-photo-file').addEventListener('change',async()=>{
        if(photoUrl)URL.revokeObjectURL(photoUrl);photo=null;rotation=0;byId('plan-photo-preview').hidden=true;
        const file=byId('plan-photo-file').files[0];if(!file)return;
        if(!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size>10*1024*1024)
            return announce('Gunakan foto JPG, PNG atau WebP maksimal 10 MB.',true);
        photoUrl=URL.createObjectURL(file);const image=new Image();
        try {
            image.src=photoUrl;await image.decode();
            if(image.naturalWidth*image.naturalHeight>40000000)throw new Error('Resolusi foto terlalu besar.');
            photo=image;displayPhoto();byId('plan-photo-preview').hidden=false;announce('Foto siap dibaca. Pastikan orientasi dokumen sudah benar.');
        } catch(error){announce(error.message || 'Foto tidak dapat dibuka.',true);}
    });
    byId('plan-rotate-left').addEventListener('click',()=>{if(photo){rotation-=90;displayPhoto();}});
    byId('plan-rotate-right').addEventListener('click',()=>{if(photo){rotation+=90;displayPhoto();}});
    const getCv=async()=>{
        if(!cvPromise)cvPromise=(async()=>{
            await new Promise((resolve,reject)=>{
                const script=document.createElement('script');script.src=page.dataset.ocrBase+'/opencv.js';
                script.onload=resolve;script.onerror=()=>reject(new Error('Pembaca foto belum tersedia. Coba muat ulang halaman.'));document.head.append(script);
            });
            let cv=window.cv;
            if(cv?.then)cv=await cv;
            if(!cv?.Mat)await new Promise((resolve,reject)=>{
                const timeout=setTimeout(()=>reject(new Error('Pembaca foto belum siap. Coba lagi.')),60000);
                cv.onRuntimeInitialized=()=>{clearTimeout(timeout);resolve();};
            });
            return cv;
        })().catch(error=>{cvPromise=null;throw error;});
        return cvPromise;
    };
    const getWorker=async()=>{
        if(!worker){
            const {createWorker}=await import('tesseract.js');
            worker=await createWorker('eng',1,{
                workerPath:page.dataset.ocrBase+'/worker.min.js',corePath:page.dataset.ocrBase,
                langPath:page.dataset.ocrBase+'/lang',workerBlobURL:false,
                logger:message=>{
                    if(message.status==='recognizing text')progress.value=recognitionStage.from+(recognitionStage.to-recognitionStage.from)*message.progress;
                },
            });
        }
        return worker;
    };
    const pixelCanvas=pixels=>{
        const canvas=document.createElement('canvas');canvas.width=pixels.width;canvas.height=pixels.height;
        canvas.getContext('2d').putImageData(new ImageData(pixels.data,pixels.width,pixels.height),0,0);return canvas;
    };
    byId('plan-read-photo').addEventListener('click',async()=>{
        if(!photo)return announce('Pilih foto terlebih dahulu.',true);
        setBusy(true);progress.hidden=false;progress.value=0;announce('Menyiapkan pembacaan foto. Pemuatan pertama dapat membutuhkan waktu lebih lama…');
        try {
            const [cv,ocr]=await Promise.all([getCv(),getWorker()]);
            const canvas=rotatedCanvas(),ctx=canvas.getContext('2d',{willReadFrequently:true});
            const prepared=preparePrintedTable(cv,ctx.getImageData(0,0,canvas.width,canvas.height));
            if(!prepared.foundTable || !prepared.cells.length)throw new Error('Batas tabel belum dikenali. Gunakan foto dengan tabel utuh dan jelas, atau isi rencana secara manual.');
            recognitionStage={from:5,to:20};announce('Membaca customer dan tanggal…');
            await ocr.setParameters({tessedit_pageseg_mode:'6',tessedit_char_whitelist:''});
            const header=await ocr.recognize(pixelCanvas(prepared.header),{rotateAuto:false});
            const cells=[];
            if(prepared.cells.length>500)throw new Error('Maksimal 500 baris dalam satu rencana.');
            for(let i=0;i<prepared.cells.length;i++){
                const cell=prepared.cells[i],result={};
                announce('Membaca baris '+(i+1)+' dari '+prepared.cells.length+'…');
                for(const kind of ['part','quantity']){
                    const position=i*2+(kind==='quantity'?1:0);
                    recognitionStage={from:20+position/(prepared.cells.length*2)*80,to:20+(position+1)/(prepared.cells.length*2)*80};
                    await ocr.setParameters({tessedit_pageseg_mode:'7',tessedit_char_whitelist:kind==='part'?'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-./':'0123456789'});
                    const {data}=await ocr.recognize(pixelCanvas(cell[kind]),{rotateAuto:false});
                    result[kind]=data.text.trim();result[kind+'Confidence']=data.confidence;
                }
                cells.push(result);
            }
            const result=parsePhotoResult(header.data.text,cells,initial.parts);
            if(!result.items.length)throw new Error('Nomor part belum terbaca. Isi rencana manual atau gunakan Excel.');
            apply(result,'photo',byId('plan-photo-file').files[0].name);progress.value=100;
        } catch(error){
            if(worker){try{await worker.terminate();}catch{}worker=null;}
            announce(error.message || 'Foto belum berhasil dibaca. Anda tetap dapat mengisi rencana manual.',true);
        } finally{setBusy(false);}
    });
    form.addEventListener('submit',event=>{
        refresh();if(save.disabled || !form.reportValidity()){event.preventDefault();return;}
        submitting=true;save.disabled=true;save.textContent='Menyimpan…';
    });
    window.addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue='';}});
    window.addEventListener('pagehide',()=>{worker?.terminate();if(photoUrl)URL.revokeObjectURL(photoUrl);});
    initial.rows.forEach(row=>addRow(row,Boolean(row.part_number)));refresh();
}
