@extends('shared.layouts.app')
@section('title', $helper ? 'Cari Box Ekspor' : 'Verifikasi Pengambilan Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/delivery-verification.css') }}">
@endsection
@php
    $total = $rows->count();
    $done = $rows->filter(fn ($row) => $row->picking_ready || !$row->requires_scan)->count();
    $remaining = max(0, $total - $done);
    $delivery = $pallet->palletPlan->deliveryPlan;
@endphp
@section('content')
<div id="deliveryVerificationPage" class="delivery-verification-page export-picking-verification-page">
    <header class="verification-header">
        <div class="verification-heading">
            <span class="verification-eyebrow">Picking / Pemeriksaan box</span>
            <h1><i class="bi bi-upc-scan" aria-hidden="true"></i> Picking Verification</h1>
            <div class="verification-context">
                <span class="verification-order">{{ $delivery->plan_code ?? 'Rencana ekspor' }}</span>
                <span>{{ $delivery->customer_name }}</span>
                <span>{{ $delivery->delivery_deadline->format('d M Y') }}</span>
                <span>Pallet {{ $pallet->position }} · {{ $pallet->location_code }}</span>
            </div>
        </div>
        <a href="{{ route($helper ? 'export.picking.index' : 'export.receiving.index') }}" class="btn verification-back-link">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar
        </a>
    </header>

    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
    <div class="verification-grid">
        <div class="verification-controls">
            <section class="card verification-card" aria-labelledby="exportVerificationScanTitle">
                <div class="card-body">
                    <span class="verification-section-label">Langkah 1 / Verifikasi</span>
                    <h2 id="exportVerificationScanTitle">{{ $helper ? 'Cari box rekomendasi' : 'Scan ID box' }}</h2>
                    <p class="verification-supporting">{{ $helper ? 'Scan box untuk mencari kecocokan pada pallet campuran. Mode ini tidak menggantikan verifikasi pengambilan wajib.' : 'Pindai atau ketik ID box rekomendasi dari pallet campuran, lalu tekan Enter. Nomor yang tidak sesuai akan ditolak tanpa mengubah progres.' }}</p>
                    <div class="receiving-scan-outcome" id="export-pick-feedback" role="status" aria-live="polite" hidden></div>
                    <div id="export-pick-workbench">@include('export.picking.workbench', ['compact' => true])</div>
                </div>
            </section>

            <section class="card verification-card" aria-labelledby="exportVerificationProgressTitle">
                <div class="card-body">
                    <span class="verification-section-label">Status sesi</span>
                    <h2 id="exportVerificationProgressTitle">Progres verifikasi</h2>
                    <div class="verification-metrics">
                        <div class="verification-metric"><span>Sudah diverifikasi</span><strong>{{ $done }}<small> / {{ $total }}</small></strong></div>
                        <div class="verification-metric"><span>Sisa box</span><strong>{{ $remaining }}</strong></div>
                    </div>
                    <div class="verification-timeout">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <div><strong>Status picking ekspor</strong><p>{{ $helper ? 'Mode pencarian aktif. Gunakan verifikasi wajib untuk menyelesaikan pengambilan.' : 'Scan wajib untuk box dari pallet campuran. Box satu jenis part tidak memerlukan scan satuan.' }}</p></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="verification-card verification-list" id="export-pick-reference">
            @include('export.picking.workbench', ['referenceOnly' => true])
        </div>
    </div>
</div>
@endsection
@section('scripts')
@include('shared.partials.scan-feedback')
@vite('resources/js/export-stock-receiving.js')
@endsection
