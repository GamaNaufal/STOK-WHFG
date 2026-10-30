export function initBoxModal({documentRef = globalThis.document, Modal = globalThis.bootstrap?.Modal,
    fetcher = (...args) => globalThis.fetch(...args)} = {}) {
    const element = documentRef.getElementById('export-stock-box-modal');
    if (!element || !Modal) return;
    const content = documentRef.getElementById('export-stock-box-content');
    const title = documentRef.getElementById('export-stock-box-title');
    const backButton = documentRef.getElementById('export-stock-modal-back');
    const modal = Modal.getOrCreateInstance(element);
    let activeRequest = null, version = 0, lastTrigger = null;
    let currentKind = 'box', palletContext = null, backContext = null;

    const open = async (url, trigger, {kind = 'box', back = null} = {}) => {
        const current = ++version;
        activeRequest?.abort();
        const controller = new AbortController(); activeRequest = controller;
        lastTrigger = trigger;
        currentKind = kind;
        palletContext = kind === 'pallet' ? {url, trigger} : null;
        backContext = back;
        if (title) title.textContent = kind === 'pallet' ? 'Detail pallet ekspor' : 'Detail box ekspor';
        if (backButton) backButton.hidden = !back;
        const loading = documentRef.createElement('p');
        loading.className = 'plan-help mb-0'; loading.setAttribute('role', 'status');
        loading.textContent = kind === 'pallet' ? 'Memuat isi pallet...' : 'Memuat detail box...';
        content.replaceChildren(loading); content.setAttribute('aria-busy', 'true');
        modal.show();
        try {
            const response = await fetcher(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error(response.status === 401 ? 'Sesi berakhir. Muat ulang halaman untuk masuk kembali.'
                : response.status === 403 ? 'Anda tidak memiliki akses ke detail '+(kind === 'pallet' ? 'pallet' : 'box')+' ini.'
                : response.status === 404 ? (kind === 'pallet' ? 'Pallet' : 'Box')+' tidak ditemukan. Muat ulang daftar stok.' : 'Detail belum dapat dimuat. Coba lagi.');
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('Respons detail box tidak dapat dibaca. Coba lagi.');
            if (current !== version) return;
            content.innerHTML = data.html;
        } catch (error) {
            if (current !== version || error.name === 'AbortError') return;
            const alert = documentRef.createElement('div'), message = documentRef.createElement('p'), retry = documentRef.createElement('button');
            alert.className = 'alert alert-danger mb-0'; alert.setAttribute('role', 'alert');
            message.textContent = error.message || 'Koneksi bermasalah. Coba lagi.';
            retry.type = 'button'; retry.className = 'btn btn-outline-primary'; retry.textContent = 'Coba lagi';
            retry.addEventListener('click', () => open(url, trigger, {kind, back}));
            alert.append(message, retry); content.replaceChildren(alert);
        } finally {
            if (current === version) { content.removeAttribute('aria-busy'); activeRequest = null; }
        }
    };

    documentRef.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const palletTrigger = event.target.closest('[data-export-stock-pallet]');
        if (palletTrigger?.dataset.exportStockPallet) {
            event.preventDefault(); return open(palletTrigger.dataset.exportStockPallet, palletTrigger, {kind: 'pallet'});
        }
        const trigger = event.target.closest('[data-export-stock-box]');
        if (trigger?.dataset.exportStockBox) {
            event.preventDefault();
            const back = currentKind === 'pallet' && content.contains(trigger) ? palletContext : null;
            return open(trigger.dataset.exportStockBox, back?.trigger ?? trigger, {back});
        }
        const link = event.target.closest('a[href]');
        if (currentKind === 'pallet' && link && content.contains(link) && link.closest('.pagination')) {
            event.preventDefault(); return open(link.href, palletContext.trigger, {kind: 'pallet'});
        }
    });
    backButton?.addEventListener('click', () => {
        if (backContext) return open(backContext.url, backContext.trigger, {kind: 'pallet'});
    });
    element.addEventListener('hidden.bs.modal', () => {
        ++version; activeRequest?.abort(); activeRequest = null;
        content.replaceChildren(); content.removeAttribute('aria-busy');
        currentKind = 'box'; palletContext = backContext = null;
        if (backButton) backButton.hidden = true;
        const target = lastTrigger?.isConnected ? lastTrigger : documentRef.getElementById('receiving-stock-search');
        target?.focus({preventScroll: true});
    });
    if (element.dataset.openOnLoad === 'true') modal.show();
    return {open};
}
