(() => {
    'use strict';
    const states = { qa: ['at_qa'], packing: ['awaiting_packing'], receive: ['at_packing'], print: ['awaiting_packing', 'at_packing', 'completed'] };
    document.querySelectorAll('[data-bulk-selection]').forEach(form => {
        const group = form.dataset.bulkSelection;
        const boxes = Array.from(document.querySelectorAll(`[data-bulk-item="${group}"]`));
        const all = document.querySelector(`[data-select-all="${group}"]`);
        const none = document.querySelector(`[data-select-none="${group}"]`);
        const count = document.querySelector(`[data-selection-count="${group}"]`);
        const submit = document.querySelector(`[data-review="${group}"]`);
        const action = form.elements.action;
        const eligible = box => box.dataset.blocked !== '1' && (group === 'start' || (states[action.value] || []).includes(box.dataset.state));
        const update = () => {
            const selected = boxes.filter(box => box.checked);
            const invalid = selected.filter(box => !eligible(box)).length;
            count.textContent = `${selected.length} box dipilih untuk transaksi ini · maks. 50${invalid ? ` · ${invalid} tidak sesuai tindakan` : ''}`;
            submit.disabled = selected.length === 0 || selected.length > 50 || invalid > 0;
        };
        all.hidden = false;
        none.hidden = false;
        all.addEventListener('click', () => {
            let selected = boxes.filter(box => box.checked).length;
            boxes.forEach(box => {
                if (box.closest('tr')?.hidden || box.checked || !eligible(box) || selected >= 50) return;
                box.checked = true;
                selected += 1;
            });
            update();
        });
        none.addEventListener('click', () => { boxes.forEach(box => { box.checked = false; }); update(); });
        boxes.forEach(box => box.addEventListener('change', update));
        action.addEventListener('change', update);
        window.addEventListener('pageshow', update);
        form.addEventListener('submit', event => { update(); if (submit.disabled) event.preventDefault(); });
        update();
    });

    const filter = document.querySelector('[data-box-filter]');
    if (filter) {
        const group = document.querySelector('[data-box-group]');
        const rows = Array.from(document.querySelectorAll('[data-box-row]'));
        const visibleCount = document.querySelector('[data-filter-visible]');
        const empty = document.querySelector('[data-box-filter-empty]');
        const updateFilter = () => {
            const term = filter.value.trim().toLocaleLowerCase();
            let visible = 0;
            rows.forEach(row => {
                const inGroup = !group || (!!group.value && row.dataset.group === group.value);
                row.hidden = !inGroup || !row.textContent.toLocaleLowerCase().includes(term);
                const checkbox = row.querySelector('[data-bulk-item]');
                if (checkbox) {
                    checkbox.disabled = !inGroup || checkbox.dataset.blocked === '1';
                    if (!inGroup || checkbox.disabled) checkbox.checked = false;
                }
                if (!row.hidden) visible += 1;
            });
            visibleCount.textContent = `${visible} dari ${rows.length} box ditampilkan`;
            empty.hidden = visible > 0 || rows.length === 0;
            if (group && !group.value) empty.textContent = 'Pilih kelompok nomor part dan PCS untuk menampilkan box.';
            else empty.textContent = 'Tidak ada box yang cocok dalam kelompok ini. Coba pencarian lain.';
            rows[0]?.querySelector('[data-bulk-item]')?.dispatchEvent(new Event('change'));
        };
        group?.addEventListener('change', () => {
            rows.forEach(row => {
                const checkbox = row.querySelector('[data-bulk-item]');
                if (checkbox) { checkbox.checked = false; checkbox.dispatchEvent(new Event('change')); }
            });
            updateFilter();
        });
        filter.addEventListener('input', updateFilter);
        window.addEventListener('pageshow', updateFilter);
        updateFilter();
    }

    document.querySelector('[data-apply-qa]')?.addEventListener('click', () => {
        const choice = document.getElementById('common-qa').value;
        if (!choice) {
            document.querySelector('[data-qa-feedback]').textContent = 'Pilih hasil QA bersama terlebih dahulu.';
            document.getElementById('common-qa').focus();
            return;
        }
        const notes = document.getElementById('common-notes').value;
        document.querySelectorAll('[data-qa-decision]').forEach(select => {
            select.value = choice;
            if (notes) document.getElementById(select.dataset.notesId).value = notes;
            select.dispatchEvent(new Event('change'));
        });
        const feedback = document.querySelector('[data-qa-feedback]');
        if (feedback) feedback.textContent = `Hasil QA diterapkan ke ${document.querySelectorAll('[data-qa-decision]').length} box. Periksa setiap baris sebelum menyimpan.`;
    });
    document.querySelectorAll('[data-qa-decision]').forEach(select => {
        const update = () => {
            document.getElementById(select.dataset.notesId).required = select.value === 'rejected';
            select.closest('[data-qa-row]')?.classList.toggle('is-rejected', select.value === 'rejected');
        };
        select.addEventListener('change', update);
        update();
    });
    const pallet = document.getElementById('batch-pallet');
    const newPalletLocation = document.querySelector('[data-new-pallet-location]');
    if (pallet && newPalletLocation) {
        const location = document.getElementById('batch-location');
        const updatePallet = () => {
            const mode = document.querySelector('[name="destination_mode"]:checked');
            const isNew = mode ? mode.value === 'new' : !pallet.value;
            newPalletLocation.hidden = !isNew;
            location.disabled = !isNew;
            location.required = isNew;
            const existing = document.querySelector('[data-existing-pallet]');
            if (existing) { existing.hidden = isNew; pallet.disabled = isNew; pallet.required = !isNew; }
        };
        pallet.addEventListener('change', updatePallet);
        document.querySelectorAll('[name="destination_mode"]').forEach(radio => radio.addEventListener('change', updatePallet));
        updatePallet();
    }
})();
