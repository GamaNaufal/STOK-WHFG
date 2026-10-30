@extends('shared.layouts.app')
@section('title', 'Penempatan '.$receipt['code'])
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page">
    <header class="plan-header">
        <div><span class="plan-eyebrow">Penerimaan Transfer Box Ekspor</span><h1>Penempatan {{ $receipt['code'] }}</h1><p>{{ $receipt['received_at']->format('d/m/Y H:i') }} · {{ $receipt['operator'] }}</p></div>
        <div class="plan-inline-actions"><a class="btn btn-outline-primary" href="{{ route('export.receiving.index') }}#receiving-pending-queue">Kembali ke antrean</a><a class="btn btn-primary" href="{{ $receipt['print_url'] }}" target="_blank" rel="noopener" data-placement-guide-link="{{ $receipt['print_url'] }}">Cetak panduan</a></div>
    </header>
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Penempatan belum disimpan.</strong><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    <div class="fulfillment-summary"><div><span>Total box</span><strong>{{ $receipt['total'] }}</strong></div><div><span>Menunggu penempatan / pemindahan</span><strong>{{ $receipt['pending'] }}</strong></div><div><span>Sudah ditempatkan</span><strong>{{ $receipt['placed'] }}</strong></div></div>
    @if($receipt['pending'] === 0)<div class="alert alert-success" role="status">Seluruh box pada transaksi ini sudah ditempatkan.</div>@else<p class="plan-help mb-3">Daftar box dikelompokkan sesuai tujuan pallet dan lokasi. Konfirmasikan setelah box benar-benar ditempatkan; penempatan dapat diselesaikan bertahap per kelompok.</p>@endif
    <h2 class="visually-hidden">Daftar box dan tujuan penempatan</h2>
    @foreach($groups as $group)@include('export.receiving.group')@endforeach
</div>
@endsection
@section('scripts')@vite('resources/js/export-stock-receiving.js')@endsection
