@extends('shared.layouts.app')

@section('title', 'Dashboard - Warehouse FG Yamato')

@section('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard-summary.css') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endsection

@section('content')
<!-- Page Header Component -->
<div class="container-fluid dashboard-page">
<x-page-header 
    title="Dashboard Sistem" 
    icon="speedometer2"
    subtitle="Ringkasan stok dan aktivitas Warehouse Finish Good Yamato"
>
    <x-slot:action>
    @if(isset($stats['role_label']))
        <span class="dashboard-role">{{ $userRole === 'admin' ? 'Admin Utama' : $stats['role_label'] }}</span>
    @endif
    </x-slot:action>
</x-page-header>
<h2 class="dashboard-section-title">Ringkasan operasional</h2>

{{-- WAREHOUSE OPERATOR DASHBOARD --}}
@if($userRole === 'warehouse_operator')
@include('shared.partials.operator-summary')

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<ul class="dashboard-actions" aria-label="Akses cepat">
    <x-dashboard-action icon="plus-circle" title="Input Lokasi Stok" description="Scan pallet dan input lokasi penyimpanan" route-name="stock-input.index" label="Mulai Input" />
    <x-dashboard-action icon="eye" title="Lihat Stok" description="Cek status stok produk yang tersedia" route-name="stock-view.index" label="Lihat Stok" />
</ul>

{{-- SALES DASHBOARD --}}
@elseif($userRole === 'sales')
@include('shared.partials.role-summary', ['variant' => 'sales', 'leadCount' => 2])

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<ul class="dashboard-actions" aria-label="Akses cepat">
    <x-dashboard-action icon="plus-circle" title="Input Sales Order" description="Buat order penjualan baru" route-name="delivery.create" label="Buat Order" />
</ul>

{{-- PPC DASHBOARD --}}
@elseif($userRole === 'ppc')
@include('shared.partials.role-summary', ['variant' => 'ppc', 'leadCount' => 2])

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<ul class="dashboard-actions" aria-label="Akses cepat">
    <x-dashboard-action icon="clipboard-check" title="Lihat Pending Approval" description="Review dan approve order yang menunggu" route-name="delivery.approvals" label="Lihat Approval" />
    <x-dashboard-action icon="truck" title="Delivery Schedule" description="Lihat jadwal delivery (read-only)" route-name="delivery.index" label="Lihat Jadwal" />
</ul>

{{-- SUPERVISI DASHBOARD --}}
@elseif($userRole === 'supervisi')
@include('shared.partials.role-summary', ['variant' => 'supervisi', 'leadCount' => 2])

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<ul class="dashboard-actions" aria-label="Akses cepat">
    <x-dashboard-action icon="eye" title="Lihat Stok" description="Monitor stok produk yang tersedia" route-name="stock-view.index" label="Lihat Stok" />
    <x-dashboard-action icon="file-earmark-bar-graph" title="Laporan" description="Lihat laporan input stok dan pengambilan" route-name="reports.stock-input" label="Lihat Laporan" />
</ul>

{{-- ADMIN WAREHOUSE DASHBOARD --}}
@elseif($userRole === 'admin_warehouse')
@include('shared.partials.role-summary', ['variant' => 'admin_warehouse', 'leadCount' => 3])

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<ul class="dashboard-actions" aria-label="Akses cepat">
    <x-dashboard-action icon="gear" title="Kelola Lokasi" description="Atur lokasi penyimpanan gudang" route-name="locations.index" label="Kelola Lokasi" />
    <x-dashboard-action icon="exclamation-triangle" title="Scan Issues" description="Kelola masalah scan" route-name="delivery.pick.issues" label="Lihat Masalah Scan" />
    <x-dashboard-action icon="eye" title="Lihat Stok" description="Monitor stok warehouse" route-name="stock-view.index" label="Lihat Stok" />
</ul>

{{-- ADMIN DASHBOARD --}}
@else
@include('shared.partials.role-summary', ['variant' => 'admin', 'leadCount' => 3])

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<ul class="dashboard-actions" aria-label="Akses cepat">
    <x-dashboard-action icon="gear" title="Kelola Lokasi" description="Setup lokasi penyimpanan" route-name="locations.index" label="Kelola Lokasi" />
    <x-dashboard-action icon="eye" title="Audit Trail" description="Pantau semua aktivitas" route-name="audit.index" label="Lihat Audit Trail" />
    <x-dashboard-action icon="file-earmark-bar-graph" title="Laporan" description="Lihat semua laporan" route-name="reports.stock-input" label="Lihat Laporan" />
</ul>
@endif

<!-- Info Box -->
<div class="row dashboard-info-row">
    <div class="col-12">
        <div class="dashboard-info">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-info-circle" style="font-size: 1.5rem; color: #5b8fc4;"></i>
                </div>
                <div class="col">
                    <h2 class="dashboard-info-title">Informasi Sistem</h2>
                    <p class="mb-0">Sistem ini membantu Anda mengelola stok barang di warehouse dengan scan barcode dan pelacakan lokasi penyimpanan yang terstruktur.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tooltipTriggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggers.forEach((el) => {
            new bootstrap.Tooltip(el);
        });
    });
</script>
@endsection
