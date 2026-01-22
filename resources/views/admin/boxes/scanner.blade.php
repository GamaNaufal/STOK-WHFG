@extends('shared.layouts.app')

@section('title', 'Barcode Scanner - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2">
                    <i class="bi bi-barcode"></i> Barcode Scanner
                </h1>
                <p class="text-muted">Scan barcode dengan hardware scanner atau input manual</p>
            </div>
            <a href="{{ route('boxes.index') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <!-- Hardware Scanner Input -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header" style="background: #0C7779; color: white;">
                <i class="bi bi-device-ssd"></i> Hardware Scanner Input
            </div>
            <div class="card-body p-4">
                <div class="input-group input-group-lg">
                    <span class="input-group-text" style="background: #0C7779; color: white; border: 2px solid #0C7779;">
                        <i class="bi bi-barcode"></i>
                    </span>
                    <input type="text" 
                           id="hardwareScanner" 
                           class="form-control" 
                           placeholder="Scan barcode dengan alat scanner..." 
                           style="font-size: 18px; border: 2px solid #0C7779;"
                           autofocus>
                </div>
                <small class="form-text text-muted mt-2">
                    <i class="bi bi-info-circle"></i> Scanner akan otomatis mendeteksi ketika Anda scan barcode
                </small>
            </div>
        </div>

        <!-- Manual Input (Fallback) -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header" style="background: #6c757d; color: white;">
                <i class="bi bi-keyboard"></i> Input Manual
            </div>
            <div class="card-body">
                <div class="input-group">
                    <input type="text" id="manualBarcode" class="form-control" placeholder="Atau ketik nomor box secara manual...">
                    <button class="btn btn-primary" id="btnScanManual" style="background: #0C7779; border-color: #0C7779;">
                        <i class="bi bi-search"></i> Cari
                    </button>
                </div>
            </div>
        </div>

        <!-- Result Area -->
        <div id="resultArea" style="display: none;" class="mt-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-check-circle"></i> Data Box Terdeteksi ✓
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>No Box:</strong> <span id="resultBoxNumber" class="badge bg-info" style="font-size: 14px; padding: 8px 12px;"></span></p>
                            <p><strong>Part Number:</strong> <span id="resultPartNumber"></span></p>
                            <p><strong>Part Name:</strong> <span id="resultPartName"></span></p>
                            <p><strong>Jumlah PCS:</strong> <span id="resultPcsQty" class="badge bg-warning"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Tipe Box:</strong> <span id="resultTypeBox"></span></p>
                            <p><strong>WK Transfer:</strong> <span id="resultWkTransfer"></span></p>
                            <p><strong>Dibuat oleh:</strong> <span id="resultCreatedBy"></span></p>
                            <p><strong>Tanggal:</strong> <span id="resultCreatedAt"></span></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>LOT Information:</strong></p>
                            <p>
                                LOT01: <code id="resultLot01"></code> | 
                                LOT02: <code id="resultLot02"></code> | 
                                LOT03: <code id="resultLot03"></code>
                            </p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="#" id="btnViewDetail" class="btn btn-info">
                            <i class="bi bi-eye"></i> Lihat Detail Barcode
                        </a>
                        <button class="btn btn-secondary" onclick="resetScanner()">
                            <i class="bi bi-arrow-clockwise"></i> Scan Lagi
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <div id="errorArea" style="display: none;" class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Barcode Tidak Ditemukan!</strong>
            <p id="errorMessage" class="mb-0 mt-2"></p>
            <button type="button" class="btn-close" onclick="document.getElementById('errorArea').style.display='none';"></button>
        </div>

        <!-- Status -->
        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i>
            <strong>Status:</strong> <span id="statusText">Siap scan barcode dengan hardware scanner</span>
        </div>

        <!-- Scan History -->
        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header" style="background: #0C7779; color: white;">
                <i class="bi bi-clock-history"></i> Riwayat Scan Terakhir
            </div>
            <div class="card-body">
                <div id="scanHistory" class="list-group">
                    <p class="text-muted">Belum ada scan</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
let scanBuffer = '';
let scanTimeout;
let scanHistory = [];
const MAX_HISTORY = 10;

// Focus on hardware scanner input on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('hardwareScanner').focus();
    updateStatus("Siap scan barcode dengan hardware scanner");
});

// Hardware Scanner Input Handler
document.getElementById('hardwareScanner').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        let barcode = this.value.trim();
        if (barcode) {
            scanBarcode(barcode);
            this.value = ''; // Clear input
        }
        return false;
    }
});

