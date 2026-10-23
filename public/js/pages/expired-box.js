(function () {
    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function init() {
        const page = document.getElementById('expired-box-page');
        if (!page) return;

        const searchUi = page.querySelector('[data-search-ui]');
        const searchInput = page.querySelector('[data-box-search]');
        const clearSearch = page.querySelector('[data-clear-search]');
        const groups = ['expired', 'warning'];

        if (searchUi && searchInput && clearSearch) {
            searchUi.hidden = false;

            const applySearch = function () {
                const query = searchInput.value.trim().toLocaleLowerCase('id');
                groups.forEach(function (group) {
                    const rows = Array.from(page.querySelectorAll('[data-filter-row="' + group + '"]'));
                    let visible = 0;

                    rows.forEach(function (row) {
                        const matches = !query || (row.dataset.search || '').toLocaleLowerCase('id').includes(query);
                        row.hidden = !matches;
                        if (matches) visible += 1;
                    });

                    const count = page.querySelector('[data-visible-count="' + group + '"]');
                    const noMatches = page.querySelector('[data-filter-empty="' + group + '"]');
                    if (count) count.textContent = query ? visible + ' dari ' + rows.length + ' box' : rows.length + ' box';
                    if (noMatches) noMatches.hidden = !query || rows.length === 0 || visible > 0;
                });
            };

            searchInput.addEventListener('input', applySearch);
            clearSearch.addEventListener('click', function () {
                searchInput.value = '';
                applySearch();
                searchInput.focus();
            });
        }

        page.querySelectorAll('.js-handle-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.dataset.submitting === 'true') return;

                const boxId = button.dataset.boxId;
                const locationSelect = page.querySelector('.js-handle-location[data-box-id="' + boxId + '"]');
                if (locationSelect && !locationSelect.value) {
                    if (typeof window.showToast === 'function') {
                        window.showToast('Pilih lokasi penyimpanan kosong untuk pallet terlebih dahulu.', 'warning');
                    }
                    locationSelect.focus();
                    return;
                }

                const boxNumber = escapeHtml(button.dataset.boxNumber || '–');
                const age = escapeHtml(button.dataset.age || '–');
                const locationName = locationSelect ? escapeHtml(locationSelect.selectedOptions[0].textContent.trim()) : null;

                WarehouseAlert.confirm({
                    title: 'Handle box expired?',
                    message: 'Box <strong>' + boxNumber + '</strong> (' + age + ') akan diaktifkan selama satu bulan kalender.',
                    infoText: locationName ? 'Lokasi tujuan pallet: ' + locationName : 'Pallet tetap pada lokasi penyimpanan saat ini.',
                    warningItems: [
                        'Box kembali dapat masuk FIFO dengan tanggal penyimpanan aslinya.',
                        'Saat masa Handle habis, box ditolak saat scan sampai di-Handle ulang.',
                        'Pastikan kondisi fisik box layak dan lokasi pallet sudah benar.'
                    ],
                    confirmText: 'Ya, Handle 1 Bulan',
                    onConfirm: function () {
                        if (button.dataset.submitting === 'true') return;
                        button.dataset.submitting = 'true';
                        button.disabled = true;
                        button.textContent = 'Memproses…';

                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = button.dataset.handleUrl;

                        const token = document.createElement('input');
                        token.type = 'hidden';
                        token.name = '_token';
                        token.value = page.dataset.csrfToken;
                        form.appendChild(token);

                        if (locationSelect) {
                            const location = document.createElement('input');
                            location.type = 'hidden';
                            location.name = 'location_id';
                            location.value = locationSelect.value;
                            form.appendChild(location);
                        }

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
