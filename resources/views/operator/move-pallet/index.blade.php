@extends('shared.layouts.app')

@section('title', 'Pemindahan Pallet')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/move-pallet.css') }}">
@endsection

@section('content')
<div class="container-fluid move-pallet-page" id="movePalletPage">
    <header class="move-pallet-header">
        <span class="move-pallet-eyebrow">Inventory / Lokasi pallet</span>
        <h1><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Pemindahan Pallet</h1>
        <p>Pilih pallet aktif, tentukan lokasi kosong, lalu periksa perpindahan sebelum menyimpan. Nomor dan isi pallet tetap sama.</p>
    </header>

    <div id="alertBox" class="alert d-none" role="alert"></div>
    <div class="row g-3 g-xl-4 move-pallet-workflow">
        <div class="col-lg-5">
            <div class="card move-pallet-panel h-100">
                <div class="card-header move-pallet-panel-head">
                    <span class="move-pallet-step" aria-hidden="true">01</span>
                    <div>
                        <h2>Pilih pallet</h2>
                        <p>Scan nomor pallet atau pilih dari daftar yang tersedia.</p>
                    </div>
                </div>
                <div class="card-body">
                    <label for="palletCode" class="form-label fw-semibold">Nomor pallet</label>
                    <div class="input-group">
                        <input type="text" id="palletCode" class="form-control" placeholder="Scan atau masukkan nomor pallet" aria-describedby="palletCodeHint palletSearchStatus" autofocus>
                        <button class="btn btn-primary" id="searchPalletButton" type="button">
                            <i class="bi bi-search" aria-hidden="true"></i> Cari
                        </button>
                    </div>
                    <div id="palletCodeHint" class="form-text">Box assigned atau not-full boleh ikut dipindahkan. Sesi picking berstatus scanning, blocked, atau approved tetap mengunci pallet.</div>
                    <div id="palletSearchStatus" class="move-pallet-search-status d-none" role="status" aria-live="polite"></div>
                    <div id="palletSummary" class="move-pallet-summary mt-4 d-none" aria-live="polite">
                        <div class="move-pallet-summary-head">
                            <span class="move-pallet-summary-label">Pallet terpilih</span>
                            <strong id="palletNumber"></strong>
                        </div>
                        <div class="move-pallet-summary-stats">
                            <div><span>Lokasi saat ini</span><strong id="currentLocation"></strong></div>
                            <div><span>Box aktif</span><strong id="totalBox"></strong></div>
                            <div><span>Total PCS</span><strong id="totalPcs"></strong></div>
                        </div>
                    </div>
                    <div class="move-pallet-choices mt-4">
                        <div class="move-pallet-list-head">
                            <h3 id="availablePalletTitle">Pallet yang dapat dipindahkan</h3>
                            <span class="move-pallet-count">{{ $pallets->count() }} pallet</span>
                        </div>
                        <label for="palletListSearch" class="visually-hidden">Filter pallet yang dapat dipindahkan</label>
                        <input type="search" id="palletListSearch" class="form-control mb-2" placeholder="Filter nomor pallet atau lokasi">
                        <div id="availablePalletList" class="list-group move-pallet-option-list" aria-labelledby="availablePalletTitle">
                            @forelse($pallets as $availablePallet)
                                <button type="button"
                                    class="list-group-item list-group-item-action text-start available-pallet"
                                    data-pallet-number="{{ $availablePallet->pallet_number }}"
                                    data-search="{{ strtolower($availablePallet->pallet_number . ' ' . ($availablePallet->stockLocation?->warehouse_location ?? '')) }}">
                                    <div class="move-pallet-option-head">
                                        <strong>{{ $availablePallet->pallet_number }}</strong>
                                        <span class="move-pallet-option-status">Tersedia</span>
                                    </div>
                                    <span class="move-pallet-option-meta">
                                        <span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $availablePallet->stockLocation?->warehouse_location ?? '-' }}</span>
                                        <span>{{ $availablePallet->boxes_count }} Box</span>
                                        <span>{{ number_format($availablePallet->boxes->sum('pcs_quantity')) }} PCS</span>
                                    </span>
                                </button>
                            @empty
                                <div class="p-3 text-center text-muted small">Tidak ada pallet yang dapat dipindahkan.</div>
                            @endforelse
                            <div id="noPalletMatch" class="p-3 text-center text-muted small d-none">Pallet tidak ditemukan.</div>
                        </div>
                        <div class="form-text">Ketersediaan diperiksa ulang saat pallet dipilih dan saat pemindahan disimpan.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card move-pallet-panel h-100">
                <div class="card-header move-pallet-panel-head">
                    <span class="move-pallet-step" aria-hidden="true">02</span>
                    <div>
                        <h2>Pilih lokasi tujuan</h2>
                        <p>Lokasi kosong dapat dipilih setelah pallet berhasil dimuat.</p>
                    </div>
                </div>
                <div class="card-body">
                    <label for="locationSearch" class="form-label fw-semibold">Cari lokasi kosong</label>
                    <div class="position-relative">
                        <input type="text" id="locationSearch" class="form-control" placeholder="Ketik kode lokasi tujuan" autocomplete="off" aria-describedby="locationSearchHint locationSearchStatus" disabled>
                        <div id="locationResults" class="list-group position-absolute w-100 move-pallet-location-results" style="display: none;"></div>
                    </div>
                    <div id="locationSearchHint" class="form-text">Pilih kode dari hasil pencarian atau daftar di bawah. Kode lokasi tidak dapat diketik bebas sebagai tujuan.</div>
                    <div id="locationSearchStatus" class="move-pallet-search-status d-none" role="status" aria-live="polite"></div>
                    <div class="move-pallet-choices mt-4">
                    <div class="move-pallet-list-head"><h3 id="availableLocationTitle">Lokasi yang bisa ditempati</h3><span class="move-pallet-count">{{ $availableLocations->count() }} lokasi</span></div>
                    <div id="availableLocationList" class="list-group move-pallet-option-list move-pallet-location-list" aria-labelledby="availableLocationTitle">
                        @forelse($availableLocations as $availableLocation)
                            <button type="button"
                                class="list-group-item list-group-item-action available-location"
                                data-location-id="{{ $availableLocation->id }}"
                                data-location-code="{{ $availableLocation->code }}"
                                data-search="{{ strtolower($availableLocation->code) }}"
                                disabled>
                                <i class="bi bi-geo-alt me-2" aria-hidden="true"></i>{{ $availableLocation->code }}
                            </button>
                        @empty
                            <div class="p-2 text-center text-muted small">Tidak ada lokasi kosong.</div>
                        @endforelse
                        <div id="noLocationMatch" class="p-2 text-center text-muted small d-none">Lokasi tidak ditemukan.</div>
                    </div>
                    </div>
                    <input type="hidden" id="targetLocationId">
                    <div id="targetSummary" class="move-pallet-target mt-4 d-none" aria-live="polite">
                        <span><i class="bi bi-check-circle" aria-hidden="true"></i> Lokasi tujuan terpilih</span>
                        <strong id="targetLocationCode"></strong>
                    </div>
                    <p class="move-pallet-note mt-4 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Lokasi tujuan harus tetap kosong saat pemindahan dikonfirmasi. Sistem akan memeriksanya kembali sebelum menyimpan.</p>
                    <div class="move-pallet-action mt-4">
                        <button type="button" id="moveButton" class="btn btn-primary" disabled>
                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i> Konfirmasi Pemindahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section class="card move-pallet-panel move-pallet-history mt-4" aria-labelledby="moveHistoryTitle">
        <header class="move-pallet-history-head">
            <div>
                <span class="move-pallet-eyebrow">Jejak aktivitas</span>
                <h2 id="moveHistoryTitle">Riwayat pemindahan pallet</h2>
                <p>Lokasi asal dan tujuan dari setiap pemindahan yang berhasil.</p>
            </div>
            <span class="move-pallet-history-count">{{ $moveHistory->count() }} catatan</span>
        </header>
        @if($moveHistory->isNotEmpty())
            <div class="table-responsive move-pallet-history-scroll" tabindex="0" role="region" aria-label="Riwayat pemindahan pallet, geser untuk melihat seluruh kolom">
                <table class="table mb-0 move-pallet-history-table">
                    <caption class="visually-hidden">Maksimal 20 pemindahan pallet terbaru.</caption>
                    <colgroup><col class="move-pallet-history-pallet-col"><col class="move-pallet-history-route-col"><col class="move-pallet-history-actor-col"><col class="move-pallet-history-time-col"></colgroup>
                    <thead><tr><th scope="col">Pallet</th><th scope="col">Perpindahan lokasi</th><th scope="col">Operator</th><th scope="col">Waktu</th></tr></thead>
                    <tbody>
                    @foreach($moveHistory as $history)
                        @php($old = $history->getOldValuesArray())
                        @php($new = $history->getNewValuesArray())
                        <tr>
                            <th scope="row" class="move-pallet-history-pallet">{{ $new['pallet_number'] ?? $history->model_id }}</th>
                            <td>
                                <div class="move-pallet-history-route">
                                    <div><span>Asal</span><strong>{{ $old['location'] ?? '-' }}</strong></div>
                                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                    <div><span>Tujuan</span><strong>{{ $new['location'] ?? '-' }}</strong></div>
                                </div>
                            </td>
                            <td class="move-pallet-history-actor">{{ $history->user?->name ?? '-' }}</td>
                            <td>
                                @if($history->created_at)
                                    <time class="move-pallet-history-time" datetime="{{ $history->created_at->toIso8601String() }}">
                                        <strong>{{ $history->created_at->format('d M Y') }}</strong>
                                        <span>{{ $history->created_at->format('H:i') }}</span>
                                    </time>
                                @else
                                    <span class="move-pallet-history-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="move-pallet-history-foot">Menampilkan maksimal 20 catatan terbaru. Geser tabel untuk melihat kolom lain.</div>
        @else
            <div class="move-pallet-history-empty">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                <strong>Belum ada pemindahan</strong>
                <span>Riwayat akan muncul setelah pallet berhasil dipindahkan.</span>
            </div>
        @endif
    </section>
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
    const palletSearchStatus = document.getElementById('palletSearchStatus');
    const locationSearchStatus = document.getElementById('locationSearchStatus');
    let pallet = null;
    let searchTimer = null;
    let palletSearchToken = 0;
    let locationSearchToken = 0;

    const setSearchStatus = (element, message, tone = 'neutral') => {
        element.textContent = message;
        element.dataset.tone = tone;
        element.classList.toggle('d-none', !message);
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[char]);

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
        moveButton.innerHTML = '<i class="bi bi-arrow-left-right" aria-hidden="true"></i> Konfirmasi Pemindahan';
    };

    const loadPallet = async (code) => {
        if (!code) {
            setSearchStatus(palletSearchStatus, 'Masukkan nomor pallet terlebih dahulu.', 'error');
            return;
        }
        const requestToken = ++palletSearchToken;
        locationSearchToken++;
        clearTimeout(searchTimer);
        locationResults.style.display = 'none';
        setSearchStatus(locationSearchStatus, '');
        pallet = null;
        document.getElementById('palletSummary').classList.add('d-none');
        locationSearch.disabled = true;
        locationSearch.value = '';
        resetTarget();
        document.getElementById('alertBox').classList.add('d-none');
        searchButton.disabled = true;
        searchButton.textContent = 'Mencari...';
        setSearchStatus(palletSearchStatus, `Memeriksa pallet ${code}...`);
        try {
            const response = await fetch(`{{ route('move-pallet.search') }}?code=${encodeURIComponent(code)}`);
            const data = await response.json();
            if (requestToken !== palletSearchToken) return;
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
            setSearchStatus(palletSearchStatus, `Pallet ${pallet.pallet_number} ditemukan. Pilih lokasi tujuan.`, 'success');
            filterAvailableLocations('');
            locationSearch.focus();
        } catch (error) {
            if (requestToken !== palletSearchToken) return;
            pallet = null;
            document.getElementById('palletSummary').classList.add('d-none');
            locationSearch.disabled = true;
            resetTarget();
            setSearchStatus(palletSearchStatus, error.message || 'Pencarian pallet gagal. Coba lagi.', 'error');
        } finally {
            if (requestToken === palletSearchToken) {
                searchButton.disabled = false;
                searchButton.innerHTML = '<i class="bi bi-search" aria-hidden="true"></i> Cari';
            }
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
        const requestToken = ++locationSearchToken;
        const query = locationSearch.value.trim();
        locationResults.style.display = 'none';
        if (!pallet || !query) {
            resetTarget();
            filterAvailableLocations('');
            setSearchStatus(locationSearchStatus, '');
            return;
        }
        resetTarget();
        filterAvailableLocations(query);
        setSearchStatus(locationSearchStatus, `Mencari lokasi kosong untuk “${query}”...`);
        searchTimer = setTimeout(async () => {
            try {
                const response = await fetch(`/api/locations/search?q=${encodeURIComponent(query)}`);
                if (!response.ok) throw new Error('Pencarian lokasi gagal. Coba lagi atau pilih dari daftar.');
                const locations = await response.json();
                if (requestToken !== locationSearchToken || !pallet) return;
                const filtered = (Array.isArray(locations) ? locations : [])
                    .filter(location => Number(location.id) !== Number(pallet.location_id));
                locationResults.replaceChildren();
                if (filtered.length) {
                    filtered.forEach((location) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'list-group-item list-group-item-action';
                        option.dataset.id = location.id;
                        option.dataset.code = location.code;
                        option.textContent = location.code;
                        locationResults.appendChild(option);
                    });
                } else {
                    const empty = document.createElement('div');
                    empty.className = 'list-group-item text-muted';
                    empty.textContent = 'Tidak ada lokasi kosong.';
                    locationResults.appendChild(empty);
                }
                locationResults.style.display = 'block';
                setSearchStatus(locationSearchStatus, filtered.length
                    ? `${filtered.length} lokasi kosong ditemukan. Pilih satu.`
                    : 'Tidak ada lokasi kosong yang cocok.', filtered.length ? 'success' : 'neutral');
            } catch (error) {
                if (requestToken !== locationSearchToken) return;
                locationResults.style.display = 'none';
                setSearchStatus(locationSearchStatus, 'Pencarian lokasi gagal. Coba lagi atau pilih dari daftar.', 'error');
            }
        }, 250);
    });

    locationSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') locationResults.style.display = 'none';
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
        document.getElementById('noLocationMatch').classList.toggle('d-none', !normalizedQuery || visible !== 0);
    };

    const selectTarget = (id, code, filterList = false) => {
        locationSearchToken++;
        clearTimeout(searchTimer);
        targetLocationId.value = id;
        document.getElementById('targetLocationCode').textContent = code;
        targetSummary.classList.remove('d-none');
        moveButton.disabled = false;
        moveButton.innerHTML = '<i class="bi bi-arrow-left-right"></i> Konfirmasi Pemindahan';
        locationSearch.value = code;
        locationResults.style.display = 'none';
        filterAvailableLocations(filterList ? code : '');
        setSearchStatus(locationSearchStatus, `Lokasi ${code} dipilih. Periksa perpindahan sebelum menyimpan.`, 'success');
    };

    availableLocationList.addEventListener('click', (event) => {
        const option = event.target.closest('.available-location');
        if (!option || option.disabled || !pallet) return;
        selectTarget(option.dataset.locationId, option.dataset.locationCode, true);
    });

    locationResults.addEventListener('click', (event) => {
        const option = event.target.closest('[data-id]');
        if (!option) return;
        selectTarget(option.dataset.id, option.dataset.code);
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
            setSearchStatus(palletSearchStatus, `Pallet ${pallet.pallet_number} sekarang di lokasi ${pallet.location}.`, 'success');
            setSearchStatus(locationSearchStatus, 'Pemindahan selesai. Muat ulang halaman untuk melihat daftar lokasi dan riwayat terbaru.', 'success');
        } catch (error) {
            showAlert(error.message);
            moveButton.disabled = false;
            moveButton.innerHTML = '<i class="bi bi-arrow-left-right"></i> Konfirmasi Pemindahan';
        }
    };

    moveButton.addEventListener('click', () => {
        if (!pallet || !targetLocationId.value) return;

        const targetLocation = document.getElementById('targetLocationCode').textContent;
        const boxCount = Number(pallet.total_box).toLocaleString('id-ID');
        const pcsCount = Number(pallet.total_pcs).toLocaleString('id-ID');
        const html = `
            <div class="move-pallet-confirm-body">
                <p>Periksa lokasi fisik pallet sebelum menyimpan perpindahan ini.</p>
                <div class="move-pallet-confirm-detail">
                    <span class="move-pallet-confirm-label">Pallet</span>
                    <strong class="move-pallet-confirm-pallet">${escapeHtml(pallet.pallet_number)}</strong>
                    <div class="move-pallet-confirm-route">
                        <div><span>Dari lokasi</span><strong>${escapeHtml(pallet.location)}</strong></div>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        <div><span>Ke lokasi</span><strong>${escapeHtml(targetLocation)}</strong></div>
                    </div>
                    <div class="move-pallet-confirm-contents">Isi tetap sama: <strong>${boxCount} Box</strong> · <strong>${pcsCount} PCS</strong></div>
                </div>
                <p class="move-pallet-confirm-note">Saat dikonfirmasi, lokasi asal dilepas dan lokasi tujuan ditempati pallet ini. Server memeriksa ulang kelayakan pallet dan ketersediaan lokasi; jika ada konflik, perpindahan ditolak tanpa mengubah stok.</p>
            </div>`;

        if (window.Swal && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                title: 'Periksa pemindahan pallet',
                html,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Pindahkan pallet',
                cancelButtonText: 'Kembali periksa',
                confirmButtonColor: '#0b7072',
                reverseButtons: true,
                focusCancel: true,
                width: 'min(40rem, calc(100vw - 2rem))',
                customClass: { popup: 'move-pallet-confirm' },
            }).then((result) => {
                if (result.isConfirmed) submitMove();
            });
            return;
        }

        if (window.confirm(`Pindahkan pallet ${pallet.pallet_number} dari ${pallet.location} ke ${targetLocation}?\nIsi: ${boxCount} Box · ${pcsCount} PCS. Lokasi dan kelayakan pallet akan diperiksa ulang.`)) {
            submitMove();
        }
    });
})();
</script>
@endpush
