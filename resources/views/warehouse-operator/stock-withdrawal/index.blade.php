@extends('shared.layouts.app')

@section('title', 'Pengambilan Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2">
                    <i class="bi bi-box-seam"></i> Pengambilan Stok
                </h1>
                <p class="text-muted">Ambil stok barang dari warehouse sesuai kebutuhan</p>
            </div>
            <a href="{{ route('stock-withdrawal.history') }}" class="btn btn-lg" style="background-color: #249E94; color: white; border: none;">
                <i class="bi bi-clock-history"></i> Riwayat Pengambilan
            </a>
        </div>
    </div>
</div>

<!-- Main Form Card -->
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
            <div class="card-header text-white" style="background-color: #0C7779;">
                <i class="bi bi-search"></i> Form Pengambilan Stok
            </div>
            <div class="card-body">
                <form id="withdrawalForm">
                    @csrf

                    <!-- Part Number Search -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-search"></i> Nomor Part (SKU) *
                        </label>
                        <input type="text" class="form-control" id="partSearch" 
                               placeholder="Cari atau pilih nomor part..." autocomplete="off" required>
                        <div id="partDropdown" class="dropdown-list border bg-white rounded mt-1" 
                             style="display: none; max-height: 250px; overflow-y: auto; position: relative; border-left: 4px solid #0C7779;">
                            <!-- Options akan di-populate via JS -->
                        </div>
                        <small class="text-muted d-block mt-2">Ketik untuk mencari, atau klik option di bawah</small>
                        <input type="hidden" id="selectedPart" name="part_number">
                    </div>

                    <!-- Stock Info Display -->
                    <div id="stockInfoContainer" style="display: none;">
                        <div class="alert border-0 mb-4" style="background-color: #e0f5f3; color: #0C7779; border-left: 4px solid #0C7779;">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Total Stok Tersedia:</strong> 
                            <span id="totalStockDisplay" class="fw-bold">0</span> PCS
                        </div>
                        <small class="text-muted d-block mb-3">Komposisi box setiap palet akan ditampilkan pada preview lokasi</small>
                    </div>

                    <!-- Quantity Input -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-stack"></i> Jumlah PCS yang Diambil *
                        </label>
                        <input type="number" class="form-control" id="pcsQuantity" name="pcs_quantity" 
                               placeholder="Masukkan jumlah PCS" min="1" required disabled>
                        <small class="text-muted d-block mt-2">Jumlah yang akan diambil dari warehouse</small>
                    </div>

                    <!-- Notes -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-pencil"></i> Keterangan (Optional)
                        </label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Tambahkan keterangan jika diperlukan"></textarea>
                    </div>

                    <!-- Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                        <button type="button" id="previewBtn" class="btn btn-lg" 
                                style="background-color: #249E94; color: white; border: none;" disabled>
                            <i class="bi bi-eye"></i> Lihat Lokasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #0C7779; color: white; border: none;">
                <h5 class="modal-title">
                    <i class="bi bi-map-fill"></i> Lokasi Pengambilan (FIFO)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                    <!-- Locations akan ditampilkan di sini -->
                </div>

                <!-- Error Alert -->
                <div id="previewError" class="alert alert-danger border-0" style="display: none;">
                    <i class="bi bi-exclamation-circle"></i>
                    <span id="errorMessage"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Batal
                </button>
                <button type="button" id="confirmBtn" class="btn btn-lg" style="background-color: #0C7779; color: white; border: none;">
                    <i class="bi bi-check-circle"></i> Lanjutkan Pengambilan
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .dropdown-list {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }
    
    .dropdown-list .dropdown-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.15s;
        font-size: 14px;
    }
    
    .dropdown-list .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .dropdown-list .dropdown-item:hover,
    .dropdown-list .dropdown-item.active {
        background-color: #e0f5f3;
        color: #0C7779;
        font-weight: 500;
    }

    .location-card {
        background-color: #f5f7fa;
        border-left: 4px solid #249E94;
        padding: 15px;
        margin-bottom: 12px;
        border-radius: 4px;
    }

    .location-order {
        display: inline-block;
        background-color: #0C7779;
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        text-align: center;
        line-height: 30px;
        font-weight: bold;
        margin-right: 10px;
    }

    .location-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 10px;
        font-size: 14px;
    }

    .location-info-row {
        padding: 5px 0;
        border-bottom: 1px solid #e0e0e0;
    }

    .location-info-row:last-child {
        border-bottom: none;
    }

    .location-label {
        color: #9ca3af;
        font-weight: 500;
    }

    .location-value {
        color: #1f2937;
        font-weight: 600;
    }

    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #0C7779;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

