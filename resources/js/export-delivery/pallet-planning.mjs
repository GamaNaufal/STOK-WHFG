import {parseCount} from './document-parser.mjs';

// Replace this row while preserving allocations on other pallets.
export function availableForRow(summary, partId, currentQuantity) {
    const part = summary.find(item => String(item.part_setting_id) === String(partId));
    if (!part || part.required <= 0) return null;
    return Math.max(0, part.required - part.allocated + (parseCount(currentQuantity) ?? 0));
}

export function analyzePallets(rows,catalog,locations) {
    const parts=new Map(catalog.map(p=>[String(p.part_setting_id),p]));
    const places=new Map(locations.map(p=>[String(p.id),p]));
    const allocated=new Map(),used=new Set();
    let itemCount=0,totalBoxes=0;
    const pallets=rows.map(row=>{
        const errors=[],finalErrors=[];let boxes=0;
        const location=row.master_location_id ? places.get(String(row.master_location_id)):null;
        if(row.master_location_id){
            if(!location || location.blocked)errors.push('Lokasi tidak tersedia. Pilih lokasi ekspor lain.');
            if(used.has(String(row.master_location_id)))errors.push('Lokasi sudah dipilih pallet lain.');
            used.add(String(row.master_location_id));
        }else finalErrors.push('Lokasi belum ditentukan.');
        const seen=new Set();
        for(const item of row.items){
            itemCount++;
            const id=String(item.part_setting_id),part=parts.get(id),qty=parseCount(item.box_quantity);
            if(!part)errors.push('Pilih nomor part yang sesuai rencana delivery.');
            if(qty===null)errors.push('Jumlah box harus bilangan bulat lebih dari nol.');
            if(seen.has(id))errors.push('Gabungkan nomor part yang sama dalam satu baris pallet.');
            seen.add(id);
            if(part && part.required===0)finalErrors.push('Hapus part yang sudah tidak ada pada rencana delivery.');
            if(part && qty!==null){allocated.set(id,(allocated.get(id)??0)+qty);boxes+=qty;}
        }
        if(!row.items.length)finalErrors.push('Pallet belum diisi.');
        totalBoxes+=boxes;
        return {errors:[...new Set(errors)],finalErrors:[...new Set(finalErrors)],boxes};
    });
    const summary=catalog.map(part=>({...part,allocated:allocated.get(String(part.part_setting_id))??0,
        remaining:part.required-(allocated.get(String(part.part_setting_id))??0)}));
    const draftValid=rows.length>0 && rows.length<=500 && itemCount<=5000 && rows.every(r=>r.items.length<=500) && pallets.every(p=>!p.errors.length);
    const finalValid=draftValid && pallets.every(p=>!p.finalErrors.length) && summary.every(p=>p.remaining===0);
    return {pallets,summary,totalBoxes,draftValid,finalValid};
}
