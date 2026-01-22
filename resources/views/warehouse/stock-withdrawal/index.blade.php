@extends('shared.layouts.app')

@section('title', 'Pengambilan Stok - Warehouse FG Yamato')

@section('content')
<div class="mb-5 pb-3" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); border-radius: 12px; padding: 40px 30px; box-shadow: 0 4px 20px rgba(12, 119, 121, 0.15);">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="h2 fw-bold text-white mb-2">
                <i class="bi bi-bag-check"></i> Pengambilan Stok
            </h1>
            <p class="text-white-50 mb-0">Kelola dan proses pengambilan stok barang dengan mudah</p>
        </div>
        <a href="{{ route('stock-withdrawal.history') }}" class="btn btn-light btn-lg" style="border-radius: 8px; padding: 12px 28px;">
            <i class="bi bi-clock-history"></i> <span class="ms-2">Riwayat</span>
        </a>
    </div>
</div>

<div class="row g-4 mt-4">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Search Bar -->
        <div class="mb-4">
            <div class="position-relative">
                <div class="input-group" style="border-radius: 10px; overflow: hidden; box-shadow: 0 2px 12px rgba(12, 119, 121, 0.1);">
                    <span class="input-group-text border-0 bg-white" style="color: #0C7779; font-size: 1.2rem;">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="searchInput" class="form-control form-control-lg border-0" 
                           placeholder="Cari berdasarkan No Part..." 
                           autocomplete="off"
                           style="font-size: 1rem; padding: 14px 16px;">
                    <button class="btn btn-outline-secondary border-0 bg-white" type="button" id="resetBtn" style="color: #6b7280;">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
                <!-- Dropdown Suggestions -->
                <div id="searchDropdown" class="search-dropdown dropdown-list border-0 bg-white rounded-3 mt-2" 
                     style="display: none; max-height: 300px; overflow-y: auto; position: absolute; width: 100%; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                    <!-- Suggestions akan di-populate via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Stok List by Part Number -->
        <div id="partsList">
            <!-- Part cards akan di-populate via JavaScript -->
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="card shadow-sm border-0" style="border-radius: 12px;">
            <div class="card-body p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #e5e7eb;"></i>
                <h5 class="mt-3 text-muted">Memuat data stok...</h5>
            </div>
        </div>
    </div>

    <!-- Cart Sidebar -->
    <div class="col-lg-4">
        <div class="card shadow border-0" style="position: sticky; top: 20px; border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb;">
            <!-- Cart Header -->
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 20px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold">
                        <i class="bi bi-bag-check"></i> Keranjang Pengambilan
                    </h6>
                    <span class="badge bg-light text-dark fw-bold" id="cartCount" style="font-size: 0.9rem; padding: 6px 12px;">0</span>
                </div>
                <small class="text-white-50">Daftar item yang akan diambil</small>
            </div>

            <!-- Cart Items -->
            <div class="card-body" style="max-height: 500px; overflow-y: auto; padding: 0;">
                <div id="cartItems">
                    <div class="text-center text-muted py-5" style="background: #f9fafb;">
                        <i class="bi bi-bag-x" style="font-size: 2.5rem; color: #d1d5db; display: block; margin-bottom: 12px;"></i>
                        <p style="font-size: 0.95rem; margin: 0;">Keranjang kosong</p>
                        <small class="text-secondary">Pilih item untuk memulai</small>
                    </div>
                </div>
            </div>

            <!-- Cart Footer -->
            <div class="card-footer bg-white border-top" style="border-top: 1px solid #e5e7eb !important; padding: 20px;">
                <!-- Summary -->
                <div class="mb-4 pb-3" style="border-bottom: 1px solid #e5e7eb;">
                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                        <span class="text-muted small">Total Item</span>
                        <span class="h6 mb-0" id="cartCount2" style="color: #0C7779;">0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-baseline">
                        <span class="text-muted small fw-semibold">Total PCS</span>
                        <h5 id="totalPcsCart" class="fw-bold mb-0" style="color: #0C7779; font-size: 1.5rem;">0</h5>
                    </div>
                </div>

                <!-- Actions -->
                <div class="d-grid gap-2">
                    <button type="button" id="submitBtn" class="btn btn-lg fw-semibold" 
                            style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; border: none; border-radius: 8px; padding: 12px; font-size: 1rem; transition: all 0.3s ease;" 
                            disabled>
                        <i class="bi bi-check-circle"></i> <span class="ms-2">Proses Pengambilan</span>
                    </button>
                    <button type="button" id="clearCartBtn" class="btn btn-outline-danger btn-sm fw-semibold" style="border-radius: 8px;">
                        <i class="bi bi-trash"></i> Kosongkan Keranjang
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: #0C7779; color: white; border: none;">
                <h5 class="modal-title">
                    <i class="bi bi-list-check"></i> Detail Lokasi Pengambilan Stok
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                <div id="previewModalContent">
                    <!-- Content akan di-populate via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Batalkan
                </button>
                <button type="button" id="confirmWithdrawalBtn" class="btn btn-lg" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-check2-circle"></i> Konfirmasi Pengambilan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal untuk input quantity -->