// Manual Scan Button
document.getElementById('btnScanManual').addEventListener('click', function() {
    let barcode = document.getElementById('manualBarcode').value.trim();
    if (barcode) {
        scanBarcode(barcode);
    } else {
        showError("Masukkan nomor box terlebih dahulu");
    }
});

// Manual scan on Enter key
document.getElementById('manualBarcode').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('btnScanManual').click();
    }
});

// Scan Barcode Function
function scanBarcode(barcode) {
    updateStatus("Memproses barcode: <strong>" + barcode + "</strong>");
    document.getElementById('hardwareScanner').focus();
    
    $.ajax({
        url: "{{ route('barcode.scan') }}",
        method: 'POST',
        data: {
            _token: "{{ csrf_token() }}",
            barcode: barcode
        },
        success: function(response) {
            if (response.success) {
                displayResult(response.data);
                addToHistory(response.data);
            } else {
                showError(response.message);
            }
        },
        error: function(xhr) {
            let errorMsg = "Terjadi kesalahan saat memproses barcode";
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            showError(errorMsg);
        }
    });
}

// Display Result
function displayResult(data) {
    document.getElementById('resultBoxNumber').textContent = data.box_number;
    document.getElementById('resultPartNumber').textContent = data.part_number;
    document.getElementById('resultPartName').textContent = data.part_name || '-';
    document.getElementById('resultPcsQty').textContent = data.pcs_quantity + ' PCS';
    document.getElementById('resultTypeBox').textContent = data.type_box || '-';
    document.getElementById('resultWkTransfer').textContent = data.wk_transfer || '-';
    document.getElementById('resultCreatedBy').textContent = data.created_by;
    document.getElementById('resultCreatedAt').textContent = data.created_at;
    document.getElementById('resultLot01').textContent = data.lot01 || '-';
    document.getElementById('resultLot02').textContent = data.lot02 || '-';
    document.getElementById('resultLot03').textContent = data.lot03 || '-';
    
    document.getElementById('btnViewDetail').href = "/boxes/" + data.id;
    
    document.getElementById('errorArea').style.display = 'none';
    document.getElementById('resultArea').style.display = 'block';
    updateStatus("✓ Barcode berhasil di-scan: <strong>" + data.box_number + "</strong>");
}

// Add to History
function addToHistory(data) {
    let historyItem = {
        boxNumber: data.box_number,
        partNumber: data.part_number,
        time: new Date().toLocaleTimeString('id-ID')
    };
    
    scanHistory.unshift(historyItem);
    if (scanHistory.length > MAX_HISTORY) {
        scanHistory.pop();
    }
    
    updateHistory();
}

// Update History Display
function updateHistory() {
    let historyHtml = '';
    if (scanHistory.length === 0) {
        historyHtml = '<p class="text-muted">Belum ada scan</p>';
    } else {
        historyHtml = '<div class="list-group">';
        scanHistory.forEach(function(item, index) {
            historyHtml += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${item.boxNumber}</strong>
                            <br>
                            <small class="text-muted">${item.partNumber}</small>
                        </div>
                        <small class="text-muted">${item.time}</small>
                    </div>
                </div>
            `;
        });
        historyHtml += '</div>';
    }
    document.getElementById('scanHistory').innerHTML = historyHtml;
}

// Show Error
function showError(message) {
    document.getElementById('errorMessage').textContent = message;
    document.getElementById('errorArea').style.display = 'block';
    document.getElementById('resultArea').style.display = 'none';
    updateStatus("✗ Error: " + message);
    document.getElementById('hardwareScanner').focus();
}

// Update Status
function updateStatus(text) {
    document.getElementById('statusText').innerHTML = text;
}

// Reset Scanner
function resetScanner() {
    document.getElementById('resultArea').style.display = 'none';
    document.getElementById('errorArea').style.display = 'none';
    document.getElementById('manualBarcode').value = '';
    document.getElementById('hardwareScanner').value = '';
    document.getElementById('hardwareScanner').focus();
    updateStatus("Siap scan barcode dengan hardware scanner");
}

// Keep focus on hardware scanner
document.getElementById('hardwareScanner').addEventListener('blur', function() {
    // Auto-refocus if user hasn't clicked on manual input
    if (document.activeElement.id !== 'manualBarcode' && document.activeElement.id !== 'btnScanManual') {
        setTimeout(() => {
            document.getElementById('hardwareScanner').focus();
        }, 100);
    }
});
</script>

<style>
.list-group-item {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 12px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.list-group-item:hover {
    background: #f8f9fa;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#hardwareScanner:focus {
    outline: none;
    border-color: #0C7779 !important;
    box-shadow: 0 0 0 3px rgba(12, 119, 121, 0.25);
}
</style>
@endsection
