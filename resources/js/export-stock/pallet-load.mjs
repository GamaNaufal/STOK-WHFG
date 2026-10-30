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

// Delegated change handling also covers placement forms loaded inside the stock modal.
export function initDestinationLoads({documentRef = globalThis.document, windowRef = globalThis.window} = {}) {
    const update = select => {
        const form = select.closest('form'), panel = form?.querySelector('[data-destination-load]');
        if (!panel) return;
        const option = select.selectedOptions[0];
        panel.hidden = !select.value;
        if (panel.hidden) return;
        const current = Number(option.dataset.boxCount || 0), incoming = Number(select.dataset.incomingCount);
        panel.querySelector('[data-load-current]').textContent = current.toLocaleString('id-ID');
        panel.querySelector('[data-load-incoming]').textContent = incoming.toLocaleString('id-ID');
        updatePalletLoad(panel.querySelector('[data-pallet-load]'), current + incoming);
    };
    documentRef.addEventListener('change', event => {
        if (event.target.matches('[data-pallet-load-select]')) update(event.target);
    });
    const restore = () => documentRef.querySelectorAll('[data-pallet-load-select]').forEach(update);
    windowRef?.addEventListener('pageshow', restore);
    restore();
}
