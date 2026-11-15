window.showToast = function(message, type = 'info', options = {}) {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;

        const typeMap = {
            success: { bg: 'success', icon: 'check-circle' },
            danger: { bg: 'danger', icon: 'exclamation-triangle' },
            warning: { bg: 'warning', icon: 'exclamation-circle' },
            info: { bg: 'info', icon: 'info-circle' }
        };

        const config = typeMap[type] || typeMap.info;
        const completion = type === 'success' && options?.emphasis === true;
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${config.bg} border-0${completion ? ' toast-completion' : ''}`;
        toastEl.setAttribute('role', completion ? 'status' : 'alert');
        toastEl.setAttribute('aria-live', completion ? 'polite' : 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${completion
                        ? '<span class="toast-completion-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></span><div class="toast-completion-copy"><strong>Berhasil</strong><span class="toast-message"></span></div>'
                        : `<i class="bi bi-${config.icon}"></i> <span class="toast-message"></span>`}
                </div>
                <button type="button" class="btn-close${completion ? '' : ' btn-close-white'} me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup notifikasi"></button>
            </div>
        `;

        toastEl.querySelector('.toast-message').textContent = String(message ?? '');
        toastContainer.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();

        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    };
