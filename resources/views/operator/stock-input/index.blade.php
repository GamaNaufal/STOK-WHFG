@extends('shared.layouts.app')

@section('title', 'Input Stok - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    <!-- Modern Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                    <i class="bi bi-plus-circle"></i> Input Stok Penyimpanan
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Scan barcode box dengan alat scanner untuk membuat palet dan tentukan lokasi penyimpanan
                </p>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-0">
        <!-- Main Content -->
        <div class="col-lg-8">
            <div class="card shadow" style="border: none; border-radius: 12px; overflow: hidden;">
                <div class="card-body" style="padding: 30px;">
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
                    </div>

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

                    <!-- STEP 3: Input Lokasi -->
                    <div id="step-3" style="display: none;">
                        <div class="card" style="border: 2px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
                            <div class="card-header" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                                                            color: white;
                                                            border: none;
                                                            padding: 20px;
                                                            font-weight: 600;
                                                            font-size: 15px;">
                                <i class="bi bi-pin-map"></i> Step 2: Tentukan Lokasi Penyimpanan
                            </div>
                            <div class="card-body" style="padding: 24px;">
                                <label class="form-label fw-bold" style="font-size: 15px; color: #333; margin-bottom: 12px;">
                                    <i class="bi bi-geo-alt" style="color: #0C7779;"></i> Lokasi Penyimpanan
                                </label>
                                
                                <!-- Searchable Location Input -->
                                <div class="position-relative">
                                    <div class="input-group">
                                        <span class="input-group-text bg-white border-end-0" style="border: 2px solid #e5e7eb; border-right: none; border-radius: 10px 0 0 10px;"><i class="bi bi-search"></i></span>
                                        <input type="text" id="locationSearchInput" class="form-control form-control-lg border-start-0 border-end-0" 
                                               placeholder="Pilih atau ketik kode lokasi..." autocomplete="off"
                                               style="border-top: 2px solid #e5e7eb; border-bottom: 2px solid #e5e7eb; padding: 12px 16px; font-size: 16px;">
                                        <button class="btn btn-outline-secondary border-start-0" type="button" id="locationDropdownBtn" 
                                                style="border-color: #e5e7eb; border-top: 2px solid #e5e7eb; border-bottom: 2px solid #e5e7eb; border-right: 2px solid #e5e7eb; border-radius: 0 10px 10px 0;">
                                            <i class="bi bi-chevron-down"></i>
                                        </button>
                                        <input type="hidden" id="selectedLocationId" name="location_id">
                                        <input type="hidden" id="selectedLocationCode" name="warehouse_location">
                                    </div>
                                    
                                    <!-- Dropdown Results -->
                                    <div id="locationSearchResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1000; display: none; max-height: 200px; overflow-y: auto;">
                                        <!-- Items will be populated via JS -->
                                    </div>
                                </div>
                                
                                <small class="text-muted d-block mt-3" style="font-size: 14px;" id="locationStatusText">
                    <i class="bi bi-info-circle"></i> Pilih lokasi kosong dari dropdown. Tidak boleh menaruh di lokasi yang sudah ada palet lain.

                                <!-- Action Buttons -->
                                <div class="d-grid gap-3 d-md-flex justify-content-md-end mt-4">
                                    <button type="button" class="btn btn-lg" id="cancel-btn"
                                            style="background: #6b7280;
                                                   color: white;
                                                   border: none;
                                                   padding: 14px 28px;
                                                   border-radius: 10px;
                                                   font-weight: 600;
                                                   font-size: 15px;
                                                   transition: all 0.3s ease;">
                                        <i class="bi bi-x-circle"></i> Batal
                                    </button>
                                    <button type="button" class="btn btn-lg" id="save-btn"
                                            style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                                                   color: white; 
                                                   border: none;
                                                   padding: 14px 28px;
                                                   border-radius: 10px;
                                                   font-weight: 600;
                                                   font-size: 15px;
                                                   transition: all 0.3s ease;
                                                   box-shadow: 0 4px 12px rgba(12, 119, 121, 0.2);">
                                        <i class="bi bi-check-circle"></i> Simpan Stok
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --color-teal: #0C7779;
    --color-turquoise: #249E94;
    --color-green: #10b981;
    --color-gray: #6b7280;
    --color-gray-light: #e5e7eb;
    --color-success: #e8f5e9;
    --color-error: #fee2e2;
    --color-info: #e0f2fe;
}