@endsection

@section('scripts')
<script>
    let selectedPart = null;
    let totalStock = 0;

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        const partSearch = document.getElementById('partSearch');
        const partDropdown = document.getElementById('partDropdown');
        const selectedPartInput = document.getElementById('selectedPart');
        const pcsQuantity = document.getElementById('pcsQuantity');
        const previewBtn = document.getElementById('previewBtn');
        const confirmBtn = document.getElementById('confirmBtn');

        // Search parts
        partSearch.addEventListener('input', function(e) {
            const query = e.target.value.trim();
            
            if (query.length < 1) {
                partDropdown.style.display = 'none';
                return;
            }

            fetch('{{ route("stock-withdrawal.search") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ q: query })
            })
            .then(r => r.json())
            .then(data => {
                if (data.results.length === 0) {
                    partDropdown.innerHTML = '<div class="dropdown-item">Tidak ada part yang ditemukan</div>';
                    partDropdown.style.display = 'block';
                    return;
                }

                partDropdown.innerHTML = data.results.map(item => 
                    `<div class="dropdown-item" data-part="${item.part_number}" data-stock="${item.total_stock}">
                        <strong>${item.part_number}</strong> 
                        <span class="text-muted" style="font-size: 12px;">(${item.total_stock} PCS)</span>
                    </div>`
                ).join('');

                partDropdown.style.display = 'block';

                // Click handlers
                partDropdown.querySelectorAll('.dropdown-item').forEach(item => {
                    item.addEventListener('click', function() {
                        selectedPart = this.dataset.part;
                        totalStock = parseInt(this.dataset.stock);
                        partSearch.value = selectedPart;
                        selectedPartInput.value = selectedPart;
                        partDropdown.style.display = 'none';

                        // Show stock info
                        document.getElementById('stockInfoContainer').style.display = 'block';
                        document.getElementById('totalStockDisplay').textContent = totalStock.toLocaleString('id-ID');

                        // Enable quantity input
                        pcsQuantity.disabled = false;
                        pcsQuantity.focus();
                    });
                });
            });
        });

        // Hide dropdown on click outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#partSearch') && !e.target.closest('#partDropdown')) {
                partDropdown.style.display = 'none';
            }
        });

        // Validate quantity on input
        pcsQuantity.addEventListener('input', function() {
            const qty = parseInt(this.value) || 0;
            previewBtn.disabled = qty < 1;
        });

        // Preview button
        previewBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (!selectedPart) {
                alert('Silakan pilih part terlebih dahulu');
                return;
            }

            const qty = parseInt(pcsQuantity.value) || 0;
            if (qty < 1) {
                alert('Silakan masukkan jumlah PCS');
                return;
            }

            showPreview(selectedPart, qty);
        });

        // Confirm button
        confirmBtn.addEventListener('click', function() {
            const qty = parseInt(pcsQuantity.value) || 0;
            const notes = document.getElementById('notes').value;

            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="loading"></span> Memproses...';

            fetch('{{ route("stock-withdrawal.confirm") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    part_number: selectedPart,
                    pcs_quantity: qty,
                    notes: notes
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    document.getElementById('withdrawalForm').reset();
                    document.getElementById('stockInfoContainer').style.display = 'none';
                    pcsQuantity.disabled = true;
                    previewBtn.disabled = true;
                    selectedPart = null;
                    const modal = bootstrap.Modal.getInstance(document.getElementById('previewModal'));
                    modal.hide();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(e => {
                console.error('Error:', e);
                alert('Terjadi kesalahan');
            })
            .finally(() => {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-check-circle"></i> Lanjutkan Pengambilan';
            });
        });
    });

    function showPreview(partNumber, qty) {
        const previewContent = document.getElementById('previewContent');
        const previewError = document.getElementById('previewError');
        const errorMessage = document.getElementById('errorMessage');
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));

        previewContent.innerHTML = '<div class="text-center"><span class="loading"></span> Memproses...</div>';
        previewError.style.display = 'none';

        fetch('{{ route("stock-withdrawal.preview") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                part_number: partNumber,
                pcs_quantity: qty
            })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                previewContent.style.display = 'none';
                previewError.style.display = 'block';
                errorMessage.textContent = data.message;
                modal.show();
                return;
            }

            previewError.style.display = 'none';
            previewContent.style.display = 'block';

            let html = `
                <div class="mb-4">
                    <h6 style="color: #0C7779;"><i class="bi bi-info-circle"></i> Ringkasan Pengambilan</h6>
                    <p class="mb-2">
                        <strong>Part Number:</strong> ${data.part_number}
                    </p>
                    <p class="mb-2">
                        <strong>Jumlah Diminta:</strong> ${data.requested_qty} PCS
                    </p>
                    <p class="mb-0">
                        <strong>Total Tersedia:</strong> ${data.total_available} PCS
                    </p>
                </div>

                <hr>

                <h6 style="color: #0C7779; margin-top: 20px;"><i class="bi bi-map-fill"></i> Lokasi Pengambilan (FIFO)</h6>
                <p class="text-muted small mb-3">Lokasi akan diambil sesuai urutan dibawah ini</p>
            `;

            data.locations.forEach(loc => {
                html += `
                    <div class="location-card">
                        <div style="display: flex; align-items: center;">
                            <div class="location-order">${loc.order}</div>
                            <div style="flex-grow: 1;">
                                <strong style="color: #0C7779;">${loc.warehouse_location}</strong>
                                <br/>
                                <small class="text-muted">Pallet: ${loc.pallet_number}</small>
                            </div>
                        </div>
                        <div class="location-info">
                            <div>
                                <div class="location-info-row">
                                    <span class="location-label">Tanggal Penyimpanan:</span>
                                    <br/>
                                    <span class="location-value">${loc.stored_date}</span>
                                </div>
                                <div class="location-info-row">
                                    <span class="location-label">Komposisi:</span>
                                    <br/>
                                    <span class="location-value">${parseFloat(loc.pcs_per_box).toLocaleString('id-ID', { maximumFractionDigits: 2 })} PCS/Box</span>
                                </div>
                                <div class="location-info-row">
                                    <span class="location-label">Stok Tersedia:</span>
                                    <br/>
                                    <span class="location-value">${loc.available_pcs} PCS (${parseFloat(loc.available_box).toLocaleString('id-ID', { maximumFractionDigits: 2 })} Box)</span>
                                </div>
                            </div>
                            <div>
                                <div class="location-info-row">
                                    <span class="location-label">Akan Diambil:</span>
                                    <br/>
                                    <span class="location-value" style="color: #249E94;">${loc.will_take_pcs} PCS</span>
                                </div>
                                <div class="location-info-row">
                                    <span class="location-label">Box Akan Dikurangi:</span>
                                    <br/>
                                    <span class="location-value" style="color: #249E94;">${Math.floor(parseFloat(loc.will_take_box))} Box</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            previewContent.innerHTML = html;
            modal.show();
        })
        .catch(e => {
            console.error('Error:', e);
            previewContent.style.display = 'none';
            previewError.style.display = 'block';
            errorMessage.textContent = 'Terjadi kesalahan saat memproses permintaan';
            modal.show();
        });
    }
</script>
@endsection
