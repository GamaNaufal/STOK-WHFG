export function parseCount(value) {
    if (typeof value === 'number') return Number.isSafeInteger(value) && value > 0 && value <= 4294967295 ? value : null;
    const text = String(value ?? '').trim();
    const normalized = /^\d+$/.test(text) ? text :
        /^\d{1,3}([., ])\d{3}(?:\1\d{3})*$/.test(text) ? text.replace(/[., ]/g, '') : '';
    const number = Number(normalized);
    return normalized && Number.isSafeInteger(number) && number > 0 && number <= 4294967295 ? number : null;
}

export function checkRows(rows, catalog, snapshots=[]) {
    const master = new Map(catalog.map(part=>[part.part_number,Number(part.qty_box)]));
    const saved = new Map(snapshots.map(row=>[row.part_number,Number(row.pcs_per_box)]));
    const counts = new Map();
    rows.forEach(row=>counts.set(row.part_number,(counts.get(row.part_number) ?? 0)+1));
    let totalPcs=0,totalBoxes=0;
    const results=rows.map(row=>{
        const quantity=parseCount(row.pcs_quantity);
        const capacity=saved.get(row.part_number) ?? master.get(row.part_number);
        let error='';
        if(!row.part_number) error='Pilih nomor part.';
        else if(!master.has(row.part_number)) error='Nomor part belum terdaftar di master ekspor.';
        else if(counts.get(row.part_number)>1) error='Part berulang. Gabungkan jumlah PCS dalam satu baris.';
        else if(quantity===null) error='Isi jumlah PCS bulat lebih dari nol.';
        else if(!Number.isSafeInteger(capacity) || capacity<1) error='Kapasitas box tidak valid.';
        else if(quantity%capacity!==0) error='Jumlah PCS harus kelipatan '+capacity+'. Box tidak penuh belum didukung.';
        const boxes=error ? null : quantity/capacity;
        if(!error) {totalPcs+=quantity;totalBoxes+=boxes;}
        return {...row,quantity,capacity,boxes,error};
    });
    return {rows:results,valid:rows.length>0 && rows.length<=500 && results.every(row=>!row.error),totalPcs,totalBoxes};
}
