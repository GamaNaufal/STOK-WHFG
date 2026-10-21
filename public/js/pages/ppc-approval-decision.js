document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('statusModal');
    if (!modalElement) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const form = document.getElementById('statusForm');
    const statusInput = document.getElementById('modalStatusInput');
    const title = document.getElementById('statusModalLabel');
    const orderNumber = document.getElementById('statusOrderNumber');
    const orderCustomer = document.getElementById('statusOrderCustomer');
    const orderDate = document.getElementById('statusOrderDate');
    const description = document.getElementById('statusConfirmationText');
    const note = document.getElementById('statusNotes');
    const noteLabel = document.getElementById('statusNotesLabel');
    const noteHelp = document.getElementById('statusNoteHelp');
    const noteError = document.getElementById('statusNoteError');
    const submitButton = document.getElementById('statusSubmitButton');
    const impactPanel = document.getElementById('impactPanel');
    const impactIcon = document.getElementById('impactIcon');
    const impactTitle = document.getElementById('impactTitle');
    const impactDescription = document.getElementById('impactDescription');
    const impactList = document.getElementById('impactList');
    const impactRetry = document.getElementById('impactRetry');
    const decisionCopy = {
        approved: {
            title: 'Setujui pesanan',
            description: 'Order akan masuk ke jadwal delivery setelah keputusan disimpan.',
            noteLabel: 'Keterangan persetujuan PPC',
            noteHelp: 'Wajib diisi. Keterangan ini tersimpan pada order dan dapat dibaca Sales.',
            submit: 'Setujui order',
        },
        correction: {
            title: 'Minta koreksi Sales',
            description: 'Order dikembalikan ke Sales untuk diperbaiki dan diajukan ulang.',
            noteLabel: 'Apa yang perlu dikoreksi?',
            noteHelp: 'Tuliskan perubahan yang perlu dilakukan Sales sebelum mengirim ulang.',
            submit: 'Kirim ke Sales',
        },
        rejected: {
            title: 'Tolak pesanan',
            description: 'Order akan ditandai ditolak. Jelaskan alasan keputusan kepada Sales.',
            noteLabel: 'Alasan penolakan',
            noteHelp: 'Tuliskan alasan penolakan agar Sales memahami keputusan PPC.',
            submit: 'Tolak order',
        },
    };

    let activeToken = 0;
    let impactRequestId = 0;
    let activeController = null;
    let currentOrderId = null;
    let impactReady = false;
    let submitting = false;
    form.noValidate = true;

    function clearImpact() {
        impactPanel.hidden = true;
        impactPanel.dataset.state = '';
        impactList.replaceChildren();
        impactList.hidden = true;
        impactRetry.hidden = true;
    }

    function showImpact(state, impacted = []) {
        impactPanel.hidden = false;
        impactPanel.dataset.state = state;
        impactList.replaceChildren();
        impactList.hidden = state !== 'warning';
        impactRetry.hidden = state !== 'error';

        const messages = {
            checking: ['Memeriksa dampak jadwal', 'Mohon tunggu sebelum menyetujui order.', 'bi-arrow-repeat'],
            safe: ['Tidak ada order terdampak', 'Tidak ada order aktif yang sebelumnya cukup stok menjadi kurang.', 'bi-check-circle'],
            warning: [`${impacted.length} order dapat terdampak`, 'Order berikut yang sebelumnya cukup stok dapat menjadi kurang setelah persetujuan ini.', 'bi-exclamation-triangle'],
            error: ['Dampak belum dapat diperiksa', 'Pemeriksaan gagal. Coba lagi sebelum menyetujui order.', 'bi-exclamation-circle'],
        };
        const [heading, detail, icon] = messages[state];
        impactTitle.textContent = heading;
        impactDescription.textContent = detail;
        impactIcon.className = `bi ${icon}`;

        if (state !== 'warning') return;
        impacted.forEach((order) => {
            const item = document.createElement('li');
            const name = document.createElement('strong');
            name.textContent = `Order #${order.id} · ${order.customer_name || 'Pelanggan tidak diketahui'}`;
            const date = document.createElement('span');
            date.textContent = `Tanggal delivery: ${order.delivery_date || '—'}`;
            item.append(name, date);

            if (Array.isArray(order.short_parts) && order.short_parts.length) {
                const parts = document.createElement('ul');
                order.short_parts.forEach((part) => {
                    const partItem = document.createElement('li');
                    partItem.textContent = `${part.part_number}: siap ${part.can_fulfill} / perlu ${part.required} PCS`;
                    parts.appendChild(partItem);
                });
                item.appendChild(parts);
            }
            impactList.appendChild(item);
        });
    }

    async function checkImpact(orderId, token) {
        activeController?.abort();
        activeController = new AbortController();
        const requestId = ++impactRequestId;
        impactReady = false;
        submitButton.disabled = true;
        showImpact('checking');

        try {
            const response = await fetch(`/delivery-stock/${encodeURIComponent(orderId)}/approval-impact`, {
                signal: activeController.signal,
            });
            if (!response.ok) throw new Error('Pemeriksaan dampak gagal');
            const data = await response.json();
            if (!Array.isArray(data?.impacted)) throw new Error('Respons dampak tidak lengkap');
            if (token !== activeToken || requestId !== impactRequestId) return;

            showImpact(data.impacted.length ? 'warning' : 'safe', data.impacted);
            impactReady = true;
            submitButton.disabled = false;
        } catch (error) {
            if (token !== activeToken || requestId !== impactRequestId || error.name === 'AbortError') return;
            showImpact('error');
            submitButton.disabled = true;
        }
    }

    function openDecision(button) {
        const status = button.dataset.status;
        const copy = decisionCopy[status];
        const card = button.closest('.ppc-approval-card');
        if (!copy || !card) return;

        activeToken += 1;
        activeController?.abort();
        currentOrderId = button.dataset.orderId;
        submitting = false;
        impactReady = status !== 'approved';
        form.action = `/delivery-stock/${encodeURIComponent(currentOrderId)}/status`;
        statusInput.value = status;
        orderNumber.textContent = `Order #${currentOrderId}`;
        orderCustomer.textContent = card.querySelector('.ppc-order-heading h2').textContent;
        const sourceDate = card.querySelector('.ppc-order-meta time');
        orderDate.textContent = sourceDate.textContent;
        orderDate.dateTime = sourceDate.dateTime;
        title.textContent = copy.title;
        description.textContent = copy.description;
        noteLabel.textContent = copy.noteLabel;
        noteHelp.textContent = copy.noteHelp;
        note.value = '';
        note.classList.remove('is-invalid');
        note.setAttribute('aria-invalid', 'false');
        noteError.textContent = '';
        noteError.hidden = true;
        submitButton.textContent = copy.submit;
        submitButton.disabled = !impactReady;
        clearImpact();

        modal.show();
        if (status === 'approved') checkImpact(currentOrderId, activeToken);
    }

    document.querySelectorAll('.js-open-status-modal').forEach((button) => {
        button.addEventListener('click', () => openDecision(button));
    });
    impactRetry.addEventListener('click', () => {
        if (statusInput.value === 'approved' && currentOrderId) checkImpact(currentOrderId, activeToken);
    });
    note.addEventListener('input', () => {
        if (!note.value.trim()) return;
        note.classList.remove('is-invalid');
        note.setAttribute('aria-invalid', 'false');
        noteError.textContent = '';
        noteError.hidden = true;
    });
    form.addEventListener('submit', (event) => {
        if (submitting || (statusInput.value === 'approved' && !impactReady)) {
            event.preventDefault();
            return;
        }
        if (!note.value.trim()) {
            event.preventDefault();
            note.classList.add('is-invalid');
            note.setAttribute('aria-invalid', 'true');
            noteError.textContent = 'Isi catatan sebelum mengirim keputusan.';
            noteError.hidden = false;
            note.focus();
            return;
        }
        submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = 'Mengirim…';
    });
    modalElement.addEventListener('hide.bs.modal', () => {
        activeToken += 1;
        activeController?.abort();
        activeController = null;
        currentOrderId = null;
    });
});
