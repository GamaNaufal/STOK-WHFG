<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(5px);">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 24px;
                        border-radius: 12px 12px 0 0;
                        position: sticky;
                        top: 0;
                        z-index: 1020;">
                <h5 class="modal-title fw-bold" style="margin: 0; font-size: 18px;">
                    <i class="bi bi-tag"></i> Detail Stok Per Part Number
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div id="detailLoadingSpinner" style="text-align: center; padding: 40px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="detailContent" style="display: none;">
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <p class="text-muted small" style="margin-bottom: 4px; font-weight: 600;">No Part</p>
                            <p style="font-size: 18px; font-weight: 700; color: #0C7779; margin: 0;" id="modalPartNumber">-</p>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0" style="background: #f9fafb; border-radius: 10px;">
                                <div class="card-body" style="padding: 16px;">
                                    <p class="text-muted small" style="margin-bottom: 8px; font-weight: 600;">Total Box</p>
                                    <p style="font-size: 24px; font-weight: 700; color: #249E94; margin: 0;" id="modalTotalBox">-</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
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
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" style="margin: 0;">
                                    <thead style="background: #f9fafb; border-top: 1px solid #e5e7eb;">
                                        <tr>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Pallet #</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Box</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">PCS</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Lokasi</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody id="palletDetailsTable">
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-4">Loading...</td>
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
<div class="modal fade" id="palletDetailModal" tabindex="-1" aria-hidden="true" style="backdrop-filter: blur(5px);">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 24px;
                        border-radius: 12px 12px 0 0;
                        position: sticky;
                        top: 0;
                        z-index: 1020;">
                <h5 class="modal-title fw-bold" style="margin: 0; font-size: 18px;">
                    <i class="bi bi-layers"></i> Detail Isi Pallet
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div id="palletDetailLoadingSpinner" style="text-align: center; padding: 40px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="palletDetailContent" style="display: none;">
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <p class="text-muted small" style="margin-bottom: 4px; font-weight: 600;">No Pallet</p>
                            <p style="font-size: 32px; font-weight: 800; color: #0C7779; margin: 0; letter-spacing: 0.4px; line-height: 1.2;" id="modalPalletNumber">-</p>
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
                            <div class="table-responsive">
                                <table class="table table-sm table-hover" style="margin: 0;">
                                    <thead style="background: #f9fafb; border-top: 1px solid #e5e7eb;">
                                        <tr>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">ID Box</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">No Part</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Box</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">PCS</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Status</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Asal Pallet</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Tanggal Masuk</th>
                                            <th style="color: #0C7779; font-weight: 600; font-size: 12px; padding: 12px 8px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="palletItemsTable">
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">Loading...</td>
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

<div class="modal fade" id="editBoxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 18px 20px;">
                <h5 class="modal-title fw-bold" style="margin:0; font-size:16px;">Edit Detail Box</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editBoxForm">
                <div class="modal-body" style="padding: 20px;">
                    <input type="hidden" id="editBoxId" name="box_id">
                    <div id="editBoxInlineAlert" class="alert d-none" role="alert" style="margin-bottom: 12px;"></div>
                    <div class="mb-3">
                        <label class="form-label">ID Box</label>
                        <input type="text" id="editBoxNumber" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Part Number</label>
                        <select id="editPartNumber" name="part_number" class="form-select" required>
                            <option value="">Pilih Part Number</option>
                            @foreach($masterPartNumbers as $masterPartNumber)
                                <option value="{{ $masterPartNumber }}">{{ $masterPartNumber }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PCS Quantity</label>
                        <input type="number" id="editPcsQuantity" name="pcs_quantity" min="1" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Masuk</label>
                        <input type="datetime-local" id="editStoredAt" name="stored_at" class="form-control" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Alasan Perubahan (wajib)</label>
                        <textarea id="editReason" name="reason" rows="3" class="form-control" minlength="3" required></textarea>
                    </div>
                    <small class="text-muted">Perubahan otomatis tercatat di Audit Trail.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" style="background:#0C7779;border-color:#0C7779;">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="boxHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 18px 20px;">
                <h5 class="modal-title fw-bold" style="margin:0; font-size:16px;">Riwayat Perubahan Box</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div id="boxHistoryLoading" class="text-center text-muted py-3">Memuat riwayat...</div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover" style="margin:0;">
                        <thead style="background:#f9fafb;">
                            <tr>
                                <th>Waktu</th>
                                <th>User</th>
                                <th>Perubahan</th>
                                <th>Alasan</th>
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
