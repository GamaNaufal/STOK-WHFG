@extends('shared.layouts.app')

@section('title', 'Pemindahan Pallet')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="p-4 text-white rounded-3 shadow-sm" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);">
                <h1 class="h2 mb-2"><i class="bi bi-arrow-left-right"></i> Pemindahan Pallet</h1>
                <p class="mb-0">Pindahkan pallet yang sama ke lokasi lain tanpa mengubah isi dan nomor pallet.</p>
            </div>
        </div>
    </div>

    <div id="alertBox" class="alert d-none" role="alert"></div>
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header text-white fw-bold" style="background-color: #0C7779;">
                    <i class="bi bi-upc-scan"></i> 1. Pilih Pallet
                </div>
                <div class="card-body">
                    <label for="palletCode" class="form-label">Scan atau masukkan nomor pallet</label>
                    <div class="input-group">
                        <input type="text" id="palletCode" class="form-control" placeholder="PLT-XXXX" autofocus>
                        <button class="btn text-white" id="searchPalletButton" style="background-color: #0C7779;" type="button">
                            <i class="bi bi-search"></i> Cari
                        </button>
                    </div>
                    <div class="form-text">Pallet harus memiliki box aktif dan lokasi yang valid.</div>
                    <div id="palletSummary" class="border rounded p-3 mt-4 d-none bg-light">
                        <div class="d-flex justify-content-between">
                            <strong id="palletNumber"></strong>
                            <span class="badge bg-success">Siap dipindahkan</span>
                        </div>
                        <hr>
                        <div class="row small">
                            <div class="col-6"><span class="text-muted">Lokasi saat ini</span><br><strong id="currentLocation"></strong></div>
                            <div class="col-3"><span class="text-muted">Box</span><br><strong id="totalBox"></strong></div>
                            <div class="col-3"><span class="text-muted">PCS</span><br><strong id="totalPcs"></strong></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0"><i class="bi bi-list-check"></i> Pallet yang dapat dipindahkan</h6>
                            <span class="badge bg-secondary">{{ $pallets->count() }}</span>
                        </div>
                        <input type="search" id="palletListSearch" class="form-control form-control-sm mb-2" placeholder="Filter nomor pallet atau lokasi...">
                        <div id="availablePalletList" class="list-group border rounded" style="max-height: 360px; overflow-y: auto;">
                            @forelse($pallets as $availablePallet)
                                <button type="button"
                                    class="list-group-item list-group-item-action text-start available-pallet"
                                    data-pallet-number="{{ $availablePallet->pallet_number }}"
                                    data-search="{{ strtolower($availablePallet->pallet_number . ' ' . ($availablePallet->stockLocation?->warehouse_location ?? '')) }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <strong>{{ $availablePallet->pallet_number }}</strong>
                                        <span class="badge bg-success">Dapat dipindahkan</span>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> {{ $availablePallet->stockLocation?->warehouse_location ?? '-' }}
                                        &bull; {{ $availablePallet->boxes_count }} Box
                                        &bull; {{ number_format($availablePallet->boxes->sum('pcs_quantity')) }} PCS
                                    </small>
                                </button>
                            @empty
                                <div class="p-3 text-center text-muted small">Tidak ada pallet yang dapat dipindahkan.</div>
                            @endforelse
                            <div id="noPalletMatch" class="p-3 text-center text-muted small d-none">Pallet tidak ditemukan.</div>
                        </div>
                        <div class="form-text">Daftar ini dapat berubah jika ada proses delivery atau picking baru.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-bold">
                    <i class="bi bi-geo-alt"></i> 2. Pilih Lokasi Tujuan
                </div>
                <div class="card-body">
                    <label for="locationSearch" class="form-label">Lokasi tujuan</label>
                    <div class="position-relative">
                        <input type="text" id="locationSearch" class="form-control" placeholder="Cari lokasi kosong..." autocomplete="off" disabled>
                        <div id="locationResults" class="list-group position-absolute w-100 shadow" style="z-index: 10; display: none;"></div>
                    </div>
                    <div class="mt-2">
                    <div class="small text-muted mb-1">Lokasi yang bisa ditempati</div>
                    <div id="availableLocationList" class="list-group border rounded" style="max-height: 180px; overflow-y: auto;">
                        @forelse($availableLocations as $availableLocation)
                            <button type="button"
                                class="list-group-item list-group-item-action py-2 available-location"
                                data-location-id="{{ $availableLocation->id }}"
                                data-location-code="{{ $availableLocation->code }}"
                                data-search="{{ strtolower($availableLocation->code) }}"
                                disabled>
                                <i class="bi bi-geo-alt me-1"></i>{{ $availableLocation->code }}
                            </button>
                        @empty
                            <div class="p-2 text-center text-muted small">Tidak ada lokasi kosong.</div>
                        @endforelse
                        <div id="noLocationMatch" class="p-2 text-center text-muted small d-none">Lokasi tidak ditemukan.</div>
                    </div>
                    </div>
                    <input type="hidden" id="targetLocationId">
                    <div id="targetSummary" class="small text-success mt-2 d-none"><i class="bi bi-check-circle"></i> Tujuan: <strong id="targetLocationCode"></strong></div>
                    <div class="alert alert-info small mt-4 mb-0">
                        <i class="bi bi-info-circle"></i> Lokasi tujuan harus terdaftar di Master Location dan masih kosong saat konfirmasi.
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button type="button" id="moveButton" class="btn btn-primary" disabled>
                            <i class="bi bi-arrow-left-right"></i> Konfirmasi Pemindahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-light fw-bold"><i class="bi bi-clock-history"></i> Pemindahan Terakhir</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Pallet</th><th>Perpindahan</th><th>Oleh</th><th>Waktu</th></tr></thead>
                <tbody>
                @forelse($moveHistory as $history)
                    @php($old = $history->getOldValuesArray())
                    @php($new = $history->getNewValuesArray())
                    <tr>
                        <td>{{ $new['pallet_number'] ?? $history->model_id }}</td>
                        <td>{{ $old['location'] ?? '-' }} <i class="bi bi-arrow-right"></i> {{ $new['location'] ?? '-' }}</td>
                        <td>{{ $history->user?->name ?? '-' }}</td>
                        <td>{{ $history->created_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">Belum ada histori pemindahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const palletCode = document.getElementById('palletCode');
    const searchButton = document.getElementById('searchPalletButton');
    const locationSearch = document.getElementById('locationSearch');
    const locationResults = document.getElementById('locationResults');
    const targetLocationId = document.getElementById('targetLocationId');
    const targetSummary = document.getElementById('targetSummary');
    const moveButton = document.getElementById('moveButton');
    const availableLocationList = document.getElementById('availableLocationList');
    let pallet = null;
    let searchTimer = null;

    const showAlert = (message, type = 'danger') => {
        const alert = document.getElementById('alertBox');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        alert.classList.remove('d-none');
    };

    const resetTarget = () => {
        targetLocationId.value = '';
        targetSummary.classList.add('d-none');
        moveButton.disabled = true;
    };

    const loadPallet = async (code) => {
        if (!code) return showAlert('Masukkan nomor pallet terlebih dahulu.');
        searchButton.disabled = true;
        try {
            const response = await fetch(`{{ route('move-pallet.search') }}?code=${encodeURIComponent(code)}`);
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Pallet tidak dapat dipilih.');
            pallet = data.pallet;
            document.getElementById('palletSummary').classList.remove('d-none');
            document.getElementById('palletNumber').textContent = pallet.pallet_number;
            document.getElementById('currentLocation').textContent = pallet.location;
            document.getElementById('totalBox').textContent = pallet.total_box;
            document.getElementById('totalPcs').textContent = Number(pallet.total_pcs).toLocaleString('id-ID');
            locationSearch.disabled = false;
            document.querySelectorAll('.available-location').forEach((button) => {
                button.disabled = Number(button.dataset.locationId) === Number(pallet.location_id);
            });
            locationSearch.value = '';
            resetTarget();
            document.getElementById('alertBox').classList.add('d-none');
            locationSearch.focus();
        } catch (error) {
            pallet = null;
            document.getElementById('palletSummary').classList.add('d-none');
            locationSearch.disabled = true;
            resetTarget();
            showAlert(error.message);
        } finally {
            searchButton.disabled = false;
        }
    };

    searchButton.addEventListener('click', () => loadPallet(palletCode.value.trim()));

    palletCode.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') loadPallet(palletCode.value.trim());
    });

    document.querySelectorAll('.available-pallet').forEach((button) => {
        button.addEventListener('click', () => {
            palletCode.value = button.dataset.palletNumber;
            loadPallet(button.dataset.palletNumber);
        });
    });

    document.getElementById('palletListSearch').addEventListener('input', (event) => {
        const query = event.target.value.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('.available-pallet').forEach((button) => {
            const matches = button.dataset.search.includes(query);
            button.classList.toggle('d-none', !matches);
            if (matches) visible++;
        });
        document.getElementById('noPalletMatch').classList.toggle('d-none', visible !== 0);
    });

    locationSearch.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = locationSearch.value.trim();
        if (!pallet || !query) {
            locationResults.style.display = 'none';
            resetTarget();
            filterAvailableLocations('');
            return;
        }
        resetTarget();
        filterAvailableLocations(query);
        searchTimer = setTimeout(async () => {
            const response = await fetch(`/api/locations/search?q=${encodeURIComponent(query)}`);
            const locations = await response.json();
            const filtered = locations.filter(location => Number(location.id) !== Number(pallet.location_id));
            locationResults.innerHTML = filtered.length
                ? filtered.map(location => `<button type="button" class="list-group-item list-group-item-action" data-id="${location.id}" data-code="${location.code}"><i class="bi bi-geo-alt"></i> ${location.code}</button>`).join('')
                : '<div class="list-group-item text-muted">Tidak ada lokasi kosong.</div>';
            locationResults.style.display = 'block';
        }, 250);
    });

    const filterAvailableLocations = (query) => {
        const normalizedQuery = query.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('.available-location').forEach((button) => {
            const isCurrentLocation = pallet && Number(button.dataset.locationId) === Number(pallet.location_id);
            const matches = !isCurrentLocation && button.dataset.search.includes(normalizedQuery);
            button.classList.toggle('d-none', !matches);
            if (matches) visible++;
        });
        document.getElementById('noLocationMatch').classList.toggle('d-none', visible !== 0);
    };

    availableLocationList.addEventListener('click', (event) => {
        const option = event.target.closest('.available-location');
        if (!option || option.disabled || !pallet) return;
        targetLocationId.value = option.dataset.locationId;
        document.getElementById('targetLocationCode').textContent = option.dataset.locationCode;
        targetSummary.classList.remove('d-none');
        moveButton.disabled = false;
        moveButton.innerHTML = '<i class="bi bi-arrow-left-right"></i> Konfirmasi Pemindahan';
        locationSearch.value = option.dataset.locationCode;
        locationResults.style.display = 'none';
        filterAvailableLocations(option.dataset.locationCode);
    });

    locationResults.addEventListener('click', (event) => {
        const option = event.target.closest('[data-id]');
        if (!option) return;
        targetLocationId.value = option.dataset.id;
        document.getElementById('targetLocationCode').textContent = option.dataset.code;
        targetSummary.classList.remove('d-none');
        moveButton.disabled = false;
        locationSearch.value = option.dataset.code;
        locationResults.style.display = 'none';
    });

    const submitMove = async () => {
        if (!pallet || !targetLocationId.value) return;
        moveButton.disabled = true;
        moveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memproses...';
        try {
            const response = await fetch('{{ route('move-pallet.store') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify({pallet_id: pallet.id, location_id: Number(targetLocationId.value)})
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Pemindahan gagal.');
            showAlert(data.message, 'success');
            pallet.location = document.getElementById('targetLocationCode').textContent;
            pallet.location_id = Number(targetLocationId.value);
            document.getElementById('currentLocation').textContent = pallet.location;
            document.querySelectorAll('.available-location').forEach((button) => {
                button.disabled = Number(button.dataset.locationId) === Number(pallet.location_id);
            });
            moveButton.disabled = true;
            moveButton.innerHTML = '<i class="bi bi-check2-circle"></i> Pemindahan Berhasil';
            filterAvailableLocations('');
        } catch (error) {
            showAlert(error.message);
            moveButton.disabled = false;
            moveButton.innerHTML = '<i class="bi bi-arrow-left-right"></i> Konfirmasi Pemindahan';
        }
    };

    moveButton.addEventListener('click', () => {
        if (!pallet || !targetLocationId.value) return;

        const targetLocation = document.getElementById('targetLocationCode').textContent;
        WarehouseAlert.confirm({
            title: 'Konfirmasi Pemindahan Pallet',
            message: `Anda akan memindahkan <strong style="color: #0C7779;">${pallet.pallet_number}</strong> ke lokasi baru.`,
            warningItems: [
                'Pastikan lokasi tujuan sudah sesuai dengan lokasi fisik pallet',
                'Pallet tetap menggunakan nomor dan isi yang sama',
                'Lokasi tujuan harus tetap kosong saat proses dikonfirmasi'
            ],
            infoText: `
                <strong>Detail Pemindahan</strong><br>
                <span class="text-muted">Dari:</span> <strong style="color: #0C7779;">${pallet.location}</strong>
                <i class="bi bi-arrow-right mx-2"></i>
                <span class="text-muted">Ke:</span> <strong style="color: #0C7779;">${targetLocation}</strong><br>
                <span class="text-muted">Isi pallet:</span> ${pallet.total_box} Box &bull; ${Number(pallet.total_pcs).toLocaleString('id-ID')} PCS
            `,
            confirmText: 'Ya, Pindahkan!',
            cancelText: 'Batal',
            confirmColor: '#0C7779',
            onConfirm: submitMove
        });
    });
})();
</script>
@endpush
