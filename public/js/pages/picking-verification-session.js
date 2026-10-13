(function () {
    const config = document.currentScript.dataset;
    const cancelButton = document.getElementById('btnCancelVerification');
    const idleStatus = document.getElementById('verificationIdleStatus');
    let deadline = config.expiresIn === '' ? null : Date.now() + Number(config.expiresIn) * 1000;
    let hasActivity = false;
    let lastSentAt = 0;
    let checking = false;
    let stopped = false;
    const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf };

    function finish(message) {
        stopped = true;
        window.verificationSessionClosing = true;
        cancelButton.disabled = true;
        document.getElementById('scanInput').disabled = true;
        document.getElementById('btnScan').disabled = true;
        document.getElementById('btnToFinal').classList.add('disabled');
        idleStatus.textContent = message;
        showToast(message, 'warning');
        setTimeout(() => window.location.assign(config.returnUrl), 1800);
    }

    async function checkActivity() {
        if (checking || stopped || window.verificationSessionClosing) return;
        checking = true;
        const active = hasActivity;
        hasActivity = false;
        lastSentAt = Date.now();
        try {
            const response = await fetch(config.activityUrl, {
                method: 'POST', headers, body: JSON.stringify({ active }),
            });
            if ([401, 403, 419].includes(response.status)) {
                finish('Akses sesi berakhir. Silakan masuk kembali.');
                return;
            }
            const data = await response.json();
            if (response.status === 409) {
                finish(data.message || 'Sesi verifikasi sudah berakhir.');
            } else if (response.ok && data.success) {
                deadline = data.expires_in === null ? null : Date.now() + data.expires_in * 1000;
            } else {
                idleStatus.textContent = data.message || 'Gagal memeriksa batas waktu sesi.';
            }
        } catch (_) {
            // Do not claim activity was accepted or restart the deadline offline.
            idleStatus.textContent = 'Koneksi terputus. Batas 10 menit tetap berlaku di server.';
        } finally {
            checking = false;
        }
    }

    function recordActivity(event) {
        if (!event.isTrusted || stopped || document.visibilityState === 'hidden') return;
        hasActivity = true;
        if (Date.now() - lastSentAt >= 5000) checkActivity();
    }

    ['keydown', 'pointerdown', 'pointermove', 'input', 'scroll'].forEach((name) => {
        document.addEventListener(name, recordActivity, { capture: true, passive: true });
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') checkActivity();
    });

    setInterval(() => {
        if (stopped) return;
        if (deadline !== null) {
            const seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            idleStatus.textContent = `Batas tidak aktif: ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}. Sesi dibatalkan setelah 10 menit tanpa aktivitas.`;
        } else {
            idleStatus.textContent = 'Timer verifikasi tidak aktif pada sesi blocked atau tahap scan final.';
        }
        const elapsed = Date.now() - lastSentAt;
        if ((hasActivity && elapsed >= 5000) || elapsed >= 15000 || (deadline !== null && Date.now() >= deadline && elapsed >= 1000)) {
            checkActivity();
        }
    }, 1000);

    cancelButton.addEventListener('click', () => {
        if (cancelButton.disabled || stopped) return;
        WarehouseAlert.confirm({
            title: 'Batalkan Verifikasi?',
            message: 'Batalkan proses verifikasi ini? Progres verifikasi dihapus dan box yang terkunci akan dilepas.',
            confirmText: 'Ya, Batalkan',
            confirmColor: '#DC2626',
            onConfirm: async () => {
                cancelButton.disabled = true;
                window.verificationSessionClosing = true;
                try {
                    const response = await fetch(config.cancelUrl, { method: 'POST', headers });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        stopped = true;
                        showToast(data.message || 'Verifikasi dibatalkan.', 'success');
                        window.location.assign(config.returnUrl);
                    } else {
                        window.verificationSessionClosing = false;
                        cancelButton.disabled = response.status === 423;
                        showToast(data.message || 'Gagal membatalkan verifikasi.', 'danger');
                    }
                } catch (_) {
                    window.verificationSessionClosing = false;
                    cancelButton.disabled = false;
                    showToast('Gagal koneksi. Coba batalkan kembali.', 'danger');
                }
            },
        });
    });
})();
