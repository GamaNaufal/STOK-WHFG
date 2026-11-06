export function palletLoadLevel(count, warningAt, dangerAt) {
    return count >= dangerAt ? 'danger' : count >= warningAt ? 'warning' : 'normal';
}

export function updatePalletLoad(element, count) {
    if (!element) return 'normal';
    const warningAt = Number(element.dataset.warningAt), dangerAt = Number(element.dataset.dangerAt);
    const level = palletLoadLevel(count, warningAt, dangerAt);
    element.dataset.level = level;
    element.querySelector('[data-load-count]').textContent = Number(count).toLocaleString('id-ID') + ' box';
    const note = element.querySelector('[data-load-note]');
    note.hidden = level === 'normal';
    note.textContent = level === 'danger' ? 'Perlu perhatian · ≥ ' + dangerAt + ' box'
        : level === 'warning' ? 'Peringatan · ≥ ' + warningAt + ' box' : '';
    return level;
}

export function palletCapacity(current, incoming, rawLimit) {
    const limit = Number(rawLimit || 0);
    if (!Number.isInteger(limit) || limit < 0 || limit > 4294967295) {
        return {text: 'Masukkan batas berupa angka bulat dari 0 sampai 4.294.967.295.', blocked: true};
    }
    if (limit === 0) return {text: '', blocked: false};

    const remaining = Math.max(0, limit - current);
    const after = remaining - incoming;
    const fmt = value => value.toLocaleString('id-ID');
    const message = after < 0
        ? 'Sisa ' + fmt(remaining) + ' box sebelum penempatan. Kurangi jumlah atau pilih tujuan lain.'
        : (after === 0 ? 'Penuh setelah penempatan.' : 'Sisa ' + fmt(after) + ' box setelah penempatan.');
    return {text: 'Maksimal ' + fmt(limit) + ' box · ' + message, blocked: after < 0};
}

// Delegated change handling also covers placement forms loaded inside the stock modal.
export function initDestinationLoads({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const update = select => {
        const form = select.closest('form'), panel = form?.querySelector('[data-destination-load]');
        if (!panel) return;
        const option = select.selectedOptions[0];
        const field = form.querySelector('[data-box-limit-field]');
        const input = field?.querySelector('[data-box-limit-input]');
        const isNew = select.value.startsWith('location:');
        if (input) {
            field.hidden = !isNew;
            input.disabled = !isNew;
        }
        panel.hidden = !select.value;
        if (panel.hidden) {
            if (input) form.querySelector('button[type="submit"]').disabled = false;
            return;
        }
        const current = Number(option.dataset.boxCount || 0), incoming = Number(select.dataset.incomingCount);
        panel.querySelector('[data-load-current]').textContent = current.toLocaleString('id-ID');
        panel.querySelector('[data-load-incoming]').textContent = incoming.toLocaleString('id-ID');
        updatePalletLoad(panel.querySelector('[data-pallet-load]'), current + incoming);
        const capacity = panel.querySelector('[data-destination-capacity]');
        if (capacity) {
            const result = palletCapacity(current, incoming, isNew ? input?.value : option.dataset.boxLimit);
            capacity.textContent = result.text;
            capacity.hidden = !result.text;
            capacity.classList.toggle('text-danger', result.blocked);
            form.querySelector('button[type="submit"]').disabled = result.blocked;
        }
    };
    documentRef.addEventListener('change', event => {
        if (event.target.matches('[data-pallet-load-select]')) update(event.target);
    });
    documentRef.addEventListener('input', event => {
        if (event.target.matches('[data-box-limit-input][name="box_limit"]')) {
            update(event.target.closest('form').querySelector('[data-pallet-load-select]'));
        }
    });
    const restore = () => documentRef.querySelectorAll('[data-pallet-load-select]').forEach(update);
    windowRef?.addEventListener('pageshow', restore);
    restore();
}
