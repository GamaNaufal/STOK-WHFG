export function initBoxModal({documentRef = globalThis.document, Modal = globalThis.bootstrap?.Modal,
    fetcher = (...args) => globalThis.fetch(...args)} = {}) {
    const element = documentRef.getElementById('export-stock-box-modal');
    if (!element || !Modal) return;
    const content = documentRef.getElementById('export-stock-box-content');
    const modal = Modal.getOrCreateInstance(element);
    let activeRequest = null, version = 0, lastTrigger = null;

    const open = async (url, trigger) => {
        const current = ++version;
        activeRequest?.abort();
        const controller = new AbortController(); activeRequest = controller;
        lastTrigger = trigger;
        const loading = documentRef.createElement('p');
        loading.className = 'plan-help mb-0'; loading.setAttribute('role', 'status');
        loading.textContent = 'Memuat detail box...';
        content.replaceChildren(loading); content.setAttribute('aria-busy', 'true');
        modal.show();
        try {
            const response = await fetcher(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error(response.status === 401 ? 'Sesi berakhir. Muat ulang halaman untuk masuk kembali.'
                : response.status === 403 ? 'Anda tidak memiliki akses ke detail box ini.'
                : response.status === 404 ? 'Box tidak ditemukan. Muat ulang daftar stok.' : 'Detail box belum dapat dimuat. Coba lagi.');
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
            retry.addEventListener('click', () => open(url, trigger));
            alert.append(message, retry); content.replaceChildren(alert);
        } finally {
            if (current === version) { content.removeAttribute('aria-busy'); activeRequest = null; }
        }
    };

    documentRef.addEventListener('click', event => {
        const trigger = event.target.closest('[data-export-stock-box]');
        if (!trigger) return;
        event.preventDefault(); return open(trigger.dataset.exportStockBox, trigger);
    });
    element.addEventListener('hidden.bs.modal', () => {
        ++version; activeRequest?.abort(); activeRequest = null;
        content.replaceChildren(); content.removeAttribute('aria-busy');
        const target = lastTrigger?.isConnected ? lastTrigger : documentRef.getElementById('receiving-stock-search');
        target?.focus({preventScroll: true});
    });
    if (element.dataset.openOnLoad === 'true') modal.show();
    return {open};
}
