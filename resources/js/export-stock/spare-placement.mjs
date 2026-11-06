import {palletCapacity, updatePalletLoad} from './pallet-load.mjs';

export function distributeSpareBoxes(boxes, allocations, {requireDestination = true} = {}) {
    if (!allocations.length || allocations.some(row => !Number.isSafeInteger(Number(row.quantity)) || Number(row.quantity) < 1)) {
        throw new Error('Jumlah box setiap tujuan harus berupa angka bulat minimal 1.');
    }
    const total = allocations.reduce((sum, row) => sum + Number(row.quantity), 0);
    if (total !== boxes.length) throw new Error(`Pembagian harus tepat ${boxes.length} box; saat ini ${total} box.`);
    if (allocations.some(row => row.destination && !/^(pallet|location):[1-9][0-9]*$/.test(row.destination))) throw new Error('Pilih tujuan dari lokasi atau pallet yang tersedia.');
    if (requireDestination && allocations.some(row => !row.destination)) throw new Error('Pilih lokasi atau pallet untuk setiap tujuan.');
    let offset = 0;
    return allocations.flatMap(row => {
        const count = Number(row.quantity), selected = boxes.slice(offset, offset + count);
        offset += count;
        return selected.map(box => ({id:box.id, revision:box.revision, request_token:box.request_token,
            box_number:box.box_number, part_number:box.part_number, destination:row.destination || '',
            ...(row.destination?.startsWith('location:') && Number(row.boxLimit) > 0 ? {box_limit: Number(row.boxLimit)} : {})}));
    });
}

export function readSparePlacement(block, options = {}) {
    const boxes = JSON.parse(block.querySelector('[data-spare-boxes]').textContent);
    const allocation = (select, quantity) => ({quantity, destination: select.value,
        boxLimit: select.closest('[data-spare-destination]').querySelector('[data-box-limit-input]').value});
    const allocations = block.dataset.split === 'true'
        ? [...block.querySelectorAll('[data-spare-allocation]')].map(row => allocation(
            row.querySelector('[data-spare-target]'), row.querySelector('[data-spare-quantity]').value))
        : [allocation(block.querySelector('[data-spare-single-select]'), boxes.length)];
    return distributeSpareBoxes(boxes, allocations, options);
}

export function spareIncoming(plans, planned = []) {
    const totals = new Map();
    for (const row of plans.flat()) if (row.destination) totals.set(row.destination, (totals.get(row.destination) || 0) + 1);
    for (const row of planned) if (row.destination) totals.set(row.destination, (totals.get(row.destination) || 0) + Number(row.incoming));
    return totals;
}

function spareDraftStorage(transaction, windowRef) {
    const key = transaction ? 'warehouse.export.receiving.placement.' + transaction : null;

    return {
        load() {
            if (!key) return {};
            try { return JSON.parse(windowRef.sessionStorage.getItem(key)) || {}; }
            catch { return {}; }
        },
        save(draft) {
            if (!key) return;
            try {
                if (Object.keys(draft.groups).length) windowRef.sessionStorage.setItem(key, JSON.stringify(draft));
                else windowRef.sessionStorage.removeItem(key);
            } catch { /* Placement still works if browser storage is unavailable. */ }
        },
    };
}

