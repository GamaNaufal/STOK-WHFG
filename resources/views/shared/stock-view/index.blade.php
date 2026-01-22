@extends('shared.layouts.app')

@section('title', 'Lihat Stok - Warehouse FG Yamato')

@section('content')
<!-- Modern Header Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    border-radius: 12px; 
                    box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;">
            <div>
                <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                    <i class="bi bi-eye"></i> Lihat Stok Tersedia
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Pantau ketersediaan stok produk di gudang dengan detail lengkap
                </p>
            </div>
            <div>
                <form method="GET" action="{{ route('stock-view.index') }}" class="d-flex gap-2">
                    @if($groupedByPart->count() > 0)
                        <button type="button" onclick="exportToExcel()" class="btn btn-light btn-lg" style="border-radius: 8px; padding: 12px 28px; font-weight: 600;">
                            <i class="bi bi-download"></i> Export Excel
                        </button>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="position-relative">
            <form method="GET" action="{{ route('stock-view.index') }}" id="searchForm" class="input-group input-group-lg">
                <span class="input-group-text border-0" style="background: #0C7779; color: white; border-radius: 10px 0 0 10px; font-size: 1.2rem;">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" name="search" id="searchInput" class="form-control form-control-lg border-0" 
                       placeholder="Cari berdasarkan No Part..." 
                       value="{{ $search ?? '' }}"
                       autocomplete="off"
                       style="font-size: 1rem; padding: 14px 16px;">
                <button class="btn btn-outline-secondary border-0" type="submit" style="border-radius: 0 10px 10px 0; background: white; color: #0C7779; font-weight: 600;">
                    <i class="bi bi-search"></i> Cari
                </button>
                @if($search)
                    <a href="{{ route('stock-view.index') }}" class="btn btn-outline-danger border-0" style="border-radius: 0 10px 10px 0; background: white;">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                @endif
            </form>
            <!-- Dropdown Suggestions -->
            <div id="searchDropdown" class="search-dropdown dropdown-list border-0 bg-white rounded-3 mt-2" 
                 style="display: none; max-height: 300px; overflow-y: auto; position: absolute; width: 100%; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                <!-- Suggestions akan di-populate via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Summary Statistics -->
@if($groupedByPart->count() > 0)
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
                <div class="card-body" style="padding: 24px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Part Numbers</p>
                            <h2 class="fw-bold" style="color: #0C7779; margin: 0; font-size: 2.5rem;">{{ $groupedByPart->count() }}</h2>
                        </div>
                        <i class="bi bi-tag" style="font-size: 2.5rem; color: #0C7779; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
                <div class="card-body" style="padding: 24px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Box</p>
                            <h2 class="fw-bold" style="color: #249E94; margin: 0; font-size: 2.5rem;">{{ (int) $groupedByPart->sum('total_box') }}</h2>
                        </div>
                        <i class="bi bi-box2" style="font-size: 2.5rem; color: #249E94; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
                <div class="card-body" style="padding: 24px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total PCS</p>
                            <h2 class="fw-bold" style="color: #3BC1A8; margin: 0; font-size: 2.5rem;">{{ $groupedByPart->sum('total_pcs') }}</h2>
                        </div>
                        <i class="bi bi-stack" style="font-size: 2.5rem; color: #3BC1A8; opacity: 0.2;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Main Table -->
