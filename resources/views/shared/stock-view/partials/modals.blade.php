<!-- Detail Modal -->
<div class="modal fade stock-detail" id="detailModal" tabindex="-1" aria-hidden="true" aria-labelledby="stockPartDetailTitle">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h5 id="stockPartDetailTitle" class="modal-title fw-bold" style="margin: 0; font-size: 18px;">
                    <i class="bi bi-tag"></i> Detail Stok Per Part Number
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup detail"></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div id="detailLoadingSpinner" style="text-align: center; padding: 40px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Memuat detail stokâ€¦</span>
                    </div>
                </div>
                <div id="detailContent" style="display: none;">
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <p class="text-muted small" style="margin-bottom: 4px; font-weight: 600;">No Part</p>
                            <p style="font-size: 18px; font-weight: 700; color: #0C7779; margin: 0;" id="modalPartNumber">-</p>
                        </div>
                        <div class="col-6">
                            <div class="card border-0" style="background: #f9fafb; border-radius: 10px;">
                                <div class="card-body" style="padding: 16px;">
                                    <p class="text-muted small" style="margin-bottom: 8px; font-weight: 600;">Total Box</p>
                                    <p style="font-size: 24px; font-weight: 700; color: #249E94; margin: 0;" id="modalTotalBox">-</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card border-0" style="background: #f9fafb; border-radius: 10px;">
                                <div class="card-body" style="padding: 16px;">
                                    <p class="text-muted small" style="margin-bottom: 8px; font-weight: 600;">Total PCS</p>
                                    <p style="font-size: 24px; font-weight: 700; color: #3BC1A8; margin: 0;" id="modalTotalPcs">-</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card border-0" style="background: #f9fafb; border-radius: 10px;">
                                <div class="card-body" style="padding: 16px;">
                                    <p class="text-muted small" style="margin-bottom: 8px; font-weight: 600;">Jumlah Pallet</p>
                                    <p style="font-size: 20px; font-weight: 700; color: #0C7779; margin: 0;" id="modalPalletCount">-</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark mb-3" style="color: #0C7779; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                                <i class="bi bi-boxes"></i> Detail Pallet
                            </h6>
                            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel detail stok, geser untuk melihat kolom lainnya">
                                <table class="table table-sm table-hover" style="margin: 0;">
                                    <thead style="background: #f9fafb; border-top: 1px solid #e5e7eb;">
                                        <tr>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Pallet #</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Box</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">PCS</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Lokasi</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Tanggal</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="palletDetailsTable">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Memuat detail stokâ€¦</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pallet Detail -->
