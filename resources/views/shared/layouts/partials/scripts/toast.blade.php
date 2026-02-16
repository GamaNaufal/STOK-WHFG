<script>
    window.showToast = function(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;

        const typeMap = {
            success: { bg: 'success', icon: 'check-circle' },
            danger: { bg: 'danger', icon: 'exclamation-triangle' },
            warning: { bg: 'warning', icon: 'exclamation-circle' },
            info: { bg: 'info', icon: 'info-circle' }
        };

        const config = typeMap[type] || typeMap.info;
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${config.bg} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');

        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${config.icon}"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;

        toastContainer.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();

        toastEl.addEventListener('hidden.bs.toast', () => {
            toastEl.remove();
        });
    };
</script>