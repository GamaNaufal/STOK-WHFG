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

    const currentUserRole = bootstrapData.currentUserRole ?? null;
    const canEditBox = currentUserRole === 'admin_warehouse' || currentUserRole === 'admin';
    const canDeleteStock = ['admin_warehouse', 'supervisi', 'admin'].includes(currentUserRole);
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
    const viewMode = bootstrapData.viewMode || 'part';
    const searchInput = document.getElementById('searchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchForm = document.getElementById('searchForm');
    const detailModalEl = document.getElementById('detailModal');
    const palletDetailModalEl = document.getElementById('palletDetailModal');
    const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
    const palletDetailModal = palletDetailModalEl ? new bootstrap.Modal(palletDetailModalEl) : null;
    let initialEditBoxState = null;
    let currentPartNumber = null;
    let currentPalletId = null;
    let currentPalletDetailData = null;
    let editPartSelectEnhanced = false;
    let pendingDeleteAction = null;

    function sortLabelsAscending(items) {
        return [...new Set((items || []).filter(Boolean))].sort((left, right) =>
            String(left).localeCompare(String(right), undefined, {
                sensitivity: 'base'
            })
        );
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
        const isSuccess = type === 'success';

        popup.style.position = 'fixed';
        popup.style.top = '24px';
        popup.style.right = '24px';
        popup.style.zIndex = '3000';
        popup.style.maxWidth = '380px';
        popup.style.padding = '12px 16px';
        popup.style.borderRadius = '10px';
        popup.style.boxShadow = '0 8px 24px rgba(0,0,0,0.18)';
        popup.style.color = isSuccess ? '#065f46' : '#7f1d1d';
        popup.style.background = isSuccess ? '#d1fae5' : '#fee2e2';
        popup.style.border = isSuccess ? '1px solid #86efac' : '1px solid #fca5a5';
        popup.style.fontWeight = '600';
        popup.style.fontSize = '14px';
        popup.style.opacity = '0';
        popup.style.transform = 'translateY(-8px)';
        popup.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        popup.textContent = message || (isSuccess ? 'Berhasil.' : 'Terjadi kesalahan.');

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
                    <td>${item.is_not_full ? 'Not Full' : 'OK'}</td>
                    <td>${escapeHtml(item.origin_pallet || '-')}</td>
                    <td>${escapeHtml(item.created_at || '-')}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="7" style="text-align:center;">Tidak ada item di pallet ini</td></tr>';

        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            showToast('Popup diblokir browser. Izinkan popup untuk mencetak.', 'warning');
            return;
        }

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

    const sortedAllParts = sortLabelsAscending(allParts);
    const sortedAllPallets = sortLabelsAscending(allPallets);

    function syncModalA11y(modalEl) {
        if (!modalEl) return;
        modalEl.addEventListener('hide.bs.modal', () => {
            const active = document.activeElement;
            if (active && modalEl.contains(active)) {
                active.blur();
            }
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            modalEl.setAttribute('inert', '');
        });
        modalEl.addEventListener('shown.bs.modal', () => {
            modalEl.removeAttribute('inert');
        });
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
        deleteConfirmModal.show();
    }

    function openDeleteBoxConfirm(dataset) {
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
                note: 'Box ini akan dihapus permanen',
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

        try {
            const response = await fetch(`/api/stock/pallet-detail/${palletId}`);
            const data = await response.json();
            if (!response.ok || data.error) {
                throw new Error(data.error || 'Gagal mengambil detail pallet.');
            }

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
                    note: item.is_not_full ? 'Not Full' : 'OK',
                })),
                action: {
                    type: 'pallet',
                    id: palletId,
                },
            });
        } catch (error) {
            showToast(error.message || 'Gagal membuka konfirmasi hapus pallet.', 'danger');
        }
    }

    async function executePendingDelete() {
        if (!pendingDeleteAction) {
            return;
        }

        const submitBtn = document.getElementById('deleteConfirmSubmitBtn');
        if (submitBtn) submitBtn.disabled = true;
        const deleteType = pendingDeleteAction.type;

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

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Gagal menghapus data');
            }

            if (deleteConfirmModal) {
                deleteConfirmModal.hide();
            }

            pendingDeleteAction = null;
            const successMessage = data.message || 'Data berhasil dihapus.';
            if (deleteType === 'box') {
                showAutoPopup(successMessage, 'success', 1800);
            } else {
                showToast(successMessage, 'success');
            }
            setTimeout(() => window.location.reload(), deleteType === 'box' ? 1900 : 450);
        } catch (error) {
            showToast(error.message || 'Gagal menghapus data.', 'danger');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
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
                dropdownParent: window.jQuery('#editBoxModal')
            });
            editPartSelectEnhanced = true;
        }
    }

    const hasSearchUi = searchInput && searchDropdown && searchForm;

    function loadSearchSuggestions(searchTerm) {
        if (!hasSearchUi) return;
        const dataSource = viewMode === 'pallet' ? sortedAllPallets : sortedAllParts;
        let filtered = dataSource;
        
        if (searchTerm.trim()) {
            filtered = dataSource.filter(item => 
                item.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }
        
        searchDropdown.innerHTML = '';
        
        if (filtered.length === 0 && searchTerm.trim()) {
            const noResult = document.createElement('div');
            noResult.className = 'dropdown-item text-muted';
            noResult.style.padding = '12px 16px';
            noResult.innerHTML = '<i class="bi bi-search me-2"></i>Tidak ada hasil untuk "' + searchTerm + '"';
            searchDropdown.appendChild(noResult);
            return;
        }
        
        if (!searchTerm.trim() && filtered.length > 0) {
            const header = document.createElement('div');
            header.style.cssText = 'padding: 12px 16px; font-weight: 600; color: #0C7779; border-bottom: 1px solid #e5e7eb; font-size: 12px; text-transform: uppercase;';
            header.textContent = viewMode === 'pallet' ? '📦 Rekomendasi Pallet' : '🏷️ Rekomendasi Part';
            searchDropdown.appendChild(header);
        }
        
        filtered.slice(0, 8).forEach(item => {
            const suggestion = document.createElement('div');
            suggestion.className = 'dropdown-item';
            suggestion.style.cssText = 'padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #f3f4f6; transition: all 0.2s ease; font-size: 14px; background: white;';
            
            const icon = viewMode === 'pallet' ? '📦' : '🏷️';
            suggestion.innerHTML = `<i class="bi bi-check-circle" style="color: #0C7779; margin-right: 8px; opacity: 0;"></i> ${icon} ${item}`;
            
            suggestion.addEventListener('click', function(e) {
                e.stopPropagation();
                searchInput.value = item;
                searchDropdown.style.display = 'none';
                searchForm.submit();
            });
            
            suggestion.addEventListener('mouseover', function() {
                this.style.backgroundColor = '#f0f4f8';
                this.style.color = '#0C7779';
                this.style.fontWeight = '600';
                this.querySelector('i').style.opacity = '1';
            });
            
            suggestion.addEventListener('mouseout', function() {
                this.style.backgroundColor = 'white';
                this.style.color = '#374151';
                this.style.fontWeight = '400';
                this.querySelector('i').style.opacity = '0';
            });
            
            searchDropdown.appendChild(suggestion);
        });
    }

    if (hasSearchUi) {
        searchInput.addEventListener('focus', function() {
            loadSearchSuggestions(searchInput.value);
            searchDropdown.style.display = 'block';
        });

        searchInput.addEventListener('input', function() {
            loadSearchSuggestions(this.value);
            searchDropdown.style.display = 'block';
        });

        searchInput.addEventListener('blur', function() {
            setTimeout(() => {
                searchDropdown.style.display = 'none';
            }, 150);
        });
    }

    window.viewDetail = function(partNumber) {
        currentPartNumber = partNumber;
        const loadingEl = document.getElementById('detailLoadingSpinner');
        const contentEl = document.getElementById('detailContent');
        if (!loadingEl || !contentEl || !detailModal) {
            console.error('Detail modal elements not found');
            return;
        }

        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';

        fetch(`/api/stock/part-detail/${encodeURIComponent(partNumber)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showToast('Error: ' + data.error, 'danger');
                    return;
                }

                document.getElementById('modalPartNumber').textContent = data.part_number;
                document.getElementById('modalTotalBox').textContent = data.total_box;
                document.getElementById('modalTotalPcs').textContent = data.total_pcs;
                document.getElementById('modalPalletCount').textContent = data.pallet_count;

                const tableBody = document.getElementById('palletDetailsTable');
                if (data.pallets.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data</td></tr>';
                } else {
                    tableBody.innerHTML = data.pallets.map((pallet) => `
                        <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.2s ease;">
                            <td style="padding: 12px 8px; color: #0C7779; font-weight: 600; font-size: 13px;">
                                <i class="bi bi-box2"></i> ${pallet.pallet_number}
                            </td>
                            <td style="padding: 12px 8px; color: #1f2937; font-size: 13px;">
                                <span class="badge bg-primary" style="font-size: 11px;">${pallet.box_quantity} BOX</span>
                                ${pallet.is_not_full ? '<span class="badge bg-warning text-dark ms-1" style="font-size: 10px;" title="Box not full">NOT FULL</span>' : ''}
                            </td>
                            <td style="padding: 12px 8px; color: #1f2937; font-weight: 600; font-size: 13px;">
                                <span class="badge bg-success">${pallet.pcs_quantity} PCS</span>
                            </td>
                            <td style="padding: 12px 8px; color: #6b7280; font-size: 13px;">
                                <i class="bi bi-geo-alt"></i> ${pallet.location}
                            </td>
                            <td style="padding: 12px 8px; color: #6b7280; font-size: 12px;">
                                ${pallet.created_at}
                            </td>
                            <td style="padding: 12px 8px; white-space: nowrap;">
                                ${pallet.box_id ? `
                                    ${canEditBox ? `<button type="button" class="btn btn-sm btn-outline-primary js-edit-box"
                                        data-box-id="${pallet.box_id}"
                                        data-box-number="${pallet.box_number || ''}"
                                        data-part-number="${pallet.part_number || ''}"
                                        data-pcs-quantity="${pallet.pcs_quantity || 0}"
                                        data-stored-at="${pallet.stored_at_raw || ''}"
                                        data-pallet-id="${pallet.pallet_id || ''}"
                                        data-pallet-number="${pallet.pallet_number || ''}"
                                        data-location="${pallet.location || ''}">Edit</button>` : ''}
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-box-history" data-box-id="${pallet.box_id}">History</button>
                                    ${canDeleteStock ? `<button type="button" class="btn btn-sm btn-outline-danger js-delete-box"
                                        data-box-id="${pallet.box_id}"
                                        data-box-number="${pallet.box_number || ''}"
                                        data-part-number="${pallet.part_number || ''}"
                                        data-pcs-quantity="${pallet.pcs_quantity || 0}"
                                        data-pallet-id="${pallet.pallet_id || ''}"
                                        data-pallet-number="${pallet.pallet_number || ''}"
                                        data-location="${pallet.location || ''}">Hapus Box</button>` : ''}
                                    ${canDeleteStock ? `<button type="button" class="btn btn-sm btn-danger js-delete-pallet"
                                        data-pallet-id="${pallet.pallet_id || ''}"
                                        data-pallet-number="${pallet.pallet_number || ''}">Hapus Pallet</button>` : ''}
                                ` : '<span class="text-muted">-</span>'}
                            </td>
                        </tr>
                    `).join('');
                }

                loadingEl.style.display = 'none';
                contentEl.style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error loading detail data', 'danger');
                loadingEl.style.display = 'none';
            });

        detailModal.show();
    };

    window.viewPalletDetail = function(palletId) {
        currentPalletId = palletId;
        currentPalletDetailData = null;
        setPrintPalletButtonState(false);
        const loadingEl = document.getElementById('palletDetailLoadingSpinner');
        const contentEl = document.getElementById('palletDetailContent');
        if (!loadingEl || !contentEl || !palletDetailModal) {
            console.error('Pallet modal elements not found');
            return;
        }

        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';

        fetch(`/api/stock/pallet-detail/${palletId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showToast('Error: ' + data.error, 'danger');
                    return;
                }

                currentPalletDetailData = data;
                setPrintPalletButtonState(true);

                document.getElementById('modalPalletNumber').textContent = data.pallet_number;
                document.getElementById('modalPalletLocation').textContent = data.location;

                const tableBody = document.getElementById('palletItemsTable');
                if (data.items.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada item di pallet ini</td></tr>';
                } else {
                    tableBody.innerHTML = data.items.map(item => `
                        <tr>
                            <td style="font-weight: 600; color: #374151; padding: 12px 8px;">${item.box_number || '-'}</td>
                            <td style="font-weight: 600; color: #374151; padding: 12px 8px;">${item.part_number}</td>
                            <td style="color: #6b7280; padding: 12px 8px;">${item.box_quantity}</td>
                            <td style="color: #6b7280; padding: 12px 8px;">${item.pcs_quantity}</td>
                            <td style="padding: 12px 8px;">
                                ${item.is_not_full ? '<span class="badge bg-warning text-dark">Not Full</span>' : '<span class="badge bg-success">OK</span>'}
                            </td>
                            <td style="color: #6b7280; padding: 12px 8px;">${item.origin_pallet || '-'}</td>
                            <td style="color: #6b7280; padding: 12px 8px;">${item.created_at}</td>
                            <td style="padding: 12px 8px; white-space: nowrap;">
                                ${item.box_id ? `
                                    ${canEditBox ? `<button type="button" class="btn btn-sm btn-outline-primary js-edit-box" 
                                        data-box-id="${item.box_id}" 
                                        data-box-number="${item.box_number || ''}" 
                                        data-part-number="${item.part_number || ''}" 
                                        data-pcs-quantity="${item.pcs_quantity || 0}" 
                                        data-stored-at="${item.stored_at_raw || ''}"
                                        data-pallet-id="${palletId || ''}"
                                        data-pallet-number="${data.pallet_number || ''}"
                                        data-location="${data.location || ''}">Edit</button>` : ''}
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-box-history" data-box-id="${item.box_id}">History</button>
                                    ${canDeleteStock ? `<button type="button" class="btn btn-sm btn-outline-danger js-delete-box"
                                        data-box-id="${item.box_id}"
                                        data-box-number="${item.box_number || ''}"
                                        data-part-number="${item.part_number || ''}"
                                        data-pcs-quantity="${item.pcs_quantity || 0}"
                                        data-pallet-id="${palletId || ''}"
                                        data-pallet-number="${data.pallet_number || ''}"
                                        data-location="${data.location || ''}">Hapus</button>` : ''}
                                ` : '<span class="text-muted">-</span>'}
                            </td>
                        </tr>
                    `).join('');
                }

                contentEl.style.display = 'block';
                loadingEl.style.display = 'none';
            })
            .catch(error => {
                console.error('Error:', error);
                loadingEl.innerHTML = '<p class="text-danger">Gagal memuat data</p>';
                setPrintPalletButtonState(false);
            });
            
        palletDetailModal.show();
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
        editBoxModal.show();
    }

    async function openBoxHistoryModal(boxId) {
        if (!boxHistoryModal) return;
        const loading = document.getElementById('boxHistoryLoading');
        const tableBody = document.getElementById('boxHistoryTableBody');
        if (!tableBody || !loading) return;

        loading.style.display = 'block';
        tableBody.innerHTML = '';
        boxHistoryModal.show();

        try {
            const response = await fetch(`/stock-view/boxes/${boxId}/history`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Gagal mengambil history box');
            }

            const historyRows = data.history || [];
            if (historyRows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Belum ada history perubahan.</td></tr>';
            } else {
                tableBody.innerHTML = historyRows.map((row) => {
                    const oldVal = row.old_values || {};
                    const newVal = row.new_values || {};
                    const diff = [
                        `Part: ${oldVal.part_number ?? '-'} → ${newVal.part_number ?? '-'}`,
                        `PCS: ${oldVal.pcs_quantity ?? '-'} → ${newVal.pcs_quantity ?? '-'}`,
                        `Tanggal: ${oldVal.stored_at ?? '-'} → ${newVal.stored_at ?? '-'}`,
                    ].join('<br>');
                    return `
                        <tr>
                            <td>${row.created_at || '-'}</td>
                            <td>${row.user_name || '-'}</td>
                            <td>${diff}</td>
                            <td>${newVal.reason || '-'}</td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">${error.message}</td></tr>`;
        } finally {
            loading.style.display = 'none';
        }
    }

    const editBoxForm = document.getElementById('editBoxForm');
    if (editBoxForm) {
        editBoxForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const boxId = document.getElementById('editBoxId').value;
            const currentPartNumber = document.getElementById('editPartNumber').value.trim();
            const currentPcsQuantity = Number.parseInt(document.getElementById('editPcsQuantity').value || '0', 10) || 0;
            const currentStoredAt = document.getElementById('editStoredAt').value;

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

                const data = await response.json();
                if (!response.ok) {
                    const firstError = data?.errors ? Object.values(data.errors)[0][0] : (data.message || 'Gagal update box');
                    throw new Error(firstError);
                }

                if (editBoxModal) {
                    editBoxModal.hide();
                }

                showToast(data.message || 'Detail box berhasil diperbarui.', 'success');
                if (currentPalletId) {
                    window.viewPalletDetail(currentPalletId);
                }
                if (currentPartNumber && detailModalEl?.classList.contains('show')) {
                    window.viewDetail(currentPartNumber);
                }
            } catch (error) {
                showEditBoxInlineAlert(error.message || 'Gagal update box', 'danger');
            }
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
        if (hasSearchUi && searchInput.value) {
            loadSearchSuggestions(searchInput.value);
        }
        initEditPartSelect();
    });
</script>