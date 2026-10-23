(() => {
    const page = document.getElementById('master-parts');
    if (!page) return;

    const searchInput = document.getElementById('part-search');
    const clearButton = document.getElementById('part-search-clear');
    const tableBody = document.getElementById('part-table-body');
    const pagination = document.getElementById('part-pagination');
    const status = document.getElementById('part-result-status');
    const searchUrl = page.dataset.searchUrl;
    const baseUrl = page.dataset.baseUrl.replace(/\/$/, '');
    const csrfToken = page.dataset.csrfToken;
    const initialRows = tableBody.innerHTML;
    const initialStatus = status.textContent;
    const initialPaginationHidden = pagination.hidden;
    let timer = null;
    let activeRequest = null;
    let requestVersion = 0;

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);

    const renderRows = (items) => {
        if (!items.length) {
            tableBody.innerHTML = '<tr><td colspan="3" class="part-table-empty"><i class="bi bi-search" aria-hidden="true"></i><strong>Tidak ada nomor part yang cocok</strong><span>Coba kata kunci lain atau bersihkan pencarian.</span></td></tr>';
            return;
        }

        tableBody.innerHTML = items.map((item) => {
            const id = Number(item.id);
            if (!Number.isSafeInteger(id) || id < 1) return '';
            const partNumber = escapeHtml(item.part_number ?? '');
            const quantity = Number(item.qty_box).toLocaleString('id-ID');
            const resourceUrl = `${baseUrl}/${id}`;

            return `<tr>
                <th scope="row" class="part-table-code">${partNumber}</th>
                <td><span class="part-quantity"><strong>${quantity}</strong> PCS / box</span></td>
                <td><div class="part-row-actions">
                    <a href="${resourceUrl}/edit" class="btn btn-sm btn-outline-primary" aria-label="Edit nomor part ${partNumber}"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
                    <button type="button" class="btn btn-sm btn-outline-danger js-delete-part" data-delete-url="${resourceUrl}" data-part-number="${partNumber}" aria-label="Hapus nomor part ${partNumber}"><i class="bi bi-trash" aria-hidden="true"></i> Hapus</button>
                </div></td>
            </tr>`;
        }).join('');
    };

    const cancelSearch = () => {
        requestVersion += 1;
        clearTimeout(timer);
        activeRequest?.abort();
        activeRequest = null;
    };

    const restoreInitialRows = () => {
        cancelSearch();
        tableBody.innerHTML = initialRows;
        pagination.hidden = initialPaginationHidden;
        status.textContent = initialStatus;
        clearButton.hidden = true;
    };

    const runSearch = async (query) => {
        const version = ++requestVersion;
        activeRequest = new AbortController();
        try {
            const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                signal: activeRequest.signal,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Pencarian gagal');
            const payload = await response.json();
            if (version !== requestVersion) return;
            const items = Array.isArray(payload.data) ? payload.data : [];
            renderRows(items);
            pagination.hidden = true;
            status.textContent = items.length === 50
                ? 'Menampilkan 50 hasil pertama. Persempit kata kunci untuk hasil lain.'
                : `${items.length} nomor part cocok dengan pencarian.`;
        } catch (error) {
            if (error.name === 'AbortError' || version !== requestVersion) return;
            status.textContent = 'Pencarian gagal. Daftar sebelumnya tetap ditampilkan; coba lagi.';
        } finally {
            if (version === requestVersion) activeRequest = null;
        }
    };

    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();
        if (!query) {
            restoreInitialRows();
            return;
        }
        cancelSearch();
        clearButton.hidden = false;
        status.textContent = 'Mencari nomor part…';
        timer = setTimeout(() => runSearch(query), 250);
    });

    clearButton.addEventListener('click', () => {
        searchInput.value = '';
        restoreInitialRows();
        searchInput.focus();
    });

    tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('.js-delete-part');
        if (!button) return;
        const deleteUrl = button.dataset.deleteUrl;
        const safePartNumber = escapeHtml(button.dataset.partNumber || '-');

        WarehouseAlert.delete({
            title: 'Hapus No Part?',
            itemName: `No Part <strong>${safePartNumber}</strong>`,
            warningItems: [
                'Nomor part yang sudah dipakai dalam transaksi atau histori tidak dapat dihapus.',
                'Penghapusan yang berhasil tidak dapat dibatalkan.'
            ],
            confirmText: 'Ya, Hapus',
            onConfirm: () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = deleteUrl;
                for (const [name, value] of [['_token', csrfToken], ['_method', 'DELETE']]) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = name;
                    input.value = value;
                    form.appendChild(input);
                }
                document.body.appendChild(form);
                form.submit();
            },
        });
    });
})();
