@extends('shared.layouts.app')
@section('title', 'Stok Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page">
    <header class="plan-header"><div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Stok ekspor</h1><p>Stok fisik dan stok cadangan ekspor.</p></div><a class="btn btn-outline-primary" href="{{ route('export.receiving.index') }}">Penerimaan Transfer Box Ekspor</a></header>
    <div class="fulfillment-summary"><div><span>Box tersimpan</span><strong>{{ $storedCount }}</strong></div><div><span>Menunggu penempatan / pemindahan</span><strong>{{ $pendingCount }}</strong></div><div><span>Hasil pencarian stok</span><strong id="stock-result-count">{{ $resultCount }}</strong></div></div>
    <section class="plan-panel">
        <form method="GET" action="{{ route('export.stock.index') }}" id="export-stock-filters" class="receiving-stock-search stock-export-filters" aria-describedby="stock-filter-help">
            <button type="submit" hidden>Terapkan filter</button>
            <div><label class="form-label" for="receiving-stock-search">Cari box, part, atau pallet</label><input class="form-control" id="receiving-stock-search" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Contoh: Pallet 1 atau nomor box" autocomplete="off" aria-controls="export-stock-results"></div>
            <div><label class="form-label" for="stock-mode">Kelompokkan berdasarkan</label><select class="form-select" id="stock-mode" name="mode" aria-controls="export-stock-results"><option value="box" @selected($filters['mode']==='box')>Box</option><option value="pallet" @selected($filters['mode']==='pallet')>Pallet</option></select></div>
            <div><label class="form-label" for="stock-location">Lokasi</label><select class="form-select" id="stock-location" name="location_id" aria-controls="export-stock-results"><option value="">Semua lokasi</option>@foreach($filterLocations as $location)<option value="{{ $location->id }}" @selected($filters['location_id']===$location->id)>{{ $location->code }}</option>@endforeach</select></div>
            <div><label class="form-label" for="stock-status">Status stok</label><select class="form-select" id="stock-status" name="status" aria-controls="export-stock-results"><option value="">Semua stok</option>@foreach(['stored'=>'Tersimpan','pending'=>'Menunggu penempatan','spare'=>'Stok cadangan','reserved'=>'Memiliki reservasi'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select></div>
            <p id="stock-filter-help">Filter langsung diterapkan. Cari pallet dengan nomor seperti “Pallet 1”, lokasi, atau kode delivery. Excel memuat seluruh hasil filter.</p>
            <div class="stock-export-actions">
                <a class="btn btn-outline-secondary" href="{{ route('export.stock.index') }}" data-stock-reset>Reset filter</a>
                <noscript><button class="btn btn-primary" type="submit">Terapkan filter</button></noscript>
                <button class="btn btn-outline-primary" type="submit" formaction="{{ route('export.stock.export') }}" data-stock-export><i class="bi bi-download" aria-hidden="true"></i> Export Excel</button>
            </div>
        </form>
        <div class="stock-request-state" aria-live="polite" aria-atomic="true"><span id="stock-request-status" role="status"></span><button type="button" class="btn btn-sm btn-outline-primary" id="stock-request-retry" hidden>Coba lagi</button></div>
        <div id="export-stock-results" aria-busy="false">@include('export.stock.results')</div>
    </section>
</div>
@include('export.stock.box-modal')
@endsection
@section('scripts')@vite('resources/js/export-stock-receiving.js')@endsection
