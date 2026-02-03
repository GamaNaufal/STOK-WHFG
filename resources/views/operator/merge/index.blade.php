@extends('shared.layouts.app')

@section('title', 'Refactor Merge Pallet')

@section('content')
<div class="container-fluid">
    <!-- Modern Gradient Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <div>
                    <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                        <i class="bi bi-intersect"></i> Merge Pallet
                    </h1>
                    <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                        Pilih beberapa pallet existing untuk digabungkan menjadi <strong>Satu Pallet Baru</strong>
                    </p>
                </div>
            </div>
        </div>
    </div>

<div class="row">
    <!-- LEFT: Search & Add -->
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header text-white fw-bold" style="background-color: #0C7779;">
                <i class="bi bi-search"></i> Cari Pallet
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Scan Barcode Pallet</label>
                    <div class="input-group">
                        <input type="text" id="scanInput" class="form-control" placeholder="Scan QR (PLT-XXXX)..." autofocus>
                        <button class="btn text-white" style="background-color: #0C7779;" type="button" onclick="searchForAdd()">
                            <i class="bi bi-plus-lg"></i> Tambah
                        </button>
                    </div>
                    <div class="form-text">Cari pallet yang ingin digabungkan.</div>
                </div>

                <!-- Preview Search Result (Single) -->
                <div id="searchResultCard" class="card mb-3" style="display: none; border: 1px dashed #0C7779;">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong id="resNumber">PLT-XXXX</strong><br>
                                <small id="resLocation" class="text-muted">Loc: A-1</small>
                            </div>
                            <button class="btn btn-sm btn-success" onclick="addToMergeList()">
                                <i class="bi bi-check"></i> Pilih
                            </button>
                        </div>
                    </div>
                </div>

                <!-- LIST PALLET TERSEDIA -->
                <div class="mt-4">
                    <h6 class="fw-bold mb-3 border-bottom pb-2">
                        <i class="bi bi-box-seam"></i> Pallet Terbaru 
                        <span class="badge bg-secondary rounded-pill ms-1">{{ $pallets->count() }}</span>
                    </h6>
                    <div class="list-group list-group-flush border rounded bg-white" style="max-height: 400px; overflow-y: auto;">
                        @forelse($pallets as $p)
                            @php
                                $loc = $p->stockLocation ? $p->stockLocation->warehouse_location : 'No Loc';
                                $activeBoxCount = $p->boxes->count();
                                $totalPcs = $p->boxes->sum('pcs_quantity');
                                $pData = [
                                    'id' => $p->id,
                                    'pallet_number' => $p->pallet_number,
                                    'location' => $loc,
                                    'total_box' => $activeBoxCount,
                                    'total_pcs' => $totalPcs
                                ];
                            @endphp
                            <div id="left-pallet-{{ $p->id }}" class="list-group-item d-flex justify-content-between align-items-center">
                                <div class="text-truncate me-2">
                                    <div class="fw-bold small">{{ $p->pallet_number }}</div>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        <i class="bi bi-geo-alt"></i> {{ $loc }}
                                        &bull; 
                                        {{ $activeBoxCount }} Box
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick='addFromList(@json($pData))'>
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        @empty
                            <div class="p-3 text-center text-muted small">Tidak ada pallet aktif</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Merge List -->
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm h-100 border-0">
            <div class="card-header bg-light fw-bold text-dark border-bottom">
                <i class="bi bi-list-check"></i> Daftar Pallet yang akan Digabung
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th class="ps-3">No Pallet</th>
                                <th>Lokasi</th>
                                <th class="text-center">Total Box</th>
                                <th class="text-center">Total Pcs</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="mergeTableBody">
                            <tr id="emptyRow">
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-basket3 display-6 d-block mb-2 opacity-50"></i>
                                    Belum ada pallet dipilih
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="2" class="ps-3">Total Estimasi Pallet Baru:</td>
                                <td class="text-center" id="sumBox">0</td>
                                <td class="text-center" id="sumPcs">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white p-3">
                <div class="mb-3">
                    <label class="form-label fw-bold"><i class="bi bi-geo-alt"></i> Lokasi Pallet Baru</label>
                    
                    <!-- Pilih Lokasi (dari DB atau dari Pallet Lama) -->
                    <div class="position-relative">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="locationSearchInput" class="form-control border-start-0" 
                                   placeholder="Pilih lokasi penyimpanan..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="locationDropdownBtn">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <input type="hidden" id="selectedLocationId">
                            <input type="hidden" id="selectedLocationCode">
                        </div>
                        <div id="locationSearchResults" class="list-group position-absolute w-100 shadow mt-1" style="z-index: 1000; display: none; max-height: 200px; overflow-y: auto;"></div>
                    </div>
                </div>
                <div class="text-end">
                    <button id="btnProcess" onclick="processMerge()" class="btn btn-lg btn-secondary" disabled>
                        <i class="bi bi-intersect"></i> Gabungkan & Generate Baru
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MERGE HISTORY SECTION -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-bold text-dark border-bottom">
                <i class="bi bi-clock-history"></i> History Merge Pallet
            </div>
            <div class="card-body">
                @if($mergeHistory->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pallet Hasil Merge</th>
                                    <th>Sumber Pallet</th>
                                    <th>Operator</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($mergeHistory as $history)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $history->created_at->format('d M Y H:i') }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $resultPallet = \App\Models\Pallet::find($history->model_id);
                                            @endphp
                                            <strong>{{ $resultPallet?->pallet_number ?? 'PLT-' . $history->model_id }}</strong>
                                        </td>
                                        <td>
                                            @php
                                                $description = $history->description;
                                                // Extract pallet numbers from description (format: "Merge dari 2 pallet: PLT-009, PLT-010")
                                                preg_match('/pallet:\s*(.+)$/', $description, $matches);
                                                $palletNumbers = $matches[1] ?? '';
                                            @endphp
                                            @if($palletNumbers)
                                                @foreach(explode(',', $palletNumbers) as $num)
                                                    <span class="badge bg-warning text-dark">{{ trim($num) }}</span>
                                                @endforeach
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($history->user)
                                                <small>{{ $history->user->name }}</small>
                                            @else
                                                <small class="text-muted">System</small>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $history->description }}</small>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-5 text-muted d-block mb-2"></i>
                        <p class="text-muted">Belum ada history merge pallet</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    let currentPreview = null;
    let selectedPallets = []; // Array of objects

    // --- Location Search Logic (Copied & Simplified) ---
    const searchInput = document.getElementById('locationSearchInput');
    const searchResults = document.getElementById('locationSearchResults');
    const dropdownBtn = document.getElementById('locationDropdownBtn');
    const selectedLocationId = document.getElementById('selectedLocationId');
    const selectedLocationCode = document.getElementById('selectedLocationCode');
    let searchTimeout;

    function performSearch(query) {
        selectedLocationId.value = ''; 
        searchResults.style.display = 'block';
        searchResults.innerHTML = '<div class="list-group-item text-muted">Mencari...</div>';

        fetch(`/api/locations/search?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                searchResults.innerHTML = '';

                // Collect locations from selected pallets
                const selectedLocations = new Set();
                selectedPallets.forEach(p => {
                    if(p.location && p.location !== '-' && p.location !== 'Not Stored') {
                        selectedLocations.add(p.location);
                    }
                });

                // Add selected pallets locations first
                selectedLocations.forEach(loc => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = `<div class="d-flex justify-content-between align-items-center">
                                        <strong>${loc}</strong>
                                        <span class="badge bg-info rounded-pill" style="font-size: 0.7em;">From Selected</span>
                                      </div>`;
                    item.style.cursor = 'pointer';
                    item.onclick = (e) => {
                        e.preventDefault();
                        searchInput.value = loc;
                        selectedLocationId.value = '';
                        selectedLocationCode.value = loc;
                        searchResults.style.display = 'none';
                    };
                    searchResults.appendChild(item);
                });

                // Add available locations from DB
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
                        item.onclick = (e) => {
                            e.preventDefault();
                            searchInput.value = loc.code;
                            selectedLocationId.value = loc.id;
                            selectedLocationCode.value = loc.code;
                            searchResults.style.display = 'none';
                        };
                        searchResults.appendChild(item);
                    });
                } else {
                    if(query === '') {
                        if(selectedLocations.size === 0) {
                            searchResults.innerHTML = '<div class="list-group-item text-muted">Tidak ada lokasi tersedia.</div>';
                        }
                    } else {
                        searchResults.innerHTML = '<div class="list-group-item text-muted">Lokasi tidak ditemukan. Gunakan sbg teks manual?</div>';
                    }
                }
                
                if(searchResults.children.length > 0) {
                    searchResults.style.display = 'block';
                }
            })
            .catch(err => {
                console.error('Search error:', err);
                searchResults.style.display = 'none';
            });
    }

    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        searchTimeout = setTimeout(() => performSearch(query), 300);
    });

    searchInput.addEventListener('focus', function() {
        if(this.value.trim() === '') performSearch('');
        else searchResults.style.display = 'block';
    });

    dropdownBtn.addEventListener('click', () => {
        searchInput.focus();
        performSearch(searchInput.value.trim());
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target) && !dropdownBtn.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // --- EOL Search ---

    document.getElementById('scanInput').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            searchForAdd();
        }
    });

    function searchForAdd() {
        const code = document.getElementById('scanInput').value.trim();
        if(!code) return;

        fetch(`{{ route('merge-pallet.search') }}?code=${encodeURIComponent(code)}`)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const p = data.pallet;
                    
                    // Check if already in list
                    if(selectedPallets.find(item => item.id === p.id)) {
                        showToast('Pallet ini sudah masuk daftar!', 'warning');
                        document.getElementById('scanInput').value = '';
                        return;
                    }

                    currentPreview = p;
                    
                    // Show preview
                    document.getElementById('searchResultCard').style.display = 'block';
                    document.getElementById('resNumber').textContent = p.pallet_number;
                    document.getElementById('resLocation').textContent = 'Loc: ' + (p.location || '-'); // Using || because location might be string "Not Stored"
                    
                } else {
                    showToast(data.message, 'danger');
                    document.getElementById('searchResultCard').style.display = 'none';
                    currentPreview = null;
                }
            })
            .catch(err => showToast('Error: ' + err, 'danger'));
    }

    function addToMergeList() {
        if(!currentPreview) return;

        selectedPallets.push(currentPreview);
        renderTable();
        
        // Reset inputs
        document.getElementById('scanInput').value = '';
        document.getElementById('searchResultCard').style.display = 'none';
        currentPreview = null;
        document.getElementById('scanInput').focus();
    }

    function addFromList(palletData) {
        if(selectedPallets.find(item => item.id === palletData.id)) {
            showToast('Pallet ' + palletData.pallet_number + ' sudah masuk daftar!', 'warning');
            return;
        }
        selectedPallets.push(palletData);
        renderTable();
    }

    function removeFromList(index) {
        selectedPallets.splice(index, 1);
        renderTable();
    }

    function renderTable() {
        const tbody = document.getElementById('mergeTableBody');
        tbody.innerHTML = '';

        let totalBox = 0;
        let totalPcs = 0;

        if(selectedPallets.length === 0) {
            tbody.innerHTML = `<tr id="emptyRow">
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-basket3 display-6 d-block mb-2 opacity-50"></i>
                                    Belum ada pallet dipilih
                                </td>
                            </tr>`;
            document.getElementById('btnProcess').disabled = true;
            document.getElementById('btnProcess').classList.remove('btn-primary');
            document.getElementById('btnProcess').classList.add('btn-secondary');
        } else {
            selectedPallets.forEach((p, index) => {
                const boxCount = parseInt(p.total_box) || 0;
                const pcsCount = parseFloat(p.total_pcs) || 0;
                
                totalBox += boxCount;
                totalPcs += pcsCount;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3 fw-bold text-dark">${p.pallet_number}</td>
                    <td><span class="badge bg-light text-dark border">${p.location || '-'}</span></td>
                    <td class="text-center">${boxCount}</td>
                    <td class="text-center">${pcsCount}</td>
                    <td class="text-end pe-3">
                        <button class="btn btn-sm btn-outline-danger" onclick="removeFromList(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            // Enable button if at least 2 pallets
            if(selectedPallets.length >= 2) {
                document.getElementById('btnProcess').disabled = false;
                document.getElementById('btnProcess').classList.remove('btn-secondary');
                document.getElementById('btnProcess').classList.add('btn-primary');
                document.getElementById('btnProcess').style.backgroundColor = '#0C7779';
            } else {
                document.getElementById('btnProcess').disabled = true;
                document.getElementById('btnProcess').classList.add('btn-secondary');
                document.getElementById('btnProcess').classList.remove('btn-primary');
            }
        }

        document.getElementById('sumBox').textContent = totalBox;
        document.getElementById('sumPcs').textContent = totalPcs;

        // Update Left List Visibility
        // Reset all to visible (remove inline display property)
        document.querySelectorAll('[id^="left-pallet-"]').forEach(el => el.style.removeProperty('display'));
        
        // Hide selected
        selectedPallets.forEach(p => {
             const el = document.getElementById('left-pallet-' + p.id);
             if(el) el.style.setProperty('display', 'none', 'important');
        });
    }

    function processMerge() {
        if(selectedPallets.length < 2) {
            showToast('Pilih minimal 2 pallet untuk digabungkan!', 'warning');
            return;
        }

        const locationVal = searchInput.value.trim();
        if(!locationVal) {
            showToast('Harap tentukan lokasi untuk pallet baru!', 'warning');
            searchInput.focus();
            return;
        }

        WarehouseAlert.confirm({
            title: 'Konfirmasi Merge Pallet',
            message: `Anda akan menggabungkan <strong style="color: #0C7779;">${selectedPallets.length} pallet</strong> menjadi satu pallet baru.`,
            warningItems: [
                'Pallet lama akan <strong>dihapus permanent</strong>',
                'Box akan dipindahkan ke pallet baru',
                'Tindakan ini <strong>tidak dapat dibatalkan</strong>'
            ],
            infoText: `<strong>Lokasi Baru:</strong> <span style="color: #0C7779; font-weight: 700;">${locationVal}</span>`,
            confirmText: 'Ya, Gabungkan!',
            confirmColor: '#10B981',
            onConfirm: () => {

            const ids = selectedPallets.map(p => p.id);
            const locId = selectedLocationId.value;
            const locCode = locationVal;

            fetch('{{ route("merge-pallet.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    pallet_ids: ids,
                    location_id: locId,
                    warehouse_location: locCode
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    showToast(data.message, 'success');
                    window.location.reload();
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(err => showToast('Error: ' + err, 'danger'));
            }
        });
    }
</script>
@endsection