export function initSparePlacements({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const receipt = documentRef.querySelector('[data-spare-draft-key]');
    const store = spareDraftStorage(receipt?.dataset.spareDraftKey, windowRef);
    const draft = store.load();
    const pendingKeys = new Set(JSON.parse(receipt?.dataset.sparePendingKeys || '[]'));
    const createdPallets = JSON.parse(receipt?.dataset.spareCreatedPallets || '{}');
    const groups = new Map(Object.entries(draft.groups || {}).filter(([key]) => pendingKeys.has(key)));
    const blocks = [...documentRef.querySelectorAll('[data-spare-placement]')];
    if (!blocks.length) {
        store.save({groups: Object.fromEntries(groups), limits: draft.limits, invalidLimits: draft.invalidLimits});
        return;
    }
    const confirmAll = documentRef.querySelector('[data-spare-confirm-all]');
    if (confirmAll) confirmAll.hidden = false;
    const fmt = value => Number(value).toLocaleString('id-ID');
    let serial = 0;
    const optionFor = (block, destination) => [...block.querySelector('[data-spare-single-select]').options].find(option => option.value === destination);
    const textFor = (block, destination) => {
        const option = optionFor(block, destination);
        return option?.value ? `${option.dataset.guidePallet} · ${option.dataset.guideLocation}` : 'Belum dipilih';
    };
    const limits = new Map(Object.entries(draft.limits || {}));
    const invalidLimits = new Set(Array.isArray(draft.invalidLimits) ? draft.invalidLimits : []);
    const restoreNotices = new Map();
    const captureDraft = [];
    const saveDraft = () => {
        captureDraft.forEach(capture => capture());
        store.save({groups: Object.fromEntries(groups), limits: Object.fromEntries(limits), invalidLimits: [...invalidLimits]});
    };
    const payload = rows => JSON.stringify(rows.map(({id, revision, request_token, destination, box_limit}) =>
        ({id, revision, request_token, destination, box_limit})));
    const syncLimits = block => block.querySelectorAll('[data-spare-destination]').forEach(control => {
        const select = control.querySelector('select');
        const field = control.querySelector('[data-box-limit-field]');
        const input = field.querySelector('[data-box-limit-input]');
        const show = !select.disabled && select.value.startsWith('location:');
        field.hidden = !show;
        input.disabled = !show;
        const value = show ? limits.get(select.value) ?? '' : '';
        if (input.value !== value) input.value = value;
    });
    const refresh = () => {
        blocks.forEach(syncLimits);
        const plans = blocks.map(block => { try { return readSparePlacement(block, {requireDestination:false}); } catch { return []; } });
        const plannedGroups = [...documentRef.querySelectorAll('[data-planned-target]')];
        const totals = spareIncoming(plans, plannedGroups.map(group => ({destination:group.dataset.plannedTarget ? `pallet:${group.dataset.plannedTarget}` : '', incoming:Number(group.dataset.plannedIncoming)})));
        blocks.forEach((block, index) => {
            const form = block.closest('form'), status = block.querySelector('[data-spare-allocation-status]');
            const targets = [...new Set(plans[index].map(row => row.destination).filter(Boolean))];
            const capacities = targets.map(destination => {
                const option = optionFor(block, destination);
                const current = Number(option?.dataset.boxCount || 0), incoming = totals.get(destination) || 0;
                const limit = destination.startsWith('location:')
                    ? (invalidLimits.has(destination) ? 'invalid' : limits.get(destination))
                    : option?.dataset.boxLimit;
                return {destination, current, incoming, ...palletCapacity(current, incoming, limit)};
            });
            const blocked = capacities.find(capacity => capacity.blocked);
            block.dataset.spareCapacityError = blocked ? textFor(block, blocked.destination) + ' · ' + blocked.text : '';
            let error = block.dataset.spareCapacityError, complete;
            try { complete = readSparePlacement(block); } catch (exception) { error ||= exception.message; }
            status.textContent = restoreNotices.get(block) || error || `${plans[index].length} box sudah terbagi. Konfirmasikan setelah ditempatkan.`;
            status.classList.toggle('text-danger', Boolean(error || restoreNotices.has(block)));
            form.querySelector('button[type="submit"]').disabled = Boolean(error);
            if (complete && !error) form.querySelector('[name="placements_json"]').value = payload(complete);
            const section = block.closest('[data-spare-part-group]'), banner = section.querySelector('[data-spare-guide-destination]');
            const selected = optionFor(block, targets[0]);
            banner.classList.toggle('is-unassigned', !targets.length);
            banner.querySelector('[data-guide-pallet]').textContent = targets.length > 1 ? `${targets.length} tujuan penyimpanan` : selected?.dataset.guidePallet || 'Pallet cadangan';
            banner.querySelector('[data-guide-location]').textContent = targets.length > 1 ? targets.map(value => optionFor(block,value)?.dataset.guideLocation).join(' · ') : selected?.dataset.guideLocation || 'Belum dipilih';
            const preview = block.querySelector('[data-spare-preview]');
            preview.hidden = false;
            if (preview.open) {
                const tbody = preview.querySelector('[data-spare-preview-rows]'); tbody.replaceChildren();
                for (const row of plans[index]) {
                    const tr = documentRef.createElement('tr');
                    for (const value of [row.box_number,row.part_number,textFor(block,row.destination)]) { const td = documentRef.createElement('td'); td.textContent = value; tr.append(td); }
                    tbody.append(tr);
                }
            }
            const loads = block.querySelector('[data-spare-loads]'); loads.replaceChildren(); loads.hidden = !targets.length;
            for (const {destination, current, incoming, text, blocked} of capacities) {
                const row = documentRef.createElement('div'); row.className = 'receiving-spare-load-row';
                const label = documentRef.createElement('p'); label.className = 'plan-help mb-1';
                label.textContent = `${textFor(block,destination)} · ${fmt(current)} box saat ini + ${fmt(incoming)} box masuk dari transaksi ini`;
                const badge = documentRef.createElement('span'); badge.className = 'export-pallet-load'; badge.dataset.warningAt = block.dataset.warningAt; badge.dataset.dangerAt = block.dataset.dangerAt; badge.dataset.palletLoad = '';
                const count = documentRef.createElement('strong'); count.dataset.loadCount = '';
                const note = documentRef.createElement('span'); note.dataset.loadNote = ''; badge.append(count,note);
                updatePalletLoad(badge, current + incoming);
                row.append(label, badge);
                if (text) {
                    const capacity = documentRef.createElement('p');
                    capacity.className = 'plan-help mt-2 mb-0' + (blocked ? ' text-danger' : '');
                    capacity.textContent = text;
                    row.append(capacity);
                }
                loads.append(row);
            }
            block.querySelector('[data-add-spare-destination]').disabled = block.querySelectorAll('[data-spare-allocation]').length >= JSON.parse(block.querySelector('[data-spare-boxes]').textContent).length;
        });
        plannedGroups.forEach(group => {
            const badge = group.querySelector('.receiving-group-load [data-pallet-load]');
            if (badge && group.dataset.plannedTarget) {
                const current=Number(group.dataset.plannedCurrent), incoming=totals.get(`pallet:${group.dataset.plannedTarget}`)||0;
                updatePalletLoad(badge,current+incoming);
                group.querySelector('[data-planned-load-summary]').textContent=`Isi pallet ${fmt(current)} box + ${fmt(incoming)} box masuk dari transaksi ini. Total setelah penempatan:`;
            }
        });
        if (confirmAll) confirmAll.querySelector('button[type="submit"]').disabled = blocks.some(block => block.closest('form').querySelector('button[type="submit"]').disabled);
        documentRef.dispatchEvent(new windowRef.CustomEvent('spare-placement-change'));
    };
    blocks.forEach(block => {
        const form = block.closest('form'), boxes = JSON.parse(block.querySelector('[data-spare-boxes]').textContent);
        const single = block.querySelector('[data-spare-single-select]'), toggle = block.querySelector('[data-split-toggle]');
        const rows = block.querySelector('[data-spare-allocation-rows]');
        const add = (quantity, destination = '') => {
            const fragment = block.querySelector('[data-spare-allocation-template]').content.cloneNode(true);
            const row = fragment.querySelector('.receiving-spare-allocation'); row.dataset.spareAllocation = '';
            const qty = row.querySelector('[data-spare-quantity]'), target = row.querySelector('[data-spare-target]');
            target.append(...[...single.childNodes].map(node => node.cloneNode(true)));
            const id = `spare-allocation-${++serial}`;
            qty.id = `${id}-quantity`; qty.value = quantity; qty.max = boxes.length;
            target.id = `${id}-target`; target.value = destination;
            row.querySelector('[data-allocation-qty-label]').htmlFor = qty.id; row.querySelector('[data-allocation-target-label]').htmlFor = target.id;
            qty.required = true; target.required = true;
            rows.append(fragment);
        };
        const setSplit = split => {
            block.dataset.split = String(split);
            block.querySelector('[data-spare-single]').hidden = split;
            block.querySelector('[data-spare-split]').hidden = !split;
            single.disabled = split; single.required = !split;
            rows.querySelectorAll('input,select,button').forEach(control => { control.disabled = !split; });
            toggle.setAttribute('aria-expanded', String(split)); toggle.textContent = split ? 'Gunakan satu tujuan' : 'Bagi ke beberapa tujuan';
        };
        // Per-box destinations are encoded in JSON. Never submit a competing common destination.
        single.removeAttribute('name');
        toggle.hidden = boxes.length < 2;

        const key = block.dataset.key;
        const boxVersion = boxes.map(box => `${box.id}:${box.revision}`).join(',');
        const saved = groups.get(key);
        if (saved?.boxVersion === boxVersion) {
            let unavailable = false;
            const restoreTarget = destination => {
                if (typeof destination !== 'string' || !destination) return '';
                const location = destination.startsWith('location:') ? destination.slice('location:'.length) : null;
                const pallet = location ? createdPallets[location] : null;
                const option = optionFor(block, pallet ? `pallet:${pallet}` : destination);
                if (option && !option.disabled && (!pallet || option.dataset.locationId === location)) {
                    return option.value;
                }
                unavailable = true;
                return '';
            };
            single.value = restoreTarget(saved.single);
            const singleUnavailable = unavailable;
            unavailable = false;
            const splitRows = Array.isArray(saved.rows) ? saved.rows.slice(0, boxes.length) : [];
            for (const row of splitRows) {
                if (row && typeof row.quantity === 'string') add(row.quantity, restoreTarget(row.destination));
            }
            const split = saved.split === true && boxes.length > 1 && rows.children.length > 0;
            setSplit(split);
            if (split ? unavailable : singleUnavailable) {
                restoreNotices.set(block, 'Tujuan sebelumnya sudah tidak tersedia. Pilih ulang lokasi atau pallet.');
            }
        } else if (saved) {
            restoreNotices.set(block, 'Daftar box untuk part ini berubah. Tinjau ulang pembagian sebelum konfirmasi.');
        }

        captureDraft.push(() => {
            const split = block.dataset.split === 'true';
            const allocations = [...rows.querySelectorAll('[data-spare-allocation]')].map(row => ({
                quantity: row.querySelector('[data-spare-quantity]').value,
                destination: row.querySelector('[data-spare-target]').value,
            }));
            if (split || single.value || allocations.some(row => row.destination)) {
                groups.set(key, {boxVersion, split, single: single.value, rows: allocations});
            } else {
                groups.delete(key);
            }
        });
        const updateDraft = () => { refresh(); saveDraft(); };
        toggle.addEventListener('click', () => {
            const split = block.dataset.split !== 'true';
            if (split && !rows.children.length) { add(boxes.length - 1, single.value); add(1); }
            if (!split) single.value = rows.querySelector('[data-spare-target]')?.value || single.value;
            setSplit(split);
            restoreNotices.delete(block);
            updateDraft();
        });
        block.addEventListener('input', event => {
            if (event.target.matches('[data-box-limit-input]')) {
                const destination = event.target.closest('[data-spare-destination]').querySelector('select').value;
                limits.set(destination, event.target.value);
                if (event.target.validity.badInput) invalidLimits.add(destination);
                else invalidLimits.delete(destination);
                updateDraft();
            } else if (event.target.matches('[data-spare-quantity]')) updateDraft();
        });
        block.addEventListener('change', event => {
            if (event.target.matches('select')) restoreNotices.delete(block);
            updateDraft();
        });
        block.querySelector('[data-spare-preview]').addEventListener('toggle', refresh);
        block.querySelector('[data-add-spare-destination]').addEventListener('click', () => {
            const current = [...rows.querySelectorAll('[data-spare-quantity]')];
            const sum = current.reduce((total,input) => total + Number(input.value || 0),0);
            const donor = [...current].reverse().find(input => Number(input.value)>1);
            if (sum === boxes.length && donor) donor.value = Number(donor.value)-1;
            add(Math.max(1,boxes.length-sum)); updateDraft();
        });
        rows.addEventListener('click', event => {
            const button = event.target.closest('[data-remove-spare-destination]');
            if (!button) return;
            const row = button.closest('[data-spare-allocation]'), quantity = Number(row.querySelector('[data-spare-quantity]').value || 0);
            row.remove(); const first = rows.querySelector('[data-spare-quantity]');
            if (first) first.value = Number(first.value || 0) + quantity; else add(boxes.length);
            restoreNotices.delete(block);
            updateDraft();
        });
        form.addEventListener('submit', event => {
            try {
                if (block.dataset.spareCapacityError) throw new Error(block.dataset.spareCapacityError);
                form.querySelector('[name="placements_json"]').value = payload(readSparePlacement(block));
                saveDraft();
            } catch (error) { event.preventDefault(); block.querySelector('[data-spare-allocation-status]').textContent = error.message; }
        });
    });
    confirmAll?.addEventListener('submit', event => {
        try {
            const rows = blocks.flatMap(block => {
                if (block.dataset.spareCapacityError) throw new Error(block.dataset.spareCapacityError);
                return readSparePlacement(block);
            });
            confirmAll.querySelector('[name="placements_json"]').value = payload(rows);
            saveDraft();
        } catch { event.preventDefault(); refresh(); }
    });
    windowRef?.addEventListener('pageshow', refresh);
    refresh();
    saveDraft();
}
