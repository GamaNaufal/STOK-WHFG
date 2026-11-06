(() => {
    const pending = new WeakSet();
    document.querySelectorAll('[data-export-stock-confirm]').forEach(form => {
        const confirmed = form.querySelector('[data-stock-confirmed]');
        const button = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', async event => {
            if (confirmed.value === '1') {
                button.disabled = true;
                return;
            }
            event.preventDefault();
            if (pending.has(form)) return;
            pending.add(form);
            try {
                const {confirmTitle, confirmMessage, confirmButton} = form.dataset;
                const destructive = form.hasAttribute('data-delete');
                const limit = form.elements.namedItem('box_limit');
                const part = form.elements.namedItem('part_number');
                let message = confirmMessage;
                if (limit) {
                    const limitLabel = limit.valueAsNumber === 0 ? 'Tanpa batas' : limit.value + ' box';
                    message += '\nBatas baru: ' + limitLabel;
                } else if (part) {
                    const pcs = form.elements.namedItem('pcs_quantity').value;
                    const receivedAt = form.elements.namedItem('first_received_at').value.replace('T', ' ');
                    message += '\nPart ' + part.value.trim() + ' · ' + pcs + ' PCS · ' + receivedAt + ' WIB';
                }
                const accepted = typeof Swal === 'undefined'
                    ? window.confirm(confirmTitle + '\n' + message)
                    : (await Swal.fire({
                        titleText: confirmTitle, text: message, icon: destructive ? 'error' : 'warning',
                        showCancelButton: true, focusCancel: true, reverseButtons: true,
                        confirmButtonText: confirmButton, cancelButtonText: 'Batal',
                        confirmButtonColor: destructive ? '#b52e3c' : '#0c7779',
                    })).isConfirmed;
                if (accepted && form.reportValidity()) {
                    confirmed.value = '1';
                    form.requestSubmit(button);
                }
            } finally {
                pending.delete(form);
            }
        });

        window.addEventListener('pageshow', () => {
            confirmed.value = '0';
            button.disabled = false;
        });
    });
})();
