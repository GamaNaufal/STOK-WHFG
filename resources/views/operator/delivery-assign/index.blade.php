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
                    Pilih delivery order, lalu input box baru atau pilih box/pallet dari stok.
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
                    <small class="text-muted d-block mt-2">Pilih delivery order terlebih dahulu sebelum input atau assign.</small>
                    <div id="deliveryAssignPartStatus" class="alert alert-info mt-3 mb-0 py-2 small" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mt-4" style="border: none; border-radius: 12px; overflow: hidden;">
        <div class="card-body" style="padding: 24px;">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="fw-semibold">Input Box Baru</div>
                    <div class="text-muted small">Box baru langsung di-assign ke delivery tanpa pallet/location.</div>
                </div>
                <span class="badge bg-light text-dark">Part mengikuti delivery order</span>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">ID Box</label>
                    <input type="text" class="form-control" id="deliveryAssignNewBoxNumber" placeholder="Scan/ketik ID box" autocomplete="off">
                </div>
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">No Part</label>
                    <select class="form-select" id="deliveryAssignNewBoxPart" disabled>
                        <option value="">Pilih delivery order terlebih dahulu</option>
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label fw-semibold">Qty PCS</label>
                    <input type="number" class="form-control" id="deliveryAssignNewBoxQty" min="1" placeholder="Qty" autocomplete="off">
                </div>
                <div class="col-lg-1 d-grid">
                    <button type="button" class="btn btn-success" id="deliveryAssignAddNewBox">
                        <i class="bi bi-plus"></i>
                    </button>
                </div>
            </div>
            <div id="deliveryAssignNewBoxError" class="alert alert-danger mt-3" style="display: none;"></div>
        </div>
    </div>

    <div class="card shadow mt-4" style="border: none; border-radius: 12px;">
        <div class="card-body" style="padding: 24px;">
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

            <div class="row g-4 mt-3">
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
        </div>
    </div>

    <div class="card shadow mt-4" style="border: none; border-radius: 12px;">
        <div class="card-body" style="padding: 24px;">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                <div>
                    <div class="fw-semibold">Ringkasan Assign</div>
                    <div class="text-muted small" id="deliveryAssignSummary">Belum ada box/pallet dipilih.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" id="deliveryAssignClearBtn">Reset Pilihan</button>
                    <button type="button" class="btn btn-primary" id="deliveryAssignSubmit">Assign Delivery</button>
                </div>
            </div>

            <div id="deliveryAssignResult" class="alert mt-3" style="display: none;"></div>

            <div class="mt-3">
                <div class="text-muted small mb-2">Daftar pilihan (urut sesuai input/klik)</div>
                <div id="deliveryAssignSelectedList" class="list-group" style="max-height: 420px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    window.deliveryAssignConfig = {
        csrfToken: '{{ csrf_token() }}',
        searchUrl: '/delivery-assign/search',
        assignUrl: '/delivery-assign/assign',
        deliveryOrderPartsUrl: '/delivery-assign/delivery-orders/__ORDER__/parts',
        palletBoxesUrl: '/delivery-assign/pallets/__PALLET__/boxes'
    };
</script>
<script src="{{ asset('js/pages/delivery-assign.js') }}"></script>
@endsection
