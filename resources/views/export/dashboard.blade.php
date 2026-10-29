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
    <h2 class="dashboard-section-title">Master data ekspor</h2>
    @if(auth()->user()->canAccess('export_parts') || auth()->user()->canAccess('export_locations'))
        <ul class="dashboard-actions" aria-label="Master data ekspor">
            <x-dashboard-action icon="list-check" title="Master Nomor Part Ekspor" description="Kelola nomor part dan kapasitas PCS per box untuk ekspor" route-name="export.part-settings.index" label="Kelola nomor part" />
            <x-dashboard-action icon="geo-alt" title="Master Lokasi Ekspor" description="Kelola lokasi pada area penyimpanan ekspor" route-name="export.locations.index" label="Kelola lokasi" />
        </ul>
    @else
        <div class="card"><div class="card-body">
            <h2 class="h5">Belum ada fitur yang dapat diakses</h2>
            <p class="text-muted mb-0">Hubungi Admin Utama untuk pengaturan akses akun Anda.</p>
        </div></div>
    @endif
</div>
@endsection
