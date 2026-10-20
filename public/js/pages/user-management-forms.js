(function () {
    const form = document.querySelector('[data-user-form]');
    if (!form) return;

    const mode = form.dataset.userForm;
    const name = form.querySelector('[name="name"]');
    const email = form.querySelector('[name="email"]');
    const role = form.querySelector('[name="role"]');
    const status = form.querySelector('[name="is_active"]');
    const password = form.querySelector('[name="password"]');
    const confirmation = form.querySelector('[name="password_confirmation"]');
    const matchError = document.getElementById('user-password-match-error');
    const submitButton = form.querySelector('[type="submit"]');
    let submitting = false;

    function escapeHtml(value) {
        const element = document.createElement('span');
        element.textContent = value;
        return element.innerHTML;
    }

    function validatePasswordMatch() {
        if (!password || !confirmation) return;
        if (mode === 'edit') confirmation.required = password.value.length > 0;

        let message = '';
        if (confirmation.value && !password.value) {
            message = 'Isi kata sandi baru terlebih dahulu.';
        } else if (confirmation.value && confirmation.value !== password.value) {
            message = 'Konfirmasi kata sandi harus sama.';
        }

        confirmation.setCustomValidity(message);
        confirmation.classList.toggle('is-invalid', message !== '');
        confirmation.setAttribute('aria-invalid', message ? 'true' : 'false');
        if (matchError) {
            matchError.textContent = message;
            matchError.hidden = !message;
        }
    }

    password?.addEventListener('input', validatePasswordMatch);
    confirmation?.addEventListener('input', validatePasswordMatch);
    validatePasswordMatch();

    document.querySelectorAll('.user-password-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordTarget);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Sembunyikan' : 'Tampilkan';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });

    function submitOnce() {
        if (submitting) return;
        submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = 'Menyimpan…';
        form.submit();
    }

    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        validatePasswordMatch();
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        if (mode === 'create') {
            event.preventDefault();
            WarehouseAlert.info({
                title: 'Buat akun baru?',
                message: 'Akun akan langsung aktif dengan data berikut:',
                details: {
                    'Nama': escapeHtml(name.value.trim()),
                    'Email': escapeHtml(email.value.trim()),
                    'Peran': escapeHtml(role.selectedOptions[0]?.textContent.trim() || '')
                },
                infoText: 'Pengguna dapat masuk setelah akun dibuat.',
                confirmText: 'Ya, buat akun',
                onConfirm: submitOnce
            });
            return;
        }

        const roleChanged = role.value !== form.dataset.originalRole;
        const statusChanged = status.value !== form.dataset.originalStatus;
        if (roleChanged || statusChanged) {
            event.preventDefault();
            const warningItems = [];
            if (roleChanged) warningItems.push('Akses fitur akan mengikuti peran yang baru.');
            if (statusChanged) {
                warningItems.push(status.value === '0'
                    ? 'Akun tidak dapat masuk setelah dinonaktifkan; riwayat aktivitas tetap tersimpan.'
                    : 'Akun kembali dapat masuk sesuai perannya.');
            }

            WarehouseAlert.confirm({
                title: 'Simpan perubahan akses?',
                message: `Perubahan akun <strong>${escapeHtml(name.value.trim())}</strong> akan diterapkan setelah disimpan.`,
                warningItems,
                confirmText: 'Ya, simpan perubahan',
                onConfirm: submitOnce
            });
            return;
        }

        submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = 'Menyimpan…';
    });
})();
