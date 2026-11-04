@extends('shared.layouts.app')

@section('title', 'Dashboard Operator Export')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/dashboard-summary.css') }}">
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endsection

@section('content')
<div class="container-fluid dashboard-page">
    <x-page-header title="Operator Export" icon="box-seam" subtitle="Area kerja pengiriman ekspor" />
    @if(auth()->user()->canAccess('export_delivery_plans'))
        <h2 class="dashboard-section-title">Rencana pengiriman</h2>
        <ul class="dashboard-actions" aria-label="Rencana pengiriman ekspor">
            <x-dashboard-action icon="calendar2-check" title="Rencana Delivery Ekspor" description="Susun kebutuhan pengiriman melalui input manual atau Excel" route-name="export.delivery-plans.index" label="Kelola rencana" />
        </ul>
    @endif
    @if(auth()->user()->canAccess('export_pallet_plans'))
        <ul class="dashboard-actions" aria-label="Perencanaan pallet ekspor">
            <x-dashboard-action icon="boxes" title="Perencanaan Pallet" description="Bagi kebutuhan delivery ke pallet dan reservasi lokasi" route-name="export.pallet-plans.index" label="Rencanakan pallet" />
        </ul>
    @endif
    @if(auth()->user()->canAccess('export_fulfillment'))
        <ul class="dashboard-actions" aria-label="Pemenuhan permintaan ekspor">
            <x-dashboard-action icon="clipboard-data" title="Pemenuhan Permintaan" description="Pantau kebutuhan, reservasi box, dan penempatan per pallet" route-name="export.fulfillment.index" label="Pantau pemenuhan" />
        </ul>
    @endif
    @if(auth()->user()->canAccess('export_stock_input'))
        <ul class="dashboard-actions" aria-label="Penerimaan transfer box ekspor">
            <x-dashboard-action icon="upc-scan" title="Penerimaan Transfer Box Ekspor" description="Scan box dan part, lalu ikuti penempatan sesuai kebutuhan dan FIFO" route-name="export.receiving.index" label="Terima box" />
            <x-dashboard-action icon="box-seam" title="Stok Ekspor" description="Lihat stok fisik, cadangan, dan antrean penempatan ekspor" route-name="export.stock.index" label="Lihat stok ekspor" />
        </ul>
    @endif
    @if(auth()->user()->canAccess('parts') || auth()->user()->canAccess('export_locations'))
        <h2 class="dashboard-section-title">Master data</h2>
        <ul class="dashboard-actions" aria-label="Master data">
            @if(auth()->user()->canAccess('parts'))<x-dashboard-action icon="list-check" title="Master Nomor Part Bersama" description="Sumber nomor part dan PCS standar untuk lokal dan ekspor" route-name="part-settings.index" label="Kelola nomor part" />@endif
            @if(auth()->user()->canAccess('export_locations'))<x-dashboard-action icon="geo-alt" title="Master Lokasi Ekspor" description="Kelola lokasi pada area penyimpanan ekspor" route-name="export.locations.index" label="Kelola lokasi" />@endif
        </ul>
    @endif
    @if(collect(['parts','export_locations','export_delivery_plans','export_pallet_plans','export_stock_input','export_fulfillment'])->every(fn($feature)=>!auth()->user()->canAccess($feature)))
        <div class="card"><div class="card-body"><h2 class="h5">Belum ada fitur yang dapat diakses</h2><p class="text-muted mb-0">Hubungi Admin Utama untuk pengaturan akses akun Anda.</p></div></div>
    @endif
</div>
@endsection
