export function initStockReview({documentRef = globalThis.document, fetcher = (...args) => globalThis.fetch(...args), modalApi = globalThis.bootstrap?.Modal, formData = form => new FormData(form)} = {}) {
    const element = documentRef?.getElementById('stock-allocation-modal');
    if (!element || !modalApi) return;
    if (element.dataset.autoOpen === 'true') modalApi.getOrCreateInstance(element).show();
    const form = element.querySelector('[data-stock-ack-form]');
    if (!form) return;
    let busy = false;
    form.addEventListener('submit', async event => {
        event.preventDefault(); if (busy) return;
        busy = true; form.setAttribute('aria-busy', 'true');
        const button = form.querySelector('button'), error = element.querySelector('[data-stock-review-error]');
        button.disabled = true; error.hidden = true;
        try {
            const response = await fetcher(form.action, {method: 'POST', body: formData(form),
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value}});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat()[0] ?? data.message ?? 'Konfirmasi belum berhasil. Coba lagi.');
            const close = documentRef.createElement('button'); close.type = 'button'; close.className = 'btn btn-primary';
            close.textContent = 'Tutup'; close.setAttribute('data-bs-dismiss', 'modal'); form.replaceWith(close);
            element.querySelector('[data-stock-review-note]').textContent = data.message;
            element.querySelector('[data-stock-review-help]').textContent = 'Daftar alokasi FIFO tersimpan. Lakukan pemindahan melalui antrean, kemudian konfirmasikan penempatan fisik.';
            element.querySelectorAll('[data-allocation-status]').forEach(cell => {
                cell.textContent = cell.dataset.placed === 'true' ? 'Sudah ditempatkan' : 'Menunggu penempatan';
            });
            const status = documentRef.querySelector('[data-stock-review-status]'); if (status) status.textContent = data.message;
            const modal = modalApi.getOrCreateInstance(element); modal.hide(); modal.dispose();
            element.dataset.bsBackdrop = 'true'; element.dataset.bsKeyboard = 'true'; element.dataset.autoOpen = 'false';
            documentRef.querySelector('[data-bs-target="#stock-allocation-modal"]')?.focus({preventScroll: true});
        } catch (failure) {
            error.textContent = failure.message || 'Koneksi bermasalah. Konfirmasi belum selesai; coba lagi.'; error.hidden = false;
        } finally { busy = false; button.disabled = false; form.removeAttribute('aria-busy'); }
    });
}
