@extends('shared.layouts.app')

@section('title', 'Assign Delivery - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                        color: white;
                        padding: 28px 24px;
                        border-radius: 12px;
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <h1 class="h3" style="margin: 0 0 6px 0; font-weight: 700;">
                    <i class="bi bi-send"></i> Assign Delivery
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 14px;">
                    Pilih delivery order, lalu assign box dari stok existing atau input box baru.
                </p>
            </div>
        </div>
    </div>

    <div class="card shadow" style="border: none; border-radius: 12px; overflow: hidden;">
        <div class="card-body" style="padding: 24px;">
            <div class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label class="form-label fw-semibold">Delivery Order</label>
                    <select class="form-select" id="deliveryOrderSelect">
                        <option value="">-- Pilih Delivery Order --</option>
                        @foreach($deliveryOrders as $order)
                            <option value="{{ $order->id }}">
                                #{{ $order->id }} - {{ $order->customer_name ?? '-' }}
                                ({{ optional($order->delivery_date)->format('d/m/Y') ?? '-' }})
                                - {{ strtoupper($order->status ?? '-') }}
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-2">Pilih delivery order terlebih dahulu sebelum assign atau input box.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mt-4" style="border: none; border-radius: 12px;">
        <div class="card-body" style="padding: 24px;">
            <ul class="nav nav-tabs" id="assignTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-existing-tab" data-bs-toggle="tab" data-bs-target="#tab-existing" type="button" role="tab">
                        Ambil dari Stok
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-input-tab" data-bs-toggle="tab" data-bs-target="#tab-input" type="button" role="tab">
                        Input Box Baru
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="assignTabContent">
                <div class="tab-pane fade show active pt-4" id="tab-existing" role="tabpanel">
                    <div class="row g-3 align-items-end">
                        <div class="col-lg-8">
                            <label class="form-label fw-semibold">Cari Box atau Pallet</label>
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

                    <div id="deliveryAssignResult" class="alert mt-3" style="display: none;"></div>

                    <div class="row g-4 mt-2">
                        <div class="col-lg-5">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Pallet Tersedia</h6>
                                <span class="text-muted small" id="deliveryAssignPalletCount">0</span>
                            </div>
                            <div class="list-group" id="deliveryAssignPalletList" style="max-height: 360px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-lg-7">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Box Tersedia</h6>
                                <span class="text-muted small" id="deliveryAssignBoxCount">0</span>
                            </div>
                            <div class="list-group" id="deliveryAssignBoxList" style="max-height: 360px; overflow-y: auto;"></div>
                        </div>
                    </div>

                    <div class="border-top mt-4 pt-3 d-flex flex-wrap gap-3 align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold">Ringkasan Pilihan</div>
                            <div class="text-muted small" id="deliveryAssignSummary">Belum ada box/pallet dipilih.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="deliveryAssignClearBtn">Reset Pilihan</button>
                            <button type="button" class="btn btn-primary" id="deliveryAssignSubmit">Assign Delivery</button>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade pt-4" id="tab-input" role="tabpanel">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Pastikan delivery order sudah dipilih sebelum melakukan scan.
                    </div>

                    <div class="card shadow" style="border: none; border-radius: 12px; overflow: hidden;">
                        <div class="card-body" style="padding: 30px;">
                            @include('operator.stock-input.partials.scanner')
                            @include('operator.stock-input.partials.pallet')
                            @include('operator.stock-input.partials.location')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/operator-stock-input.css') }}">
@endsection

@section('scripts')
<script>
    window.deliveryAssignConfig = {
        csrfToken: '{{ csrf_token() }}',
        searchUrl: '{{ route('delivery-assign.search') }}',
        assignUrl: '{{ route('delivery-assign.assign') }}'
    };

    window.stockInputConfig = {
        csrfToken: '{{ csrf_token() }}',
        scanBarcodeUrl: '{{ route("stock-input.scan-barcode") }}',
        scanPartUrl: '{{ route("stock-input.scan-part") }}',
        searchExistingPalletUrl: '{{ route("stock-input.search-existing-pallet") }}',
        selectExistingPalletUrl: '{{ route("stock-input.select-existing-pallet") }}',
        getPalletDataUrl: '{{ route("stock-input.get-pallet-data") }}',
        clearSessionUrl: '{{ route("stock-input.clear-session") }}',
        storeUrl: '{{ route("stock-input.store") }}',
        indexUrl: '{{ route("delivery-assign.index") }}',
        locationSearchUrl: '/api/locations/search',
        requireDeliveryOrder: true,
        assignAfterSaveUrl: '{{ route("delivery-assign.assign-input") }}'
    };
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('js/pages/operator-stock-input.js') }}"></script>
<script src="{{ asset('js/pages/delivery-assign.js') }}"></script>
@endsection
