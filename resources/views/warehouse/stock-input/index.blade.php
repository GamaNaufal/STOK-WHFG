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

                        <label class="form-label fw-bold" style="font-size: 15px; color: #333; margin-bottom: 12px;">
                            <i class="bi bi-barcode" style="color: #0C7779;"></i> Input Barcode
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
                                   placeholder="Scan barcode dengan alat scanner..." 
                                   style="font-size: 16px; 
                                          border: 2px solid #0C7779;
                                          border-radius: 0 10px 10px 0;
                                          padding: 12px 16px;
                                          transition: all 0.3s ease;"
                                   autofocus>
                        </div>

                        <small class="form-text text-muted mt-3 d-block" style="font-size: 14px;">
                            <i class="bi bi-info-circle"></i> Arahkan scanner ke barcode dan tekan tombol pada alat
                        </small>

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
                                <input type="text" class="form-control form-control-lg" id="warehouse_location" 
                                       placeholder="Contoh: A-1-1 (Rak A, Baris 1, Posisi 1)"
                                       style="border: 2px solid #e5e7eb;
                                              border-radius: 10px;
                                              padding: 12px 16px;
                                              font-size: 16px;
                                              transition: all 0.3s ease;">
                                <small class="text-muted d-block mt-3" style="font-size: 14px;">
                                    <i class="bi bi-info-circle"></i> Format lokasi: [RAK]-[BARIS]-[POSISI]<br>
                                    Contoh: A-1-1, B-2-3, C-3-5, dll
                                </small>

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
    
    // Auto-focus barcode input on page load
    document.addEventListener('DOMContentLoaded', function() {
        barcodeInput.focus();
    });
    
    barcodeInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            let barcode = this.value.trim();
            if (barcode) {
                scanBarcodeHardware(barcode);
                this.value = ''; // Clear input
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
                    showBarcodeStatus('✓ Berhasil scan: ' + response.box_number + ' (' + response.boxes_in_pallet + ' box dalam palet)');
                    loadAndDisplayPalletData();
                    // Show step 2 & 3
                    document.getElementById('step-2').style.display = 'block';
                    document.getElementById('step-3').style.display = 'block';
                    // Refresh focus
                    setTimeout(() => barcodeInput.focus(), 500);
                } else {
                    showBarcodeError(response.message);
                }
            },
            error: function(xhr) {
                let msg = 'Terjadi kesalahan';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showBarcodeError(msg);
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
                                <div class="col-md-4">
                                    <small class="text-muted">Box</small>
                                    <div class="fw-bold">${box.box_number}</div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Part</small>
                                    <div class="fw-bold">${box.part_number}</div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">PCS</small>
                                    <div class="fw-bold">${box.pcs_quantity}</div>
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

    // Clear Pallet Button
    document.getElementById('clear-pallet-btn').addEventListener('click', function() {
        if (confirm('Yakin ingin mulai palet baru? Palet saat ini belum disimpan.')) {
            fetch('{{ route("stock-input.clear-session") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
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

    // Cancel Button
    document.getElementById('cancel-btn').addEventListener('click', function() {
        if (confirm('Batal? Data palet akan hilang.')) {
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

    // Save Button
    document.getElementById('save-btn').addEventListener('click', function() {
        const warehouse_location = document.getElementById('warehouse_location').value.trim();
        
        if (!warehouse_location) {
            showError('Masukkan lokasi penyimpanan terlebih dahulu');
            return;
        }

        const form = new FormData();
        form.append('pallet_id', currentPalletId);
        form.append('warehouse_location', warehouse_location);
        form.append('_token', '{{ csrf_token() }}');

        fetch('{{ route("stock-input.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: form
        })
        .then(response => response.text())
        .then(html => {
            window.location.href = '{{ route("stock-input.index") }}';
        })
        .catch(error => {
            showError('Terjadi kesalahan saat menyimpan: ' + error);
        });
    });

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