@extends('shared.layouts.app')

@section('title', 'Dashboard - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h2">
            <i class="bi bi-speedometer2"></i> Dashboard
        </h1>
        <p class="text-muted">Selamat datang di Sistem Stok Penyimpanan Warehouse Finish Good Yamato</p>
    </div>
</div>

{{-- PACKING DEPARTMENT DASHBOARD --}}
@if($userRole === 'packing_department')
<div class="row mb-4">
    <!-- Total Pallets Today -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pallet Hari Ini</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['today_pallets'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-box2-heart" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Pallets All Time -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Pallet</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_pallets'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-boxes" style="font-size: 2.5rem; color: #249E94; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Input Pallet Action Card -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-plus-circle" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Input Pallet Baru</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Buat pallet baru dan input item dengan nomor part berbeda
                </p>
                <a href="{{ route('pallet-input.create') }}" class="btn btn-lg" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Buat Pallet
                </a>
            </div>
        </div>
    </div>
</div>

{{-- WAREHOUSE OPERATOR DASHBOARD --}}
@elseif($userRole === 'warehouse_operator')
<div class="row mb-4">
    <!-- Total Items in Warehouse -->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Item</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_items'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-archive" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pallets with Location -->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pallet Tersimpan</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pallets_with_location'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check-circle-fill" style="font-size: 2.5rem; color: #249E94; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pallets Without Location -->
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #3BC1A8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pending Input</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pallets_without_location'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-exclamation-circle-fill" style="font-size: 2.5rem; color: #3BC1A8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Input Stok Action Card -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-plus-circle" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Input Lokasi Stok</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Scan pallet dan input lokasi penyimpanan di gudang
                </p>
                <a href="{{ route('stock-input.index') }}" class="btn" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Mulai Input
                </a>
            </div>
        </div>
    </div>

    <!-- Lihat Stok Card -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #249E94;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-eye" style="font-size: 3rem; color: #249E94;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Lihat Stok Tersedia</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Cari dan lihat detail stok produk yang tersedia
                </p>
                <a href="{{ route('stock-view.index') }}" class="btn" style="background: #249E94; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Stok
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ADMIN DASHBOARD --}}
@elseif($userRole === 'admin')
<div class="row mb-4">
    <!-- Total Pallets -->
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Pallet</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_pallets'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-boxes" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Items -->
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Item</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_items'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-archive" style="font-size: 2.5rem; color: #249E94; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Box -->
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #3BC1A8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Box</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ (int) ($stats['total_box'] ?? 0) }}</h3>
                    </div>
                    <i class="bi bi-box2" style="font-size: 2.5rem; color: #3BC1A8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total PCS -->
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #005461;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total PCS</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_pcs'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-capsule" style="font-size: 2.5rem; color: #005461; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Pallets with Location -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pallet Tersimpan</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pallets_with_location'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check-circle-fill" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Locations -->
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Lokasi</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_locations'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill" style="font-size: 2.5rem; color: #249E94; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- All Action Cards -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f0f4f8; border-top: 4px solid #5b8fc4;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-plus-circle" style="font-size: 3rem; color: #5b8fc4;"></i>
                </div>
                <h5 class="card-title" style="color: #2c3e50;">Input Pallet Baru</h5>
                <p class="card-text" style="color: #7d8fa3; margin-bottom: 1.5rem;">
                    Kelola pallet dan item warehouse
                </p>
                <a href="{{ route('pallet-input.create') }}" class="btn" style="background: #5b8fc4; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Buat Pallet
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f0f4f8; border-top: 4px solid #6b9bd1;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-eye" style="font-size: 3rem; color: #6b9bd1;"></i>
                </div>
                <h5 class="card-title" style="color: #2c3e50;">Lihat Stok Tersedia</h5>
                <p class="card-text" style="color: #7d8fa3; margin-bottom: 1.5rem;">
                    Pantau stok produk yang tersedia
                </p>
                <a href="{{ route('stock-view.index') }}" class="btn" style="background: #6b9bd1; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Stok
                </a>
            </div>
        </div>
    </div>
</div>

@endif

<!-- Info Box -->
<div class="row mt-5">
    <div class="col-12">
        <div class="alert border-0 shadow-sm" style="background: #f0f4f8; border-left: 4px solid #5b8fc4;" role="alert">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-info-circle" style="font-size: 1.5rem; color: #5b8fc4;"></i>
                </div>
                <div class="col">
                    <h5 class="mb-1" style="color: #2c3e50;">Informasi Sistem</h5>
                    <p class="mb-0" style="color: #7d8fa3;">Sistem ini membantu Anda mengelola stok barang di warehouse dengan scan barcode dan pelacakan lokasi penyimpanan yang terstruktur.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
