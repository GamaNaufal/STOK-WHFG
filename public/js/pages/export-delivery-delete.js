(() => {
    let busy = false, submitted = false;
    document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-delete-delivery-preview]');
        if (!trigger) return;
        event.preventDefault();
        if (busy || submitted) return;
        busy = true;
        trigger.setAttribute('aria-busy', 'true');
        let form;
        try {
            const response = await fetch(trigger.dataset.previewUrl, {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('Tinjauan belum dapat dimuat. Muat ulang halaman dan coba lagi.');
            const data = await response.json();
            const review = await Swal.fire({
                title: 'Tinjauan penghapusan rencana', html: data.html, width: 'min(960px, calc(100vw - 2rem))',
                showCancelButton: true, showConfirmButton: data.can_delete, confirmButtonText: 'Konfirmasi tinjauan',
                cancelButtonText: data.can_delete ? 'Batal' : 'Tutup', confirmButtonColor: '#0C7779', focusConfirm: false,
                preConfirm: () => {
                    const source = Swal.getHtmlContainer().querySelector('[data-delete-delivery-form]');
                    if (!source || !source.reportValidity()) return false;
                    form = source.cloneNode(true);
                    const reason = source.querySelector('[name="reason"]');
                    if (reason) form.querySelector('[name="reason"]').value = reason.value;
                    return true;
                },
            });
            if (!data.can_delete || !review.isConfirmed || !form) return;
            const final = await Swal.fire({
                title: 'Hapus rencana delivery?',
                text: 'Rencana '+data.plan_code+' akan dihapus dari daftar aktif dan reservasi yang ditinjau akan dilepas. Riwayat tetap tersimpan, kode delivery tidak dapat digunakan ulang.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, hapus rencana',
                cancelButtonText: 'Batal', confirmButtonColor: '#DC2626', reverseButtons: true,
            });
            if (!final.isConfirmed) return;
            submitted = true;
            form.hidden = true;
            document.body.appendChild(form);
            form.submit();
        } catch (error) {
            await Swal.fire({title: 'Penghapusan belum dilanjutkan', text: error.message || 'Koneksi bermasalah. Coba lagi.', icon: 'error'});
        } finally {
            busy = false;
            trigger.removeAttribute('aria-busy');
        }
    });
})();
