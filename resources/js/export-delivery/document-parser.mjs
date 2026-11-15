export function parseCount(value) {
    if (typeof value === 'number') return Number.isSafeInteger(value) && value > 0 && value <= 4294967295 ? value : null;
    const text = String(value ?? '').trim();
    const normalized = /^\d+$/.test(text) ? text :
        /^\d{1,3}([., ])\d{3}(?:\1\d{3})*$/.test(text) ? text.replace(/[., ]/g, '') : '';
    const number = Number(normalized);
    return normalized && Number.isSafeInteger(number) && number > 0 && number <= 4294967295 ? number : null;
}

export function checkRows(rows, catalog, snapshots=[]) {
    const saved = new Map(snapshots.map(row=>[row.scan_part_number ?? row.part_number,Number(row.pcs_per_box)]));
    const resolve = number => {
        const historical = snapshots.find(row => row.part_number === number);
        if(historical) {
            const part = catalog.find(part => historical.part_setting_id ? Number(part.id) === Number(historical.part_setting_id)
                : part.part_number === (historical.scan_part_number ?? number));
            if(part) return [part];
        }
        return catalog.filter(part => part.part_number === number || (part.document_numbers ?? []).some(alias => alias.document_number === number));
    };
    const matches = rows.map(row => resolve(row.part_number));
    const counts = new Map();
    matches.forEach(parts=>{if(parts.length === 1) counts.set(parts[0].part_number,(counts.get(parts[0].part_number) ?? 0)+1);});
    let totalPcs=0,totalBoxes=0;
    const results=rows.map((row,index)=>{
        const parts=matches[index],part=parts.length===1 ? parts[0] : null;
        const quantity=parseCount(row.pcs_quantity);
        const capacity=part ? (saved.get(part.part_number) ?? Number(part.qty_box)) : undefined;
        let error='';
        if(!row.part_number) error='Pilih nomor part.';
        else if(parts.length>1) error='Kode ambigu. Periksa pemetaan master part.';
        else if(!part) error='Nomor part belum terdaftar di master bersama. Lengkapi kode dokumen pada master part.';
        else if(counts.get(part.part_number)>1) error='Part berulang. Gabungkan jumlah PCS dalam satu baris.';
        else if(quantity===null) error='Isi jumlah PCS bulat lebih dari nol.';
        else if(!Number.isSafeInteger(capacity) || capacity<1) error='Kapasitas box tidak valid.';
        const boxes=error ? null : Math.ceil(quantity/capacity);
        const notFullPcs=error ? 0 : quantity%capacity;
        if(!error) {totalPcs+=quantity;totalBoxes+=boxes;}
        return {...row,quantity,capacity,boxes,error,notFullPcs,partKnown:Boolean(part),scanPartNumber:part?.part_number,partSettingId:part?.id};
    });
    return {rows:results,valid:rows.length>0 && rows.length<=500 && results.every(row=>!row.error),totalPcs,totalBoxes};
}
