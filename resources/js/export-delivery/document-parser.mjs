export function parseCount(value) {
    if (typeof value === 'number') return Number.isSafeInteger(value) && value > 0 && value <= 4294967295 ? value : null;
    const text = String(value ?? '').trim();
    const normalized = /^\d+$/.test(text) ? text :
        /^\d{1,3}([., ])\d{3}(?:\1\d{3})*$/.test(text) ? text.replace(/[., ]/g, '') : '';
    const number = Number(normalized);
    return normalized && Number.isSafeInteger(number) && number > 0 && number <= 4294967295 ? number : null;
}

export function parseDocumentDate(text) {
    const months = { januari:1,january:1,februari:2,february:2,maret:3,march:3,april:4,mei:5,may:5,
        juni:6,june:6,juli:7,july:7,agustus:8,august:8,september:9,oktober:10,october:10,november:11,desember:12,december:12 };
    const valid = (year,month,day) => {
        const date = new Date(Date.UTC(year,month-1,day));
        return date.getUTCFullYear()===year && date.getUTCMonth()===month-1 && date.getUTCDate()===day ?
            year+'-'+String(month).padStart(2,'0')+'-'+String(day).padStart(2,'0') : '';
    };
    let m=String(text).match(/\b(\d{4})-(\d{2})-(\d{2})\b/);
    if(m) return valid(Number(m[1]),Number(m[2]),Number(m[3]));
    m=String(text).match(/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/);
    if(m) return valid(Number(m[3]),Number(m[2]),Number(m[1]));
    m=String(text).match(/\b(\d{1,2})\s+([a-z]+)\s+(\d{4})\b/i);
    return m ? valid(Number(m[3]),months[m[2].toLowerCase()] ?? 0,Number(m[1])) : '';
}

export function parsePhotoResult(headerText, cells, catalog) {
    const customer = String(headerText).match(/(?:CUSTOMER|PELANGGAN)\s*[:：]\s*([^\n]+)/i)?.[1]?.trim() ?? '';
    const known = new Set(catalog.map(part=>part.part_number));
    const items = cells.map(cell => {
        const raw = String(cell.part ?? '').trim().replace(/[–—−]/g,'-');
        const tokens = raw.split(/\s+/).map(value=>value.replace(/^[\[\]|]+|[\[\]|]+$/g,''));
        const exact = [...new Set(tokens.filter(token=>known.has(token)))];
        // Only choose a literal master match; never fuzzy-correct an OCR code.
        const partNumber = exact.length===1 ? exact[0] : raw;
        return { part_number:partNumber, pcs_quantity:parseCount(cell.quantity) ?? '',
            warning: Math.min(cell.partConfidence ?? 0, cell.quantityConfidence ?? 0)<70 ? 'Cocokkan kembali dengan foto.' : '' };
    }).filter(row=>row.part_number || row.pcs_quantity);
    return { customer_name:customer,delivery_deadline:parseDocumentDate(headerText),items };
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