<div class="modal fade stock-detail" id="palletDetailModal" tabindex="-1" aria-hidden="true" aria-labelledby="stockPalletDetailTitle">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header">
                <h5 id="stockPalletDetailTitle" class="modal-title fw-bold" style="margin: 0; font-size: 18px;">
                    <i class="bi bi-layers"></i> Detail Isi Pallet
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup detail"></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div id="palletDetailLoadingSpinner" style="text-align: center; padding: 40px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Memuat detail stokâ€¦</span>
                    </div>
                </div>
                <div id="palletDetailContent" style="display: none;">
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <p class="text-muted small" style="margin-bottom: 4px; font-weight: 600;">No Pallet</p>
                            <p style="font-size: 24px; font-weight: 800; color: #0C7779; margin: 0; letter-spacing: 0.4px; line-height: 1.2;" id="modalPalletNumber">-</p>
                        </div>
                        <div class="col-6">
                            <p class="text-muted small" style="margin-bottom: 4px; font-weight: 600;">Lokasi</p>
                            <p style="font-size: 18px; font-weight: 700; color: #4b5563; margin: 0;" id="modalPalletLocation">-</p>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="button" id="printPalletDetailBtn" class="btn btn-sm btn-outline-secondary" disabled>
                                <i class="bi bi-printer"></i> Print Keterangan Pallet
                            </button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark mb-3" style="color: #0C7779; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                                <i class="bi bi-box-seam"></i> Daftar Item
                            </h6>
                            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel detail stok, geser untuk melihat kolom lainnya">
                                <table class="table table-sm table-hover" style="margin: 0;">
                                    <thead style="background: #f9fafb; border-top: 1px solid #e5e7eb;">
                                        <tr>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">ID Box</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">No Part</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Box</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">PCS</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Status</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Asal Pallet</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Tanggal Masuk</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="palletItemsTable">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Memuat detail stokâ€¦</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-12">
                            <h6 class="fw-bold text-dark mb-3" style="color: #0C7779; border-bottom: 2px solid #e5e7eb; padding-bottom: 12px;">
                                <i class="bi bi-clock-history"></i> Riwayat Pallet
                            </h6>
                            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel detail stok, geser untuk melihat kolom lainnya" style="max-height: 250px; overflow-y: auto;">
                                <table class="table table-sm table-hover" style="margin: 0;">
                                    <thead style="background: #f9fafb; border-top: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 10px 8px;">Waktu</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 10px 8px;">Aktivitas</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 10px 8px;">Detail</th>
                                            <th scope="col" style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 10px 8px;">User</th>
                                        </tr>
                                    </thead>
                                    <tbody id="palletHistoryTable">
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-3">Belum ada riwayat tercatat</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade stock-detail stock-maintenance" id="editBoxModal" tabindex="-1" aria-hidden="true" aria-labelledby="editBoxTitle">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header">
                <h5 id="editBoxTitle" class="modal-title fw-bold" style="margin:0; font-size:16px;">Edit Detail Box</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup dialog"></button>
            </div>
            <form id="editBoxForm">
                <div class="modal-body" style="padding: 20px;">
                    <input type="hidden" id="editBoxId" name="box_id">
                    <div id="editBoxInlineAlert" class="alert d-none" role="alert" style="margin-bottom: 12px;"></div>
                    <div class="mb-3">
                        <label for="editBoxNumber" class="form-label">ID Box</label>
                        <input type="text" id="editBoxNumber" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="editPartNumber" class="form-label">Part Number</label>
                        <select id="editPartNumber" name="part_number" class="form-select" required>
                            <option value="">Pilih Part Number</option>
                            @foreach($masterPartNumbers as $masterPartNumber)
                                <option value="{{ $masterPartNumber }}">{{ $masterPartNumber }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editPcsQuantity" class="form-label">PCS Quantity</label>
                        <input type="number" id="editPcsQuantity" name="pcs_quantity" min="1" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="editStoredAt" class="form-label">Tanggal Masuk</label>
                        <input type="datetime-local" id="editStoredAt" name="stored_at" class="form-control" required>
                    </div>
                    <div class="mb-1">
                        <label for="editReason" class="form-label">Alasan Perubahan (wajib)</label>
                        <textarea id="editReason" aria-describedby="editReasonHint" name="reason" rows="3" class="form-control" minlength="3" required></textarea>
                    </div>
                    <small id="editReasonHint" class="text-muted">Jelaskan alasan koreksi minimal 3 karakter. Perubahan tercatat di Audit Trail.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" style="background:#0C7779;border-color:#0C7779;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade stock-detail stock-maintenance" id="boxHistoryModal" tabindex="-1" aria-hidden="true" aria-labelledby="boxHistoryTitle">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header">
                <h5 id="boxHistoryTitle" class="modal-title fw-bold" style="margin:0; font-size:16px;">Riwayat Aktivitas Box</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup dialog"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div id="boxHistoryLoading" role="status" class="text-center text-muted py-3">Memuat riwayat...</div>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel rincian, geser untuk melihat kolom lainnya">
                    <table class="table table-sm table-hover" style="margin:0;">
                        <thead style="background:#f9fafb;">
                            <tr>
                                <th scope="col">Waktu</th>
                                <th scope="col">Aktivitas</th>
                                <th scope="col">Detail / Keterangan</th>
                                <th scope="col">User</th>
                            </tr>
                        </thead>
                        <tbody id="boxHistoryTableBody">
                            <tr>
                                <td colspan="4" class="text-center text-muted">Belum ada data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade stock-detail stock-maintenance" id="deleteConfirmModal" tabindex="-1" aria-hidden="true" aria-labelledby="deleteConfirmTitle">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteConfirmTitle" style="margin:0; font-size:16px;">Konfirmasi Penghapusan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup dialog"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div class="alert shadow-sm" style="background: #FFFBEB; border: none; border-left: 4px solid #F59E0B; color: #92400E; margin-bottom: 14px; border-radius: 8px;">
                    <i class="bi bi-exclamation-triangle"></i> Stok berikut akan dikeluarkan dari daftar stok aktif. Riwayat tetap tersimpan. Periksa identitas dan jumlah sebelum menghapus.
                </div>
                <div id="deleteConfirmSummary" class="mb-3 text-muted"></div>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel rincian, geser untuk melihat kolom lainnya">
                    <table class="table table-sm table-hover" style="margin:0;">
                        <thead style="background:#f9fafb;">
                            <tr>
                                <th scope="col">ID/No</th>
                                <th scope="col">Part Number</th>
                                <th scope="col">PCS</th>
                                <th scope="col">Catatan</th>
                            </tr>
                        </thead>
                        <tbody id="deleteConfirmItemsBody">
                            <tr>
                                <td colspan="4" class="text-center text-muted">Belum ada data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted d-block mt-2">Aksi hapus akan dicatat ke audit trail.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="deleteConfirmSubmitBtn">Hapus dari Stok Aktif</button>
            </div>
        </div>
    </div>
</div>