/* Ensure proper Bootstrap grid behavior */
@media (min-width: 992px) {
    .row.g-4 {
        display: flex;
        flex-wrap: wrap;
    }
    
    .col-lg-8 {
        flex: 0 0 auto;
        width: 66.666667%;
    }
    
    .col-lg-4 {
        flex: 0 0 auto;
        width: 33.333333%;
    }
}

/* Input Focus States */
#barcodeInput:focus,
#warehouse_location:focus {
    outline: none;
    border-color: var(--color-teal) !important;
    box-shadow: 0 0 0 4px rgba(12, 119, 121, 0.1);
    transform: translateY(-2px);
}

/* Button Hover States */
#clear-pallet-btn:hover {
    background: #d97706 !important;
    box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3) !important;
    transform: translateY(-2px);
}

#cancel-btn:hover {
    background: #4b5563 !important;
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.2) !important;
    transform: translateY(-2px);
}

#save-btn:hover {
    box-shadow: 0 8px 24px rgba(12, 119, 121, 0.3) !important;
    transform: translateY(-2px);
}

/* Items List Box */
#itemsList > div {
    background: #f9fafb;
    border: 1px solid var(--color-gray-light);
    border-radius: 10px;
    padding: 14px 16px;
    transition: all 0.3s ease;
}

#itemsList > div:hover {
    background: #f3f4f6;
    border-color: var(--color-teal);
    box-shadow: 0 2px 8px rgba(12, 119, 121, 0.1);
}

/* Smooth Animations */
.btn, .form-control, .card {
    transition: background-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
}

/* Scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background: var(--color-teal);
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--color-turquoise);
}

/* Responsive */
@media (max-width: 991px) {
    .card-body {
        padding: 20px !important;
    }
    
    .btn-lg {
        width: 100%;
    }
    
    .col-lg-4 .card {
        position: relative !important;
        top: 0 !important;
    }
}
</style>
@endsection

