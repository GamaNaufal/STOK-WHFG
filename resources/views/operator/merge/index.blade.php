@extends('shared.layouts.app')

@section('title', 'Merge Pallet')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/merge-pallet.css') }}">
@endsection

@section('content')
<div class="container-fluid merge-pallet-page" id="mergePalletPage">
    <header class="merge-pallet-header">
        <span class="merge-pallet-eyebrow">Inventory / Konsolidasi pallet</span>
        <h1><i class="bi bi-intersect" aria-hidden="true"></i> Merge Pallet</h1>
        <p>Pilih minimal dua pallet sumber. Box aktifnya akan digabung ke satu pallet baru setelah lokasi tujuan diperiksa.</p>
    </header>

<div class="row g-3 g-xl-4 merge-pallet-workflow">
    <!-- LEFT: Search & Add -->
    <div class="col-lg-5">
        <section class="card merge-pallet-panel h-100" aria-labelledby="mergeSourceTitle">
            <div class="card-header merge-pallet-panel-head">
                <span class="merge-pallet-step" aria-hidden="true">01</span>
                <div>
                    <h2 id="mergeSourceTitle">Pilih pallet sumber</h2>
                    <p>Scan nomor pallet atau tambahkan dari daftar terbaru.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="scanInput" class="form-label fw-semibold">Nomor pallet</label>
                    <div class="input-group">
                        <input type="text" id="scanInput" class="form-control" placeholder="Scan atau masukkan nomor pallet" aria-describedby="mergeScanHint" autofocus>
                        <button class="btn btn-primary" type="button" onclick="searchForAdd()">
                            <i class="bi bi-search" aria-hidden="true"></i> Cari
                        </button>
                    </div>
                    <div id="mergeScanHint" class="form-text">Tekan Enter setelah scan. Kelayakan pallet diperiksa ulang saat merge.</div>
                </div>

                <!-- Preview Search Result (Single) -->
                <div id="searchResultCard" class="merge-pallet-preview mb-3" style="display: none;" aria-live="polite">
                    <div class="merge-pallet-preview-body">
                        <div class="merge-pallet-preview-content">
                            <div>
                                <span class="merge-pallet-preview-label">Pallet ditemukan</span>
                                <strong id="resNumber">PLT-XXXX</strong>
                                <small id="resLocation">Lokasi: A-1</small>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="addToMergeList()"><i class="bi bi-plus-lg" aria-hidden="true"></i> Pilih pallet</button>
                        </div>
                    </div>
                </div>

                <!-- LIST PALLET TERSEDIA -->
                <div class="merge-pallet-choices mt-4">
                    <div class="merge-pallet-list-head">
                        <h3 id="mergeRecentTitle">Pallet terbaru</h3>
                        <span>{{ $pallets->count() }} pallet</span>
                    </div>
                    <div class="list-group merge-pallet-source-list" aria-labelledby="mergeRecentTitle">
                        @forelse($pallets as $p)
                            @php
                                $loc = $p->stockLocation ? $p->stockLocation->warehouse_location : 'No Loc';
                                $activeBoxCount = $p->boxes->count();
                                $totalPcs = $p->boxes->sum('pcs_quantity');
                                $pData = [
                                    'id' => $p->id,
                                    'pallet_number' => $p->pallet_number,
                                    'location' => $loc,
                                    'location_id' => $p->stockLocation?->master_location_id,
                                    'total_box' => $activeBoxCount,
                                    'total_pcs' => $totalPcs
                                ];
                            @endphp
                            <div id="left-pallet-{{ $p->id }}" class="list-group-item merge-pallet-source-row">
                                <div class="merge-pallet-source-main">
                                    <strong>{{ $p->pallet_number }}</strong>
                                    <span class="merge-pallet-source-meta">
                                        <span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $loc }}</span>
                                        <span>{{ $activeBoxCount }} Box</span>
                                        <span>{{ number_format($totalPcs) }} PCS</span>
                                    </span>
                                </div>
                                <button type="button" class="btn btn-outline-primary"
                                    data-pallet='{{ json_encode($pData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}'
                                    aria-label="Pilih pallet {{ $p->pallet_number }} untuk merge"
                                    onclick="addFromList(this.dataset.pallet)">
                                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                </button>
                            </div>
                        @empty
                            <div class="p-3 text-center text-muted small">Belum ada pallet aktif untuk dipilih.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- RIGHT: Merge List -->
    <div class="col-lg-7">
        <section class="card merge-pallet-panel h-100" aria-labelledby="mergeSelectionTitle">
            <div class="card-header merge-pallet-panel-head merge-pallet-panel-head-split">
                <span class="merge-pallet-step" aria-hidden="true">02</span>
                <div>
                    <h2 id="mergeSelectionTitle">Pallet pilihan</h2>
                    <p>Minimal dua pallet sebelum lokasi pallet baru ditentukan.</p>
                </div>
                <span class="merge-pallet-selected-count" id="selectedPalletCount" aria-live="polite">0 dipilih</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive merge-pallet-selection-scroll" tabindex="0" role="region" aria-label="Pallet pilihan, geser untuk melihat seluruh kolom">
                    <table class="table table-hover align-middle mb-0 merge-pallet-selection-table">
                        <thead class="bg-light sticky-top">
                            <tr>
                                <th scope="col" class="ps-3">No Pallet</th>
                                <th scope="col">Lokasi</th>
                                <th scope="col" class="text-center">Total Box</th>
                                <th scope="col" class="text-center">Total PCS</th>
                                <th scope="col"><span class="visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody id="mergeTableBody">
                            <tr id="emptyRow">
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-basket3 display-6 d-block mb-2 opacity-50"></i>
                                    Belum ada pallet dipilih. Scan atau pilih dari daftar di samping.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td colspan="2" class="ps-3">Estimasi pallet baru</td>
                                <td class="text-center" id="sumBox">0</td>
                                <td class="text-center" id="sumPcs">0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer merge-pallet-destination">
                <div class="merge-pallet-destination-head">
                    <span class="merge-pallet-step" aria-hidden="true">03</span>
                    <div>
                        <h3 id="mergeDestinationTitle">Lokasi pallet baru</h3>
                        <p>Pilih lokasi kosong atau gunakan lokasi dari salah satu pallet sumber.</p>
                    </div>
                </div>
                <label for="locationSearchInput" class="form-label fw-semibold">Cari lokasi tujuan</label>
                <div class="position-relative">
                    <div class="input-group merge-pallet-location-input">
                        <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                        <input type="text" id="locationSearchInput" class="form-control"
                               placeholder="Cari kode lokasi" autocomplete="off"
                               aria-controls="locationSearchResults" aria-expanded="false"
                               aria-describedby="mergeLocationHint locationSelectionStatus">
                        <button class="btn btn-outline-secondary" type="button" id="locationDropdownBtn" aria-label="Tampilkan pilihan lokasi">
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </button>
                        <input type="hidden" id="selectedLocationId">
                        <input type="hidden" id="selectedLocationCode">
                    </div>
                    <div id="locationSearchResults" class="list-group merge-pallet-location-results" role="group" aria-label="Pilihan lokasi tujuan" hidden></div>
                </div>
                <p id="mergeLocationHint" class="form-text mb-0">Pilih hasil pencarian; kode yang diketik saja belum menjadi lokasi tujuan.</p>
                <div id="locationSelectionStatus" class="merge-pallet-location-status" role="status" aria-live="polite">Belum ada lokasi tujuan dipilih.</div>
                <div class="merge-pallet-action-row">
                    <span id="mergeActionHint">Pilih minimal dua pallet dan satu lokasi tujuan.</span>
                    <button id="btnProcess" type="button" onclick="processMerge()" class="btn btn-primary" disabled>
                        <i class="bi bi-intersect" aria-hidden="true"></i> Tinjau & gabungkan
                    </button>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <section class="card merge-pallet-panel merge-pallet-history" aria-labelledby="mergeHistoryTitle">
            <header class="merge-pallet-history-head">
                <div>
                    <span class="merge-pallet-eyebrow">Jejak aktivitas</span>
                    <h2 id="mergeHistoryTitle">Riwayat merge pallet</h2>
                    <p>Catatan audit terbaru untuk menelusuri pallet hasil dan sumbernya.</p>
                </div>
                <span class="merge-pallet-history-count">{{ $mergeHistory->count() }} catatan</span>
            </header>
            @if($mergeHistory->count() > 0)
                <div class="table-responsive merge-pallet-history-scroll" tabindex="0" role="region" aria-label="Riwayat merge pallet, geser untuk melihat seluruh kolom">
                    <table class="table align-middle mb-0 merge-pallet-history-table">
                        <caption class="visually-hidden">Maksimal 20 catatan merge pallet terbaru.</caption>
                        <thead>
                            <tr>
                                <th scope="col">Waktu</th>
                                <th scope="col">Pallet hasil</th>
                                <th scope="col">Pallet sumber</th>
                                <th scope="col">Operator</th>
                                <th scope="col">Keterangan audit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($mergeHistory as $history)
                                <tr>
                                    <td>
                                        <time class="merge-pallet-history-time" datetime="{{ $history->created_at->toIso8601String() }}">
                                            <strong>{{ $history->created_at->format('d M Y') }}</strong>
                                            <span>{{ $history->created_at->format('H:i') }}</span>
                                        </time>
                                    </td>
                                    <th scope="row" class="merge-pallet-history-result">
                                        @php
                                            $resultPallet = \App\Models\Pallet::find($history->model_id);
                                        @endphp
                                        {{ $resultPallet?->pallet_number ?? 'PLT-' . $history->model_id }}
                                    </th>
                                    <td>
                                        @php
                                            $description = $history->description;
                                            preg_match('/pallet:\s*(.+)$/', $description, $matches);
                                            $palletNumbers = $matches[1] ?? '';
                                        @endphp
                                        @if($palletNumbers)
                                            <div class="merge-pallet-history-sources">
                                                @foreach(explode(',', $palletNumbers) as $num)
                                                    <span>{{ trim($num) }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="merge-pallet-history-muted">Tidak tercatat</span>
                                        @endif
                                    </td>
                                    <td class="merge-pallet-history-operator">{{ $history->user?->name ?? 'System' }}</td>
                                    <td class="merge-pallet-history-description">{{ $history->description }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="merge-pallet-history-foot">Menampilkan maksimal 20 catatan terbaru.</div>
            @else
                <div class="merge-pallet-history-empty">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <strong>Belum ada riwayat merge</strong>
                    <span>Catatan akan muncul setelah merge pallet berhasil.</span>
                </div>
            @endif
        </section>
    </div>
</div>

</div>

<script>
    let currentPreview = null;
    let selectedPallets = []; // Array of objects
    let isProcessing = false;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
    }

    // --- Destination location selection ---
    const searchInput = document.getElementById('locationSearchInput');
    const searchResults = document.getElementById('locationSearchResults');
    const dropdownBtn = document.getElementById('locationDropdownBtn');
    const selectedLocationId = document.getElementById('selectedLocationId');
    const selectedLocationCode = document.getElementById('selectedLocationCode');
    const locationStatus = document.getElementById('locationSelectionStatus');
    const actionHint = document.getElementById('mergeActionHint');
    let searchTimeout;
    let locationSearchSequence = 0;
    let selectedLocationKind = null;
    let skipNextLocationFocus = false;

    function updateMergeButton() {
        const hasPallets = selectedPallets.length >= 2;
        const hasLocation = selectedLocationCode.value !== '' && searchInput.value.trim() === selectedLocationCode.value;
        document.getElementById('btnProcess').disabled = isProcessing || !hasPallets || !hasLocation;
        actionHint.textContent = !hasPallets
            ? 'Tambahkan minimal dua pallet sumber.'
            : !hasLocation
                ? 'Pilih lokasi tujuan dari daftar.'
                : `${selectedPallets.length} pallet akan digabung di ${selectedLocationCode.value}. Tinjau sebelum memproses.`;
    }

    function closeLocationResults() {
        locationSearchSequence++;
        clearTimeout(searchTimeout);
        searchResults.hidden = true;
        searchInput.setAttribute('aria-expanded', 'false');
    }

    function clearSelectedLocation() {
        selectedLocationId.value = '';
        selectedLocationCode.value = '';
        selectedLocationKind = null;
        locationStatus.textContent = 'Belum ada lokasi tujuan dipilih.';
        locationStatus.classList.remove('is-selected');
        updateMergeButton();
    }

    function chooseLocation(code, id, kind) {
        locationSearchSequence++;
        searchInput.value = code;
        selectedLocationId.value = id ? String(id) : '';
        selectedLocationCode.value = code;
        selectedLocationKind = kind;
        locationStatus.textContent = `${code} dipilih — ${kind === 'source' ? 'lokasi pallet sumber' : 'lokasi kosong'}.`;
        locationStatus.classList.add('is-selected');
        closeLocationResults();
        skipNextLocationFocus = true;
        searchInput.focus();
        updateMergeButton();
    }

    function sourceLocationOptions(query) {
        const found = new Map();
        selectedPallets.forEach(p => {
            const code = p.location;
            if (!code || ['-', 'Not Stored', 'No Loc'].includes(code) || !code.toLowerCase().includes(query.toLowerCase())) return;
            found.set(code.toLowerCase(), { code, id: p.location_id || null, kind: 'source' });
        });
        return found;
    }

    function renderLocationResults(query, available, failed = false) {
        const options = sourceLocationOptions(query);
        available.forEach(loc => {
            if (!options.has(loc.code.toLowerCase())) options.set(loc.code.toLowerCase(), { code: loc.code, id: loc.id, kind: 'available' });
        });
        searchResults.replaceChildren();

        options.forEach(({ code, id, kind }) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'list-group-item list-group-item-action merge-pallet-location-option';
            const name = document.createElement('strong');
            name.textContent = code;
            const detail = document.createElement('span');
            detail.textContent = kind === 'source' ? 'Lokasi pallet sumber' : 'Lokasi kosong';
            option.append(name, detail);
            option.addEventListener('click', () => chooseLocation(code, id, kind));
            searchResults.appendChild(option);
        });

        if (!options.size || failed) {
            const note = document.createElement('div');
            note.className = 'merge-pallet-location-empty';
            note.textContent = failed
                ? 'Lokasi kosong belum dapat dimuat. Coba pencarian lagi.'
                : 'Tidak ada lokasi yang cocok. Pilih lokasi dari daftar yang tersedia.';
            searchResults.appendChild(note);
        }
        searchResults.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
    }

    function showLocationLoading() {
        const note = document.createElement('div');
        note.className = 'merge-pallet-location-empty';
        note.textContent = 'Mencari lokasi…';
        searchResults.replaceChildren(note);
        searchResults.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
    }

    function performSearch(query) {
        const requestSequence = ++locationSearchSequence;
        showLocationLoading();

        fetch(`/api/locations/search?q=${encodeURIComponent(query)}`)
            .then(res => {
                if (!res.ok) throw new Error('Pencarian lokasi gagal.');
                return res.json();
            })
            .then(data => {
                if (requestSequence !== locationSearchSequence) return;
                renderLocationResults(query, Array.isArray(data) ? data : []);
            })
            .catch(() => {
                if (requestSequence !== locationSearchSequence) return;
                renderLocationResults(query, [], true);
            });
    }

    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        locationSearchSequence++;
        if (this.value.trim() !== selectedLocationCode.value) clearSelectedLocation();
        const query = e.target.value.trim();
        showLocationLoading();
        searchTimeout = setTimeout(() => performSearch(query), 300);
    });

    searchInput.addEventListener('focus', function() {
        if (skipNextLocationFocus) {
            skipNextLocationFocus = false;
            return;
        }
        if(this.value.trim() === '') performSearch('');
        else performSearch(this.value.trim());
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLocationResults();
        if (e.key === 'ArrowDown' && !searchResults.hidden) {
            const firstOption = searchResults.querySelector('button');
            if (firstOption) {
                e.preventDefault();
                firstOption.focus();
            }
        }
    });

    searchResults.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLocationResults();
            skipNextLocationFocus = true;
            searchInput.focus();
        }
    });

    dropdownBtn.addEventListener('click', () => {
        if (!searchResults.hidden) {
            closeLocationResults();
            return;
        }
        skipNextLocationFocus = true;
        searchInput.focus();
        performSearch(searchInput.value.trim());
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target) && !dropdownBtn.contains(e.target)) {
            closeLocationResults();
        }
    });

    // --- End destination location selection ---

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
                    document.getElementById('resLocation').textContent = 'Lokasi: ' + (p.location || '-');
                    
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
        if (typeof palletData === 'string') {
            try {
                palletData = JSON.parse(palletData);
            } catch (e) {
                showToast('Data pallet tidak valid.', 'danger');
                return;
            }
        }

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
        document.getElementById('selectedPalletCount').textContent = `${selectedPallets.length} dipilih`;

        let totalBox = 0;
        let totalPcs = 0;

        if(selectedPallets.length === 0) {
            tbody.innerHTML = `<tr id="emptyRow">
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-basket3 display-6 d-block mb-2 opacity-50"></i>
                                    Belum ada pallet dipilih. Scan atau pilih dari daftar di samping.
                                </td>
                            </tr>`;
        } else {
            selectedPallets.forEach((p, index) => {
                const boxCount = parseInt(p.total_box) || 0;
                const pcsCount = parseFloat(p.total_pcs) || 0;
                
                totalBox += boxCount;
                totalPcs += pcsCount;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="ps-3 fw-bold text-dark">${escapeHtml(p.pallet_number)}</td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(p.location || '-')}</span></td>
                    <td class="text-center">${boxCount}</td>
                    <td class="text-center">${pcsCount}</td>
                    <td class="text-end pe-3">
                        <button type="button" class="btn btn-sm btn-outline-danger" aria-label="Hapus pallet ${escapeHtml(p.pallet_number)} dari pilihan" onclick="removeFromList(${index})">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

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
        if (selectedLocationKind === 'source' && !selectedPallets.some(p => p.location === selectedLocationCode.value)) {
            searchInput.value = '';
            clearSelectedLocation();
        }
        if (!searchResults.hidden) performSearch(searchInput.value.trim());
        updateMergeButton();
    }

    function processMerge() {
        if (isProcessing) return;

        if(selectedPallets.length < 2) {
            showToast('Pilih minimal 2 pallet untuk digabungkan!', 'warning');
            return;
        }

        const locationVal = selectedLocationCode.value;
        if (!locationVal || searchInput.value.trim() !== locationVal) {
            showToast('Pilih lokasi tujuan dari daftar yang tersedia.', 'warning');
            searchInput.focus();
            return;
        }

        const pallets = selectedPallets.map(p => ({ ...p }));
        const ids = pallets.map(p => p.id);
        const locId = selectedLocationId.value;
        const totalBox = pallets.reduce((sum, p) => sum + (parseInt(p.total_box, 10) || 0), 0);
        const totalPcs = pallets.reduce((sum, p) => sum + (parseFloat(p.total_pcs) || 0), 0);
        const palletRows = pallets.map(p => `<li><strong>${escapeHtml(p.pallet_number)}</strong><span>${parseInt(p.total_box, 10) || 0} Box</span></li>`).join('');

        Swal.fire({
            title: 'Tinjau Merge Pallet',
            icon: 'warning',
            html: `<div class="merge-pallet-confirm-body">
                <p>${pallets.length} pallet sumber akan digabung menjadi satu pallet baru.</p>
                <div class="merge-pallet-confirm-summary">
                    <div><span>Lokasi tujuan</span><strong>${escapeHtml(locationVal)}</strong><small>${selectedLocationKind === 'source' ? 'Lokasi pallet sumber' : 'Lokasi kosong'}</small></div>
                    <div><span>Perkiraan isi</span><strong>${totalBox} Box · ${new Intl.NumberFormat('id-ID').format(totalPcs)} PCS</strong></div>
                </div>
                <span class="merge-pallet-confirm-label">Pallet sumber</span>
                <ul class="merge-pallet-confirm-list" tabindex="0" aria-label="Pallet sumber yang akan digabung">${palletRows}</ul>
                <p class="merge-pallet-confirm-warning">Pallet sumber akan dihapus dan box aktif dipindahkan. Lokasi sumber lain dilepas. Tindakan ini tidak dapat dibatalkan.</p>
                <p class="merge-pallet-confirm-note">Kelayakan pallet dan lokasi diperiksa ulang saat proses.</p>
            </div>`,
            showCancelButton: true,
            confirmButtonText: 'Gabungkan pallet',
            cancelButtonText: 'Kembali',
            confirmButtonColor: '#0C7779',
            cancelButtonColor: '#6B7280',
            reverseButtons: true,
            focusCancel: true,
            width: '36rem',
            customClass: { popup: 'merge-pallet-confirm' }
        }).then(result => {
            if (!result.isConfirmed) return;
            if (isProcessing) return;
            isProcessing = true;

            const btn = document.getElementById('btnProcess');
            updateMergeButton();
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';

            fetch('{{ route("merge-pallet.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    pallet_ids: ids,
                    location_id: locId ? parseInt(locId, 10) : null,
                    warehouse_location: locationVal
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    showToast(data.message, 'success');
                    window.location.reload();
                } else {
                    showToast(data.message, 'danger');
                    isProcessing = false;
                    btn.innerHTML = '<i class="bi bi-intersect" aria-hidden="true"></i> Tinjau & gabungkan';
                    updateMergeButton();
                }
            })
            .catch(err => {
                showToast('Error: ' + err, 'danger');
                isProcessing = false;
                btn.innerHTML = '<i class="bi bi-intersect" aria-hidden="true"></i> Tinjau & gabungkan';
                updateMergeButton();
            });
        });
    }
</script>
@endsection
