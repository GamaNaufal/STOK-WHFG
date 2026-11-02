@extends('shared.layouts.app')
@section('title', $helper ? 'Cari Box Ekspor' : 'Verifikasi Pengambilan Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/delivery-verification.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-picking.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection
@section('content')
<div id="deliveryVerificationPage" class="delivery-verification-page"><div id="export-picking-page">
    <header class="verification-header"><div class="verification-heading"><span class="verification-eyebrow">Ekspor / Pemeriksaan box</span><h1><i class="bi bi-upc-scan" aria-hidden="true"></i> Picking Verification</h1><div class="verification-context"><span class="verification-order">{{ $pallet->palletPlan->deliveryPlan?->plan_code }}</span><span>{{ $pallet->palletPlan->deliveryPlan?->customer_name }}</span><span>Pallet {{ $pallet->position }} · {{ $pallet->location_code }}</span></div></div><a class="verification-back-link" href="{{ route($helper ? 'export.picking.index' : 'export.receiving.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali</a></header>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
    <p class="alert {{ $helper ? 'alert-info' : 'alert-warning' }}">{{ $helper ? 'Mode pencarian: scan salah hanya menampilkan NG. Scan di sini tidak menggantikan verifikasi pengambilan wajib.' : 'Mode verifikasi wajib: scan box yang direkomendasikan dari pallet campuran. Salah nomor box 8 digit memblokir proses sampai approval admin dan penempatan pengembalian dikonfirmasi.' }}</p>
    <div class="receiving-scan-outcome" id="export-pick-feedback" role="status" aria-live="polite" hidden></div>
    <div id="export-pick-workbench">@include('export.picking.workbench')</div>
    @unless($helper)<a class="btn btn-outline-primary mt-3" href="{{ route('export.receiving.index') }}#receiving-pending-queue">Kembali ke antrean penempatan</a>@endunless
</div></div>
@endsection
@section('scripts')
@include('shared.partials.scan-feedback')
@vite('resources/js/export-stock-receiving.js')
@endsection
