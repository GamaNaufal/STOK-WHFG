@extends('shared.layouts.app')

@section('title', 'Laporan Audit Trail - Warehouse FG Yamato')

@section('styles')
<style>
    .audit-table { min-width: 960px; }
    .audit-table td { vertical-align: top; }
    .audit-table .audit-description-cell {
        min-width: 320px;
        width: 40%;
        white-space: normal;
    }
    .audit-description {
        color: #334155;
        font-size: 0.875rem;
        line-height: 1.65;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .audit-detail-trigger { min-height: 36px; }
    .audit-detail-modal .modal-content { border: 0; border-radius: 12px; overflow: hidden; }
    .audit-detail-modal .modal-header { background: #f0fdfa; }
    .audit-detail-modal .modal-title { color: #0C7779; font-weight: 700; }
    .audit-detail-modal .modal-body { padding: 24px; }
    .audit-detail-modal dt { color: #64748b; font-size: .8rem; font-weight: 500; }
    .audit-detail-modal dd { color: #1e293b; margin: 4px 0 0; overflow-wrap: anywhere; }
    .audit-detail-summary { background: #f8fafc; border-left: 3px solid #249E94; padding: 16px; border-radius: 6px; }
    .audit-change-heading, .audit-change-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; padding: 12px; }
    .audit-change-heading { background: #f1f5f9; color: #475569; font-size: .8rem; font-weight: 600; }
    .audit-change-row { border-bottom: 1px solid #e2e8f0; font-size: .875rem; overflow-wrap: anywhere; }
    .audit-value-after { color: #065f46; font-weight: 600; }
    .audit-value-label { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
    .audit-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .audit-detail-table { min-width: 480px; font-size: .875rem; }
    .audit-detail-table th, .audit-detail-table td { white-space: normal !important; overflow-wrap: anywhere; max-width: 240px; padding: 10px; vertical-align: top; }
    @media (max-width: 575.98px) {
        .audit-detail-modal .modal-body { padding: 16px; }
        .audit-change-heading { display: none; }
        .audit-change-row { grid-template-columns: 1fr; gap: 6px; }
        .audit-value-label { position: static; width: auto; height: auto; clip-path: none; font-weight: 500; }
        .audit-facts { grid-template-columns: 1fr; }
    }
</style>
@endsection

@section('content')
<!-- Modern Header Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="app-page-banner" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                    color: white; 
                    padding: 40px 30px; 
                    border-radius: 12px; 
                    box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;">
            <div>
                <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                    <i class="bi bi-shield-check"></i> Laporan Audit Trail
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Pencatatan lengkap semua aktivitas input stok dan pengambilan delivery
                </p>
            </div>
            <a href="{{ route('audit.export', array_filter($filters)) }}" class="btn btn-light btn-lg" style="border-radius: 8px; padding: 12px 28px; font-weight: 600;">
                <i class="bi bi-download"></i> Export Excel
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Aksi</p>
                        <h2 class="fw-bold" style="color: #0C7779; margin: 0; font-size: 2.5rem;">{{ intval($summary['total_actions']) }}</h2>
                    </div>
                    <i class="bi bi-file-text" style="font-size: 2.5rem; color: #0C7779; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Input Stok</p>
                        <h2 class="fw-bold" style="color: #249E94; margin: 0; font-size: 2.5rem;">{{ intval($summary['stock_inputs']) }}</h2>
                    </div>
                    <i class="bi bi-inbox" style="font-size: 2.5rem; color: #249E94; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Pengambilan Stok</p>
                        <h2 class="fw-bold" style="color: #3BC1A8; margin: 0; font-size: 2.5rem;">{{ intval($summary['stock_withdrawals']) }}</h2>
                    </div>
                    <i class="bi bi-box-seam" style="font-size: 2.5rem; color: #3BC1A8; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Redo Delivery</p>
                        <h2 class="fw-bold" style="color: #ff9800; margin: 0; font-size: 2.5rem;">{{ intval($summary['delivery_redos']) }}</h2>
                    </div>
                    <i class="bi bi-arrow-clockwise" style="font-size: 2.5rem; color: #ff9800; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card shadow-sm border-0 mb-4" style="border-radius: 12px; overflow: hidden;">
    <div class="app-section-heading" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 20px 24px; font-weight: 600; font-size: 15px;">
        <i class="bi bi-funnel"></i> Filter Laporan
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label fw-bold" style="font-size: 13px;">Dari Tanggal</label>
                <input type="date" class="form-control" name="start_date" value="{{ $filters['start_date'] ?? '' }}" style="border-radius: 8px; border: 1px solid #e5e7eb;">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold" style="font-size: 13px;">Sampai Tanggal</label>
                <input type="date" class="form-control" name="end_date" value="{{ $filters['end_date'] ?? '' }}" style="border-radius: 8px; border: 1px solid #e5e7eb;">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold" style="font-size: 13px;">Tipe Aksi</label>
                <select class="form-select" name="type" style="border-radius: 8px; border: 1px solid #e5e7eb;">
                    <option value="">-- Semua Tipe --</option>
                    <option value="stock_input" {{ $filters['type'] === 'stock_input' ? 'selected' : '' }}>Input Stok</option>
                    <option value="stock_withdrawal" {{ $filters['type'] === 'stock_withdrawal' ? 'selected' : '' }}>Pengambilan Stok</option>
                    <option value="delivery_assignment" {{ $filters['type'] === 'delivery_assignment' ? 'selected' : '' }}>Assignment Delivery</option>
                    <option value="delivery_pickup" {{ $filters['type'] === 'delivery_pickup' ? 'selected' : '' }}>Pengiriman</option>
                    <option value="delivery_redo" {{ $filters['type'] === 'delivery_redo' ? 'selected' : '' }}>Redo Delivery</option>
                    <option value="pallet_location_moved" {{ $filters['type'] === 'pallet_location_moved' ? 'selected' : '' }}>Pemindahan Lokasi Pallet</option>
                    <option value="box_pallet_moved" {{ $filters['type'] === 'box_pallet_moved' ? 'selected' : '' }}>Pemindahan Box</option>
                    <option value="pallet_merged" {{ $filters['type'] === 'pallet_merged' ? 'selected' : '' }}>Penggabungan Pallet</option>
                    <option value="other" {{ $filters['type'] === 'other' ? 'selected' : '' }}>Edit / Hapus Stok</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold" style="font-size: 13px;">Aksi</label>
                <select class="form-select" name="action" style="border-radius: 8px; border: 1px solid #e5e7eb;">
                    <option value="">-- Semua Aksi --</option>
                    @foreach(['created', 'updated', 'completed', 'reversed', 'redo', 'moved', 'merged'] as $action)
                        <option value="{{ $action }}" {{ $filters['action'] === $action ? 'selected' : '' }}>{{ \App\Support\AuditLogPresenter::actionLabel($action) }}</option>
                    @endforeach
                    <option value="box_updated_by_admin_warehouse" {{ $filters['action'] === 'box_updated_by_admin_warehouse' ? 'selected' : '' }}>Edit Box (Admin Warehouse)</option>
                    <option value="box_deleted_by_stock_view" {{ $filters['action'] === 'box_deleted_by_stock_view' ? 'selected' : '' }}>Hapus Box (Stock View)</option>
                    <option value="pallet_deleted_by_stock_view" {{ $filters['action'] === 'pallet_deleted_by_stock_view' ? 'selected' : '' }}>Hapus Pallet (Stock View)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold" style="font-size: 13px;">Operator</label>
                <select class="form-select" name="user_id" style="border-radius: 8px; border: 1px solid #e5e7eb;">
                    <option value="">-- Semua --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ $filters['user_id'] == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn w-100" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; border: none; border-radius: 8px; font-weight: 600;">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
        <a href="{{ route('audit.index', ['quick_filter' => 'box_edit']) }}" class="btn btn-sm mt-2" style="background: #dbeafe; color: #1e40af; border: none; border-radius: 8px;">
            <i class="bi bi-funnel-fill"></i> Filter Perubahan Box
        </a>
        <a href="{{ route('audit.index', ['type' => 'other', 'action' => 'box_deleted_by_stock_view']) }}" class="btn btn-sm mt-2" style="background: #fee2e2; color: #991b1b; border: none; border-radius: 8px;">
            <i class="bi bi-trash"></i> Filter Hapus Box
        </a>
        <a href="{{ route('audit.index', ['type' => 'other', 'action' => 'pallet_deleted_by_stock_view']) }}" class="btn btn-sm mt-2" style="background: #fecaca; color: #7f1d1d; border: none; border-radius: 8px;">
            <i class="bi bi-trash3"></i> Filter Hapus Pallet
        </a>
        <a href="{{ route('audit.index') }}" class="btn btn-sm mt-2" style="background: #f3f4f6; color: #6b7280; border: none; border-radius: 8px;">
            <i class="bi bi-arrow-clockwise"></i> Atur Ulang
        </a>
    </div>
</div>

<!-- Audit Trail Table -->
<div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
    <div class="app-section-heading" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 20px 24px; font-weight: 600; font-size: 15px;">
        <i class="bi bi-table"></i> Riwayat Audit Trail
    </div>
    <div class="card-body p-0">
        @if($auditLogs->count() > 0)
            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel data, geser untuk melihat kolom lainnya">
                <table class="table table-hover mb-0 audit-table">
                    <thead style="background-color: #f9fafb; color: #0C7779; border-bottom: 2px solid #e5e7eb;">
                        <tr>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">No</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Tanggal & Waktu</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Tipe</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Aksi</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Operator</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditLogs as $key => $log)
                            <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                <td style="padding: 16px 20px;">{{ $auditLogs->firstItem() + $key }}</td>
                                <td style="padding: 16px 20px;">
                                    <small class="text-muted">
                                        {{ $log->created_at->format('d/m/Y') }}<br>
                                        <strong style="color: #0C7779;">{{ $log->created_at->format('H:i:s') }}</strong>
                                    </small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    @switch($log->type)
                                        @case('stock_input')
                                            <span class="badge" style="background-color: #d1fae5; color: #065f46;">Input Stok</span>
                                            @break
                                        @case('stock_withdrawal')
                                            <span class="badge" style="background-color: #fef3c7; color: #92400e;">Ambil Stok</span>
                                            @break
                                        @case('delivery_pickup')
                                            <span class="badge" style="background-color: #dbeafe; color: #0c2d6b;">Pengiriman</span>
                                            @break
                                        @case('delivery_redo')
                                            <span class="badge" style="background-color: #fecaca; color: #7f1d1d;">Redo</span>
                                            @break
                                        @case('delivery_assignment')
                                            <span class="badge" style="background-color: #e0e7ff; color: #3730a3;">Assignment Delivery</span>
                                            @break
                                        @case('other')
                                            @if($log->action === 'box_updated_by_admin_warehouse')
                                                <span class="badge" style="background-color: #dbeafe; color: #1e40af;">Perubahan Box</span>
                                            @elseif($log->action === 'box_deleted_by_stock_view')
                                                <span class="badge" style="background-color: #fee2e2; color: #991b1b;">Hapus Box</span>
                                            @elseif($log->action === 'pallet_deleted_by_stock_view')
                                                <span class="badge" style="background-color: #fecaca; color: #7f1d1d;">Hapus Pallet</span>
                                            @else
                                                <span class="badge" style="background-color: #e5e7eb; color: #374151;">Lainnya</span>
                                            @endif
                                            @break
                                        @default
                                            <span class="badge" style="background-color: #e5e7eb; color: #374151;">{{ \App\Support\AuditLogPresenter::typeLabel($log->type) }}</span>
                                    @endswitch
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280; font-weight: 600;">
                                        {{ \App\Support\AuditLogPresenter::actionLabel($log->action) }}
                                    </small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280;">
                                        {{ $log->user?->name ?? 'System' }}
                                    </small>
                                </td>
                                <td class="audit-description-cell" style="padding: 16px 20px;">
                                    <div class="audit-description">{{ $log->description ?: '-' }}</div>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2 audit-detail-trigger"
                                            data-bs-toggle="modal" data-bs-target="#detailModal{{ $log->id }}"
                                            aria-label="Lihat rincian aktivitas audit nomor {{ $auditLogs->firstItem() + $key }}">
                                            <i class="bi bi-list-ul" aria-hidden="true"></i> Lihat rincian
                                        </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4" style="border-top: 1px solid #e5e7eb;">
                {{ $auditLogs->appends(request()->query())->links() }}
            </div>
        @else
            <div class="p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #e5e7eb;"></i>
                <x-empty-state 
                    icon="bi-file-earmark-text"
                    title="Belum Ada Data Audit"
                    message="Data audit trail akan muncul ketika ada aktivitas sistem"
                />
            </div>
        @endif
    </div>
</div>

<!-- Detail Modals -->
@foreach($auditLogs as $log)
    @include('supervisor.audit.partials.detail', ['log' => $log])
@endforeach

@endsection
