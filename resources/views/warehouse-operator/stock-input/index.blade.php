@extends('shared.layouts.app')

@section('title', 'Input Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h2">
            <i class="bi bi-plus-circle"></i> Input Stok Penyimpanan
        </h1>
        <p class="text-muted">Scan barcode box dengan alat scanner untuk membuat palet dan tentukan lokasi penyimpanan</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <!-- TABS: Barcode Scanner -->
                <div class="mb-4">
                    <div class="card-header" style="background: #0C7779; color: white; margin: -20px -20px 20px -20px; padding: 15px;">
                        <i class="bi bi-device-ssd"></i> Step 1: Scan Barcode dengan Alat Scanner
                    </div>

                    <label class="form-label fw-bold">
                        <i class="bi bi-barcode"></i> Input Barcode
                    </label>
                    
                    <div class="input-group input-group-lg">
                        <span class="input-group-text" style="background: #0C7779; color: white; border: 2px solid #0C7779;">
                            <i class="bi bi-barcode"></i>
                        </span>
                        <input type="text" 
                               id="barcodeInput" 
                               class="form-control" 
                               placeholder="Scan barcode dengan alat scanner..." 
                               style="font-size: 16px; border: 2px solid #0C7779;"
                               autofocus>
                    </div>

                    <small class="form-text text-muted mt-2 d-block">
                        <i class="bi bi-info-circle"></i> Arahkan scanner ke barcode dan tekan tombol pada alat
                    </small>

                    <!-- Barcode Result -->
                    <div id="barcode-status" class="alert alert-info mt-3" style="display: none;">
                        <i class="bi bi-info-circle"></i> <span id="barcode-status-text">-</span>
                    </div>

                    <!-- Barcode Error -->
                    <div id="barcode-error" class="alert alert-danger alert-dismissible fade show mt-3" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i> <span id="barcode-error-text"></span>
                        <button type="button" class="btn-close" onclick="document.getElementById('barcode-error').style.display='none';"></button>
                    </div>

                    <!-- Info Box -->
                    <div id="info-box" class="alert alert-info mt-3" style="display: none;">
                        <strong><i class="bi bi-info-circle"></i> Status:</strong>
                        <p id="info-text" class="mb-0">-</p>
                    </div>

                    <!-- Error Message -->
                    <div id="error-message" class="alert alert-danger alert-dismissible fade show mt-3" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i> <span id="error-text"></span>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>

                <!-- STEP 2: Palet dan Items -->
                <div id="step-2" style="display: none;">
                    <div class="card mb-4 bg-light">
                        <div class="card-header" style="background: #0C7779; color: white;">
                            <i class="bi bi-box2"></i> Detail Palet Saat Ini
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">No Palet</label>
                                        <p class="fs-5 fw-bold" id="display_pallet_number">-</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Jumlah Box</label>
                                        <p class="fs-5 fw-bold"><span id="box_count">0</span> box</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Items dalam Palet -->
                            <div class="mt-3 pt-3 border-top">
                                <label class="form-label text-muted small d-block mb-2">Box yang Ter-scan:</label>
                                <div id="itemsList" class="d-grid gap-2">
                                    <!-- Box akan tampil di sini -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <button type="button" class="btn btn-warning w-100" id="clear-pallet-btn">
                            <i class="bi bi-arrow-clockwise"></i> Mulai Palet Baru
                        </button>
                    </div>
                </div>

                <hr class="my-4" id="hr-step3" style="display: none;">

                <!-- STEP 3: Input Lokasi -->
                <div id="step-3" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header" style="background: #0C7779; color: white;">
                            <i class="bi bi-pin-map"></i> Step 2: Tentukan Lokasi Penyimpanan
                        </div>
                        <div class="card-body">
                            <label class="form-label fw-bold">
                                <i class="bi bi-geo-alt"></i> Lokasi Penyimpanan
                            </label>
                            <input type="text" class="form-control form-control-lg" id="warehouse_location" 
                                   placeholder="Contoh: A-1-1 (Rak A, Baris 1, Posisi 1)">
                            <small class="text-muted d-block mt-2">
                                Format lokasi: [RAK]-[BARIS]-[POSISI]<br>
                                Contoh: A-1-1, B-2-3, C-3-5, dll
                            </small>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                                <button type="button" class="btn btn-secondary" id="cancel-btn">
                                    <i class="bi bi-x-circle"></i> Batal
                                </button>
                                <button type="button" class="btn btn-lg" id="save-btn" style="background: #0C7779; color: white; border: none;">
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
        showBarycodeStatus('Memproses: ' + barcode);
        
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
                    showBarycodeStatus('✓ Berhasil scan: ' + response.box_number + ' (' + response.boxes_in_pallet + ' box dalam palet)');
                    loadAndDisplayPalletData();
                    // Show step 2 & 3
                    document.getElementById('step-2').style.display = 'block';
                    document.getElementById('step-3').style.display = 'block';
                    document.getElementById('hr-step3').style.display = 'block';
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

    function showBarycodeStatus(text) {
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

    // ===== QR CODE CAMERA SCANNER =====
    const video = document.getElementById('video');
    const canvas = document.createElement('canvas');
    const canvasContext = canvas.getContext('2d', { willReadFrequently: true });
    const startCameraBtn = document.getElementById('start-camera-btn');
    const stopCameraBtn = document.getElementById('stop-camera-btn');
    const cameraStatus = document.getElementById('camera-status');
    const cameraStatusText = document.getElementById('camera-status-text');
    
    const infoBox = document.getElementById('info-box');
    const infoText = document.getElementById('info-text');
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const step2 = document.getElementById('step-2');
    const step3 = document.getElementById('step-3');
    const hrStep3 = document.getElementById('hr-step3');
    
    let currentPalletId = null;
    let cameraActive = false;
    let detectionInterval = null;
    const detectedCodes = new Set();
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
            // STOP CAMERA FIRST
            if (cameraActive) {
                const stream = video.srcObject;
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                }
                cameraActive = false;
            }

            // Reset scan tracking
            lastScannedCode = null;
            lastScanTime = 0;

            // CLEAR SESSION - JANGAN ATTACH BOX KE DATABASE!
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
                hrStep3.style.display = 'block';
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
