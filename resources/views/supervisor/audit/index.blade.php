@extends('shared.layouts.app')

@section('title', 'Laporan Audit Trail - Warehouse FG Yamato')

@section('content')
<!-- Modern Header Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
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
    <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 20px 24px; font-weight: 600; font-size: 15px;">
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
                    <option value="delivery_redo" {{ $filters['type'] === 'delivery_redo' ? 'selected' : '' }}>Redo Delivery</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold" style="font-size: 13px;">Aksi</label>
                <select class="form-select" name="action" style="border-radius: 8px; border: 1px solid #e5e7eb;">
                    <option value="">-- Semua Aksi --</option>
                    <option value="created" {{ $filters['action'] === 'created' ? 'selected' : '' }}>Created</option>
                    <option value="completed" {{ $filters['action'] === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="reversed" {{ $filters['action'] === 'reversed' ? 'selected' : '' }}>Reversed</option>
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
        <a href="{{ route('audit.index') }}" class="btn btn-sm mt-2" style="background: #f3f4f6; color: #6b7280; border: none; border-radius: 8px;">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
    </div>
</div>

<!-- Audit Trail Table -->
<div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 20px 24px; font-weight: 600; font-size: 15px;">
        <i class="bi bi-table"></i> Riwayat Audit Trail
    </div>
    <div class="card-body p-0">
        @if($auditLogs->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
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
                                        @default
                                            <span class="badge" style="background-color: #e5e7eb; color: #374151;">Lainnya</span>
                                    @endswitch
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280; font-weight: 600;">{{ ucfirst($log->action) }}</small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280;">
                                        {{ $log->user?->name ?? 'System' }}
                                    </small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small class="text-muted" title="{{ $log->description ?? '-' }}">
                                        {{ $log->description ? substr($log->description, 0, 50) . (strlen($log->description) > 50 ? '...' : '') : '-' }}
                                    </small>
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
                <h5 class="mt-3 text-muted" style="font-weight: 600;">Tidak ada data audit trail</h5>
            </div>
        @endif
    </div>
</div>

<!-- Detail Modals -->
@foreach($auditLogs as $log)
    @if($log->new_values)
        <div class="modal fade" id="detailModal{{ $log->id }}" tabindex="-1" aria-labelledby="detailLabel{{ $log->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="border: none; border-radius: 12px;">
                    <div class="modal-header" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; border: none; border-radius: 12px 12px 0 0;">
                        <h5 class="modal-title" id="detailLabel{{ $log->id }}">Detail Aksi Audit</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="padding: 30px;">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">Tanggal & Waktu</p>
                                <p style="color: #1f2937; font-weight: 600;">{{ $log->created_at->format('d/m/Y H:i:s') }}</p>
                            </div>
                            <div class="col-md-6">
                                <p style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">Operator</p>
                                <p style="color: #1f2937; font-weight: 600;">{{ $log->user?->name ?? 'System' }}</p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">IP Address</p>
                                <p style="color: #1f2937; font-family: monospace; font-size: 12px;">{{ $log->ip_address ?? '-' }}</p>
                            </div>
                            <div class="col-md-6">
                                <p style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 5px;">Model</p>
                                <p style="color: #1f2937; font-weight: 600;">{{ $log->model ?? '-' }}</p>
                            </div>
                        </div>

                        <hr style="border-top: 1px solid #e5e7eb; margin: 20px 0;">

                        <p style="font-size: 12px; color: #6b7280; font-weight: 600; text-transform: uppercase; margin-bottom: 10px;">Data Perubahan</p>
                        
                        @php
                            $oldValues = $log->getOldValuesArray();
                            $newValues = $log->getNewValuesArray();
                        @endphp

                        <div style="background: #f9fafb; padding: 15px; border-radius: 8px; font-size: 13px; font-family: monospace; max-height: 300px; overflow-y: auto;">
                            @if(!empty($oldValues))
                                <p style="color: #dc2626; font-weight: 600; margin-bottom: 8px;">Sebelum:</p>
                                <pre style="color: #374151; margin-bottom: 15px;">{{ json_encode($oldValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif

                            @if(!empty($newValues))
                                <p style="color: #059669; font-weight: 600; margin-bottom: 8px;">Sesudah:</p>
                                <pre style="color: #374151;">{{ json_encode($newValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid #e5e7eb;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

@endsection