<div class="row">
    <div class="col-12">
        @if($groupedByPart->count() > 0)
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <!-- Table Header -->
                <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                            color: white; 
                            padding: 20px 24px; 
                            font-weight: 600;
                            font-size: 15px;">
                    <i class="bi bi-table"></i> Daftar Stok Tersedia
                </div>

                <!-- Table -->
                <div class="table-responsive" style="overflow: hidden;">
                    <table class="table table-hover mb-0" style="margin-bottom: 0;">
                        <thead style="background: #f9fafb; border-bottom: 2px solid #e5e7eb;">
                            <tr>
                                <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase;">
                                    <i class="bi bi-tag"></i> No Part
                                </th>
                                <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                    <i class="bi bi-box2"></i> Total Box
                                </th>
                                <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                    <i class="bi bi-stack"></i> Total PCS
                                </th>
                                <th style="color: #0C7779; font-weight: 700; padding: 16px 20px; font-size: 13px; text-transform: uppercase; text-align: center;">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groupedByPart as $partData)
                                <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                    <td style="padding: 16px 20px; color: #1f2937; font-weight: 600;">
                                        <span style="background: #f0f4f8; color: #0C7779; padding: 6px 12px; border-radius: 8px; font-size: 13px;">
                                            {{ $partData['part_number'] }}
                                        </span>
                                    </td>
                                    <td style="padding: 16px 20px; color: #1f2937; font-weight: 700; text-align: center; font-size: 15px;">
                                        {{ (int) $partData['total_box'] }}
                                    </td>
                                    <td style="padding: 16px 20px; color: #1f2937; font-weight: 700; text-align: center; font-size: 15px;">
                                        {{ $partData['total_pcs'] }}
                                    </td>
                                    <td style="padding: 16px 20px; text-align: center;">
                                        <button type="button" class="btn btn-sm" 
                                                onclick="viewDetail('{{ $partData['part_number'] }}', {{ $partData['total_box'] }}, {{ $partData['total_pcs'] }}, {{ $partData['items']->count() }})"
                                                style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                                                       color: white; 
                                                       border: none; 
                                                       border-radius: 8px;
                                                       padding: 8px 16px;
                                                       font-weight: 600;
                                                       font-size: 13px;
                                                       transition: all 0.3s ease;">
                                            <i class="bi bi-eye"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                <div class="card-body p-5 text-center">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #e5e7eb;"></i>
                    <h5 class="mt-4 text-muted" style="font-weight: 600;">
                        @if($search)
                            Tidak ada stok dengan No Part "<strong>{{ $search }}</strong>"
                        @else
                            Belum ada stok tersimpan
                        @endif
                    </h5>
                    <p class="text-muted">Mulai dengan melakukan input stok untuk melihat data di sini</p>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15); max-height: 90vh; overflow-y: auto;">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 24px;
                        border-radius: 12px 12px 0 0;
                        position: sticky;
                        top: 0;
                        z-index: 1020;">
                <h5 class="modal-title fw-bold" style="margin: 0; font-size: 18px;">
                    <i class="bi bi-info-circle"></i> Detail Stok
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

                    <!-- Pallet Details Table -->
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
            <div class="modal-footer" style="border-top: 1px solid #e5e7eb; padding: 16px 24px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px; font-weight: 600;">Tutup</button>
            </div>
        </div>
    </div>
</div>

<style>
    .search-dropdown {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        overflow: hidden;
    }
    
    .search-dropdown .dropdown-item {
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #e5e7eb;
        transition: all 0.3s ease;
        font-size: 14px;
        background: white;
    }
    
    .search-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .search-dropdown .dropdown-item:hover,
    .search-dropdown .dropdown-item.active {
        background-color: #f0f4f8;
        color: #0C7779;
        font-weight: 600;
    }

    .table tbody tr:hover {
        background-color: #f9fafb;
    }
</style>

@endsection

