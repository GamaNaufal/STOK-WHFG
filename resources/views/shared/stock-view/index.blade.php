@extends('shared.layouts.app')

@section('title', 'Lihat Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h2">
            <i class="bi bi-eye"></i> Lihat Stok Tersedia
        </h1>
        <p class="text-muted">Cari dan lihat detail stok produk yang tersedia di gudang</p>
    </div>
</div>

<!-- Search Bar with Autocomplete -->
<div class="row mb-4">
    <div class="col-md-8 mx-auto">
        <div class="position-relative">
            <form method="GET" action="{{ route('stock-view.index') }}" id="searchForm" class="input-group input-group-lg">
                <input type="text" name="search" id="searchInput" class="form-control" 
                       placeholder="Cari berdasarkan No Part..." 
                       value="{{ $search ?? '' }}"
                       autocomplete="off">
                <button class="btn btn-outline-secondary" type="submit">
                    <i class="bi bi-search"></i> Cari
                </button>
                @if($search)
                    <a href="{{ route('stock-view.index') }}" class="btn btn-outline-danger">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                @endif
            </form>
            <!-- Dropdown Suggestions -->
            <div id="searchDropdown" class="search-dropdown dropdown-list border bg-white rounded mt-1" 
                 style="display: none; max-height: 300px; overflow-y: auto; position: absolute; width: 100%; z-index: 1000;">
                <!-- Suggestions akan di-populate via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- Stok By Part Number -->
<div class="row">
    <div class="col-12">
        <!-- Summary Stats -->
        @if($groupedByPart->count() > 0)
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm" style="background: #f5f7fa; border-left: 4px solid #0C7779; height: 140px; display: flex; align-items: center;">
                        <div class="card-body text-center w-100" style="padding: 1.5rem;">
                            <h6 class="text-muted" style="color: #0C7779; margin: 0 0 0.5rem 0; font-size: 0.875rem;">Total Part Numbers</h6>
                            <h2 class="fw-bold" style="color: #1f2937; margin: 0; font-size: 2rem;">{{ $groupedByPart->count() }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm" style="background: #f5f7fa; border-left: 4px solid #249E94; height: 140px; display: flex; align-items: center;">
                        <div class="card-body text-center w-100" style="padding: 1.5rem;">
                            <h6 class="text-muted" style="color: #249E94; margin: 0 0 0.5rem 0; font-size: 0.875rem;">Total Box</h6>
                            <h2 class="fw-bold" style="color: #1f2937; margin: 0; font-size: 2rem;">{{ (int) $groupedByPart->sum('total_box') }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm" style="background: #f5f7fa; border-left: 4px solid #3BC1A8; height: 140px; display: flex; align-items: center;">
                        <div class="card-body text-center w-100" style="padding: 1.5rem;">
                            <h6 class="text-muted" style="color: #3BC1A8; margin: 0 0 0.5rem 0; font-size: 0.875rem;">Total PCS</h6>
                            <h2 class="fw-bold" style="color: #1f2937; margin: 0; font-size: 2rem;">{{ $groupedByPart->sum('total_pcs') }}</h2>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Stok Details by Part Number -->
        @if($groupedByPart->count() > 0)
            @foreach($groupedByPart as $partData)
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header" style="background: #0C7779; color: white;">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="mb-0">
                                    <i class="bi bi-tag"></i> {{ $partData['part_number'] }}
                                </h6>
                            </div>
                            <div class="col-auto">
                                <div class="row g-2" style="min-width: 400px;">
                                    <div class="col" style="flex: 1; min-width: 0;">
                                        <div class="bg-white rounded px-3 py-2 text-dark text-center" style="height: 70px; display: flex; flex-direction: column; justify-content: center;">
                                            <small class="d-block" style="color: #9ca3af; font-size: 0.75rem;">Total Box</small>
                                            <h6 class="text-dark fw-bold" style="margin: 0.25rem 0 0 0; font-size: 1.25rem;">{{ (int) $partData['total_box'] }}</h6>
                                        </div>
                                    </div>
                                    <div class="col" style="flex: 1; min-width: 0;">
                                        <div class="bg-white rounded px-3 py-2 text-dark text-center" style="height: 70px; display: flex; flex-direction: column; justify-content: center;">
                                            <small class="d-block" style="color: #9ca3af; font-size: 0.75rem;">Total PCS</small>
                                            <h6 class="text-dark fw-bold" style="margin: 0.25rem 0 0 0; font-size: 1.25rem;">{{ $partData['total_pcs'] }}</h6>
                                        </div>
                                    </div>
                                    @if($partData['items']->count() > 0)
                                        <div class="col" style="flex: 1; min-width: 0;">
                                            <div class="bg-white rounded px-3 py-2 text-dark text-center" style="height: 70px; display: flex; flex-direction: column; justify-content: center;">
                                                <small class="d-block" style="color: #9ca3af; font-size: 0.75rem;">Pallet(s)</small>
                                                <h6 class="text-dark fw-bold" style="margin: 0.25rem 0 0 0; font-size: 1.25rem;">{{ $partData['items']->count() }}</h6>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead style="background: #f5f7fa;">
                                    <tr>
                                        <th style="width: 8%; text-align: center; color: #0C7779;">
                                            <i class="bi bi-sort-up"></i>
                                        </th>
                                        <th style="width: 20%; color: #0C7779;">No Pallet</th>
                                        <th style="width: 12%; text-align: center; color: #0C7779;">Box</th>
                                        <th style="width: 12%; text-align: center; color: #0C7779;">PCS</th>
                                        <th style="width: 20%; color: #0C7779;">Lokasi</th>
                                        <th style="width: 16%; text-align: center; color: #0C7779;">Tanggal Input</th>
                                        <th style="width: 12%; text-align: center; color: #0C7779;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($partData['items'] as $itemIndex => $item)
                                        <tr @if($itemIndex === 0) style="background: #fef8e7;" @endif>
                                            <td style="text-align: center;">
                                                @if($itemIndex === 0)
                                                    <span class="badge" style="background: #f4c430; color: #333;">
                                                        <i class="bi bi-arrow-up-circle"></i> AMBIL
                                                    </span>
                                                @else
                                                    <span style="color: #a0a8b3;">{{ $itemIndex + 1 }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong style="color: #1f2937;">{{ $item->pallet->pallet_number }}</strong>
                                            </td>
                                            <td style="text-align: center;">
                                                <strong style="color: #1f2937;">{{ (int) ceil($item->pcs_quantity / $item->box_quantity) }}</strong>
                                            </td>
                                            <td style="text-align: center;">
                                                <strong style="color: #1f2937;">{{ $item->pcs_quantity }}</strong>
                                            </td>
                                            <td>
                                                @if($item->pallet->stockLocation)
                                                    <span class="badge" style="background: #d4edda; color: #155724;">
                                                        <i class="bi bi-pin-map"></i> {{ $item->pallet->stockLocation->warehouse_location }}
                                                    </span>
                                                @else
                                                    <span class="badge" style="background: #fff3cd; color: #856404;">
                                                        <i class="bi bi-exclamation-triangle"></i> Belum Input
                                                    </span>
                                                @endif
                                            </td>
                                            <td style="text-align: center;">
                                                <small style="color: #9ca3af;">{{ $item->created_at->format('d/m/Y H:i') }}</small>
                                            </td>
                                            <td style="text-align: center;">
                                                <a href="{{ route('stock-view.show', $item->pallet->id) }}" 
                                                   class="btn btn-sm" style="background: #0C7779; color: white; border: none;">
                                                    <i class="bi bi-eye"></i> Detail
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="card shadow-sm border-0">
                <div class="card-body p-5 text-center">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                    <h5 class="mt-3 text-muted">
                        @if($search)
                            Tidak ada stok dengan No Part "{{ $search }}"
                        @else
                            Belum ada stok tersimpan
                        @endif
                    </h5>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    .search-dropdown {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .search-dropdown .dropdown-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.15s;
        font-size: 14px;
    }
    
    .search-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .search-dropdown .dropdown-item:hover,
    .search-dropdown .dropdown-item.active {
        background-color: #e3f2fd;
        color: #0066cc;
        font-weight: 500;
    }
</style>

@endsection

@section('scripts')
<script>
    // Get all available part numbers from the grouped data
    const allParts = @json($groupedByPart->pluck('part_number')->values());
    const searchInput = document.getElementById('searchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchForm = document.getElementById('searchForm');

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
            item.style.cursor = 'pointer';
            
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                searchInput.value = part;
                searchDropdown.style.display = 'none';
                // Auto submit form
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

    // Show dropdown on focus
    searchInput.addEventListener('focus', function() {
        loadSearchSuggestions(searchInput.value);
        searchDropdown.style.display = 'block';
    });

    // Filter on input
    searchInput.addEventListener('input', function() {
        loadSearchSuggestions(this.value);
        searchDropdown.style.display = 'block';
    });

    // Close on blur
    searchInput.addEventListener('blur', function() {
        setTimeout(() => {
            searchDropdown.style.display = 'none';
        }, 150);
    });

    // Load initial suggestions on page load
    window.addEventListener('DOMContentLoaded', function() {
        if (searchInput.value) {
            loadSearchSuggestions(searchInput.value);
        }
    });
</script>
@endsection
