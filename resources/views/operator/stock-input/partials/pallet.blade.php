<!-- STEP 2: Palet dan Items -->
<div id="step-2" style="display: none;">
    <div class="card mb-4" style="border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                                        color: white;
                                        border: none;
                                        padding: 20px;
                                        font-weight: 600;
                                        font-size: 15px;">
            <i class="bi bi-box2"></i> Detail Palet Saat Ini
        </div>
        <div class="card-body" style="padding: 24px;">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted small" style="font-size: 13px; font-weight: 600;">No Palet</label>
                        <p class="fs-5 fw-bold" id="display_pallet_number" style="color: #0C7779; margin: 0;">-</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label text-muted small" style="font-size: 13px; font-weight: 600;">Jumlah Box</label>
                        <p class="fs-5 fw-bold" style="color: #0C7779; margin: 0;"><span id="box_count">0</span> box</p>
                    </div>
                </div>
            </div>

            <!-- Items dalam Palet -->
            <div class="mt-4 pt-4" style="border-top: 2px solid #e5e7eb;">
                <label class="form-label text-muted small d-block mb-3" style="font-size: 13px; font-weight: 600;">Box yang Ter-scan:</label>
                <div id="itemsList" class="d-grid gap-3">
                    <!-- Box akan tampil di sini -->
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <button type="button" class="btn w-100" id="clear-pallet-btn"
                style="background: #f59e0b;
                       color: white;
                       border: none;
                       padding: 14px 20px;
                       border-radius: 10px;
                       font-weight: 600;
                       font-size: 15px;
                       transition: all 0.3s ease;
                       box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);">
            <i class="bi bi-arrow-clockwise"></i> Mulai Palet Baru
        </button>
    </div>
</div>
