@extends('shared.layouts.app')
@section('title', 'Stok Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-stock.css') }}?v={{ filemtime(public_path('css/pages/export-stock.css')) }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page export-stock-page">
    <header class="plan-header stock-header">
        <div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Stok ekspor</h1><p>Tinjau posisi box, isi pallet, dan tujuan reservasi.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('export.receiving.index') }}"><i class="bi bi-upc-scan" aria-hidden="true"></i> Penerimaan Transfer Box Ekspor</a>
    </header>
    <div class="stock-summary" aria-label="Ringkasan stok ekspor">
        <div class="stock-summary-item">
            <i class="bi bi-box-seam stock-summary-icon" aria-hidden="true"></i>
            <div><span>Tersimpan</span><p><strong>{{ number_format($storedCount, 0, ',', '.') }}</strong> <small>box</small></p></div>
        </div>
        <div class="stock-summary-item">
            <i class="bi bi-boxes stock-summary-icon" aria-hidden="true"></i>
            <div><span>Stok cadangan</span><p><strong>{{ number_format($spareCount, 0, ',', '.') }}</strong> <small>box</small></p></div>
        </div>
        <div class="stock-summary-item">
            <i class="bi bi-funnel stock-summary-icon" aria-hidden="true"></i>
            <div><span>Hasil pencarian</span><p><strong id="stock-result-count">{{ number_format($resultCount, 0, ',', '.') }}</strong> <small>box</small></p></div>
        </div>
    </div>
    <section class="plan-panel">
        <form method="GET" action="{{ route('export.stock.index') }}" id="export-stock-filters" class="stock-toolbar" aria-label="Pencarian dan filter stok ekspor">
            <button type="submit" hidden>Terapkan filter</button>
            <div class="stock-search-row">
                <div class="stock-search-field">
                    <label class="visually-hidden" for="receiving-stock-search">Cari ID Box, nomor part, atau pallet</label>
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input class="form-control" id="receiving-stock-search" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="Cari ID Box, nomor part, atau pallet…" autocomplete="off" aria-controls="export-stock-results">
                </div>
                <button class="btn btn-outline-primary stock-export-button" type="submit" formaction="{{ route('export.stock.export') }}" data-stock-export aria-label="Export Excel sesuai filter">
                    <i class="bi bi-download" aria-hidden="true"></i><span>Export Excel</span>
                </button>
            </div>
            <div class="stock-filter-row">
                <fieldset id="stock-mode" class="stock-view-switch">
                    <legend class="visually-hidden">Kelompokkan stok berdasarkan</legend>
                    <input class="visually-hidden" id="stock-mode-box" type="radio" name="mode" value="box" @checked($filters['mode']==='box') aria-controls="export-stock-results">
                    <label for="stock-mode-box"><i class="bi bi-box" aria-hidden="true"></i> Box</label>
                    <input class="visually-hidden" id="stock-mode-pallet" type="radio" name="mode" value="pallet" @checked($filters['mode']==='pallet') aria-controls="export-stock-results">
                    <label for="stock-mode-pallet"><i class="bi bi-grid" aria-hidden="true"></i> Pallet</label>
                </fieldset>
                <div class="stock-select-field">
                    <label class="visually-hidden" for="stock-status">Kategori stok</label>
                    <i class="bi bi-funnel" aria-hidden="true"></i>
                    <select class="form-select" id="stock-status" name="status" aria-controls="export-stock-results">
                        <option value="">Semua kategori</option>
                        @foreach(['stored'=>'Tersimpan','spare'=>'Stok cadangan'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="stock-select-field stock-location-field">
                    <label class="visually-hidden" for="stock-location">Lokasi fisik</label>
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <select class="form-select" id="stock-location" name="location_id" aria-controls="export-stock-results">
                        <option value="">Semua lokasi</option>
                        @foreach($filterLocations as $location)<option value="{{ $location->id }}" @selected($filters['location_id']===$location->id)>{{ $location->code }}</option>@endforeach
                    </select>
                </div>
                <a class="btn stock-reset-button" href="{{ route('export.stock.index') }}" data-stock-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset</a>
            </div>
            <input id="stock-part-number" name="part_number" type="hidden" value="{{ $filters['part_number'] }}">
            <div class="stock-part-chip" data-stock-part-filter @if($filters['part_number']==='') hidden @endif>
                <span>Nomor part <strong data-stock-part-label>{{ $filters['part_number'] }}</strong></span>
                <button type="button" data-stock-clear-part aria-label="Hapus filter nomor part"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <div class="stock-toolbar-caption">
                <div class="stock-request-state" aria-live="polite" aria-atomic="true">
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                    <span id="stock-request-status" role="status">Filter diterapkan otomatis</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="stock-request-retry" hidden>Coba lagi</button>
                </div>
                <details class="stock-category-guide">
                    <summary><i class="bi bi-info-circle" aria-hidden="true"></i> Arti kategori</summary>
                    <p><strong>Tersimpan</strong> berarti pallet sudah dikonfirmasi Siap di Pemenuhan Permintaan. <strong>Stok cadangan</strong> mencakup box yang memiliki reservasi dan yang masih menunggu konfirmasi pallet Siap. Excel mengikuti seluruh hasil filter.</p>
                </details>
            </div>
            <noscript><button class="btn btn-primary" type="submit">Terapkan filter</button></noscript>
        </form>
        <div id="export-stock-results" aria-busy="false">@include('export.stock.results')</div>
    </section>
</div>
@include('export.stock.box-modal')
@endsection
@section('scripts')@vite('resources/js/export-stock-receiving.js')@endsection
