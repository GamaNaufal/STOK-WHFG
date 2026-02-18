<!-- TABS: Barcode Scanner -->
<div class="mb-4">
    <div class="card-header" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                                     color: white;
                                     margin: -30px -30px 20px -30px;
                                     padding: 20px 30px;
                                     border: none;
                                     border-radius: 12px 12px 0 0;
                                     font-weight: 600;
                                     font-size: 16px;">
        <i class="bi bi-upc-scan"></i> Step 1: Scan Barcode dengan Alat Scanner
    </div>

    <form id="scanForm" autocomplete="off">
        <label class="form-label fw-bold" style="font-size: 15px; color: #333; margin-bottom: 12px;">
            <i class="bi bi-barcode" style="color: #0C7779;"></i> Input ID Box
        </label>

        <div class="input-group input-group-lg">
            <span class="input-group-text" style="background: #0C7779;
                                                         color: white;
                                                         border: 2px solid #0C7779;
                                                         border-radius: 10px 0 0 10px;
                                                         padding: 12px 16px;">
                <i class="bi bi-barcode"></i>
            </span>
            <input type="text"
                   id="barcodeInput"
                   class="form-control"
                   placeholder="Scan/ketik ID Box..."
                   style="font-size: 16px;
                          border: 2px solid #0C7779;
                          border-radius: 0;
                          padding: 12px 16px;
                          transition: all 0.3s ease;"
                   autofocus>
            <button type="button" id="barcodeSubmitBtn" class="btn"
                    style="background: #0C7779; color: white; border: 2px solid #0C7779; border-radius: 0 10px 10px 0;">
                Proses
            </button>
        </div>

        <small class="form-text text-muted mt-3 d-block" style="font-size: 14px;">
            <i class="bi bi-info-circle"></i> Scan ID Box terlebih dahulu, lalu scan No Part
        </small>

        <div class="mt-4">
            <label class="form-label fw-bold" style="font-size: 15px; color: #333; margin-bottom: 12px;">
                <i class="bi bi-upc" style="color: #0C7779;"></i> Input No Part
            </label>

            <div class="input-group input-group-lg">
                <span class="input-group-text" style="background: #0C7779;
                                                             color: white;
                                                             border: 2px solid #0C7779;
                                                             border-radius: 10px 0 0 10px;
                                                             padding: 12px 16px;">
                    <i class="bi bi-upc"></i>
                </span>
                <input type="text"
                       id="partInput"
                       class="form-control"
                       placeholder="Scan no part untuk konfirmasi..."
                       style="font-size: 16px;
                              border: 2px solid #0C7779;
                              border-radius: 0;
                              padding: 12px 16px;
                              transition: all 0.3s ease;"
                       disabled>
                <button type="button" id="partSubmitBtn" class="btn"
                        style="background: #0C7779; color: white; border: 2px solid #0C7779; border-radius: 0 10px 10px 0;"
                        disabled>
                    Proses
                </button>
            </div>

            <small class="form-text text-muted mt-3 d-block" style="font-size: 14px;">
                <i class="bi bi-info-circle"></i> Scan No Part setelah scan ID Box
            </small>
        </div>
    </form>

    <!-- Part Status -->
    <div id="part-status" class="alert mt-3" style="display: none;
                                                       background: #e8f5e9;
                                                       border: 2px solid #10b981;
                                                       color: #047857;
                                                       border-radius: 10px;
                                                       padding: 14px 16px;">
        <i class="bi bi-check-circle"></i> <span id="part-status-text">-</span>
    </div>

    <!-- Part Error -->
    <div id="part-error" class="alert alert-dismissible fade show mt-3" style="display: none;
                                                                                   background: #fee2e2;
                                                                                   border: 2px solid #dc2626;
                                                                                   color: #991b1b;
                                                                                   border-radius: 10px;
                                                                                   padding: 14px 16px;">
        <i class="bi bi-exclamation-triangle"></i> <span id="part-error-text"></span>
        <button type="button" class="btn-close" onclick="document.getElementById('part-error').style.display='none';" style="filter: invert(0.3);"></button>
    </div>
</div>

<!-- Barcode Result -->
<div id="barcode-status" class="alert mt-3" style="display: none;
                                                   background: #e8f5e9;
                                                   border: 2px solid #10b981;
                                                   color: #047857;
                                                   border-radius: 10px;
                                                   padding: 14px 16px;">
    <i class="bi bi-check-circle"></i> <span id="barcode-status-text">-</span>
</div>

<!-- Barcode Error -->
<div id="barcode-error" class="alert alert-dismissible fade show mt-3" style="display: none;
                                                                               background: #fee2e2;
                                                                               border: 2px solid #dc2626;
                                                                               color: #991b1b;
                                                                               border-radius: 10px;
                                                                               padding: 14px 16px;">
    <i class="bi bi-exclamation-triangle"></i> <span id="barcode-error-text"></span>
    <button type="button" class="btn-close" onclick="document.getElementById('barcode-error').style.display='none';" style="filter: invert(0.3);"></button>
</div>

<!-- Info Box -->
<div id="info-box" class="alert mt-3" style="display: none;
                                              background: #e0f2fe;
                                              border: 2px solid #0284c7;
                                              color: #0c4a6e;
                                              border-radius: 10px;
                                              padding: 14px 16px;">
    <strong><i class="bi bi-info-circle"></i> Status:</strong>
    <p id="info-text" class="mb-0" style="margin-top: 8px;">-</p>
</div>

<!-- Error Message -->
<div id="error-message" class="alert alert-dismissible fade show mt-3" style="display: none;
                                                                              background: #fee2e2;
                                                                              border: 2px solid #dc2626;
                                                                              color: #991b1b;
                                                                              border-radius: 10px;
                                                                              padding: 14px 16px;">
    <i class="bi bi-exclamation-triangle"></i> <span id="error-text"></span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: invert(0.3);"></button>
</div>
