@extends('shared.layouts.app')

@section('title', 'Laporan Audit Trail - Warehouse FG Yamato')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/audit-trail.css') }}?v={{ filemtime(public_path('css/pages/audit-trail.css')) }}">
<style>
    .audit-description {
        color: #334155;
        font-size: 0.875rem;
        line-height: 1.65;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
</style>
@endsection

@section('content')
<header class="audit-page-header">
    <div class="audit-page-intro">
        <span class="audit-page-eyebrow">Laporan operasional</span>
        <h1>Laporan Audit Trail</h1>
        <p>Telusuri aktivitas stok, pallet, dan delivery beserta rincian yang tercatat saat aksi terjadi.</p>
    </div>
    <div class="audit-page-export">
        <span>Ekspor mengikuti filter yang sedang diterapkan.</span>
        <a href="{{ route('audit.export', array_filter($filters)) }}" class="btn btn-outline-primary">
            <i class="bi bi-download" aria-hidden="true"></i> Export Excel
        </a>
    </div>
</header>

<section class="audit-overview" aria-labelledby="audit-overview-title">
    <div class="audit-overview-heading">
        <div>
            <h2 id="audit-overview-title">Ringkasan aktivitas</h2>
            <p>Angka mengikuti rentang tanggal; filter jenis, aksi, dan petugas hanya berlaku pada tabel riwayat.</p>
        </div>
        <span class="audit-overview-period">
            @if($filters['start_date'] || $filters['end_date'])
                {{ $filters['start_date'] ?: 'Awal data' }} – {{ $filters['end_date'] ?: 'Sekarang' }}
            @else
                Semua tanggal
            @endif
        </span>
    </div>
    <dl class="audit-metrics">
        <div class="audit-metric audit-metric--total">
            <dt>Total aksi</dt>
            <dd><strong>{{ number_format((int) $summary['total_actions']) }}</strong><span>Seluruh aktivitas tercatat</span></dd>
        </div>
        <div class="audit-metric">
            <dt>Input stok</dt>
            <dd><strong>{{ number_format((int) $summary['stock_inputs']) }}</strong><span>Stok masuk</span></dd>
        </div>
        <div class="audit-metric">
            <dt>Pengambilan stok</dt>
            <dd><strong>{{ number_format((int) $summary['stock_withdrawals']) }}</strong><span>Stok keluar</span></dd>
        </div>
        <div class="audit-metric">
            <dt>Redo delivery</dt>
            <dd><strong>{{ number_format((int) $summary['delivery_redos']) }}</strong><span>Pengiriman diulang</span></dd>
        </div>
    </dl>
</section>

<!-- Filter Form -->
@php
    $activeFilterCount = collect(request()->only('start_date', 'end_date', 'type', 'action', 'user_id', 'quick_filter'))
        ->filter(fn ($value) => is_scalar($value) && filled($value))->count();
    $quickQuery = request()->except('page');
    $boxEditQuickActive = $quickQuery == ['quick_filter' => 'box_edit'];
    $boxDeleteQuickActive = $quickQuery == ['type' => 'other', 'action' => 'box_deleted_by_stock_view'];
    $palletDeleteQuickActive = $quickQuery == ['type' => 'other', 'action' => 'pallet_deleted_by_stock_view'];
@endphp
<section class="card audit-filter-panel mb-4" aria-labelledby="audit-filter-title">
    <header class="audit-panel-heading">
        <div>
            <h2 id="audit-filter-title">Filter aktivitas</h2>
            <p>Persempit riwayat berdasarkan tanggal, jenis, aksi, dan petugas.</p>
        </div>
        <span class="audit-filter-count">{{ $activeFilterCount }} filter aktif</span>
    </header>
    <div class="card-body">
        <form method="GET" action="{{ route('audit.index') }}" class="audit-filter-form">
            <div class="audit-filter-field">
                <label for="audit-start-date" class="form-label">Dari tanggal</label>
                <input id="audit-start-date" type="date" class="form-control" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="audit-filter-field">
                <label for="audit-end-date" class="form-label">Sampai tanggal</label>
                <input id="audit-end-date" type="date" class="form-control" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="audit-filter-field">
                <label for="audit-type" class="form-label">Jenis aktivitas</label>
                <select id="audit-type" class="form-select" name="type">
                    <option value="">Semua jenis</option>
                    <option value="stock_input" {{ $filters['type'] === 'stock_input' ? 'selected' : '' }}>Input Stok</option>
                    <option value="stock_withdrawal" {{ $filters['type'] === 'stock_withdrawal' ? 'selected' : '' }}>Pengambilan Stok</option>
                    <option value="delivery_assignment" {{ $filters['type'] === 'delivery_assignment' ? 'selected' : '' }}>Assignment Delivery</option>
                    <option value="delivery_pickup" {{ $filters['type'] === 'delivery_pickup' ? 'selected' : '' }}>Pengiriman</option>
                    <option value="export_fulfillment" {{ $filters['type'] === 'export_fulfillment' ? 'selected' : '' }}>Pemenuhan Permintaan Ekspor</option>
                    <option value="delivery_redo" {{ $filters['type'] === 'delivery_redo' ? 'selected' : '' }}>Redo Delivery</option>
                    <option value="pallet_location_moved" {{ $filters['type'] === 'pallet_location_moved' ? 'selected' : '' }}>Pemindahan Lokasi Pallet</option>
                    <option value="box_pallet_moved" {{ $filters['type'] === 'box_pallet_moved' ? 'selected' : '' }}>Pemindahan Box</option>
                    <option value="pallet_merged" {{ $filters['type'] === 'pallet_merged' ? 'selected' : '' }}>Penggabungan Pallet</option>
                    <option value="expired_box" {{ $filters['type'] === 'expired_box' ? 'selected' : '' }}>Penanganan Box Expired</option>
                    <option value="not_full_box" {{ $filters['type'] === 'not_full_box' ? 'selected' : '' }}>Box Not Full</option>
                    <option value="delivery_pick" {{ $filters['type'] === 'delivery_pick' ? 'selected' : '' }}>Picking Delivery</option>
                    <option value="delivery_issue" {{ $filters['type'] === 'delivery_issue' ? 'selected' : '' }}>Issue Scan Delivery</option>
                    <option value="master_location" {{ $filters['type'] === 'master_location' ? 'selected' : '' }}>Master Lokasi</option>
                    <option value="master_part" {{ $filters['type'] === 'master_part' ? 'selected' : '' }}>Master Part</option>
                    <option value="user_management" {{ $filters['type'] === 'user_management' ? 'selected' : '' }}>Kelola User</option>
                    <option value="profile" {{ $filters['type'] === 'profile' ? 'selected' : '' }}>Profil Pengguna</option>
                    <option value="role_feature_access" {{ $filters['type'] === 'role_feature_access' ? 'selected' : '' }}>Hak Akses Role</option>
                    <option value="other" {{ $filters['type'] === 'other' ? 'selected' : '' }}>Edit / Hapus Stok</option>
                </select>
            </div>
            <div class="audit-filter-field">
                <label for="audit-action" class="form-label">Aksi</label>
                <select id="audit-action" class="form-select" name="action">
                    <option value="">Semua aksi</option>
                    @foreach(['created', 'updated', 'completed', 'ready', 'reduced', 'reopened', 'reversed', 'redo', 'moved', 'merged', 'handled'] as $action)
                        <option value="{{ $action }}" {{ $filters['action'] === $action ? 'selected' : '' }}>{{ \App\Support\AuditLogPresenter::actionLabel($action) }}</option>
                    @endforeach
                    @foreach(['approved', 'rejected', 'started', 'cancelled', 'verified', 'scanned', 'terminated', 'timed_out', 'deleted', 'deactivated'] as $action)
                        <option value="{{ $action }}" {{ $filters['action'] === $action ? 'selected' : '' }}>{{ \App\Support\AuditLogPresenter::actionLabel($action) }}</option>
                    @endforeach
                    <option value="box_updated_by_admin_warehouse" {{ $filters['action'] === 'box_updated_by_admin_warehouse' ? 'selected' : '' }}>Edit Box (Admin Warehouse)</option>
                    <option value="box_deleted_by_stock_view" {{ $filters['action'] === 'box_deleted_by_stock_view' ? 'selected' : '' }}>Hapus Box (Stock View)</option>
                    <option value="pallet_deleted_by_stock_view" {{ $filters['action'] === 'pallet_deleted_by_stock_view' ? 'selected' : '' }}>Hapus Pallet (Stock View)</option>
                </select>
            </div>
            <div class="audit-filter-field">
                <label for="audit-user" class="form-label">Petugas</label>
                <select id="audit-user" class="form-select" name="user_id">
                    <option value="">Semua petugas</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ $filters['user_id'] == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="audit-filter-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Terapkan</button>
                <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary">Atur ulang</a>
            </div>
        </form>
        <div class="audit-quick-filters" aria-label="Filter cepat">
            <span>Filter cepat</span>
            <a href="{{ route('audit.index', ['quick_filter' => 'box_edit']) }}" class="audit-quick-link {{ $boxEditQuickActive ? 'is-active' : '' }}" @if($boxEditQuickActive) aria-current="page" @endif>Perubahan box</a>
            <a href="{{ route('audit.index', ['type' => 'other', 'action' => 'box_deleted_by_stock_view']) }}" class="audit-quick-link {{ $boxDeleteQuickActive ? 'is-active' : '' }}" @if($boxDeleteQuickActive) aria-current="page" @endif>Hapus box</a>
            <a href="{{ route('audit.index', ['type' => 'other', 'action' => 'pallet_deleted_by_stock_view']) }}" class="audit-quick-link {{ $palletDeleteQuickActive ? 'is-active' : '' }}" @if($palletDeleteQuickActive) aria-current="page" @endif>Hapus pallet</a>
        </div>
    </div>
</section>

<!-- Audit Trail Table -->
<section class="card audit-results-panel" aria-labelledby="audit-results-title">
    <header class="audit-panel-heading">
        <div>
            <h2 id="audit-results-title">Riwayat aktivitas</h2>
            <p>Aktivitas terbaru ditampilkan lebih dahulu. Rincian memuat catatan yang tersimpan saat aksi terjadi.</p>
        </div>
        <span class="audit-results-count">{{ number_format($auditLogs->total()) }} aktivitas sesuai filter</span>
    </header>
    <div class="card-body p-0">
        @if($auditLogs->count() > 0)
            <p class="audit-scroll-hint" id="audit-scroll-hint">Geser tabel untuk melihat semua kolom. Nomor baris tetap terlihat.</p>
            <div class="table-responsive audit-results-scroll" tabindex="0" role="region" aria-labelledby="audit-results-title" aria-describedby="audit-scroll-hint">
                <table class="table table-hover mb-0 audit-table">
                    <caption class="visually-hidden">Riwayat audit sesuai filter, diurutkan dari aktivitas terbaru</caption>
                    <thead>
                        <tr>
                            <th scope="col">No</th>
                            <th scope="col">Tanggal & waktu</th>
                            <th scope="col">Jenis</th>
                            <th scope="col">Aksi</th>
                            <th scope="col">Petugas</th>
                            <th scope="col">Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditLogs as $key => $log)
                            <tr>
                                <th scope="row" class="audit-row-number">{{ $auditLogs->firstItem() + $key }}</th>
                                <td>
                                    <time class="audit-row-date" datetime="{{ $log->created_at->toIso8601String() }}">
                                        <strong>{{ $log->created_at->format('d/m/Y') }}</strong>
                                        <span>{{ $log->created_at->format('H:i:s') }}</span>
                                    </time>
                                </td>
                                <td>
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
                                <td>
                                    <span class="audit-row-action">
                                        {{ \App\Support\AuditLogPresenter::actionLabel($log->action) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="audit-row-operator">
                                        {{ $log->user?->name ?? 'System' }}
                                    </span>
                                </td>
                                <td class="audit-description-cell">
                                    <div class="audit-description">{{ $log->description ?: '-' }}</div>
                                        <button type="button" class="btn btn-outline-primary mt-2 audit-detail-trigger"
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

            <div class="audit-results-pagination">
                <p>Menampilkan {{ number_format($auditLogs->firstItem() ?? 0) }}–{{ number_format($auditLogs->lastItem() ?? 0) }} dari {{ number_format($auditLogs->total()) }} aktivitas</p>
                {{ $auditLogs->appends(request()->query())->fragment('audit-results-title')->links() }}
            </div>
        @else
            <div class="audit-results-empty">
                <x-empty-state 
                    icon="bi-file-earmark-text"
                    title="{{ $activeFilterCount ? 'Tidak ada aktivitas sesuai filter' : 'Belum ada data audit' }}"
                    message="{{ $activeFilterCount ? 'Ubah filter atau atur ulang untuk melihat riwayat lainnya.' : 'Aktivitas sistem akan muncul di sini setelah tercatat.' }}"
                />
            </div>
        @endif
    </div>
</section>

<!-- Detail Modals -->
@foreach($auditLogs as $log)
    @include('supervisor.audit.partials.detail', ['log' => $log])
@endforeach

@endsection
