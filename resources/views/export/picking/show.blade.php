@extends('shared.layouts.app')
@section('title', $helper ? 'Cari Box Ekspor' : 'Verifikasi Pengambilan Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page" id="export-picking-page">
    <header class="plan-header"><div><span class="plan-eyebrow">{{ $pallet->palletPlan->deliveryPlan?->plan_code }} · {{ $pallet->palletPlan->deliveryPlan?->customer_name }}</span><h1>{{ $helper ? 'Cari box rekomendasi' : 'Verifikasi pengambilan box' }}</h1><p>Tujuan Pallet {{ $pallet->position }} · lokasi {{ $pallet->location_code }}</p></div><div class="plan-inline-actions"><a class="btn btn-outline-secondary" href="{{ route($helper ? 'export.picking.index' : 'export.receiving.index') }}">Kembali</a>@if($helper)<a class="btn btn-primary" href="{{ route('export.receiving.picking.show', ['pallet'=>$pallet->id]) }}">Buka verifikasi pengambilan wajib</a>@endif</div></header>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
    <p class="alert {{ $helper ? 'alert-info' : 'alert-warning' }}">{{ $helper ? 'Mode pencarian: scan salah hanya menampilkan NG. Scan di sini tidak menggantikan verifikasi pengambilan wajib.' : 'Mode verifikasi wajib: scan box yang direkomendasikan dari pallet campuran. Salah nomor box 8 digit memblokir proses sampai approval admin dan penempatan pengembalian dikonfirmasi.' }}</p>
    <div class="receiving-scan-outcome" id="export-pick-feedback" role="status" aria-live="polite" hidden></div>
    <div id="export-pick-workbench">@include('export.picking.workbench')</div>
    @unless($helper)<a class="btn btn-outline-primary mt-3" href="{{ route('export.receiving.index') }}#receiving-pending-queue">Kembali ke antrean penempatan</a>@endunless
</div>
@endsection
@section('scripts')
<script src="{{ asset('js/scan-feedback.js') }}"></script>
@vite('resources/js/export-stock-receiving.js')
@endsection
