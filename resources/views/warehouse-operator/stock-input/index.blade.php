@extends('shared.layouts.app')

@section('title', 'Input Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h2">
            <i class="bi bi-plus-circle"></i> Input Stok Penyimpanan
        </h1>
        <p class="text-muted">Scan barcode pallet dan tentukan lokasi penyimpanan</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <!-- Step 1: Scan Barcode -->
                <div class="mb-4">
                    <label class="form-label fw-bold">
                        <i class="bi bi-qr-code"></i> Nomor Pallet (Scan Barcode)
                    </label>
                    <div class="position-relative">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control" id="pallet_number" 
                                   placeholder="Scan barcode pallet atau ketik nomor..." autofocus>
                            <button class="btn btn-outline-secondary" type="button" id="scan_btn">
                                <i class="bi bi-search"></i> Cari
                            </button>
                        </div>
                        <!-- Dropdown Search Results -->
                        <div id="palletDropdown" class="dropdown-menu w-100 p-0" style="display: none; max-height: 300px; overflow-y: auto; position: absolute; top: 100%; left: 0; right: 0; z-index: 1000;">
                            <div id="dropdownResults"></div>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div id="loading" class="alert alert-info" style="display: none;">
                    <i class="bi bi-hourglass-split"></i> Mencari data pallet...
                </div>

                <!-- Pallet Data (Hidden by default) -->
                <form id="form-stock-input" style="display: none;">
                    @csrf
                    
                    <!-- Pallet Details -->
                    <div class="card mb-4 bg-light">
                        <div class="card-header" style="background: #0C7779; color: white;">
                            <i class="bi bi-box2"></i> Detail Pallet
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label text-muted small">No Pallet</label>
                                        <p class="fs-5 fw-bold" id="display_pallet_number">-</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Items dalam Pallet -->
                            <div class="mt-3 pt-3 border-top">
                                <label class="form-label text-muted small d-block mb-2">Items dalam Pallet:</label>
                                <div id="itemsList" class="d-grid gap-2">
                                    <!-- Items akan tampil di sini -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Pallet ID -->
                    <input type="hidden" name="pallet_id" id="pallet_id">

                    <!-- Lokasi Input -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-pin-map"></i> Lokasi Penyimpanan
                        </label>
                        <input type="text" class="form-control form-control-lg" name="warehouse_location" 
                               id="warehouse_location" placeholder="Contoh: A-1-1 (Rak A, Baris 1, Posisi 1)"
                               required>
                        <small class="text-muted d-block mt-2">
                            Format lokasi: [RAK]-[BARIS]-[POSISI]<br>
                            Contoh: A-1-1, B-2-3, C-3-5, dll
                        </small>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary" id="reset_btn">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-lg" style="background: #0C7779; color: white; border: none;">
                            <i class="bi bi-check-circle"></i> Simpan Stok
                        </button>
                    </div>
                </form>

                <!-- Error Message -->
                <div id="error-message" class="alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i> <span id="error-text"></span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const palletInput = document.getElementById('pallet_number');
    const palletDropdown = document.getElementById('palletDropdown');
    const dropdownResults = document.getElementById('dropdownResults');
    let allPallets = [];

    // Display dropdown with filtered results
    function displayDropdown(items = null) {
        const itemsToDisplay = items || allPallets;
        
        if (itemsToDisplay.length === 0) {
            dropdownResults.innerHTML = '<div class="p-3 text-muted text-center">Tidak ada pallet tersedia</div>';
        } else {
            dropdownResults.innerHTML = itemsToDisplay.map(pallet => `
                <a href="#" class="dropdown-item p-3 border-bottom" onclick="selectPallet('${pallet.pallet_number}'); return false;">
                    <div class="fw-bold">${pallet.pallet_number}</div>
                    <small class="text-muted">${pallet.items_count} item</small>
                </a>
            `).join('');
        }
        
        palletDropdown.style.display = 'block';
    }

    // Load all pallets on page load
    function loadAllPallets() {
        fetch('{{ route("stock-input.get-pallets") }}', {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            allPallets = data.pallets || [];
            console.log('Pallets loaded:', allPallets);
        })
        .catch(error => console.error('Error loading pallets:', error));
    }

    // Show dropdown saat input di-fokus (fokus pertama kali)
    palletInput.addEventListener('focus', function() {
        if (allPallets.length > 0) {
            displayDropdown();
        }
    });

    // Filter and display dropdown results
    palletInput.addEventListener('input', function() {
        const query = this.value.trim().toLowerCase();
        
        if (query.length === 0) {
            // Tampilkan semua pallet jika input kosong
            if (allPallets.length > 0) {
                displayDropdown();
            }
            return;
        }

        const filtered = allPallets.filter(pallet => 
            pallet.pallet_number.toLowerCase().includes(query)
        );

        displayDropdown(filtered);
    });

    function selectPallet(palletNumber) {
        palletInput.value = palletNumber;
        palletDropdown.style.display = 'none';
        searchPallet();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== palletInput && !palletDropdown.contains(e.target)) {
            palletDropdown.style.display = 'none';
        }
    });

    document.getElementById('scan_btn').addEventListener('click', function() {
        searchPallet();
    });

    document.getElementById('pallet_number').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchPallet();
            palletDropdown.style.display = 'none';
        }
    });

    // Load pallets on page ready
    document.addEventListener('DOMContentLoaded', function() {
        loadAllPallets();
    });

    function searchPallet() {
        const pallet_number = document.getElementById('pallet_number').value.trim();
        
        if (!pallet_number) {
            showError('Masukkan nomor pallet terlebih dahulu');
            return;
        }

        document.getElementById('loading').style.display = 'block';
        document.getElementById('error-message').style.display = 'none';

        fetch('{{ route("stock-input.search") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ pallet_number: pallet_number })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loading').style.display = 'none';
            
            if (data.success) {
                displayPalletData(data.pallet);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            document.getElementById('loading').style.display = 'none';
            showError('Terjadi kesalahan: ' + error);
        });
    }

    function displayPalletData(pallet) {
        document.getElementById('pallet_id').value = pallet.id;
        document.getElementById('display_pallet_number').textContent = pallet.pallet_number;
        
        // Display items list
        const itemsList = document.getElementById('itemsList');
        itemsList.innerHTML = '';
        
        if (pallet.items && pallet.items.length > 0) {
            pallet.items.forEach((item, index) => {
                const itemRow = document.createElement('div');
                itemRow.className = 'row mb-2 p-2 bg-light rounded';
                itemRow.innerHTML = `
                    <div class="col-md-3">
                        <small class="text-muted">No Part</small>
                        <div class="fw-bold">${item.part_number}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Jumlah Box</small>
                        <div class="fw-bold">${Math.ceil(item.pcs_quantity / item.box_quantity || item.box_quantity)}</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Jumlah PCS</small>
                        <div class="fw-bold">${item.pcs_quantity}</div>
                    </div>
                `;
                itemsList.appendChild(itemRow);
            });
        } else {
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'alert alert-warning';
            emptyMsg.textContent = 'Tidak ada item dalam pallet ini';
            itemsList.appendChild(emptyMsg);
        }
        
        document.getElementById('warehouse_location').value = '';
        document.getElementById('form-stock-input').style.display = 'block';
        document.getElementById('warehouse_location').focus();
    }

    function showError(message) {
        document.getElementById('error-message').style.display = 'block';
        document.getElementById('error-text').textContent = message;
        document.getElementById('form-stock-input').style.display = 'none';
    }

    document.getElementById('reset_btn').addEventListener('click', function() {
        document.getElementById('pallet_number').value = '';
        document.getElementById('form-stock-input').style.display = 'none';
        document.getElementById('error-message').style.display = 'none';
        document.getElementById('pallet_number').focus();
    });

    document.getElementById('form-stock-input').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = new FormData(this);
        
        fetch('{{ route("stock-input.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: form
        })
        .then(response => response.text())
        .then(html => {
            // Redirect on success
            window.location.href = '{{ route("stock-input.index") }}';
        })
        .catch(error => {
            showError('Terjadi kesalahan saat menyimpan: ' + error);
        });
    });
</script>
@endsection
