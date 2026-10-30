document.addEventListener('DOMContentLoaded', () => {
    let loading = false;
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-ready-url]');
        if (!button || loading) return;
        loading = true; button.disabled = true;
        try {
            const response = await fetch(button.dataset.readyUrl, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}});
            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(Object.values(error.errors || {}).flat()[0] || error.message || 'Rincian pallet tidak dapat dimuat. Muat ulang halaman.');
            }
            const content = document.getElementById('ready-pallet-content');
            content.innerHTML = await response.text();
            const modal = document.getElementById('ready-pallet-modal');
            modal.addEventListener('hidden.bs.modal', () => button.focus(), {once: true});
            bootstrap.Modal.getOrCreateInstance(modal).show();
        } catch (error) { window.showToast?.(error.message, 'danger'); }
        finally { loading = false; button.disabled = false; }
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.closest('.fulfillment-page, #ready-pallet-modal, #complete-request-modal')) return;
        if (form.dataset.submitting) { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
    });
});
