@extends('shared.layouts.app')

@section('title', 'Detail Box')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1">Detail Box</h4>
        <p class="text-muted mb-0">Informasi lengkap box</p>
    </div>
    <a href="{{ route('boxes.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <div class="text-muted small">Box Number</div>
                <div class="fw-semibold">{{ $box->box_number }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Part Number</div>
                <div class="fw-semibold">{{ $box->part_number }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Part Name</div>
                <div class="fw-semibold">{{ $box->part_name ?? '-' }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">PCS Quantity</div>
                <div class="fw-semibold">{{ (int) $box->pcs_quantity }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Qty Box</div>
                <div class="fw-semibold">{{ $box->qty_box ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Type Box</div>
                <div class="fw-semibold">{{ $box->type_box ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">WK Transfer</div>
                <div class="fw-semibold">{{ $box->wk_transfer ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Lot 01</div>
                <div class="fw-semibold">{{ $box->lot01 ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Lot 02</div>
                <div class="fw-semibold">{{ $box->lot02 ?? '-' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Lot 03</div>
                <div class="fw-semibold">{{ $box->lot03 ?? '-' }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Operator</div>
                <div class="fw-semibold">{{ $box->user?->name ?? 'System' }}</div>
            </div>
            <div class="col-md-6">
                <div class="text-muted small">Created At</div>
                <div class="fw-semibold">{{ $box->created_at?->format('d M Y H:i') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
