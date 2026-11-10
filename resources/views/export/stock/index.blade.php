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
        <div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Stok ekspor</h1><p>Pantau stok yang tersedia dan kesiapan delivery per customer.</p></div>
        <div class="plan-inline-actions">
            @if(auth()->user()->canAccess('export_stock_manage'))
                <a class="btn btn-outline-primary" href="{{ route('export.stock.box-history') }}"><i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat box</a>
            @endif
            @if(auth()->user()->canAccessRoute('export.receiving.index'))
                <a class="btn btn-outline-primary" href="{{ route('export.receiving.index') }}"><i class="bi bi-upc-scan" aria-hidden="true"></i> Penerimaan Transfer Box Ekspor</a>
            @endif
        </div>
    </header>
    <div class="stock-summary" id="export-stock-summary" aria-label="Ringkasan seluruh stok ekspor">
        @foreach(['free' => ['Stok Bersih', 'box-seam'], 'in_progress' => ['Sedang Diproses', 'hourglass-split'], 'stored' => ['Siap Kirim', 'check2-circle']] as $key => [$label, $icon])
            <a class="stock-summary-item" href="{{ route('export.stock.index', ['customer' => $customer, 'mode' => $filters['mode'], 'status' => $key]) }}#export-stock-results"
                data-stock-quick-status="{{ $key }}" @if($status === $key) aria-current="true" @endif>
                <i class="bi bi-{{ $icon }} stock-summary-icon" aria-hidden="true"></i>
                <div><span>{{ $label }}</span><p><strong data-stock-count="{{ $key }}">{{ number_format($stockCounts[$key], 0, ',', '.') }}</strong> <small>box</small></p></div>
                <i class="bi bi-chevron-right stock-summary-arrow" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
    <section class="plan-panel stock-readiness" aria-labelledby="stock-readiness-title">
        <div class="plan-panel-heading stock-readiness-heading">
            <div><h2 id="stock-readiness-title">Kesiapan Customer</h2><p>Lihat progres delivery dan bandingkan kebutuhan dengan stok bersih.</p></div>
            <div class="stock-customer-select">
                <label class="form-label" for="stock-customer">Customer</label>
                <select class="form-select" id="stock-customer" name="customer" form="export-stock-filters" aria-controls="stock-customer-readiness">
                    <option value="">Pilih customer</option>
                    @if($customer !== '' && !$customers->has($customer))
                        <option value="{{ $customer }}" selected>{{ $customer }}</option>
                    @endif
                    @foreach($customers as $key => $name)
                        <option value="{{ $key }}" @selected((string) $key === $customer)>{{ $name }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-outline-primary mt-2" type="submit" form="export-stock-filters">Tampilkan customer</button></noscript>
            </div>
        </div>
        <div id="stock-customer-readiness" aria-busy="false">@include('export.stock.customer-readiness')</div>
    </section>
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
                        @foreach(['free'=>'Stok bersih','in_progress'=>'Sedang diproses','stored'=>'Siap kirim','spare'=>'Stok cadangan'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach
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
                <a class="btn stock-reset-button" href="{{ route('export.stock.index', $customer === '' ? [] : ['customer' => $customer]) }}" data-stock-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset</a>
            </div>
            <input id="stock-part-number" name="part_number" type="hidden" value="{{ $filters['part_number'] }}">
            <input name="pcs_per_box" type="hidden" value="{{ $filters['pcs_per_box'] }}">
            <div class="stock-part-chip" data-stock-part-filter @if($filters['part_number']==='' && !$filters['pcs_per_box']) hidden @endif>
                <span data-stock-part-label>{{ $filters['part_number'] === '' ? '' : 'Nomor part '.$filters['part_number'] }}{{ $filters['pcs_per_box'] ? ($filters['part_number'] !== '' ? ' · ' : '').$filters['pcs_per_box'].' PCS/box' : '' }}</span>
                <button type="button" data-stock-clear-part aria-label="Hapus filter nomor part"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <div class="stock-toolbar-caption">
                <div class="stock-request-state" aria-live="polite" aria-atomic="true">
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                    <span id="stock-request-status" role="status">Filter diterapkan otomatis</span>
                    <span id="stock-result-count" hidden>{{ number_format($resultCount, 0, ',', '.') }}</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="stock-request-retry" hidden>Coba lagi</button>
                </div>
                <details class="stock-category-guide">
                    <summary><i class="bi bi-info-circle" aria-hidden="true"></i> Arti kategori</summary>
                    <p><strong>Stok bersih</strong> siap dialokasikan. <strong>Sedang diproses</strong> mencakup box yang menunggu penempatan atau masih terikat delivery yang belum siap. <strong>Siap kirim</strong> berarti pallet sudah dikonfirmasi Siap. <strong>Stok cadangan</strong> mencakup semua box aktif di luar pallet Siap. Excel mengikuti filter daftar stok.</p>
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
