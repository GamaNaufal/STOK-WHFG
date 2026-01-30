@extends('shared.layouts.app')

@section('title', 'Dashboard - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h2">
            <i class="bi bi-speedometer2"></i> Dashboard
        </h1>
        <p class="text-muted">Selamat datang di Sistem Stok Penyimpanan Warehouse Finish Good Yamato</p>
        @if(isset($stats['role_label']))
            <span class="badge" style="background: #0C7779; color: white; font-size: 0.9rem; padding: 0.5rem 1rem;">{{ $stats['role_label'] }}</span>
        @endif
    </div>
</div>

{{-- WAREHOUSE OPERATOR DASHBOARD --}}
@if($userRole === 'warehouse_operator')
<div class="row mb-4">
    <div class="col-md-6 mb-3">
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
    <div class="col-md-6 mb-3">
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
</div>

<div class="row mb-4">
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
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #005461;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Lokasi</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_locations'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill" style="font-size: 2.5rem; color: #005461; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Delivery Pending</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pending_deliveries'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-truck" style="font-size: 2.5rem; color: #17a2b8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-plus-circle" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Input Lokasi Stok</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Scan pallet dan input lokasi penyimpanan
                </p>
                <a href="{{ route('stock-input.index') }}" class="btn" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Mulai Input
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #249E94;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-eye" style="font-size: 3rem; color: #249E94;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Lihat Stok</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Cek status stok produk yang tersedia
                </p>
                <a href="{{ route('stock-view.index') }}" class="btn" style="background: #249E94; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Stok
                </a>
            </div>
        </div>
    </div>
</div>

{{-- SALES DASHBOARD --}}
@elseif($userRole === 'sales')
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Order</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-cart-check" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pending Today</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['today_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-calendar-day" style="font-size: 2.5rem; color: #17a2b8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #ffc107;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pending</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pending_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-hourglass-split" style="font-size: 2.5rem; color: #ffc107; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Approved</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['approved_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 2.5rem; color: #249E94; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Completed</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['completed_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check2-all" style="font-size: 2.5rem; color: #28a745; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-plus-circle" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Input Sales Order</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Buat order penjualan baru
                </p>
                <a href="{{ route('delivery.create') }}" class="btn" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Buat Order
                </a>
            </div>
        </div>
    </div>
</div>

{{-- PPC DASHBOARD --}}
@elseif($userRole === 'ppc')
<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #ff6b6b;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pending Approval</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pending_approval'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-hourglass-split" style="font-size: 2.5rem; color: #ff6b6b; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Approved Orders</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['approved_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check-circle" style="font-size: 2.5rem; color: #249E94; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Orders</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-cart-check" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Stok (Pcs)</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_stok_pcs'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-capsule" style="font-size: 2.5rem; color: #17a2b8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Items</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_stok_items'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-archive" style="font-size: 2.5rem; color: #28a745; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #ff6b6b;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-clipboard-check" style="font-size: 3rem; color: #ff6b6b;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Lihat Pending Approval</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Review dan approve order yang menunggu
                </p>
                <a href="{{ route('delivery.approvals') }}" class="btn" style="background: #ff6b6b; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Approval
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-truck" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Delivery Schedule</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Lihat jadwal delivery (read-only)
                </p>
                <a href="{{ route('delivery.index') }}" class="btn" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Jadwal
                </a>
            </div>
        </div>
    </div>
</div>

{{-- SUPERVISI DASHBOARD --}}
@elseif($userRole === 'supervisi')
<div class="row mb-4">
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
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total PCS</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_pcs'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-capsule" style="font-size: 2.5rem; color: #17a2b8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
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
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Order</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_orders'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-cart-check" style="font-size: 2.5rem; color: #28a745; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #ffc107;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Completed Today</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['completed_orders_today'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check2-all" style="font-size: 2.5rem; color: #ffc107; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Delivery</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_deliveries'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-truck" style="font-size: 2.5rem; color: #17a2b8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-eye" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Lihat Stok</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Monitor stok produk yang tersedia
                </p>
                <a href="{{ route('stock-view.index') }}" class="btn" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Stok
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #28a745;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-file-earmark-bar-graph" style="font-size: 3rem; color: #28a745;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Laporan</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Lihat laporan input stok dan pengambilan
                </p>
                <a href="{{ route('reports.stock-input') }}" class="btn" style="background: #28a745; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Laporan
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ADMIN WAREHOUSE DASHBOARD --}}
@elseif($userRole === 'admin_warehouse')
<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Lokasi</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_locations'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
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
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Pallet Tersimpan</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pallets_with_location'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check-circle-fill" style="font-size: 2.5rem; color: #17a2b8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total PCS</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_pcs'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-capsule" style="font-size: 2.5rem; color: #28a745; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #ff6b6b;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Scan Issues</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pending_scan_issues'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-exclamation-triangle" style="font-size: 2.5rem; color: #ff6b6b; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #005461;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Item</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_items'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-archive" style="font-size: 2.5rem; color: #005461; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-gear" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Kelola Lokasi</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Atur lokasi penyimpanan gudang
                </p>
                <a href="{{ route('locations.index') }}" class="btn" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Manage
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #ff6b6b;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ff6b6b;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Scan Issues</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Kelola masalah scan
                </p>
                <a href="{{ route('delivery.pick.issues') }}" class="btn" style="background: #ff6b6b; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat Issues
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #249E94;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-eye" style="font-size: 3rem; color: #249E94;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Lihat Stok</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem;">
                    Monitor stok warehouse
                </p>
                <a href="{{ route('stock-view.index') }}" class="btn" style="background: #249E94; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ADMIN DASHBOARD --}}
