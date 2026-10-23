@extends('shared.layouts.app')

@section('title', 'Assign Delivery - Warehouse FG Yamato')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/delivery-assign.css') }}">
@endsection

@section('content')
<div class="container-fluid delivery-assign-page" id="deliveryAssignPage">
    <header class="delivery-assign-header">
        <span class="delivery-assign-eyebrow">Delivery / Alokasi stok</span>
        <h1><i class="bi bi-send" aria-hidden="true"></i> Assign Delivery</h1>
        <p>Pilih order, siapkan box baru atau stok yang tersedia, lalu periksa pilihan sebelum assign.</p>
    </header>

    <section class="card delivery-assign-panel delivery-assign-order" aria-labelledby="deliveryAssignOrderTitle">
        <div class="card-body p-3 p-md-4">
            <div class="delivery-assign-section-head">
                <span class="delivery-assign-step" aria-hidden="true">01</span>
                <div>
                    <h2 id="deliveryAssignOrderTitle">Pilih delivery order</h2>
                    <p>Assignment hanya dapat dimulai untuk order yang tersedia di daftar ini.</p>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-lg-8 col-xl-6">
                    <label class="form-label fw-semibold" for="deliveryOrderSelect">Delivery Order</label>
                    <select class="form-select" id="deliveryOrderSelect" aria-describedby="deliveryAssignOrderHint">
                        <option value="">-- Pilih Delivery Order --</option>
                        @foreach($deliveryOrders as $order)
                            <option value="{{ $order->id }}">
                                #{{ $order->id }} - {{ $order->customer_name ?? '-' }}
                                ({{ optional($order->delivery_date)->format('d/m/Y') ?? '-' }})
                                - {{ strtoupper($order->status ?? '-') }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-2" id="deliveryAssignOrderHint">Pilih delivery order terlebih dahulu sebelum input atau assign.</small>
                    <div id="deliveryAssignPartStatus" class="alert alert-info mt-3 mb-0 py-2 small" style="display: none;" role="status" aria-live="polite"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="card delivery-assign-panel delivery-assign-new-box mt-4" aria-labelledby="deliveryAssignNewBoxTitle">
        <div class="card-body p-3 p-md-4">
            <div class="delivery-assign-section-head delivery-assign-section-head-split">
                <span class="delivery-assign-step" aria-hidden="true">02</span>
                <div>
                    <h2 id="deliveryAssignNewBoxTitle">Input box baru</h2>
                    <p>Box baru wajib memilih pallet. Pallet baru wajib memilih lokasi.</p>
                </div>
                <span class="delivery-assign-context">Part mengikuti delivery order</span>
            </div>

            <div class="row g-3 align-items-end mb-3 delivery-assign-pallet-fields">
                <div class="col-12">
                    <div class="fw-semibold">Pallet untuk Input Box Baru</div>
                    <div class="text-muted small">Pallet wajib diisi. Jika pallet baru: pilih lokasi kosong. Jika pallet existing: lokasi mengikuti lokasi pallet.</div>
                </div>

                <div class="col-lg-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="deliveryAssignPalletMode" id="deliveryAssignPalletModeNew" value="new" checked>
                        <label class="form-check-label" for="deliveryAssignPalletModeNew">Pallet baru</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="deliveryAssignPalletMode" id="deliveryAssignPalletModeExisting" value="existing">
                        <label class="form-check-label" for="deliveryAssignPalletModeExisting">Pallet existing</label>
                    </div>
                </div>

                <div class="col-lg-4" id="deliveryAssignNewPalletNumberSection">
                    <label class="form-label fw-semibold" for="deliveryAssignNewPalletNumber">No Pallet Baru</label>
                    <input type="text" class="form-control" id="deliveryAssignNewPalletNumber" placeholder="Dibuat otomatis saat assign" readonly>
                    <small class="text-muted d-block mt-1">Nomor unik dialokasikan sistem saat proses berhasil.</small>
                </div>

                <div class="col-lg-5" id="deliveryAssignExistingPalletSection" style="display: none;">
                    <label class="form-label fw-semibold" for="deliveryAssignExistingPalletSearchInput">Cari Pallet Existing</label>
                    <input type="text" class="form-control" id="deliveryAssignExistingPalletSearchInput" placeholder="Ketik no pallet..." autocomplete="off">
                    <input type="hidden" id="deliveryAssignSelectedExistingPalletId" value="">
                    <input type="text" class="form-control mt-2" id="deliveryAssignSelectedExistingPalletText" value="" placeholder="Belum ada pallet dipilih" readonly>
                    <div class="list-group mt-2" id="deliveryAssignExistingPalletSearchResults" style="display: none; max-height: 220px; overflow-y: auto;"></div>
                </div>

                <div class="col-lg-6" id="deliveryAssignLocationSection">
                    <label class="form-label fw-semibold" for="deliveryAssignLocationSearchInput">Lokasi (untuk pallet baru)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                        <input type="text" class="form-control" id="deliveryAssignLocationSearchInput" placeholder="Cari lokasi kosong..." autocomplete="off">
                        <button class="btn btn-outline-primary" type="button" id="deliveryAssignLocationDropdownBtn" aria-label="Tampilkan lokasi tersedia" title="Tampilkan lokasi tersedia">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <input type="hidden" id="deliveryAssignLocationId" value="">
                    <input type="hidden" id="deliveryAssignLocationCode" value="">
                    <div class="list-group mt-2" id="deliveryAssignLocationSearchResults" style="display: none; max-height: 220px; overflow-y: auto;"></div>
                    <small class="text-muted d-block mt-1">Pilih dari Master Location; ketersediaannya diperiksa ulang saat assign.</small>
                </div>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-lg-3">
                    <label class="form-label fw-semibold" for="deliveryAssignNewBoxNumber">ID Box</label>
                    <input type="text" class="form-control" id="deliveryAssignNewBoxNumber" placeholder="Scan/ketik ID box" minlength="8" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" autocomplete="off">
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-semibold" for="deliveryAssignNewBoxPart">No Part</label>
                    <select class="form-select" id="deliveryAssignNewBoxPart" disabled>
                        <option value="">Pilih delivery order terlebih dahulu</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-semibold" for="deliveryAssignNewBoxQty">Qty PCS</label>
                    <input type="number" class="form-control" id="deliveryAssignNewBoxQty" min="1" placeholder="Mengikuti Master Part" autocomplete="off" readonly>
                </div>
                <div class="col-lg-3 d-grid">
                    <button type="button" class="btn btn-success" id="deliveryAssignAddNewBox">
                        <i class="bi bi-plus" aria-hidden="true"></i> Tambah box
                    </button>
                </div>
            </div>
            <small class="text-muted d-block mt-2">
                Qty box baru mengikuti fixed qty Master Part. Untuk box not full, gunakan
                <a href="{{ route('box-not-full.create') }}">Request Box Not Full</a> dan tunggu approval Supervisi.
            </small>
            <div id="deliveryAssignNewBoxError" class="alert alert-danger mt-3" style="display: none;"></div>
        </div>
    </section>

    <section class="card delivery-assign-panel delivery-assign-stock mt-4" aria-labelledby="deliveryAssignStockTitle">
        <div class="card-body p-3 p-md-4">
            <div class="delivery-assign-section-head">
                <span class="delivery-assign-step" aria-hidden="true">03</span>
                <div>
                    <h2 id="deliveryAssignStockTitle">Pilih stok tersedia</h2>
                    <p>Cari box atau pallet yang sesuai dengan kebutuhan order.</p>
                </div>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold" for="deliveryAssignSearchInput">Cari Box atau Pallet</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="deliveryAssignSearchInput"
                               placeholder="Cari box, part number, pallet, atau lokasi..." autocomplete="off">
                        <button class="btn btn-outline-primary" type="button" id="deliveryAssignSearchBtn">
                            Cari
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-3">
                <div class="col-lg-5">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Pallet Tersedia</h6>
                        <span class="text-muted small" id="deliveryAssignPalletCount">0</span>
                    </div>
                    <div class="list-group" id="deliveryAssignPalletList" style="max-height: 360px; overflow-y: auto;" role="group" aria-label="Pallet tersedia"></div>
                </div>
                <div class="col-lg-7">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Box Tersedia</h6>
                        <span class="text-muted small" id="deliveryAssignBoxCount">0</span>
                    </div>
                    <div class="list-group" id="deliveryAssignBoxList" style="max-height: 360px; overflow-y: auto;" role="group" aria-label="Box tersedia"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="card delivery-assign-panel delivery-assign-review mt-4" aria-labelledby="deliveryAssignReviewTitle">
        <div class="card-body p-3 p-md-4">
            <div class="delivery-assign-review-head">
                <span class="delivery-assign-step" aria-hidden="true">04</span>
                <div>
                    <h2 id="deliveryAssignReviewTitle">Periksa pilihan</h2>
                    <p id="deliveryAssignSummary" role="status" aria-live="polite">Belum ada box/pallet dipilih.</p>
                </div>
                <div class="delivery-assign-actions">
                    <button type="button" class="btn btn-outline-secondary" id="deliveryAssignClearBtn">Reset Pilihan</button>
                    <button type="button" class="btn btn-primary" id="deliveryAssignSubmit">Assign Delivery</button>
                </div>
            </div>

            <div id="deliveryAssignResult" class="alert mt-3" style="display: none;"></div>

            <div class="delivery-assign-selection mt-3">
                <div class="delivery-assign-list-label">Daftar pilihan <span>Urut sesuai input atau klik</span></div>
                <div id="deliveryAssignSelectedList" class="list-group" style="max-height: 420px; overflow-y: auto;" role="group" aria-label="Box dan pallet yang dipilih"></div>
            </div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
    window.deliveryAssignConfig = {
        csrfToken: '{{ csrf_token() }}',
        userId: {{ (int) auth()->id() }},
        searchUrl: '/delivery-assign/search',
        previewUrl: '{{ route('delivery-assign.preview') }}',
        assignUrl: '/delivery-assign/assign',
        deliveryOrderPartsUrl: '/delivery-assign/delivery-orders/__ORDER__/parts',
        palletBoxesUrl: '/delivery-assign/pallets/__PALLET__/boxes',
        newBoxPalletSearchUrl: '{{ route("delivery-assign.search-new-box-pallets") }}',
        locationSearchUrl: '/api/locations/search'
    };
</script>
<script src="{{ asset('js/pages/delivery-assign.js') }}"></script>
@endsection
