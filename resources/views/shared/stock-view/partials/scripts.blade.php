<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const bootstrapDataEl = document.getElementById('stockViewBootstrap');
    let bootstrapData = {};
    try {
        bootstrapData = bootstrapDataEl ? JSON.parse(bootstrapDataEl.textContent || '{}') : {};
    } catch (error) {
        console.error('Failed to parse stockViewBootstrap JSON:', error);
    }

    const canEditBox = bootstrapData.canEditBox === true;
    const canDeleteStock = bootstrapData.canDeleteStock === true;
    const csrfToken = bootstrapData.csrfToken || '';
    const editBoxModalEl = document.getElementById('editBoxModal');
    const boxHistoryModalEl = document.getElementById('boxHistoryModal');
    const deleteConfirmModalEl = document.getElementById('deleteConfirmModal');
    const editBoxModal = editBoxModalEl ? new bootstrap.Modal(editBoxModalEl) : null;
    const boxHistoryModal = boxHistoryModalEl ? new bootstrap.Modal(boxHistoryModalEl) : null;
    const deleteConfirmModal = deleteConfirmModalEl ? new bootstrap.Modal(deleteConfirmModalEl) : null;
    const allParts = Array.isArray(bootstrapData.allParts) ? bootstrapData.allParts : [];
    const masterParts = Array.isArray(bootstrapData.masterParts) ? bootstrapData.masterParts : [];
    const allPallets = Array.isArray(bootstrapData.allPallets) ? bootstrapData.allPallets : [];
    const allBoxNumbers = Array.isArray(bootstrapData.allBoxNumbers) ? bootstrapData.allBoxNumbers : [];
    const allLocations = Array.isArray(bootstrapData.allLocations) ? bootstrapData.allLocations : [];
    const allSearchTerms = Array.isArray(bootstrapData.allSearchTerms)
        ? bootstrapData.allSearchTerms
        : [...allParts, ...allPallets, ...allBoxNumbers, ...allLocations];
    const currentSearch = String(bootstrapData.search || '').trim();
    const directBoxTarget = bootstrapData.directBoxTarget && typeof bootstrapData.directBoxTarget === 'object'
        ? bootstrapData.directBoxTarget
        : null;
    const viewMode = bootstrapData.viewMode || 'part';
    const searchInput = document.getElementById('searchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchForm = document.getElementById('searchForm');
    const detailModalEl = document.getElementById('detailModal');
    const palletDetailModalEl = document.getElementById('palletDetailModal');
    const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
    const palletDetailModal = palletDetailModalEl ? new bootstrap.Modal(palletDetailModalEl) : null;
    let initialEditBoxState = null;
    let currentPalletDetailData = null;
    let pendingHighlightBoxId = null;
    let hasAutoOpenedDirectBox = false;
    let editPartSelectEnhanced = false;
    let pendingDeleteAction = null;
    let deleting = false;
    let editing = false;
    let partRequest = 0;
    let palletRequest = 0;
    let historyRequest = 0;
    let deletePreviewRequest = 0;
    const stockModals = [detailModalEl, palletDetailModalEl, editBoxModalEl, boxHistoryModalEl, deleteConfirmModalEl].filter(Boolean);
    const modalParents = new Map();
    const modalTriggers = new Map();
    const suspendedModals = new Set();
    const busyControls = new WeakMap();

    function showStockModal(instance, element) {
        if (element.classList.contains('show')) return;
        const trigger = document.activeElement;
        const parent = stockModals.find(modal => modal.classList.contains('show'));
        modalTriggers.set(element, trigger);
        if (parent) {
            modalParents.set(element, parent);
            suspendedModals.add(parent);
            bootstrap.Modal.getInstance(parent).hide();
        }
        element.removeAttribute('inert');
        instance.show();
    }

    async function readStockResponse(response) {
        let data;
        try { data = await response.json(); }
        catch { throw new Error('Respons server tidak dapat dibaca. Silakan coba lagi.'); }
        if (!data || typeof data !== 'object') {
            throw new Error('Respons server tidak dapat dibaca. Silakan coba lagi.');
        }
        if (!response.ok || data.error || data.success === false) {
            const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(validation || data.message || data.error || 'Permintaan gagal. Silakan coba lagi.');
        }
        return data;
    }

    function showStockError(container, message, retry) {
        container.replaceChildren();
        container.style.display = 'block';
        const alert = document.createElement('div');
        alert.className = container.classList.contains('alert') ? 'text-start' : 'alert alert-danger text-start';
        if (container.getAttribute('role') !== 'alert') alert.setAttribute('role', 'alert');
        alert.textContent = message || 'Gagal memuat data. Silakan coba lagi.';
        container.appendChild(alert);
        if (retry) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary';
            button.textContent = 'Coba lagi';
            button.addEventListener('click', retry);
            container.appendChild(button);
        }
    }

    function startStockLoading(element) {
        element.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Memuat detail stok...</span></div>';
        element.style.display = 'block';
    }

    function setModalBusy(element, busy) {
        element.dataset.busy = String(busy);
        element.setAttribute('aria-busy', String(busy));
        if (busy) element.focus();
        if (busy && !busyControls.has(element)) {
            const controls = [...element.querySelectorAll('input, select, textarea, [data-bs-dismiss="modal"], button[type="submit"]')];
            busyControls.set(element, controls.map(control => [control, control.disabled]));
            controls.forEach(control => control.disabled = true);
        } else if (!busy && busyControls.has(element)) {
            busyControls.get(element).forEach(([control, disabled]) => control.disabled = disabled);
            busyControls.delete(element);
        }
    }

    function refreshStockWithFeedback(message) {
        try {
            sessionStorage.setItem('stockViewSuccess', message);
            window.location.reload();
        } catch {
            showAutoPopup(message, 'success', 1800);
            setTimeout(() => window.location.reload(), 1900);
        }
    }

    function sortLabelsAscending(items) {
        return [...new Set((items || []).filter(Boolean))].sort((left, right) =>
            String(left).localeCompare(String(right), undefined, {
                sensitivity: 'base'
            })
        );
    }

    function normalizeSearchKey(value) {
        return String(value ?? '').trim().toLowerCase();
    }

    function escapeHtml(input) {
        return String(input ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setPrintPalletButtonState(enabled) {
        const printBtn = document.getElementById('printPalletDetailBtn');
        if (!printBtn) return;
        printBtn.disabled = !enabled;
    }

    function showAutoPopup(message, type = 'success', duration = 1800) {
        const popup = document.createElement('div');
        const appearance = {
            success: { color: '#065f46', background: '#d1fae5', border: '#86efac', role: 'status' },
            info: { color: '#075b5d', background: '#e8f3f1', border: '#b5d9d7', role: 'status' },
            danger: { color: '#7f1d1d', background: '#fee2e2', border: '#fca5a5', role: 'alert' },
        }[type] || { color: '#075b5d', background: '#e8f3f1', border: '#b5d9d7', role: 'status' };

        popup.style.position = 'fixed';
        popup.style.top = '24px';
        popup.style.right = '24px';
        popup.style.zIndex = '3000';
        popup.style.maxWidth = '380px';
        popup.style.padding = '12px 16px';
        popup.style.borderRadius = '10px';
        popup.style.boxShadow = '0 8px 24px rgba(0,0,0,0.18)';
        popup.style.color = appearance.color;
        popup.style.background = appearance.background;
        popup.style.border = `1px solid ${appearance.border}`;
        popup.style.fontWeight = '600';
        popup.style.fontSize = '14px';
        popup.style.opacity = '0';
        popup.style.transform = 'translateY(-8px)';
        popup.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        popup.textContent = message || (type === 'success' ? 'Berhasil.' : (type === 'danger' ? 'Terjadi kesalahan.' : 'Informasi stok tersedia.'));
        popup.setAttribute('role', appearance.role);
        popup.style.maxWidth = 'min(380px, calc(100vw - 48px))';
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            popup.style.transition = 'none';
        }

        document.body.appendChild(popup);
        requestAnimationFrame(() => {
            popup.style.opacity = '1';
            popup.style.transform = 'translateY(0)';
        });

        setTimeout(() => {
            popup.style.opacity = '0';
            popup.style.transform = 'translateY(-8px)';
            setTimeout(() => {
                popup.remove();
            }, 220);
        }, duration);
    }

    function printCurrentPalletDetail() {
        if (!currentPalletDetailData) {
            showToast('Data pallet belum siap untuk dicetak.', 'warning');
            return;
        }

        const palletNumber = escapeHtml(currentPalletDetailData.pallet_number || '-');
        const location = escapeHtml(currentPalletDetailData.location || '-');
        const items = Array.isArray(currentPalletDetailData.items) ? currentPalletDetailData.items : [];

        const rowsHtml = items.length
            ? items.map(item => `
                <tr>
                    <td>${escapeHtml(item.box_number || '-')}</td>
                    <td>${escapeHtml(item.part_number || '-')}</td>
                    <td>${escapeHtml(item.box_quantity ?? '-')}</td>
                    <td>${escapeHtml(item.pcs_quantity ?? '-')}</td>
                    <td>${item.is_expired ? 'Expired - tidak dapat masuk FIFO' : (item.is_not_full ? 'Not Full' : 'OK')}</td>
                    <td>${escapeHtml(item.origin_pallet || '-')}</td>
                    <td>${escapeHtml(item.created_at || '-')}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="7" style="text-align:center;">Tidak ada item di pallet ini</td></tr>';

        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            showStockError(document.getElementById('palletDetailLoadingSpinner'), 'Popup diblokir browser. Izinkan popup lalu tekan Print kembali.');
            return;
        }
        document.getElementById('palletDetailLoadingSpinner').style.display = 'none';

        const printedAt = new Date().toLocaleString('id-ID');
        const doc = `
            <!doctype html>
            <html>
            <head>
                <meta charset="utf-8" />
                <title>Detail Pallet ${palletNumber}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 24px; color: #111827; }
                    .company-letterhead {
                        display: flex;
                        align-items: center;
                        gap: 16px;
                        border-bottom: 2px solid #111827;
                        padding-bottom: 10px;
                        margin-bottom: 12px;
                    }
                    .letterhead-logo img { width: 82px; max-height: 82px; object-fit: contain; }
                    .letterhead-info { flex: 1; text-align: center; line-height: 1.35; font-size: 12px; color: #111827; }
                    .letterhead-info .company-name { font-size: 24px; font-weight: 700; letter-spacing: 0.3px; }
                    h1 { margin: 0 0 16px 0; font-size: 20px; }
                    .meta { margin-bottom: 16px; line-height: 1.6; }
                    .meta strong { display: inline-block; min-width: 120px; }
                    .pallet-label { font-size: 24px; font-weight: 700; }
                    .pallet-number { font-size: 24px; font-weight: 700; }
                    table { width: 100%; border-collapse: collapse; font-size: 12px; }
                    th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
                    thead th { background: #f3f4f6; }
                    .footer { margin-top: 12px; font-size: 11px; color: #6b7280; }
                </style>
            </head>
            <body>
                <div class="company-letterhead">
                    <div class="letterhead-logo">
                        <img src="${window.location.origin}/logo.png" alt="Company Logo" />
                    </div>
                    <div class="letterhead-info">
                        <div class="company-name">PT. YAMATOGOMU INDONESIA</div>
                        <div>Kawasan Industri Indotaisei</div>
                        <div>Blok K - 6, Cikampek</div>
                        <div>Jawa Barat - Indonesia 41373</div>
                        <div>Phone : 0264 - 351216, 351217&nbsp;&nbsp;Fax : 0264 - 351137</div>
                    </div>
                </div>
                <h1>Detail Isi Pallet</h1>
                <div class="meta">
                    <div><strong class="pallet-label">No Pallet</strong>: <span class="pallet-number">${palletNumber}</span></div>
                    <div><strong>Lokasi</strong>: ${location}</div>
                    <div><strong>Total Item</strong>: ${items.length}</div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID Box</th>
                            <th>No Part</th>
                            <th>Box</th>
                            <th>PCS</th>
                            <th>Status</th>
                            <th>Asal Pallet</th>
                            <th>Tanggal Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
                <div class="footer">Dicetak pada: ${escapeHtml(printedAt)}</div>
                <script>
                    window.onload = function() {
                        window.print();
                    };
                <\/script>
            </body>
            </html>
        `;

        printWindow.document.open();
        printWindow.document.write(doc);
        printWindow.document.close();
    }

    const sortedAllSearchTerms = sortLabelsAscending(allSearchTerms);
    const partTermSet = new Set((allParts || []).map(normalizeSearchKey));
    const palletTermSet = new Set((allPallets || []).map(normalizeSearchKey));
    const boxTermSet = new Set((allBoxNumbers || []).map(normalizeSearchKey));
    const locationTermSet = new Set((allLocations || []).map(normalizeSearchKey));

    function getSearchTermTypes(term) {
        const key = normalizeSearchKey(term);
        const types = [];

        if (partTermSet.has(key)) types.push('No Part');
        if (palletTermSet.has(key)) types.push('Pallet');
        if (boxTermSet.has(key)) types.push('ID Box');
        if (locationTermSet.has(key)) types.push('Lokasi');

        return types.length ? types : ['Lainnya'];
    }

    function syncModalA11y(modalEl) {
        if (!modalEl) return;
        modalEl.addEventListener('hide.bs.modal', event => {
            if (modalEl.dataset.busy === 'true') { event.preventDefault(); return; }
            if (modalEl.contains(document.activeElement)) document.activeElement.blur();
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            modalEl.setAttribute('inert', '');
            if (suspendedModals.delete(modalEl)) return;
            const parent = modalParents.get(modalEl);
            modalParents.delete(modalEl);
            if (parent) {
                parent.removeAttribute('inert');
                bootstrap.Modal.getInstance(parent).show();
            }
            const trigger = modalTriggers.get(modalEl);
            if (trigger?.isConnected && !trigger.closest('[inert]')) trigger.focus();
        });
        modalEl.addEventListener('show.bs.modal', () => modalEl.removeAttribute('inert'));
    }

    syncModalA11y(detailModalEl);
    syncModalA11y(palletDetailModalEl);
    syncModalA11y(editBoxModalEl);
    syncModalA11y(boxHistoryModalEl);
    syncModalA11y(deleteConfirmModalEl);

    function renderDeleteConfirmation(config) {
        const titleEl = document.getElementById('deleteConfirmTitle');
        const summaryEl = document.getElementById('deleteConfirmSummary');
        const bodyEl = document.getElementById('deleteConfirmItemsBody');
        const submitBtn = document.getElementById('deleteConfirmSubmitBtn');

        if (!titleEl || !summaryEl || !bodyEl || !submitBtn || !deleteConfirmModal) {
            return;
        }

        document.getElementById('deleteConfirmError').classList.add('d-none');
        titleEl.textContent = config.title || 'Konfirmasi Penghapusan';
        summaryEl.innerHTML = config.summary || '';

        const rows = Array.isArray(config.items) ? config.items : [];
        if (rows.length === 0) {
            bodyEl.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Tidak ada data</td></tr>';
        } else {
            bodyEl.innerHTML = rows.map((row) => `
                <tr>
                    <td>${escapeHtml(row.id || '-')}</td>
                    <td>${escapeHtml(row.part_number || '-')}</td>
                    <td>${escapeHtml(row.pcs_quantity ?? '-')}</td>
                    <td>${escapeHtml(row.note || '-')}</td>
                </tr>
            `).join('');
        }

        pendingDeleteAction = config.action || null;
        submitBtn.disabled = !pendingDeleteAction;
        showStockModal(deleteConfirmModal, deleteConfirmModalEl);
    }

    function openDeleteBoxConfirm(dataset) {
        ++deletePreviewRequest;
        if (!canDeleteStock || !deleteConfirmModal) {
            return;
        }

        const boxId = dataset.boxId || dataset.box_id;
        if (!boxId) {
            showToast('ID box tidak valid untuk dihapus.', 'danger');
            return;
        }

        renderDeleteConfirmation({
            title: `Konfirmasi Hapus Box ${dataset.boxNumber || ''}`.trim(),
            summary: `
                <div><strong>Box:</strong> ${escapeHtml(dataset.boxNumber || '-')}</div>
                <div><strong>Pallet:</strong> ${escapeHtml(dataset.palletNumber || '-')}</div>
                <div><strong>Lokasi:</strong> ${escapeHtml(dataset.location || '-')}</div>
            `,
            items: [{
                id: dataset.boxNumber || boxId,
                part_number: dataset.partNumber || '-',
                pcs_quantity: dataset.pcsQuantity || 0,
                note: 'Dikeluarkan dari stok aktif; riwayat tetap tersimpan',
            }],
            action: {
                type: 'box',
                id: boxId,
            },
        });
    }

    async function openDeletePalletConfirm(dataset) {
        if (!canDeleteStock || !deleteConfirmModal) {
            return;
        }

        const palletId = dataset.palletId || dataset.pallet_id;
        if (!palletId) {
            showToast('ID pallet tidak valid untuk dihapus.', 'danger');
            return;
        }

        const requestId = ++deletePreviewRequest;
        renderDeleteConfirmation({ title: 'Memuat detail pallet', summary: 'Sedang memeriksa isi pallet...', items: [] });
        try {
            const response = await fetch(`/api/stock/pallet-detail/${palletId}`);
            const data = await readStockResponse(response);
            if (requestId !== deletePreviewRequest || !deleteConfirmModalEl.classList.contains('show')) return;

            const items = Array.isArray(data.items) ? data.items : [];
            renderDeleteConfirmation({
                title: `Konfirmasi Hapus Pallet ${data.pallet_number || dataset.palletNumber || ''}`.trim(),
                summary: `
                    <div><strong>Pallet:</strong> ${escapeHtml(data.pallet_number || dataset.palletNumber || '-')}</div>
                    <div><strong>Lokasi:</strong> ${escapeHtml(data.location || '-')}</div>
                    <div><strong>Total Item:</strong> ${items.length}</div>
                `,
                items: items.map((item) => ({
                    id: item.box_number || item.box_id || '-',
                    part_number: item.part_number || '-',
                    pcs_quantity: item.pcs_quantity || 0,
                    note: item.is_expired ? 'Expired - tidak dapat masuk FIFO' : (item.is_not_full ? 'Not Full' : 'OK'),
                })),
                action: {
                    type: 'pallet',
                    id: palletId,
                },
            });
        } catch (error) {
            if (requestId !== deletePreviewRequest) return;
            const alert = document.getElementById('deleteConfirmError');
            alert.classList.remove('d-none');
            document.getElementById('deleteConfirmTitle').textContent = 'Detail pallet belum tersedia';
            document.getElementById('deleteConfirmSummary').textContent = 'Muat ulang detail sebelum menghapus pallet.';
            showStockError(alert, error.message, () => openDeletePalletConfirm(dataset));
        }
    }

    async function executePendingDelete() {
        if (!pendingDeleteAction || deleting) {
            return;
        }

        const submitBtn = document.getElementById('deleteConfirmSubmitBtn');
        if (submitBtn) submitBtn.disabled = true;
        deleting = true;
        setModalBusy(deleteConfirmModalEl, true);
        const previousLabel = submitBtn.textContent;
        submitBtn.textContent = 'Menghapus...';
        document.getElementById('deleteConfirmError').classList.add('d-none');

        try {
            const endpoint = pendingDeleteAction.type === 'pallet'
                ? `/stock-view/pallets/${pendingDeleteAction.id}`
                : `/stock-view/boxes/${pendingDeleteAction.id}`;

            const response = await fetch(endpoint, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });

            const data = await readStockResponse(response);
            setModalBusy(deleteConfirmModalEl, false);

            if (deleteConfirmModal) {
                deleteConfirmModal.hide();
            }

            pendingDeleteAction = null;
            refreshStockWithFeedback(data.message || 'Data berhasil dihapus.');
        } catch (error) {
            const alert = document.getElementById('deleteConfirmError');
            alert.textContent = error.message || 'Gagal menghapus data.';
            alert.classList.remove('d-none');
            alert.focus();
        } finally {
            deleting = false;
            setModalBusy(deleteConfirmModalEl, false);
            submitBtn.textContent = previousLabel;
            if (submitBtn) submitBtn.disabled = !pendingDeleteAction;
        }
    }

    function hideEditBoxInlineAlert() {
        const alertEl = document.getElementById('editBoxInlineAlert');
        if (!alertEl) return;
        alertEl.classList.add('d-none');
        alertEl.classList.remove('alert-warning', 'alert-danger', 'alert-success', 'alert-info');
        alertEl.textContent = '';
    }

    function showEditBoxInlineAlert(message, type = 'warning') {
        const alertEl = document.getElementById('editBoxInlineAlert');
        if (!alertEl) return;
        const resolvedType = ['warning', 'danger', 'success', 'info'].includes(type) ? type : 'warning';
        alertEl.classList.remove('d-none', 'alert-warning', 'alert-danger', 'alert-success', 'alert-info');
        alertEl.classList.add(`alert-${resolvedType}`);
        alertEl.textContent = message || '';
        if (editBoxModalEl.classList.contains('show')) alertEl.focus();
    }

    function initEditPartSelect() {
        const selectEl = document.getElementById('editPartNumber');
        if (!selectEl || !window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
            return;
        }

        if (!editPartSelectEnhanced) {
            window.jQuery(selectEl).select2({
                width: '100%',
                placeholder: 'Pilih Part Number',
                minimumResultsForSearch: 0,
                language: { noResults: () => 'Part number tidak ditemukan.' },
                dropdownParent: window.jQuery('#editBoxModal')
            });
            window.jQuery(selectEl).on('select2:open', () => {
                window.jQuery('#editBoxModal .select2-search__field')
                    .attr('aria-label', 'Cari part number')
                    .off('keydown.stockEdit')
                    .on('keydown.stockEdit', event => {
                        if (event.key === 'Escape') {
                            event.stopPropagation();
                            window.jQuery(selectEl).select2('close');
                        }
                    });
            });
            editPartSelectEnhanced = true;
        }
    }

    const hasSearchUi = searchInput && searchDropdown && searchForm;

    function loadSearchSuggestions(searchTerm) {
        if (!hasSearchUi) return;
        const filtered = sortedAllSearchTerms.filter(item =>
            item.toLowerCase().includes(searchTerm.toLowerCase())
        );
        searchDropdown.replaceChildren();
        if (!filtered.length) {
            const message = document.createElement('div');
            message.className = 'stock-toolbar__message';
            message.textContent = searchTerm.trim()
                ? 'Tidak ada saran yang cocok. Tekan Terapkan untuk mencari.'
                : 'Belum ada saran pencarian.';
            searchDropdown.appendChild(message);
        }
        filtered.slice(0, 8).forEach(item => {
            const suggestion = document.createElement('button');
            suggestion.type = 'button';
            suggestion.className = 'stock-toolbar__suggestion';
            suggestion.innerHTML = `<span>${escapeHtml(item)}</span><span class="stock-toolbar__types">${
                getSearchTermTypes(item).map(type => `<span>${escapeHtml(type)}</span>`).join('')
            }</span>`;
            suggestion.addEventListener('click', () => {
                searchInput.value = item;
                searchDropdown.style.display = 'none';
                searchInput.setAttribute('aria-expanded', 'false');
                searchForm.submit();
            });
            searchDropdown.appendChild(suggestion);
        });
    }

    if (hasSearchUi) {
        const searchRegion = searchInput.closest('.stock-toolbar__search');
        const hideSuggestions = () => {
            searchDropdown.style.display = 'none';
            searchInput.setAttribute('aria-expanded', 'false');
        };
        const showSuggestions = () => {
            loadSearchSuggestions(searchInput.value);
            searchDropdown.style.display = 'block';
            searchInput.setAttribute('aria-expanded', 'true');
        };
        searchInput.addEventListener('focus', showSuggestions);
        searchInput.addEventListener('input', showSuggestions);
        searchRegion.addEventListener('focusout', event => {
            if (!searchRegion.contains(event.relatedTarget)) hideSuggestions();
        });
        document.addEventListener('pointerdown', event => {
            if (!searchRegion.contains(event.target)) hideSuggestions();
        });
        searchRegion.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                searchInput.focus();
                hideSuggestions();
            }
        });
    }

    window.viewDetail = function(partNumber) {
        const loadingEl = document.getElementById('detailLoadingSpinner');
        const contentEl = document.getElementById('detailContent');
        if (!loadingEl || !contentEl || !detailModal) {
            console.error('Detail modal elements not found');
            return;
        }

        const requestId = ++partRequest;
        startStockLoading(loadingEl);
        contentEl.style.display = 'none';

        fetch(`/api/stock/part-detail/${encodeURIComponent(partNumber)}`)
            .then(readStockResponse)
            .then(data => {
                if (requestId !== partRequest) return;

                document.getElementById('modalPartNumber').textContent = data.part_number;
                document.getElementById('modalTotalBox').textContent = data.total_box;
                document.getElementById('modalTotalPcs').textContent = data.total_pcs;

                const groupedPallets = Object.values((data.pallets || []).reduce((acc, pallet) => {
                    const key = String(pallet.pallet_id ?? pallet.pallet_number ?? 'unknown');
                    if (!acc[key]) {
                        acc[key] = {
                            pallet_id: pallet.pallet_id ?? null,
                            pallet_number: pallet.pallet_number || '-',
                            total_box: 0,
                            expired_box: 0,
                            total_pcs: 0,
                            location: pallet.location || '-',
                            latest_created_at: pallet.created_at || '-',
                            latest_created_at_raw: pallet.stored_at_raw || '',
                        };
                    }

                    if (pallet.is_expired) acc[key].expired_box += Number(pallet.box_quantity || 0);
                    acc[key].total_box += Number(pallet.box_quantity || 0);
                    acc[key].total_pcs += Number(pallet.pcs_quantity || 0);

                    const currentRaw = String(acc[key].latest_created_at_raw || '');
                    const incomingRaw = String(pallet.stored_at_raw || '');
                    if (incomingRaw && incomingRaw > currentRaw) {
                        acc[key].latest_created_at_raw = incomingRaw;
                        acc[key].latest_created_at = pallet.created_at || acc[key].latest_created_at;
                    }

                    return acc;
                }, {})).sort((left, right) => String(left.pallet_number).localeCompare(String(right.pallet_number), undefined, { sensitivity: 'base' }));

                document.getElementById('modalPalletCount').textContent = groupedPallets.length;

                const tableBody = document.getElementById('palletDetailsTable');
                if (groupedPallets.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data</td></tr>';
                } else {
                    tableBody.innerHTML = groupedPallets.map((pallet) => `
                        <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.2s ease;">
                            <td style="padding: 12px 8px; color: #0C7779; font-weight: 600; font-size: 13px;">
                                <i class="bi bi-box2"></i> ${escapeHtml(pallet.pallet_number)}
                                ${pallet.expired_box ? `<span class="badge bg-danger">${pallet.expired_box} box Expired</span>` : ''}
                            </td>
                            <td style="padding: 12px 8px; color: #1f2937; font-size: 13px;">
                                <span class="badge bg-primary" style="font-size: 11px;">${pallet.total_box} BOX</span>
                            </td>
                            <td style="padding: 12px 8px; color: #1f2937; font-weight: 600; font-size: 13px;">
                                <span class="badge bg-success">${pallet.total_pcs} PCS</span>
                            </td>
                            <td style="padding: 12px 8px; color: #6b7280; font-size: 13px;">
                                <i class="bi bi-geo-alt"></i> ${escapeHtml(pallet.location)}
                            </td>
                            <td style="padding: 12px 8px; color: #6b7280; font-size: 12px;">
                                ${escapeHtml(pallet.latest_created_at)}
                            </td>
                            <td style="padding: 12px 8px; white-space: nowrap;">
                                ${pallet.pallet_id ? `
                                    <button type="button" class="btn btn-sm btn-outline-primary js-detail-pallet"
                                        aria-label="Detail pallet ${escapeHtml(pallet.pallet_number)}"
                                        data-pallet-id="${pallet.pallet_id}">
                                        Detail Pallet
                                    </button>
                                    ${canDeleteStock ? `<button type="button" class="btn btn-sm btn-danger js-delete-pallet ms-1"
                                        aria-label="Hapus pallet ${escapeHtml(pallet.pallet_number)} dari stok aktif"
                                        data-pallet-id="${pallet.pallet_id}"
                                        data-pallet-number="${escapeHtml(pallet.pallet_number || '')}">
                                        Hapus Pallet
                                    </button>` : ''}
                                ` : '<span class="text-muted">-</span>'}
                            </td>
                        </tr>
                    `).join('');
                }

                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            })
            .catch(error => {
                if (requestId !== partRequest) return;
                showStockError(loadingEl, error.message, () => window.viewDetail(partNumber));
            });

        showStockModal(detailModal, detailModalEl);
    };

    window.viewPalletDetail = function(palletId) {
        currentPalletDetailData = null;
        setPrintPalletButtonState(false);
        const loadingEl = document.getElementById('palletDetailLoadingSpinner');
        const contentEl = document.getElementById('palletDetailContent');
        if (!loadingEl || !contentEl || !palletDetailModal) {
            console.error('Pallet modal elements not found');
            return;
        }

        const requestId = ++palletRequest;
        startStockLoading(loadingEl);
        contentEl.style.display = 'none';

        fetch(`/api/stock/pallet-detail/${palletId}`)
            .then(readStockResponse)
            .then(data => {
                if (requestId !== palletRequest) return;

                currentPalletDetailData = data;
                setPrintPalletButtonState(true);

                document.getElementById('modalPalletNumber').textContent = data.pallet_number;
                document.getElementById('modalPalletLocation').textContent = data.location;

                const tableBody = document.getElementById('palletItemsTable');
                if (data.items.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada item di pallet ini</td></tr>';
                } else {
                    tableBody.innerHTML = data.items.map(item => `
                        <tr data-box-id="${item.box_id || ''}">
                            <td style="font-weight: 600; color: #374151; padding: 12px 8px;">${escapeHtml(item.box_number || '-')}</td>
                            <td style="font-weight: 600; color: #374151; padding: 12px 8px;">${escapeHtml(item.part_number)}</td>
                            <td style="color: #6b7280; padding: 12px 8px;">${item.box_quantity}</td>
                            <td style="color: #6b7280; padding: 12px 8px;">${item.pcs_quantity}</td>
                            <td style="padding: 12px 8px;">
                                ${item.is_expired ? '<span class="badge bg-danger">Expired</span> ' : ''}${item.is_not_full ? '<span class="badge bg-warning text-dark">Not Full</span>' : (item.is_expired ? '' : '<span class="badge bg-success">OK</span>')}
                            </td>
                            <td style="color: #6b7280; padding: 12px 8px;">${escapeHtml(item.origin_pallet || '-')}</td>
                            <td style="color: #6b7280; padding: 12px 8px;">${escapeHtml(item.created_at)}</td>
                            <td style="padding: 12px 8px; white-space: nowrap;">
                                ${item.box_id ? `
                                    ${canEditBox && !item.is_expired ? `<button type="button" class="btn btn-sm btn-outline-primary js-edit-box"
                                        aria-label="Edit box ${escapeHtml(item.box_number || '-')}"
                                        data-box-id="${item.box_id}"
                                        data-box-number="${escapeHtml(item.box_number || '')}"
                                        data-part-number="${escapeHtml(item.part_number || '')}"
                                        data-pcs-quantity="${item.pcs_quantity || 0}"
                                        data-stored-at="${item.stored_at_raw || ''}"
                                        data-pallet-id="${palletId || ''}"
                                        data-pallet-number="${escapeHtml(data.pallet_number || '')}"
                                        data-location="${escapeHtml(data.location || '')}">Edit</button>` : ''}
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-box-history"
                                        aria-label="Riwayat box ${escapeHtml(item.box_number || '-')}"
                                        data-box-id="${item.box_id}">Riwayat</button>
                                    ${canDeleteStock ? `<button type="button" class="btn btn-sm btn-outline-danger js-delete-box"
                                        aria-label="Hapus box ${escapeHtml(item.box_number || '-')} dari stok aktif"
                                        data-box-id="${item.box_id}"
                                        data-box-number="${escapeHtml(item.box_number || '')}"
                                        data-part-number="${escapeHtml(item.part_number || '')}"
                                        data-pcs-quantity="${item.pcs_quantity || 0}"
                                        data-pallet-id="${palletId || ''}"
                                        data-pallet-number="${escapeHtml(data.pallet_number || '')}"
                                        data-location="${escapeHtml(data.location || '')}">Hapus</button>` : ''}
                                ` : '<span class="text-muted">-</span>'}
                            </td>
                        </tr>
                    `).join('');
                }

                const historyTableBody = document.getElementById('palletHistoryTable');
                if (historyTableBody) {
                    const historyList = Array.isArray(data.history) ? data.history : [];
                    if (historyList.length === 0) {
                        historyTableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat tercatat</td></tr>';
                    } else {
                        historyTableBody.innerHTML = historyList.map(h => {
                            let badgeClass = 'bg-secondary';
                            let actionLabel = h.action || h.type || 'Aktivitas';
                            if (h.action === 'stock_input' || h.type === 'stock_input') {
                                badgeClass = 'bg-success';
                                actionLabel = 'Input Stok';
                            } else if (h.action === 'box_pallet_moved' || h.type === 'box_pallet_moved') {
                                badgeClass = 'bg-info text-dark';
                                actionLabel = 'Pindah Box';
                            } else if (h.action === 'merged' || h.type === 'pallet_merged') {
                                badgeClass = 'bg-primary';
                                actionLabel = 'Merge Pallet';
                            } else if (h.action === 'deleted') {
                                badgeClass = 'bg-danger';
                                actionLabel = 'Hapus';
                            }

                            return `
                                <tr>
                                    <td style="font-size: 11px; color: #6b7280; padding: 8px; white-space: nowrap;">${escapeHtml(h.created_at || '-')}</td>
                                    <td style="padding: 8px;"><span class="badge ${badgeClass}">${escapeHtml(actionLabel)}</span></td>
                                    <td style="font-size: 12px; color: #374151; padding: 8px;">${escapeHtml(h.description || '-')}</td>
                                    <td style="font-size: 11px; color: #6b7280; padding: 8px;">${escapeHtml(h.user_name || 'System')}</td>
                                </tr>
                            `;
                        }).join('');
                    }
                }

                if (pendingHighlightBoxId) {
                    const selector = `tr[data-box-id="${pendingHighlightBoxId}"]`;
                    const targetRow = tableBody.querySelector(selector);
                    if (targetRow) {
                        targetRow.style.backgroundColor = '#ecfeff';
                        targetRow.style.outline = '2px solid #14b8a6';
                        targetRow.style.outlineOffset = '-2px';
                        targetRow.scrollIntoView({
                            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                            block: 'center'
                        });

                        setTimeout(() => {
                            targetRow.style.backgroundColor = '';
                            targetRow.style.outline = '';
                            targetRow.style.outlineOffset = '';
                        }, 3000);
                    }

                    pendingHighlightBoxId = null;
                }

                contentEl.style.display = 'block';
                loadingEl.style.display = 'none';
            })
            .catch(error => {
                if (requestId !== palletRequest) return;
                showStockError(loadingEl, error.message, () => window.viewPalletDetail(palletId));
                setPrintPalletButtonState(false);
            });

        showStockModal(palletDetailModal, palletDetailModalEl);
    };

    function parseDisplayDateToInputValue(displayDate) {
        if (!displayDate) return '';
        if (/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}(:\d{2})?$/.test(displayDate)) {
            return displayDate.replace(' ', 'T').slice(0, 16);
        }
        const parsed = new Date(displayDate);
        if (!Number.isNaN(parsed.getTime())) {
            return new Date(parsed.getTime() - (parsed.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
        }
        return '';
    }

    function openEditBoxModal(dataset) {
        if (!editBoxModal) return;
        hideEditBoxInlineAlert();
        initEditPartSelect();

        const initialStoredAt = parseDisplayDateToInputValue(dataset.storedAt || '');
        document.getElementById('editBoxId').value = dataset.boxId || '';
        document.getElementById('editBoxNumber').value = dataset.boxNumber || '';

        const partSelect = document.getElementById('editPartNumber');
        if (partSelect) {
            const incomingPartNumber = (dataset.partNumber || '').trim();
            partSelect.value = incomingPartNumber;
            if (editPartSelectEnhanced && window.jQuery) {
                window.jQuery(partSelect).val(incomingPartNumber).trigger('change.select2');
            }

            if (incomingPartNumber && !masterParts.includes(incomingPartNumber)) {
                partSelect.value = '';
                if (editPartSelectEnhanced && window.jQuery) {
                    window.jQuery(partSelect).val('').trigger('change.select2');
                }
                showEditBoxInlineAlert('Part lama tidak ditemukan di master part. Silakan pilih part yang valid dari daftar.', 'warning');
            }
        }

        document.getElementById('editPcsQuantity').value = dataset.pcsQuantity || '';
        document.getElementById('editStoredAt').value = initialStoredAt;
        document.getElementById('editReason').value = '';

        initialEditBoxState = {
            partNumber: (partSelect?.value || '').trim(),
            pcsQuantity: Number.parseInt(dataset.pcsQuantity || '0', 10) || 0,
            storedAt: initialStoredAt,
        };
        showStockModal(editBoxModal, editBoxModalEl);
    }

    async function openBoxHistoryModal(boxId) {
        if (!boxHistoryModal) return;
        const loading = document.getElementById('boxHistoryLoading');
        const tableBody = document.getElementById('boxHistoryTableBody');
        if (!tableBody || !loading) return;

        const requestId = ++historyRequest;
        loading.textContent = 'Memuat riwayat...';
        loading.style.display = 'block';
        tableBody.innerHTML = '';
        showStockModal(boxHistoryModal, boxHistoryModalEl);

        try {
            const response = await fetch(`/stock-view/boxes/${boxId}/history`);
            const data = await readStockResponse(response);
            if (requestId !== historyRequest) return;

            const historyRows = data.history || [];
            if (historyRows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Belum ada riwayat perubahan.</td></tr>';
            } else {
                tableBody.innerHTML = historyRows.map((row) => {
                    const oldVal = row.old_values || {};
                    const newVal = row.new_values || {};
                    let badgeClass = 'bg-secondary';
                    let actionLabel = row.action || row.type || 'Aktivitas';
                    let detailHtml = '';

                    if (row.action === 'box_updated_by_admin_warehouse') {
                        badgeClass = 'bg-warning text-dark';
                        actionLabel = 'Edit Box';
                        const diffLines = [
                            `Part: ${escapeHtml(oldVal.part_number ?? '-')} menjadi ${escapeHtml(newVal.part_number ?? '-')}`,
                            `PCS: ${escapeHtml(String(oldVal.pcs_quantity ?? '-'))} menjadi ${escapeHtml(String(newVal.pcs_quantity ?? '-'))}`,
                            `Tanggal: ${escapeHtml(oldVal.stored_at ?? '-')} menjadi ${escapeHtml(newVal.stored_at ?? '-')}`,
                        ];
                        if (newVal.reason) {
                            diffLines.push(`Alasan: ${escapeHtml(newVal.reason)}`);
                        }
                        detailHtml = diffLines.join('<br>');
                    } else if (row.action === 'stock_input' || row.type === 'stock_input') {
                        badgeClass = 'bg-success';
                        actionLabel = 'Input Stok';
                        detailHtml = escapeHtml(row.description || 'Input stok ke pallet');
                    } else if (row.action === 'box_pallet_moved' || row.type === 'box_pallet_moved') {
                        badgeClass = 'bg-info text-dark';
                        actionLabel = 'Pindah Pallet';
                        detailHtml = escapeHtml(row.description || 'Box dipindahkan');
                    } else if (row.action === 'stock_withdrawn' || row.type === 'stock_withdrawn') {
                        badgeClass = 'bg-danger';
                        actionLabel = 'Withdrawal';
                        detailHtml = escapeHtml(row.description || 'Pengambilan stok');
                    } else {
                        detailHtml = escapeHtml(row.description || '-');
                    }

                    return `
                        <tr>
                            <td style="white-space: nowrap; font-size: 11px;">${escapeHtml(row.created_at || '-')}</td>
                            <td><span class="badge ${badgeClass}">${escapeHtml(actionLabel)}</span></td>
                            <td style="font-size: 12px;">${detailHtml}</td>
                            <td style="font-size: 11px;">${escapeHtml(row.user_name || 'System')}</td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (error) {
            if (requestId !== historyRequest) return;
            showStockError(loading, error.message, () => openBoxHistoryModal(boxId));
        } finally {
            if (requestId === historyRequest && tableBody.children.length) loading.style.display = 'none';
        }
    }

    const editBoxForm = document.getElementById('editBoxForm');
    if (editBoxForm) {
        editBoxForm.addEventListener('submit', function (event) {
            event.preventDefault();
            if (editing) return;

            const boxId = document.getElementById('editBoxId').value;
            const currentPartNumber = document.getElementById('editPartNumber').value.trim();
            const currentPcsQuantity = Number.parseInt(document.getElementById('editPcsQuantity').value || '0', 10) || 0;
            const currentStoredAt = document.getElementById('editStoredAt').value;
            const reason = document.getElementById('editReason').value.trim();
            if (reason.length < 3) {
                showEditBoxInlineAlert('Jelaskan alasan perubahan minimal 3 karakter.', 'danger');
                document.getElementById('editReason').focus();
                return;
            }

            if (
                initialEditBoxState
                && currentPartNumber === initialEditBoxState.partNumber
                && currentPcsQuantity === initialEditBoxState.pcsQuantity
                && currentStoredAt === initialEditBoxState.storedAt
            ) {
                showEditBoxInlineAlert('Tidak ada perubahan data box. Aksi tidak diproses.', 'warning');
                return;
            }

            hideEditBoxInlineAlert();

            const warningItems = [
                'Perubahan detail box akan langsung memperbarui data stok aktif.',
                'Perubahan ini akan otomatis tercatat ke Audit Trail.'
            ];

            const fixedQty = (bootstrapData.masterPartCapacities ?? {})[currentPartNumber] ?? 0;
            if (fixedQty > 0 && currentPcsQuantity < fixedQty) {
                warningItems.push(`<strong class="text-danger">PERINGATAN: Kuantitas PCS (${currentPcsQuantity}) di bawah kapasitas standar (${fixedQty}). Perubahan menjadi Not Full memerlukan request yang sudah disetujui. Server akan memvalidasi kelayakan koreksi.</strong>`);
            }

            const infoHtml = `
                <div class="small">
                    <strong>Part Number:</strong> ${escapeHtml(initialEditBoxState ? initialEditBoxState.partNumber : '-')} &rarr; ${escapeHtml(currentPartNumber)}<br>
                    <strong>PCS Quantity:</strong> ${initialEditBoxState ? initialEditBoxState.pcsQuantity : '-'} &rarr; ${currentPcsQuantity}<br>
                    <strong>Tanggal Masuk:</strong> ${initialEditBoxState ? initialEditBoxState.storedAt.replace('T', ' ') : '-'} &rarr; ${currentStoredAt.replace('T', ' ')}<br>
                    <strong>Alasan Perubahan:</strong> ${escapeHtml(document.getElementById('editReason').value)}
                </div>
            `;

            editing = true;
            suspendedModals.add(editBoxModalEl);
            editBoxModal.hide();
            Swal.fire({
                title: 'Konfirmasi Perubahan Detail Box',
                html: `<p>Periksa perubahan sebelum menyimpan.</p><ul class="text-start">${warningItems.map(item => `<li>${item}</li>`).join('')}</ul>${infoHtml}`,
                icon: 'warning', showCancelButton: true, reverseButtons: true,
                showClass: { popup: '' }, hideClass: { popup: '' }, returnFocus: false,
                confirmButtonText: 'Ya, Simpan', cancelButtonText: 'Kembali',
                confirmButtonColor: '#0C7779',
            }).then(async result => {
                editBoxModalEl.removeAttribute('inert');
                editBoxModal.show();
                if (!result.isConfirmed) { editing = false; return; }
                setModalBusy(editBoxModalEl, true);
                const saveButton = editBoxForm.querySelector('button[type="submit"]');
                const previousLabel = saveButton.textContent;
                saveButton.textContent = 'Menyimpan...';
                const payload = {
                    part_number: currentPartNumber,
                    pcs_quantity: currentPcsQuantity,
                    stored_at: currentStoredAt,
                    reason: document.getElementById('editReason').value,
                };

                try {
                    const response = await fetch(`/stock-view/boxes/${boxId}/update`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await readStockResponse(response);
                    setModalBusy(editBoxModalEl, false);
                    editBoxModal.hide();
                    // Refresh summaries and all row data while preserving the current URL filters.
                    refreshStockWithFeedback(data.message || 'Detail box berhasil diperbarui.');
                } catch (error) {
                    showEditBoxInlineAlert(error.message || 'Gagal update box', 'danger');
                } finally {
                    editing = false;
                    setModalBusy(editBoxModalEl, false);
                    saveButton.textContent = previousLabel;
                }
            });
        });
    }

    const deleteConfirmSubmitBtn = document.getElementById('deleteConfirmSubmitBtn');
    if (deleteConfirmSubmitBtn) {
        deleteConfirmSubmitBtn.addEventListener('click', function (event) {
            event.preventDefault();
            executePendingDelete();
        });
    }

    document.addEventListener('click', function(event) {
        const printPalletBtn = event.target.closest('#printPalletDetailBtn');
        if (printPalletBtn) {
            event.preventDefault();
            printCurrentPalletDetail();
            return;
        }

        const partBtn = event.target.closest('.js-detail-part');
        if (partBtn) {
            event.preventDefault();
            const partNumber = partBtn.getAttribute('data-part-number');
            if (window.viewDetail) {
                window.viewDetail(partNumber);
            }
            return;
        }

        const palletBtn = event.target.closest('.js-detail-pallet');
        if (palletBtn) {
            event.preventDefault();
            const palletId = palletBtn.getAttribute('data-pallet-id');
            if (window.viewPalletDetail) {
                window.viewPalletDetail(palletId);
            }
            return;
        }

        const editBtn = event.target.closest('.js-edit-box');
        if (editBtn) {
            event.preventDefault();
            openEditBoxModal(editBtn.dataset);
            return;
        }

        const historyBtn = event.target.closest('.js-box-history');
        if (historyBtn) {
            event.preventDefault();
            openBoxHistoryModal(historyBtn.dataset.boxId);
            return;
        }

        const deleteBoxBtn = event.target.closest('.js-delete-box');
        if (deleteBoxBtn) {
            event.preventDefault();
            openDeleteBoxConfirm(deleteBoxBtn.dataset);
            return;
        }

        const deletePalletBtn = event.target.closest('.js-delete-pallet');
        if (deletePalletBtn) {
            event.preventDefault();
            openDeletePalletConfirm(deletePalletBtn.dataset);
        }
    }, true);

    window.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.stock-results .stock-sort-link.is-active').forEach(link => {
            const heading = link.closest('th');
            if (heading) heading.setAttribute('aria-sort', link.querySelector('.bi-sort-up') ? 'ascending' : 'descending');
        });
        try {
            const message = sessionStorage.getItem('stockViewSuccess');
            sessionStorage.removeItem('stockViewSuccess');
            if (message) showAutoPopup(message, 'success', 3000);
        } catch { /* Storage may be unavailable in private browsing. */ }
        if (hasSearchUi && searchInput.value) {
            loadSearchSuggestions(searchInput.value);
        }
        initEditPartSelect();

        const shouldAutoOpenDirectBox = (
            viewMode === 'part'
            && !hasAutoOpenedDirectBox
            && !!currentSearch
            && directBoxTarget
            && Number.isFinite(Number(directBoxTarget.box_id))
        );

        if (shouldAutoOpenDirectBox) {
            hasAutoOpenedDirectBox = true;
            if (directBoxTarget.pallet_id && Number.isFinite(Number(directBoxTarget.pallet_id))) {
                pendingHighlightBoxId = String(directBoxTarget.box_id);
                window.viewPalletDetail(String(directBoxTarget.pallet_id));
                showAutoPopup(`Membuka detail pallet untuk ID Box ${directBoxTarget.box_number || directBoxTarget.box_id}`, 'success', 2200);
            } else {
                openBoxHistoryModal(directBoxTarget.box_id);
                showAutoPopup(`Box ${directBoxTarget.box_number || directBoxTarget.box_id}: ${directBoxTarget.status_label || 'Tanpa Pallet Aktif'}`, 'info', 3000);
            }
        }
    });
</script>
