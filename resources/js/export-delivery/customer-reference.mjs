export const normalizeCustomer = value => String(value ?? '').trim().replace(/\s+/gu, ' ').toLowerCase();

export function createCustomerReference({input, idInput, sourceInput, status, catalog = [], lookup, onChange = () => {}}) {
    const initialId = String(idInput?.value || '');
    let trusted = initialId ? {customer_id:initialId, customer_name:input.value, label:input.value} : null;
    let importError = null, explicitSelection = false, generation = 0, timer = null, controller = null;
    const cancel = () => { generation++; clearTimeout(timer); timer = null; controller?.abort(); controller = null; };
    const setMatch = match => {
        idInput.value = String(match.customer_id);
        status.textContent = 'Terhubung ke ' + match.customer_name;
    };
    const sync = ({edited = false, lookupMissing = false, immediate = false} = {}) => {
        if (!idInput) return;
        cancel();
        if (edited) { importError = null; explicitSelection = true; if (sourceInput) sourceInput.value = input.value; }
        if (importError) {
            idInput.value = '';
            status.textContent = importError.message || 'Pilih customer master untuk memperbaiki hasil impor.';
            onChange();
            return;
        }
        const legacy = !initialId && !explicitSelection && idInput.dataset.required !== '1' && input.value === idInput.dataset.legacyName;
        if (legacy) {
            idInput.value = '';
            status.textContent = 'Customer historis belum dipetakan, stok umum tetap dapat dipakai.';
            onChange();
            return;
        }
        // Preserve the identity of unchanged historical names and server-confirmed
        // imports, even when this page's initial catalog is older than the master.
        if (trusted && input.value === trusted.label) { setMatch(trusted); onChange(); return; }
        const key = normalizeCustomer(input.value);
        const matches = catalog.filter(customer => (customer.active || String(customer.id) === initialId)
            && (customer.labels || []).some(label => normalizeCustomer(label) === key));
        if (matches.length === 1) {
            setMatch({customer_id:matches[0].id, customer_name:matches[0].name});
            onChange();
            return;
        }
        idInput.value = '';
        status.textContent = 'Belum cocok, pilih customer master pada kolom ini.';
        onChange();
        if (!lookupMissing || !lookup || !key || legacy || matches.length > 1) return;
        const current = generation, label = input.value;
        status.textContent = 'Memeriksa nama atau alias pada master customer…';
        const run = async () => {
            controller = new AbortController();
            try {
                const match = await lookup(label, {signal:controller.signal});
                if (current !== generation || input.value !== label) return;
                if (match.status === 'matched') {
                    trusted = {...match, label};
                    setMatch(match);
                } else {
                    idInput.value = '';
                    status.textContent = match.message || 'Nama atau alias belum cocok dengan master customer aktif.';
                }
            } catch (error) {
                if (current !== generation || error.name === 'AbortError') return;
                idInput.value = '';
                status.textContent = 'Customer belum dapat diperiksa. Ketik ulang nama atau muat ulang halaman untuk mencoba lagi.';
            } finally {
                if (current === generation) { controller = null; onChange(); }
            }
        };
        if (immediate) return run();
        timer = setTimeout(run, 180);
    };
    return {
        sync,
        capture: () => ({customer_name:input.value, customer_id:idInput?.value || '',
            customer_source_name:sourceInput?.value || '', customer_match_error:importError, customer_selection_explicit:explicitSelection}),
        applyImport(data) {
            cancel(); trusted = null; importError = null; explicitSelection = true;
            input.value = data.customer_name || '';
            if (sourceInput) sourceInput.value = input.value;
            if (data.customer_match?.status === 'matched') {
                input.value = data.customer_match.customer_name;
                trusted = {...data.customer_match, label:input.value};
            } else if (data.customer_match) importError = data.customer_match;
            return sync({lookupMissing:true, immediate:true});
        },
        restore(data) {
            cancel();
            input.value = data.customer_name || '';
            if (sourceInput) sourceInput.value = data.customer_source_name || '';
            if (idInput) idInput.value = data.customer_id || '';
            trusted = data.customer_id ? {customer_id:data.customer_id, customer_name:input.value, label:input.value} : null;
            importError = data.customer_match_error || null;
            explicitSelection = Boolean(data.customer_selection_explicit);
            return sync({lookupMissing:true, immediate:true});
        },
    };
}