<div class="modal fade" id="quantityModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.2);">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 24px; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-stack"></i> Tentukan Jumlah Pengambilan
                </h5>
            </div>
            <div class="modal-body" style="padding: 28px;">
                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small">Part Number</label>
                    <p id="modalPartNumber" class="form-control-plaintext fw-bold" style="color: #0C7779; font-size: 1.2rem; margin: 0;"></p>
                </div>
                <div class="mb-4 p-3" style="background: #f0faf9; border-left: 4px solid #0C7779; border-radius: 6px;">
                    <label class="form-label text-muted small mb-1">Stok Tersedia</label>
                    <h6 id="modalAvailableStock" class="fw-bold mb-0" style="color: #0C7779; font-size: 1.3rem;">0 PCS</h6>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-bold">Jumlah Pengambilan *</label>
                </div>
                <div class="d-flex gap-3 align-items-center mb-2">
                    <button type="button" class="btn" id="minusBtn" style="background: #f3f4f6; border: none; border-radius: 8px; width: 50px; height: 50px; font-size: 1.3rem; color: #0C7779; font-weight: bold;">−</button>
                    <input type="number" id="quantityInput" class="form-control form-control-lg text-center fw-bold" value="1" min="1" style="border-radius: 8px; font-size: 1.3rem;">
                    <button type="button" class="btn" id="plusBtn" style="background: #f3f4f6; border: none; border-radius: 8px; width: 50px; height: 50px; font-size: 1.3rem; color: #0C7779; font-weight: bold;">+</button>
                </div>
                <small class="text-muted d-block text-center">Gunakan tombol untuk mengubah jumlah</small>
            </div>
            <div class="modal-footer border-top" style="padding: 20px; gap: 12px;">
                <button type="button" class="btn btn-outline-secondary fw-semibold" style="border-radius: 8px;" data-bs-dismiss="modal">Batalkan</button>
                <button type="button" id="addToCartBtn" class="btn fw-semibold" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; border: none; border-radius: 8px;">
                    <i class="bi bi-plus-circle"></i> <span class="ms-2">Tambah ke Keranjang</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    :root {
        --primary-color: #0C7779;
        --secondary-color: #249E94;
        --success-color: #10b981;
        --light-bg: #f9fafb;
        --border-color: #e5e7eb;
    }

    body {
        background: #f5f7fa;
    }

    .search-dropdown {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-radius: 10px !important;
    }
    
    .search-dropdown .dropdown-item {
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid var(--border-color);
        transition: all 0.2s ease;
        font-size: 14px;
        color: #374151;
    }
    
    .search-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .search-dropdown .dropdown-item:hover,
    .search-dropdown .dropdown-item.active {
        background-color: #e0f5f3;
        color: var(--primary-color);
        padding-left: 20px;
    }

    .cart-item {
        border: none;
        background: white;
        border-bottom: 1px solid var(--border-color);
        padding: 14px 16px;
        margin: 0;
        border-radius: 0;
        transition: all 0.2s ease;
    }

    .cart-item:hover {
        background: #f9fafb;
    }

    .cart-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .cart-item-qty {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    /* Modal styling */
    .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }

    /* Card hover effects */
    .card {
        transition: all 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.1) !important;
    }

    /* Button hover effects */
    .btn:not(:disabled):hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(12, 119, 121, 0.2);
    }

    /* Input focus */
    .form-control:focus,
    .form-control.form-control-lg:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(12, 119, 121, 0.1);
    }

    /* Scrollbar styling */
    #cartItems::-webkit-scrollbar {
        width: 6px;
    }

    #cartItems::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    #cartItems::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 3px;
    }

    #cartItems::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }

    /* Badge animation */
    .badge {
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: scale(0.8);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .mb-5 {
            margin-bottom: 2rem !important;
        }

        .row.g-4 {
            gap: 1rem;
        }
    }
