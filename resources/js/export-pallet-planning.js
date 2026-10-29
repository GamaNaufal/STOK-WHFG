import {parseCount} from './export-delivery/document-parser.mjs';
import {analyzePallets, availableForRow} from './export-delivery/pallet-planning.mjs';

const page = document.getElementById('pallet-planning-editor');
if (page) {
    const byId = id => document.getElementById(id);
    const initial = JSON.parse(byId('pallet-bootstrap').textContent);
    const form = byId('pallet-planning-form'), container = byId('planned-pallets');
    const draft = byId('pallet-save-draft'), finalize = byId('pallet-finalize');
    const navigation = byId('pallet-active'), status = byId('pallet-editor-status');
    const search = byId('pallet-part-search'), incomplete = byId('pallet-incomplete-only');
    const parts = new Map(initial.catalog.map(part => [String(part.part_setting_id), part]));
    const places = new Map(initial.locations.map(place => [String(place.id), place]));
    const summaryRows = new Map([...byId('allocation-summary').children].map(row => [row.dataset.partId, row]));
    const format = value => Number(value).toLocaleString('id-ID');
    let dirty = false, submitting = false, activeIndex = 0, latestAnalysis;

    const itemRows = card => [...card.querySelector('.pallet-items').children];
    const collect = () => [...container.children].map(card => ({
        id: card.dataset.id ? Number(card.dataset.id) : null,
        master_location_id: card.querySelector('.pallet-location').value ? Number(card.querySelector('.pallet-location').value) : null,
        items: itemRows(card).map(row => ({
            part_setting_id: row.querySelector('.pallet-part').value ? Number(row.querySelector('.pallet-part').value) : '',
            box_quantity: row.querySelector('.pallet-quantity').value,
        })).filter(item => item.part_setting_id || item.box_quantity !== ''),
    }));
    const focusField = field => {
        field.focus({preventScroll: true});
        (field.closest('.pallet-item') ?? field).scrollIntoView({block: 'nearest', inline: 'nearest', behavior: 'instant'});
    };
    const addPart = (card, item = {}) => {
        const fragment = byId('pallet-item-template').content.cloneNode(true);
        const row = fragment.querySelector('.pallet-item'), select = row.querySelector('.pallet-part');
        initial.catalog.forEach(part => {
            const option = document.createElement('option');
            option.value = part.part_setting_id;
            option.textContent = part.part_number + (part.required === 0 ? ' (tidak ada di revisi delivery)' : '');
            select.append(option);
        });
        select.value = item.part_setting_id ?? '';
        row.querySelector('.pallet-quantity').value = item.box_quantity ?? '';
        card.querySelector('.pallet-items').append(fragment);
        return row;
    };
    const addPallet = (row = {}) => {
        const fragment = byId('planned-pallet-template').content.cloneNode(true);
        const card = fragment.querySelector('.planned-pallet'), select = card.querySelector('.pallet-location');
        if (row.id) card.dataset.id = row.id;
        initial.locations.forEach(location => {
            const option = document.createElement('option');
            option.value = location.id;
            option.disabled = Boolean(location.blocked);
            option.textContent = location.code + (location.blocked ? ' (tidak tersedia)' : location.reserved ? ' (direservasi untuk rencana ini)' : '');
            select.append(option);
        });
        select.value = row.master_location_id ?? '';
        const items = Array.isArray(row.items) && row.items.length ? row.items : [{}];
        items.forEach(item => addPart(card, item));
        container.append(fragment);
    };
    const filterParts = () => {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        let visible = 0;
        latestAnalysis.summary.forEach(part => {
            const row = summaryRows.get(String(part.part_setting_id));
            if (!row) return;
            row.hidden = !part.part_number.toLocaleLowerCase('id-ID').includes(query) || (incomplete.checked && part.remaining === 0);
            if (!row.hidden) visible++;
        });
        byId('pallet-parts-empty').hidden = visible > 0;
        const required = latestAnalysis.summary.filter(part => part.required > 0);
        const complete = required.filter(part => part.remaining === 0).length;
        byId('pallet-part-progress').textContent = format(complete) + ' dari ' + format(required.length) + ' part sesuai · ' + format(visible) + ' ditampilkan';
    };
    const refresh = () => {
        const rows = collect(), analysis = analyzePallets(rows, initial.catalog, initial.locations);
        latestAnalysis = analysis;
        activeIndex = Math.max(0, Math.min(activeIndex, rows.length - 1));
        const progress = new Map(analysis.summary.map(part => [String(part.part_setting_id), part]));
        while (navigation.options.length > rows.length) navigation.remove(navigation.options.length - 1);
        while (navigation.options.length < rows.length) navigation.add(new Option('', navigation.options.length));
        [...container.children].forEach((card, i) => {
            card.hidden = i !== activeIndex;
            card.querySelector('.pallet-title').textContent = 'Pallet ' + (i + 1);
            card.querySelector('.pallet-remove').setAttribute('aria-label', 'Hapus pallet ' + (i + 1));
            const location = card.querySelector('.pallet-location');
            location.id = 'pallet-location-' + i;
            card.querySelector('.pallet-location-label').htmlFor = location.id;
            card.querySelector('.pallet-location-label').textContent = 'Lokasi pallet ' + (i + 1);
            const entries = itemRows(card);
            entries.forEach((entry, j) => {
                const part = entry.querySelector('.pallet-part'), quantity = entry.querySelector('.pallet-quantity');
                const info = parts.get(part.value), counts = progress.get(part.value);
                part.id = 'pallet-part-' + i + '-' + j;
                quantity.id = 'pallet-quantity-' + i + '-' + j;
                entry.querySelector('.pallet-part-label').htmlFor = part.id;
                entry.querySelector('.pallet-quantity-label').htmlFor = quantity.id;
                part.setAttribute('aria-label', 'Nomor part pallet ' + (i + 1) + ' baris ' + (j + 1));
                quantity.setAttribute('aria-label', 'Jumlah box pallet ' + (i + 1) + ' baris ' + (j + 1));
                entry.querySelector('.pallet-capacity').textContent = info ? format(info.pcs_per_box) + ' PCS / box' : 'Pilih dari daftar kebutuhan part.';
                const indicator = entry.querySelector('.pallet-item-progress');
                indicator.id = 'pallet-progress-' + i + '-' + j;
                quantity.setAttribute('aria-describedby', indicator.id);
                entry.querySelector('.pallet-item-required').textContent = counts ? format(counts.required) : '—';
                entry.querySelector('.pallet-item-allocated').textContent = counts ? format(counts.allocated) : '—';
                entry.querySelector('.pallet-item-remaining').textContent = counts ? (counts.remaining < 0 ? 'Lebih ' : '') + format(Math.abs(counts.remaining)) : '—';
                entry.querySelector('.pallet-item-remainder').classList.toggle('is-excess', Boolean(counts && counts.remaining < 0));
                const available = availableForRow(analysis.summary, part.value, quantity.value);
                const duplicate = entries.some(other => other !== entry && other.querySelector('.pallet-part').value === part.value);
                const fill = entry.querySelector('.pallet-fill-remaining');
                fill.disabled = submitting || !available || available === parseCount(quantity.value) || duplicate;
                fill.title = available ? 'Jadikan ' + format(available) + ' box tanpa mengubah alokasi di pallet lain' : 'Tidak ada sisa kebutuhan untuk baris ini';
            });
            const result = analysis.pallets[i];
            card.querySelector('.pallet-box-count').textContent = format(result.boxes) + ' box';
            const errors = card.querySelector('.pallet-errors');
            errors.textContent = [...result.errors, ...result.finalErrors].join(' ');
            errors.classList.toggle('text-danger', result.errors.length > 0);
            card.querySelector('.pallet-add-part').disabled = submitting || entries.length >= 500;
            const locationLabel = places.get(String(rows[i].master_location_id))?.code ?? 'Belum ada lokasi';
            const suffix = result.errors.length ? ' · Periksa isian' : result.finalErrors.length ? ' · Belum lengkap' : '';
            navigation.options[i].textContent = 'Pallet ' + (i + 1) + ' · ' + locationLabel + ' · ' + format(result.boxes) + ' box' + suffix;
        });
        navigation.value = String(activeIndex);
        byId('pallet-previous').disabled = activeIndex === 0;
        byId('pallet-next').disabled = activeIndex === rows.length - 1;
        const activeParts = new Set(itemRows(container.children[activeIndex]).map(row => row.querySelector('.pallet-part').value));
        analysis.summary.forEach(part => {
            const row = summaryRows.get(String(part.part_setting_id));
            if (!row) return;
            row.querySelector('.allocation-allocated').textContent = format(part.allocated);
            const cell = row.querySelector('.allocation-remaining');
            cell.textContent = part.remaining === 0 ? 'Sesuai' : (part.remaining > 0 ? 'Kurang ' : 'Lebih ') + format(Math.abs(part.remaining));
            cell.classList.toggle('text-danger', part.remaining < 0);
            cell.classList.toggle('text-success', part.remaining === 0);
            const inActive = activeParts.has(String(part.part_setting_id));
            row.classList.toggle('is-active-part', inActive);
            const pick = row.querySelector('.allocation-pick');
            pick.disabled = submitting || part.required === 0 || (!inActive && part.remaining <= 0);
            pick.title = inActive ? 'Fokus ke jumlah box pada pallet aktif' : 'Tambahkan ke pallet ' + (activeIndex + 1);
        });
        filterParts();
        byId('pallets-json').value = JSON.stringify(rows);
        byId('pallet-total-count').textContent = format(rows.length);
        byId('pallet-total-boxes').textContent = format(analysis.totalBoxes);
        draft.disabled = submitting || !analysis.draftValid;
        finalize.disabled = submitting || !analysis.finalValid;
        byId('pallet-add').disabled = rows.length >= 500 || submitting;
        byId('pallet-save-help').textContent = analysis.finalValid ? 'Seluruh alokasi sesuai. Perencanaan siap difinalisasi.' : !analysis.draftValid ? 'Periksa isian part, jumlah box, atau lokasi sebelum menyimpan draft.' : 'Draft boleh belum lengkap. Lengkapi lokasi dan seluruh alokasi untuk finalisasi.';
        return analysis;
    };
    const activate = index => {
        activeIndex = index;
        status.textContent = '';
        refresh();
        container.scrollTop = 0;
    };
    navigation.addEventListener('change', () => activate(Number(navigation.value)));
    byId('pallet-previous').addEventListener('click', () => activate(activeIndex - 1));
    byId('pallet-next').addEventListener('click', () => activate(activeIndex + 1));
    search.addEventListener('input', filterParts);
    incomplete.addEventListener('change', filterParts);
    byId('pallet-clear-search').addEventListener('click', () => {
        search.value = '';
        incomplete.checked = false;
        filterParts();
        search.focus({preventScroll: true});
    });
    search.addEventListener('keydown', event => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const first = [...summaryRows.values()].find(row => !row.hidden && !row.querySelector('.allocation-pick').disabled);
        first?.querySelector('.allocation-pick').focus();
    });
    byId('allocation-summary').addEventListener('click', event => {
        const button = event.target.closest('.allocation-pick');
        if (!button || button.disabled) return;
        const card = container.children[activeIndex], entries = itemRows(card);
        let entry = entries.find(row => row.querySelector('.pallet-part').value === button.dataset.partId);
        if (!entry) {
            entry = entries.find(row => !row.querySelector('.pallet-part').value && !row.querySelector('.pallet-quantity').value);
            if (!entry && entries.length >= 500) {
                status.textContent = 'Maksimal 500 baris part pada satu pallet.';
                return;
            }
            entry ??= addPart(card);
            entry.querySelector('.pallet-part').value = button.dataset.partId;
            dirty = true;
            refresh();
        }
        focusField(entry.querySelector('.pallet-quantity'));
    });
    container.addEventListener('input', () => { dirty = true; status.textContent = ''; refresh(); });
    container.addEventListener('change', () => { dirty = true; status.textContent = ''; refresh(); });
    container.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const card = button.closest('.planned-pallet');
        if (button.classList.contains('pallet-add-part')) {
            const entry = addPart(card);
            dirty = true;
            refresh();
            focusField(entry.querySelector('.pallet-part'));
        } else if (button.classList.contains('pallet-fill-remaining')) {
            const entry = button.closest('.pallet-item'), quantity = entry.querySelector('.pallet-quantity');
            const available = availableForRow(latestAnalysis.summary, entry.querySelector('.pallet-part').value, quantity.value);
            if (!available || button.disabled) return;
            quantity.value = available;
            dirty = true;
            status.textContent = format(available) + ' box dialokasikan pada baris ini.';
            refresh();
            focusField(quantity);
        } else if (button.classList.contains('pallet-remove-part')) {
            button.closest('.pallet-item').remove();
            if (!itemRows(card).length) addPart(card);
            dirty = true;
            refresh();
        } else if (button.classList.contains('pallet-remove')) {
            WarehouseAlert.confirm({title: 'Hapus pallet dari susunan?', message: 'Alokasi pada pallet ini akan dikeluarkan dari susunan. Simpan draft untuk menerapkan perubahan.', confirmText: 'Hapus pallet', onConfirm: () => {
                card.remove();
                if (!container.children.length) addPallet();
                dirty = true;
                activate(activeIndex);
                status.textContent = 'Pallet dihapus dari susunan. Simpan draft untuk menerapkan perubahan.';
                navigation.focus({preventScroll: true});
            }});
        }
    });
    byId('pallet-add').addEventListener('click', () => {
        const count = parseCount(byId('pallet-add-count').value), firstNew = container.children.length;
        if (!count || count + firstNew > 500) {
            status.textContent = 'Tambahkan jumlah bulat yang valid. Maksimal 500 pallet per rencana.';
            return;
        }
        for (let i = 0; i < count; i++) addPallet();
        dirty = true;
        activate(firstNew);
        status.textContent = format(count) + ' pallet ditambahkan. Pallet ' + (firstNew + 1) + ' sedang aktif.';
        navigation.focus({preventScroll: true});
    });
    form.addEventListener('submit', event => {
        const analysis = refresh(), isFinal = event.submitter?.value === 'finalize';
        if (!analysis.draftValid || (isFinal && !analysis.finalValid)) {
            event.preventDefault();
            const invalid = analysis.pallets.findIndex(pallet => pallet.errors.length || (isFinal && pallet.finalErrors.length));
            if (invalid >= 0) activate(invalid);
            status.textContent = 'Periksa isian dan sisa kebutuhan sebelum menyimpan.';
            return;
        }
        // Reveal an invalid hidden pallet before native validation reports its field.
        const invalid = [...container.querySelectorAll('.pallet-quantity')].find(input => !input.checkValidity());
        if (invalid) {
            event.preventDefault();
            activate([...container.children].indexOf(invalid.closest('.planned-pallet')));
            invalid.reportValidity();
            return;
        }
        submitting = true;
        // Keep submitter enabled so native submission includes the action.
    });
    window.addEventListener('beforeunload', event => {
        if (dirty && !submitting) { event.preventDefault(); event.returnValue = ''; }
    });
    const rows = Array.isArray(initial.rows) && initial.rows.length ? initial.rows : [{items: []}];
    rows.forEach(row => addPallet(row));
    refresh();

    const fitWorkspace = () => {
        const workspace = byId('pallet-workspace');
        form.style.setProperty('--pallet-save-bar-height', (byId('pallet-save-bar').offsetHeight + 24) + 'px');
        if (window.innerWidth < 992) { workspace.style.removeProperty('--pallet-workspace-height'); return; }
        const top = workspace.getBoundingClientRect().top + window.scrollY;
        const available = window.innerHeight - top - byId('pallet-save-bar').offsetHeight - 28;
        workspace.style.setProperty('--pallet-workspace-height', Math.max(352, available) + 'px');
    };
    window.addEventListener('resize', fitWorkspace);
    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(fitWorkspace);
        observer.observe(byId('pallet-save-bar'));
        observer.observe(page.querySelector('.plan-header'));
    }
    requestAnimationFrame(fitWorkspace);
}