@section('scripts')
<script>
    // Get all available part numbers
    const allParts = @json($groupedByPart->pluck('part_number')->values());
    const searchInput = document.getElementById('searchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchForm = document.getElementById('searchForm');
    const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

    function loadSearchSuggestions(searchTerm) {
        let filtered = allParts;
        
        if (searchTerm.trim()) {
            filtered = allParts.filter(part => 
                part.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }
        
        searchDropdown.innerHTML = '';
        
        if (filtered.length === 0 && searchTerm.trim()) {
            const noResult = document.createElement('div');
            noResult.className = 'dropdown-item text-muted';
            noResult.textContent = 'Tidak ada hasil untuk "' + searchTerm + '"';
            searchDropdown.appendChild(noResult);
            return;
        }
        
        filtered.forEach(part => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = part;
            
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                searchInput.value = part;
                searchDropdown.style.display = 'none';
                searchForm.submit();
            });
            
            item.addEventListener('mouseover', function() {
                this.classList.add('active');
            });
            
            item.addEventListener('mouseout', function() {
                this.classList.remove('active');
            });
            
            searchDropdown.appendChild(item);
        });
    }

    searchInput.addEventListener('focus', function() {
        loadSearchSuggestions(searchInput.value);
        searchDropdown.style.display = 'block';
    });

    searchInput.addEventListener('input', function() {
        loadSearchSuggestions(this.value);
        searchDropdown.style.display = 'block';
    });

    searchInput.addEventListener('blur', function() {
        setTimeout(() => {
            searchDropdown.style.display = 'none';
        }, 150);
    });

    // View detail modal
    function viewDetail(partNumber, totalBox, totalPcs, palletCount) {
        // Show loading spinner
        document.getElementById('detailLoadingSpinner').style.display = 'block';
        document.getElementById('detailContent').style.display = 'none';
        
        // Fetch detailed part information from API
        fetch(`/api/stock/part-detail/${encodeURIComponent(partNumber)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.error);
                    return;
                }

                // Populate summary
                document.getElementById('modalPartNumber').textContent = data.part_number;
                document.getElementById('modalTotalBox').textContent = data.total_box;
                document.getElementById('modalTotalPcs').textContent = data.total_pcs;
                document.getElementById('modalPalletCount').textContent = data.pallet_count;

                // Populate pallet details table
                const tableBody = document.getElementById('palletDetailsTable');
                if (data.pallets.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data</td></tr>';
                } else {
                    tableBody.innerHTML = data.pallets.map((pallet, index) => `
                        <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.2s ease;">
                            <td style="padding: 12px 8px; color: #0C7779; font-weight: 600; font-size: 13px;">
                                <i class="bi bi-box2"></i> ${pallet.pallet_number}
                            </td>
                            <td style="padding: 12px 8px; color: #1f2937; font-size: 13px;">
                                <span class="badge bg-primary" style="font-size: 11px;">${pallet.box_quantity} BOX</span>
                            </td>
                            <td style="padding: 12px 8px; color: #1f2937; font-weight: 600; font-size: 13px;">
                                <span class="badge bg-success">${pallet.pcs_quantity} PCS</span>
                            </td>
                            <td style="padding: 12px 8px; color: #6b7280; font-size: 13px;">
                                <i class="bi bi-geo-alt"></i> ${pallet.location}
                            </td>
                            <td style="padding: 12px 8px; color: #6b7280; font-size: 12px;">
                                ${pallet.created_at}
                            </td>
                        </tr>
                    `).join('');
                }

                // Hide loading and show content
                document.getElementById('detailLoadingSpinner').style.display = 'none';
                document.getElementById('detailContent').style.display = 'block';
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading detail data');
                document.getElementById('detailLoadingSpinner').style.display = 'none';
            });

        detailModal.show();
    }

    // Export to Excel
    function exportToExcel() {
        const table = document.querySelector('table');
        if (!table) {
            alert('Tidak ada data untuk diexport');
            return;
        }

        let csv = 'No Part,Total Box,Total PCS\n';
        
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 3) {
                const partNumber = cells[0].textContent.trim();
                const totalBox = cells[1].textContent.trim();
                const totalPcs = cells[2].textContent.trim();
                csv += `"${partNumber}","${totalBox}","${totalPcs}"\n`;
            }
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', `Stok_${new Date().toISOString().split('T')[0]}.csv`);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Load initial suggestions
    window.addEventListener('DOMContentLoaded', function() {
        if (searchInput.value) {
            loadSearchSuggestions(searchInput.value);
        }
    });
</script>
@endsection
