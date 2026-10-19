(() => {
    'use strict';
    const requestForm = document.getElementById('notFullRequestForm');
    const approvalForms = [...document.querySelectorAll('.js-approve-form, .js-reject-form')];
    let confirming = false;
    let submitting = false;
    const labels = new Map();

    function submit(form) {
        submitting = true;
        form.setAttribute('aria-busy', 'true');
        document.querySelectorAll('.not-full-page button[type="submit"]').forEach(button => {
            labels.set(button, button.innerHTML);
            button.disabled = true;
        });
        const button = form.querySelector('button[type="submit"]');
        if (button) button.textContent = 'Memproses...';
        HTMLFormElement.prototype.submit.call(form);
    }

    async function confirm(form, title, text, action, danger = false) {
        if (confirming || submitting) return;
        confirming = true;
        try {
            const result = window.Swal ? await Swal.fire({
                title, text, icon: danger ? 'warning' : 'question',
                showCancelButton: true, reverseButtons: true, focusCancel: true,
                confirmButtonText: action, cancelButtonText: 'Kembali',
                confirmButtonColor: danger ? '#b42318' : '#0c7779',
                showClass: { popup: '' }, hideClass: { popup: '' },
                customClass: { popup: 'not-full-confirm' },
            }) : { isConfirmed: window.confirm(`${title}\n\n${text}`) };
            if (result.isConfirmed) submit(form);
        } finally {
            confirming = false;
        }
    }

    window.addEventListener('pageshow', () => {
        submitting = false;
        confirming = false;
        document.querySelectorAll('.not-full-page form').forEach(form => form.removeAttribute('aria-busy'));
        labels.forEach((label, button) => { button.disabled = false; button.innerHTML = label; });
        labels.clear();
    });

    if (requestForm) {
        const part = document.getElementById('partNumberSelect');
        const pcs = document.getElementById('pcsQuantityInput');
        const reason = document.getElementById('notFullReason');
        const pallet = document.getElementById('targetPalletId');
        const location = document.getElementById('targetLocationId');
        const errorSummary = document.getElementById('notFullFormError');
        const fields = [...requestForm.querySelectorAll('input:not([type=hidden]):not([type=radio]),select,textarea')];
        requestForm.noValidate = true;

        function errorFor(field) {
            if (field.disabled) return '';
            if (field.required && !field.value.trim()) return 'Field ini wajib diisi.';
            if (field.id === 'boxNumber' && !/^\d{8}$/.test(field.value)) return 'ID Box harus terdiri dari 8 angka.';
            if (field === pcs) {
                const value = Number(field.value);
                const fixed = Number(part.selectedOptions[0]?.dataset.fixed || 0);
                if (!Number.isInteger(value) || value < 1) return 'Isi jumlah PCS dengan bilangan bulat minimal 1.';
                if (fixed && value >= fixed) return `PCS aktual harus kurang dari ${fixed} PCS.`;
            }
            if (field === reason && field.value.trim().length < 3) return 'Jelaskan alasan minimal 3 karakter.';
            return field.validity.valid ? '' : 'Periksa kembali nilai field ini.';
        }

        function validate(field) {
            const message = errorFor(field);
            let feedback = document.getElementById(`${field.id}Error`);
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.id = `${field.id}Error`;
                feedback.className = 'not-full-field-error';
                field.parentElement.appendChild(feedback);
                field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), feedback.id].filter(Boolean).join(' '));
            }
            feedback.textContent = message;
            feedback.hidden = !message;
            field.classList.toggle('not-full-invalid', !!message);
            field.classList.toggle('is-invalid', !!message);
            field.setAttribute('aria-invalid', String(!!message));
            if (field === part) {
                const selection = field.parentElement.querySelector('.select2-selection');
                selection?.setAttribute('aria-invalid', String(!!message));
                selection?.setAttribute('aria-describedby', feedback.id);
            }
            return !message;
        }

        function toggleTarget() {
            const newLocation = document.getElementById('targetLocation').checked;
            document.getElementById('palletTargetSection').style.display = newLocation ? 'none' : '';
            document.getElementById('locationTargetSection').style.display = newLocation ? '' : 'none';
            pallet.disabled = newLocation;
            pallet.required = !newLocation;
            location.disabled = !newLocation;
            location.required = newLocation;
        }
        requestForm.querySelectorAll('[name="target_type"]').forEach(radio => radio.addEventListener('change', toggleTarget));
        toggleTarget();

        function updateCapacity() {
            const fixed = Number(part.selectedOptions[0]?.dataset.fixed || 0);
            document.getElementById('pcsHint').textContent = fixed > 1 ? `Kapasitas standar ${fixed} PCS. Isi 1–${fixed - 1} PCS.` : fixed === 1 ? 'Kapasitas 1 PCS tidak memiliki kuantitas Not Full yang valid.' : 'Pilih part untuk melihat kapasitas standar box.';
            if (fixed > 0) pcs.max = String(fixed - 1); else pcs.removeAttribute('max');
            if (pcs.value) validate(pcs);
        }
        fields.forEach(field => {
            field.addEventListener('blur', () => validate(field));
            field.addEventListener('input', () => { if (field.getAttribute('aria-invalid') === 'true') validate(field); });
            field.addEventListener('change', () => validate(field));
        });
        part.addEventListener('change', updateCapacity);
        if (window.jQuery?.fn?.select2) {
            window.jQuery(part).select2({ width: '100%', placeholder: 'Pilih No Part', allowClear: true,
                language: { noResults: () => 'Part number tidak ditemukan.', removeAllItems: () => 'Hapus pilihan' } })
                .on('change', () => { updateCapacity(); validate(part); })
                .on('select2:open', () => window.jQuery('.select2-search__field').attr('aria-label', 'Cari part number'));
            part.parentElement.querySelector('.select2-selection')?.setAttribute('aria-labelledby', 'partNumberLabel select2-partNumberSelect-container');
        }
        updateCapacity();
        requestForm.addEventListener('submit', event => {
            event.preventDefault();
            if (confirming || submitting) return;
            const invalid = fields.filter(field => !validate(field));
            errorSummary.classList.toggle('d-none', invalid.length === 0);
            if (invalid.length) {
                errorSummary.textContent = 'Request belum dikirim. Periksa field yang ditandai.';
                if (invalid[0] === part && window.jQuery?.fn?.select2) window.jQuery(part).select2('open');
                else invalid[0].focus();
                return;
            }
            const choice = id => document.getElementById(id).selectedOptions[0]?.textContent.trim() || '';
            confirm(requestForm, 'Kirim request Not Full?',
                `ID Box: ${document.getElementById('boxNumber').value}\nPart: ${part.value}\nPCS aktual: ${pcs.value}\nDelivery: ${choice('deliveryOrder')}\nTipe: ${choice('requestType')}\nTujuan: ${location.disabled ? choice('targetPalletId') : choice('targetLocationId')}\n\nStok dibuat setelah persetujuan. Request pending menahan proses delivery terkait.`, 'Kirim Request');
        });
    }

    approvalForms.forEach(form => form.addEventListener('submit', event => {
        event.preventDefault();
        const approve = form.classList.contains('js-approve-form');
        const detail = `ID Box: ${form.dataset.box}\nPart: ${form.dataset.part}\nPCS: ${form.dataset.quantity}\nDelivery: #${form.dataset.delivery}`;
        const effect = approve
            ? `Box akan menjadi stok dan di-assign ke delivery.${form.dataset.type === 'additional' ? ' Kuantitas order juga bertambah.' : ' Kuantitas order tidak berubah.'}`
            : 'Request akan ditolak. Stok tidak dibuat.';
        confirm(form, approve ? 'Setujui request?' : 'Tolak request?', `${detail}\n\n${effect}`, approve ? 'Ya, Setujui' : 'Ya, Tolak', !approve);
    }));
})();
