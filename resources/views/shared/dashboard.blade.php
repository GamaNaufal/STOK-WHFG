@extends('shared.layouts.app')

@section('title', 'Dashboard - Warehouse FG Yamato')

@section('styles')
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
        <span class="dashboard-role">{{ $stats['role_label'] }}</span>
    @endif
    </x-slot:action>
</x-page-header>
<h2 class="dashboard-section-title">Ringkasan operasional</h2>

{{-- WAREHOUSE OPERATOR DASHBOARD --}}
@if($userRole === 'warehouse_operator')
<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah seluruh pallet di sistem.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Pallet</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_pallets'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-boxes dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Pallet yang memiliki stok aktif dan sudah tersimpan di lokasi.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Pallet Tersimpan</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pallets_with_location'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-check-circle-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Pallet yang belum memiliki lokasi penyimpanan.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Pending Input</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pallets_without_location'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-exclamation-circle-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah lokasi penyimpanan terdaftar.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Lokasi</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_locations'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Sesi picking dengan status scanning/blocked.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Picking Aktif</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pending_deliveries'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-truck dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<div class="row dashboard-actions-row">
    <div class="col-12 col-sm-6 col-md-6 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-plus-circle dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Input Lokasi Stok</h3>
                <p class="card-text">
                    Scan pallet dan input lokasi penyimpanan
                </p>
                @if(auth()->user()->canAccessRoute('stock-input.index'))
                <a href="{{ route('stock-input.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Mulai Input
                </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-6 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-eye dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Lihat Stok</h3>
                <p class="card-text">
                    Cek status stok produk yang tersedia
                </p>
                @if(auth()->user()->canAccessRoute('stock-view.index'))
                <a href="{{ route('stock-view.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Stok
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- SALES DASHBOARD --}}
@elseif($userRole === 'sales')
<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah seluruh order penjualan.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Order</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-cart-check dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Order yang dibuat hari ini.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Order Hari Ini</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['today_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-calendar-day dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Order berstatus pending.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Menunggu Persetujuan</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pending_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-hourglass-split dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Order berstatus approved.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Disetujui</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['approved_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-check-circle dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Order berstatus completed.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Selesai</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['completed_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-check2-all dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<div class="row dashboard-actions-row">
    <div class="col-md-12 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-plus-circle dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Input Sales Order</h3>
                <p class="card-text">
                    Buat order penjualan baru
                </p>
                @if(auth()->user()->canAccessRoute('delivery.create'))
                <a href="{{ route('delivery.create') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Buat Order
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- PPC DASHBOARD --}}
@elseif($userRole === 'ppc')
<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Order menunggu persetujuan.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Menunggu Persetujuan</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pending_approval'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-hourglass-split dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Order yang sudah disetujui.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Order Disetujui</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['approved_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-check-circle dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah seluruh order.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Order</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-cart-check dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Total PCS stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Stok (PCS)</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_stok_pcs'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-capsule dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah item/part dengan stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Part</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_stok_items'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-archive dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<div class="row dashboard-actions-row">
    <div class="col-12 col-sm-6 col-md-6 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-clipboard-check dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Lihat Pending Approval</h3>
                <p class="card-text">
                    Review dan approve order yang menunggu
                </p>
                @if(auth()->user()->canAccessRoute('delivery.approvals'))
                <a href="{{ route('delivery.approvals') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Approval
                </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-6 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-truck dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Delivery Schedule</h3>
                <p class="card-text">
                    Lihat jadwal delivery (read-only)
                </p>
                @if(auth()->user()->canAccessRoute('delivery.index'))
                <a href="{{ route('delivery.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Jadwal
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- SUPERVISI DASHBOARD --}}
@elseif($userRole === 'supervisi')
<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Pallet</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_pallets'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-boxes dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total PCS</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_pcs'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-capsule dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Lokasi</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_locations'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Order</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_orders'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-cart-check dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Selesai Hari Ini</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['completed_orders_today'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-check2-all dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-6 mb-3">
        <div class="card dashboard-stat h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Delivery</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_deliveries'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-truck dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<div class="row dashboard-actions-row">
    <div class="col-12 col-sm-6 col-md-6 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-eye dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Lihat Stok</h3>
                <p class="card-text">
                    Monitor stok produk yang tersedia
                </p>
                @if(auth()->user()->canAccessRoute('stock-view.index'))
                <a href="{{ route('stock-view.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Stok
                </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-6 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-file-earmark-bar-graph dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Laporan</h3>
                <p class="card-text">
                    Lihat laporan input stok dan pengambilan
                </p>
                @if(auth()->user()->canAccessRoute('reports.stock-input'))
                <a href="{{ route('reports.stock-input') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Laporan
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ADMIN WAREHOUSE DASHBOARD --}}
@elseif($userRole === 'admin_warehouse')
<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah lokasi penyimpanan terdaftar.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Lokasi</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_locations'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah seluruh pallet di sistem.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Pallet</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_pallets'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-boxes dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Pallet dengan stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Pallet Tersimpan</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pallets_with_location'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-check-circle-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Total PCS stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total PCS</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_pcs'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-capsule dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah scan issue berstatus pending.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Scan Issues</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pending_scan_issues'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-exclamation-triangle dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah item/part dengan stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Part</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_items'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-archive dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<div class="row dashboard-actions-row">
    <div class="col-12 col-sm-6 col-xl-4 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-gear dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Kelola Lokasi</h3>
                <p class="card-text">
                    Atur lokasi penyimpanan gudang
                </p>
                @if(auth()->user()->canAccessRoute('locations.index'))
                <a href="{{ route('locations.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Kelola Lokasi
                </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-exclamation-triangle dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Scan Issues</h3>
                <p class="card-text">
                    Kelola masalah scan
                </p>
                @if(auth()->user()->canAccessRoute('delivery.pick.issues'))
                <a href="{{ route('delivery.pick.issues') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Masalah Scan
                </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-eye dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Lihat Stok</h3>
                <p class="card-text">
                    Monitor stok warehouse
                </p>
                @if(auth()->user()->canAccessRoute('stock-view.index'))
                <a href="{{ route('stock-view.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Stok
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ADMIN DASHBOARD --}}
@else
<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah seluruh pallet di sistem.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Pallet</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_pallets'] ?? 0, 0, ',', '.') }}</h3>
                        <small>{{ $stats['today_pallets'] ?? 0 }} Hari Ini</small>
                    </div>
                    <i class="bi bi-boxes dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Pallet dengan stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Pallet Tersimpan</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['pallets_with_location'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-check-circle-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah lokasi penyimpanan terdaftar.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Lokasi</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_locations'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah item/part dengan stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Part</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_items'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-archive dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row dashboard-stats-row">
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Total box aktif (belum withdrawn).">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Box</p>
                        <h3 class="dashboard-stat-value">{{ number_format((int) ($stats['total_box'] ?? 0), 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-box2 dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Total PCS stok aktif.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total PCS</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_pcs'] ?? 0, 0, ',', '.') }}</h3>
                    </div>
                    <i class="bi bi-capsule dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-3">
        <div class="card dashboard-stat h-100" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah seluruh order penjualan.">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2">Total Order</p>
                        <h3 class="dashboard-stat-value">{{ number_format($stats['total_orders'] ?? 0, 0, ',', '.') }}</h3>
                        <small>{{ $stats['pending_orders'] ?? 0 }} Pending</small>
                    </div>
                    <i class="bi bi-cart-check dashboard-stat-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="dashboard-section-title dashboard-actions-title">Akses cepat</h2>
<div class="row dashboard-actions-row">
    <div class="col-12 col-sm-6 col-xl-3 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-gear dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Kelola Lokasi</h3>
                <p class="card-text">
                    Setup lokasi penyimpanan
                </p>
                @if(auth()->user()->canAccessRoute('locations.index'))
                <a href="{{ route('locations.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Kelola Lokasi
                </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-eye dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Audit Trail</h3>
                <p class="card-text">
                    Pantau semua aktivitas
                </p>
                @if(auth()->user()->canAccessRoute('audit.index'))
                <a href="{{ route('audit.index') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Audit Trail
                </a>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3 mb-4">
        <div class="card dashboard-action h-100">
            <div class="card-body">
                <div class="mb-3">
                    <i class="bi bi-file-earmark-bar-graph dashboard-action-icon" aria-hidden="true"></i>
                </div>
                <h3 class="card-title">Laporan</h3>
                <p class="card-text">
                    Lihat semua laporan
                </p>
                @if(auth()->user()->canAccessRoute('reports.stock-input'))
                <a href="{{ route('reports.stock-input') }}" class="btn dashboard-action-link">
                    <i class="bi bi-arrow-right"></i> Lihat Laporan
                </a>
                @endif
            </div>
        </div>
    </div>
</div>
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