</style>

@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Data stok dari server
    let allParts = [];
    let cart = {};
    const modalQuantity = new bootstrap.Modal(document.getElementById('quantityModal'));
    let currentModalPart = null;
    let currentModalAvailable = 0;

    // Load stok data
    function loadStockData() {
        $.ajax({
            url: '/api/stock/by-part',
            method: 'GET',
            success: function(response) {
                allParts = response;
                displayParts(allParts);
                setupSearch();
                updateCartDisplay();
            },
            error: function() {
                document.getElementById('partsList').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> Gagal memuat data stok
                    </div>
                `;
            }
        });
    }

    // Display parts
    function displayParts(parts) {
        const partsList = document.getElementById('partsList');
        const emptyState = document.getElementById('emptyState');

        if (parts.length === 0) {
            emptyState.style.display = 'block';
            partsList.innerHTML = '';
            return;
        }

        emptyState.style.display = 'none';
        partsList.innerHTML = parts.map(partData => `
            <div class="card shadow-sm border-0 mb-3" style="border-radius: 12px; overflow: hidden; border: 1px solid #e5e7eb; transition: all 0.3s ease;">
                <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 16px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div style="flex: 1;">
                            <h6 class="mb-1 fw-bold" style="font-size: 1.1rem;">
                                <i class="bi bi-box"></i> ${partData.part_number}
                            </h6>
                            <small class="text-white-50">Stok tersedia di warehouse</small>
                        </div>
                        <div class="text-end" style="min-width: 120px;">
                            <div style="font-size: 0.8rem; text-white-50; margin-bottom: 4px;">Total Stok</div>
                            <div class="fw-bold" style="font-size: 1.5rem;">${partData.total_pcs}</div>
                            <small style="font-size: 0.75rem;">PCS</small>
                        </div>
                    </div>
                </div>
                <div style="padding: 16px;">
                    <button type="button" class="btn w-100 fw-semibold" 
                            style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 8px; padding: 10px; transition: all 0.3s ease;"
                            onmouseover="this.style.boxShadow='0 4px 12px rgba(16, 185, 129, 0.3)'"
                            onmouseout="this.style.boxShadow='none'"
                            onclick="openQuantityModal('${partData.part_number}', ${partData.total_pcs})">
                        <i class="bi bi-plus-circle"></i> <span class="ms-2">Tambah ke Keranjang</span>
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Setup search
    function setupSearch() {
        const searchInput = document.getElementById('searchInput');
        const searchDropdown = document.getElementById('searchDropdown');
        const resetBtn = document.getElementById('resetBtn');

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            if (query.length === 0) {
                searchDropdown.style.display = 'none';
                displayParts(allParts);
                return;
            }

            const filtered = allParts.filter(p => p.part_number.toLowerCase().includes(query));
            displayParts(filtered);

            // Show dropdown with suggestions
            const suggestions = allParts.filter(p => p.part_number.toLowerCase().includes(query)).slice(0, 10);
            if (suggestions.length > 0) {
                searchDropdown.innerHTML = suggestions.map(p => `
                    <div class="dropdown-item" onclick="selectPart('${p.part_number}')">
                        <strong>${p.part_number}</strong> - ${p.total_pcs} PCS
                    </div>
                `).join('');
                searchDropdown.style.display = 'block';
            } else {
                searchDropdown.style.display = 'none';
            }
        });

        resetBtn.addEventListener('click', function() {
            searchInput.value = '';
            searchDropdown.style.display = 'none';
            displayParts(allParts);
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#searchInput') && !e.target.closest('#searchDropdown')) {
                searchDropdown.style.display = 'none';
            }
        });
    }

    function selectPart(partNumber) {
        document.getElementById('searchInput').value = partNumber;
        document.getElementById('searchDropdown').style.display = 'none';
        const filtered = allParts.filter(p => p.part_number === partNumber);
        displayParts(filtered);
    }

    // Modal quantity
    function openQuantityModal(partNumber, availableStock) {
        currentModalPart = partNumber;
        currentModalAvailable = availableStock;
        document.getElementById('modalPartNumber').textContent = partNumber;
        document.getElementById('modalAvailableStock').textContent = availableStock + ' PCS';
        document.getElementById('quantityInput').value = 1;
        document.getElementById('quantityInput').max = availableStock;
        modalQuantity.show();
    }

    document.getElementById('minusBtn').addEventListener('click', function() {
        const input = document.getElementById('quantityInput');
        if (parseInt(input.value) > 1) {
            input.value = parseInt(input.value) - 1;
        }
    });

    document.getElementById('plusBtn').addEventListener('click', function() {
        const input = document.getElementById('quantityInput');
        if (parseInt(input.value) < currentModalAvailable) {
            input.value = parseInt(input.value) + 1;
        }
    });

    document.getElementById('addToCartBtn').addEventListener('click', function() {
        const qty = parseInt(document.getElementById('quantityInput').value);
        if (qty > 0 && qty <= currentModalAvailable) {
            addToCart(currentModalPart, qty);
            modalQuantity.hide();
        }
    });

    // Cart management
    function addToCart(partNumber, quantity) {
        if (!cart[partNumber]) {
            cart[partNumber] = 0;
        }
        cart[partNumber] += quantity;
        updateCartDisplay();
    }

    function removeFromCart(partNumber) {
        delete cart[partNumber];
        updateCartDisplay();
    }

    function updateQuantityInCart(partNumber, newQuantity) {
        if (newQuantity <= 0) {
            removeFromCart(partNumber);
        } else {
            cart[partNumber] = newQuantity;
        }
        updateCartDisplay();
    }

    function updateCartDisplay() {
        const cartItems = document.getElementById('cartItems');
        const cartCount = Object.keys(cart).length;
        const totalPcs = Object.values(cart).reduce((a, b) => a + b, 0);

        document.getElementById('cartCount').textContent = cartCount;
        document.getElementById('cartCount2').textContent = cartCount;
        document.getElementById('totalPcsCart').textContent = totalPcs;

        if (cartCount === 0) {
            cartItems.innerHTML = `
                <div class="text-center text-muted py-5" style="background: #f9fafb;">
                    <i class="bi bi-bag-x" style="font-size: 2.5rem; color: #d1d5db; display: block; margin-bottom: 12px;"></i>
                    <p style="font-size: 0.95rem; margin: 0;">Keranjang kosong</p>
                    <small class="text-secondary">Pilih item untuk memulai</small>
                </div>
            `;
            document.getElementById('submitBtn').disabled = true;
        } else {
            cartItems.innerHTML = Object.entries(cart).map(([part, qty]) => {
                const partInfo = allParts.find(p => p.part_number === part);
                return `
                    <div class="cart-item" style="border: none; background: white; border-bottom: 1px solid #e5e7eb; padding: 14px 16px; margin: 0; border-radius: 0; transition: all 0.2s ease;">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div style="flex: 1;">
                                <div class="fw-bold" style="color: #0C7779; font-size: 0.95rem;">${part}</div>
                                <small class="text-muted">${qty} PCS</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-link text-danger p-0" style="text-decoration: none;" onclick="removeFromCart('${part}')" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="btn btn-sm" style="background: #f3f4f6; border: none; border-radius: 6px; width: 32px; height: 32px; padding: 0; color: #0C7779;" onclick="updateQuantityInCart('${part}', ${qty - 1})">−</button>
                            <span style="width: 40px; text-align: center; font-weight: bold; color: #0C7779;">${qty}</span>
                            <button type="button" class="btn btn-sm" style="background: #f3f4f6; border: none; border-radius: 6px; width: 32px; height: 32px; padding: 0; color: #0C7779;" onclick="updateQuantityInCart('${part}', ${qty + 1})">+</button>
                        </div>
                    </div>
                `;
            }).join('');
            document.getElementById('submitBtn').disabled = false;
        }
    }

    document.getElementById('clearCartBtn').addEventListener('click', function() {
        if (confirm('Hapus semua item dari keranjang?')) {
            cart = {};
            updateCartDisplay();
        }
    });

    document.getElementById('submitBtn').addEventListener('click', function() {
        // Ambil items dari cart
        const items = Object.entries(cart).map(([partNumber, quantity]) => ({
            part_number: partNumber,
            pcs_quantity: quantity
        }));

        // Fetch preview data sebelum submit
        $.ajax({
            url: '{{ route("stock-withdrawal.preview-cart") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                items: items
            },
            success: function(response) {
                if (response.success) {
                    // Tampilkan preview modal dengan detail lokasi
                    showPreviewModal(response.preview);
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Terjadi kesalahan saat memuat preview';
                alert('Error: ' + message);
            }
        });
    });

    function showPreviewModal(previewData) {
        const modalContent = document.getElementById('previewModalContent');
        
        let totalItems = previewData.length;
        let totalPcsToWithdraw = previewData.reduce((sum, item) => sum + parseInt(item.requested_qty), 0);

        let html = `
            <div class="alert alert-info mb-3">
                <strong><i class="bi bi-info-circle"></i> Ringkasan Pengambilan</strong>
                <p class="mb-0">Total ${totalItems} part | Total ${totalPcsToWithdraw} PCS</p>
            </div>
        `;

        previewData.forEach((partData, index) => {
            html += `
                <div class="card mb-3 border-start border-5" style="border-color: #0C7779 !important;">
                    <div class="card-header" style="background: #f8f9fa; padding: 12px;">
                        <h6 class="mb-1" style="color: #0C7779;">
                            <i class="bi bi-tag"></i> Part #${index + 1}: ${partData.part_number}
                        </h6>
                        <small class="text-muted">
                            <strong>Akan diambil:</strong> ${partData.requested_qty} PCS 
                            <span style="margin-left: 15px;"><strong>Total tersedia:</strong> ${partData.total_available} PCS</span>
                        </small>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead style="background: #f8f9fa; border-top: none;">
                                <tr>
                                    <th style="border-top: none; color: #0C7779; font-weight: 600; padding: 12px 15px;">No Palet</th>
                                    <th style="border-top: none; color: #0C7779; font-weight: 600; padding: 12px 15px;">Lokasi Warehouse</th>
                                    <th style="border-top: none; color: #0C7779; font-weight: 600; padding: 12px 15px; text-align: right;">PCS Tersedia</th>
                                    <th style="border-top: none; color: #0C7779; font-weight: 600; padding: 12px 15px; text-align: right;">PCS Diambil</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            partData.locations.forEach((location, idx) => {
                html += `
                    <tr style="border-bottom: 1px solid #e9ecef;">
                        <td style="padding: 12px 15px; color: #1f2937;">
                            <span class="badge bg-info" style="font-size: 12px; font-weight: 600;">
                                <i class="bi bi-box"></i> ${location.pallet_number}
                            </span>
                        </td>
                        <td style="padding: 12px 15px; color: #1f2937;">
                            <strong>${location.warehouse_location}</strong>
                        </td>
                        <td style="padding: 12px 15px; text-align: right; color: #6b7280;">
                            ${location.available_pcs} PCS
                        </td>
                        <td style="padding: 12px 15px; text-align: right;">
                            <span class="badge bg-success">${location.will_take_pcs} PCS</span>
                        </td>
                    </tr>
                `;
            });

            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        });

        modalContent.innerHTML = html;
        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
        previewModal.show();
    }

    document.getElementById('confirmWithdrawalBtn').addEventListener('click', function() {
        // Confirm dan submit cart ke server
        const items = Object.entries(cart).map(([partNumber, quantity]) => ({
            part_number: partNumber,
            pcs_quantity: quantity
        }));

        $.ajax({
            url: '{{ route("stock-withdrawal.store") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                items: items
            },
            success: function(response) {
                if (response.success) {
                    alert('Pengambilan stok berhasil diproses!');
                    cart = {};
                    loadStockData();
                    bootstrap.Modal.getInstance(document.getElementById('previewModal')).hide();
                }
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message || 'Terjadi kesalahan';
                alert('Error: ' + message);
            }
        });
    });

    // Load data on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadStockData();
    });
</script>
@endsection
