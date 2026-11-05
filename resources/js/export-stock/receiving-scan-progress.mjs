export function receivingScanProgress(userId, windowRef = globalThis.window) {
    const key = 'warehouse.export.receiving.scan.' + userId;
    const save = (batch, {box, part, checked, inputDate}) => {
        try {
            if (box || part || inputDate) {
                windowRef.localStorage.setItem(key, JSON.stringify({batchId: batch.id, box, part, checked, inputDate}));
            } else {
                windowRef.localStorage.removeItem(key);
            }
        } catch { /* Draft scans on the server remain available if storage is disabled. */ }
    };

    return {
        save,
        restore(batch) {
            let saved;
            try { saved = JSON.parse(windowRef.localStorage.getItem(key)); } catch { return null; }
            if (!saved) return null;

            // A draft may have been created just before the first scan request was interrupted.
            const emptyNewDraft = saved.batchId === null && batch.items.length === 0;
            if (batch.status !== 'draft' || (saved.batchId !== batch.id && !emptyNewDraft)) {
                save(batch, {});
                return null;
            }

            const box = typeof saved.box === 'string' && /^[0-9]{1,8}$/.test(saved.box) ? saved.box : '';
            const alreadyScanned = box && batch.items.some(item => item.box_number === box);
            const restored = {
                box: alreadyScanned ? '' : box,
                part: !alreadyScanned && typeof saved.part === 'string' ? saved.part.slice(0, 256) : '',
                checked: !alreadyScanned && box.length === 8 && saved.checked === true,
                inputDate: typeof saved.inputDate === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(saved.inputDate) ? saved.inputDate : '',
            };
            if (alreadyScanned || saved.batchId !== batch.id) save(batch, restored);
            return restored;
        },
    };
}