@section('scripts')
<!-- Library untuk QR Code Detection -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // ===== BARCODE SCANNER INPUT =====
    const barcodeInput = document.getElementById('barcodeInput');
    const partInput = document.getElementById('partInput');
    const scanForm = document.getElementById('scanForm');
    const barcodeSubmitBtn = document.getElementById('barcodeSubmitBtn');
    const partSubmitBtn = document.getElementById('partSubmitBtn');
    
    // Auto-focus barcode input on page load
    document.addEventListener('DOMContentLoaded', function() {
        barcodeInput.focus();
    });

    scanForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!barcodeInput.disabled) {
            const barcode = barcodeInput.value.trim();
            if (barcode) {
                scanBarcodeHardware(barcode);
                barcodeInput.value = '';
            }
            return;
        }

        if (!partInput.disabled) {
            const partNumber = partInput.value.trim();
            if (partNumber) {
                scanPartNumber(partNumber);
                partInput.value = '';
            }
        }
    });

    if (barcodeSubmitBtn) {
        barcodeSubmitBtn.addEventListener('click', function() {
            if (barcodeInput.disabled) return;
            const barcode = barcodeInput.value.trim();
            if (barcode) {
                scanBarcodeHardware(barcode);
                barcodeInput.value = '';
            }
        });
    }

    if (partSubmitBtn) {
        partSubmitBtn.addEventListener('click', function() {
            if (partInput.disabled) return;
            const partNumber = partInput.value.trim();
            if (partNumber) {
                scanPartNumber(partNumber);
                partInput.value = '';
            }
        });
    }
    
    barcodeInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            let barcode = this.value.trim();
            if (barcode) {
                scanBarcodeHardware(barcode);
                this.value = ''; // Clear input
            }
        }
    });

    partInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            let partNumber = this.value.trim();
            if (partNumber) {
                scanPartNumber(partNumber);
                this.value = '';
            }
        }
    });

    // Global fallback: handle Enter based on focused input
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            const active = document.activeElement;
            if (active === barcodeInput && !barcodeInput.disabled) {
                e.preventDefault();
                const barcode = barcodeInput.value.trim();
                if (barcode) {
                    scanBarcodeHardware(barcode);
                    barcodeInput.value = '';
                }
            }
            if (active === partInput && !partInput.disabled) {
                e.preventDefault();
                const partNumber = partInput.value.trim();
                if (partNumber) {
                    scanPartNumber(partNumber);
                    partInput.value = '';
                }
            }
        }
    });

    function scanBarcodeHardware(barcode) {
        showBarcodeStatus('Memproses: ' + barcode);

        $.ajax({
            url: "{{ route('stock-input.scan-barcode') }}",
            method: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                barcode: barcode
            },
            success: function(response) {
                if (response.success) {
                    currentPalletId = response.pallet_id;
                    showBarcodeStatus('✓ Box: ' + response.box_number + ' | Lanjut scan No Part');

                    barcodeInput.disabled = true;
                    partInput.disabled = false;
                    if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = true;
                    if (partSubmitBtn) partSubmitBtn.disabled = false;

                    document.getElementById('step-2').style.display = 'block';
                    document.getElementById('step-3').style.display = 'block';
                    setTimeout(() => partInput.focus(), 300);
                } else {
                    showBarcodeError(response.message);
                    barcodeInput.disabled = false;
                    partInput.disabled = true;
                    if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = false;
                    if (partSubmitBtn) partSubmitBtn.disabled = true;
                    setTimeout(() => barcodeInput.focus(), 300);
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showBarcodeError(msg);
                barcodeInput.disabled = false;
                partInput.disabled = true;
                if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = false;
                if (partSubmitBtn) partSubmitBtn.disabled = true;
                setTimeout(() => barcodeInput.focus(), 300);
            }
        });
    }

    function scanPartNumber(partNumber) {
        showPartStatus('Memproses: ' + partNumber);

        $.ajax({
            url: "{{ route('stock-input.scan-part') }}",
            method: 'POST',
            data: {
                _token: "{{ csrf_token() }}",
                part_number: partNumber
            },
            success: function(response) {
                if (response.success) {
                    showPartStatus('✓ Part sesuai: ' + response.part_number);
                    loadAndDisplayPalletData();

                    partInput.disabled = true;
                    barcodeInput.disabled = false;
                    if (partSubmitBtn) partSubmitBtn.disabled = true;
                    if (barcodeSubmitBtn) barcodeSubmitBtn.disabled = false;
                    setTimeout(() => barcodeInput.focus(), 300);
                } else {
                    showPartError(response.message);
                    setTimeout(() => partInput.focus(), 300);
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showPartError(msg);
                setTimeout(() => partInput.focus(), 300);
            }
        });
    }

    function loadAndDisplayPalletData() {
        $.ajax({
            url: "{{ route('stock-input.get-pallet-data') }}",
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(data) {
                if (data.success) {
                    const pallet = data.pallet;
                    currentPalletId = pallet.id;
                    document.getElementById('display_pallet_number').textContent = pallet.pallet_number;
                    document.getElementById('box_count').textContent = pallet.total_boxes;
                    
                    // Display boxes
                    const itemsList = document.getElementById('itemsList');
                    itemsList.innerHTML = '';
                    
                    if (pallet.boxes && pallet.boxes.length > 0) {
                        pallet.boxes.forEach((box) => {
                            const boxRow = document.createElement('div');
                            boxRow.className = 'row mb-2 p-2 bg-light rounded';
                            boxRow.innerHTML = `
                                <div class="col-md-3">
                                    <small class="text-muted">Box</small>
                                    <div class="fw-bold">${box.box_number}</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Part</small>
                                    <div class="fw-bold">${box.part_number}</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">PCS</small>
                                    <div class="fw-bold">${box.pcs_quantity}</div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Qty Box</small>
                                    <div class="fw-bold">${box.qty_box ?? '-'} ${box.is_not_full ? '<span class="badge bg-warning text-dark ms-1">Not Full</span>' : ''}</div>
                                </div>
                            `;
                            itemsList.appendChild(boxRow);
                        });
                    }
                }
            },
            error: function(xhr) {
                console.error('Error loading pallet data:', xhr);
            }
        });
    }

    function showBarcodeStatus(text) {
        const statusEl = document.getElementById('barcode-status');
        document.getElementById('barcode-status-text').textContent = text;
        statusEl.style.display = 'block';
        document.getElementById('barcode-error').style.display = 'none';
    }

    function showBarcodeError(text) {
        const errorEl = document.getElementById('barcode-error');
        document.getElementById('barcode-error-text').textContent = text;
        errorEl.style.display = 'block';
        document.getElementById('barcode-status').style.display = 'none';
    }

    function showPartStatus(text) {
        const statusEl = document.getElementById('part-status');
        document.getElementById('part-status-text').textContent = text;
        statusEl.style.display = 'block';
        document.getElementById('part-error').style.display = 'none';
    }

    function showPartError(text) {
        const errorEl = document.getElementById('part-error');
        document.getElementById('part-error-text').textContent = text;
        errorEl.style.display = 'block';
        document.getElementById('part-status').style.display = 'none';
    }

    // ===== VARIABLES =====
    const infoBox = document.getElementById('info-box');
    const infoText = document.getElementById('info-text');
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const step2 = document.getElementById('step-2');
    const step3 = document.getElementById('step-3');
    
    let currentPalletId = null;
    let lastScannedCode = null;
    let lastScanTime = 0;

    const showConfirm = ({ title, message, confirmText, onConfirm }) => {
        WarehouseAlert.confirm({
            title: title,
            message: message,
            confirmText: confirmText,
            confirmColor: '#0C7779',
            onConfirm: onConfirm
        });
    };

    // Clear Pallet Button
    document.getElementById('clear-pallet-btn').addEventListener('click', function() {
        showConfirm({
            title: 'Mulai Palet Baru',
            message: 'Palet saat ini belum disimpan. Jika lanjut, data palet saat ini akan dihapus.',
            confirmText: 'Mulai Baru',
            onConfirm: () => {
                fetch('{{ route("stock-input.clear-session") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(response => response.json())
                .then(() => {
                    // Reset scan tracking untuk palet baru
                    lastScannedCode = null;
                    lastScanTime = 0;
                    // Reset barcode input focus
                    barcodeInput.value = '';
                    barcodeInput.focus();
                    location.reload();
                });
            }
        });
    });

    // Cancel Button
    document.getElementById('cancel-btn').addEventListener('click', function() {
        showConfirm({
            title: 'Batalkan Input',
            message: 'Data palet saat ini akan hilang jika dibatalkan.',
            confirmText: 'Batalkan',
            onConfirm: () => {
                // Reset scan tracking
                lastScannedCode = null;
                lastScanTime = 0;

                // CLEAR SESSION
                fetch('{{ route("stock-input.clear-session") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(() => location.reload());
            }
        });
    });

    // Save Button
    document.getElementById('save-btn').addEventListener('click', function() {
        const searchInputVal = document.getElementById('locationSearchInput').value.trim();
        const selectedId = document.getElementById('selectedLocationId').value;
        const selectedCode = document.getElementById('selectedLocationCode').value;

        // REQUIRE selection from dropdown - no manual text input allowed
        if (!selectedId || !selectedCode) {
            showError('Pilih lokasi dari list yang tersedia. Lokasi tidak boleh diketik manual.');
            return;
        }

        const form = new FormData();
        form.append('pallet_id', currentPalletId);
        form.append('location_id', selectedId);
        form.append('warehouse_location', selectedCode);
        form.append('_token', '{{ csrf_token() }}');

        fetch('{{ route("stock-input.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: form
        })
        .then(response => {
            if (!response.ok) {
                 return response.json().then(err => { throw new Error(err.message || 'Error saving stock'); });
            }
            return response.json();
        })
        .then(data => {
            // Success
            showToast('Stok berhasil disimpan di lokasi: ' + selectedCode, 'success');
            window.location.href = '{{ route("stock-input.index") }}';
        })
        .catch(error => {
            showError('Terjadi kesalahan saat menyimpan: ' + error.message);
        });
    });

    // --- LOCATION SEARCH LOGIC ---
    const searchInput = document.getElementById('locationSearchInput');
    const searchResults = document.getElementById('locationSearchResults');
    const dropdownBtn = document.getElementById('locationDropdownBtn');
    const selectedLocationId = document.getElementById('selectedLocationId');
    const selectedLocationCode = document.getElementById('selectedLocationCode');
    let searchTimeout;

    // Function to perform search
    function performSearch(query) {
        // Clear ID if typing (forcing re-selection or manual mode)
        selectedLocationId.value = ''; 
        
        // Show loading or empty state if needed
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<div class="list-group-item text-muted">Mencari...</div>';

        fetch(`/api/locations/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                searchResults.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(loc => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action';
                        item.innerHTML = `<div class="d-flex justify-content-between align-items-center">
                                            <strong>${loc.code}</strong>
                                            <span class="badge bg-success rounded-pill" style="font-size: 0.7em;">Available</span>
                                          </div>`;
                        item.style.cursor = 'pointer';
                        item.dataset.value = loc.code; // For testing
                        item.onclick = (e) => {
                            e.preventDefault();
                            searchInput.value = loc.code;
                            selectedLocationId.value = loc.id;
                            selectedLocationCode.value = loc.code;
                            searchResults.style.display = 'none';
                        };
                        searchResults.appendChild(item);
                    });
                    searchResults.style.display = 'block';
                } else {
                    if(query === '') {
                         searchResults.innerHTML = '<div class="list-group-item text-muted">Tidak ada lokasi tersedia.</div>';
                    } else {
                         // Allow manual input
                         searchResults.innerHTML = '<div class="list-group-item text-muted">Lokasi tidak ditemukan di Master. Gunakan sebagai lokasi baru?</div>';
                    }
                    searchResults.style.display = 'block';
                }
            })
            .catch(err => {
                console.error('Search error:', err);
                searchResults.style.display = 'none';
            });
    }

    if (searchInput) {
        // Input event (typing)
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        // Focus event (clicked or tabbed into)
        searchInput.addEventListener('focus', function() {
            if(this.value.trim() === '') {
                 performSearch('');
            } else {
                 searchResults.style.display = 'block'; // Show previous results/state
            }
        });

        // Dropdown button click
        if (dropdownBtn) {
            dropdownBtn.addEventListener('click', function() {
                searchInput.focus();
                // Toggle logic could be added here, but search('') functions as "Open Dropdown"
                performSearch(searchInput.value.trim()); 
            });
        }

        // Hide results on click outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && 
                !searchResults.contains(e.target) && 
                (!dropdownBtn || !dropdownBtn.contains(e.target))) {
                searchResults.style.display = 'none';
            }
        });
    }

    function showError(message) {
        errorText.textContent = message;
        errorMessage.style.display = 'block';
        infoBox.style.display = 'none';
    }

    function showInfo(message) {
        infoText.textContent = message;
        infoBox.style.display = 'block';
        errorMessage.style.display = 'none';
    }

    // Load initial pallet data on page load
    document.addEventListener('DOMContentLoaded', function() {
        fetch('{{ route("stock-input.get-pallet-data") }}', {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPalletId = data.pallet.id;
                step2.style.display = 'block';
                step3.style.display = 'block';
                loadAndDisplayPalletData();
                showInfo('Palet aktif ditemukan. Lanjutkan scan box atau tentukan lokasi.');
            }
        })
        .catch(() => {
            // No active pallet, ready for new scan
        });
    });
</script>
@endsection