@else
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Pallet</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_pallets'] ?? 0 }}</h3>
                        <small style="color: #9ca3af;">{{ $stats['today_pallets'] ?? 0 }} Hari Ini</small>
                    </div>
                    <i class="bi bi-boxes" style="font-size: 2.5rem; color: #0C7779; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Pallet Tersimpan</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['pallets_with_location'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-check-circle-fill" style="font-size: 2.5rem; color: #249E94; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #17a2b8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Lokasi</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_locations'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-geo-alt-fill" style="font-size: 2.5rem; color: #17a2b8; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #28a745;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Item</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_items'] ?? 0 }}</h3>
                    </div>
                    <i class="bi bi-archive" style="font-size: 2.5rem; color: #28a745; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #ffc107;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Box</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ (int) ($stats['total_box'] ?? 0) }}</h3>
                    </div>
                    <i class="bi bi-box2" style="font-size: 2.5rem; color: #ffc107; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
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
    <div class="col-md-3 mb-3">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-left: 4px solid #ff6b6b;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="card-text mb-2" style="color: #9ca3af;">Total Order</p>
                        <h3 class="mb-0" style="color: #1f2937;">{{ $stats['total_orders'] ?? 0 }}</h3>
                        <small style="color: #9ca3af;">{{ $stats['pending_orders'] ?? 0 }} Pending</small>
                    </div>
                    <i class="bi bi-cart-check" style="font-size: 2.5rem; color: #ff6b6b; opacity: 0.7;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #0C7779;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-gear" style="font-size: 3rem; color: #0C7779;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Kelola Lokasi</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    Setup lokasi penyimpanan
                </p>
                <a href="{{ route('locations.index') }}" class="btn btn-sm" style="background: #0C7779; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Manage
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #17a2b8;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-eye" style="font-size: 3rem; color: #17a2b8;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Audit Trail</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    Pantau semua aktivitas
                </p>
                <a href="{{ route('audit.index') }}" class="btn btn-sm" style="background: #17a2b8; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card shadow-sm border-0 h-100" style="background: #f5f7fa; border-top: 4px solid #28a745;">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="bi bi-file-earmark-bar-graph" style="font-size: 3rem; color: #28a745;"></i>
                </div>
                <h5 class="card-title" style="color: #1f2937;">Laporan</h5>
                <p class="card-text" style="color: #9ca3af; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    Lihat semua laporan
                </p>
                <a href="{{ route('reports.stock-input') }}" class="btn btn-sm" style="background: #28a745; color: white; border: none;">
                    <i class="bi bi-arrow-right"></i> Lihat
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
