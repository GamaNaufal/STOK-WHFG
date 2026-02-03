@extends('shared.layouts.app')

@section('title', 'Laporan Pengambilan Stok - Warehouse FG Yamato')

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
                    <i class="bi bi-file-earmark-pdf"></i> Laporan Pengambilan Stok
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Riwayat lengkap pengambilan stok dengan filter tanggal dan operator
                </p>
            </div>
            <a href="{{ route('reports.withdrawal.export', array_filter($filters)) }}" class="btn btn-light btn-lg" style="border-radius: 8px; padding: 12px 28px; font-weight: 600;">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Pengambilan</p>
                        <h2 class="fw-bold" style="color: #0C7779; margin: 0; font-size: 2.5rem;">{{ $totalWithdrawals }}</h2>
                    </div>
                    <i class="bi bi-box-seam" style="font-size: 2.5rem; color: #0C7779; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total PCS Diambil</p>
                        <h2 class="fw-bold" style="color: #249E94; margin: 0; font-size: 2.5rem;">{{ number_format($totalPcsWithdrawn) }}</h2>
                    </div>
                    <i class="bi bi-stack" style="font-size: 2.5rem; color: #249E94; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Pembatalan</p>
                        <h2 class="fw-bold" style="color: #3BC1A8; margin: 0; font-size: 2.5rem;">{{ $totalReversed }}</h2>
                    </div>
                    <i class="bi bi-exclamation-circle" style="font-size: 2.5rem; color: #3BC1A8; opacity: 0.2;"></i>
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
                <label class="form-label fw-bold" style="font-size: 13px;">Status</label>
                <select class="form-select" name="status" style="border-radius: 8px; border: 1px solid #e5e7eb;">
                    <option value="">-- Semua Status --</option>
                    <option value="completed" {{ $filters['status'] === 'completed' ? 'selected' : '' }}>Selesai</option>
                    <option value="reversed" {{ $filters['status'] === 'reversed' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold" style="font-size: 13px;">Part Number</label>
                <input type="text" class="form-control" name="part_number" placeholder="Cari part..." value="{{ $filters['part_number'] ?? '' }}" style="border-radius: 8px; border: 1px solid #e5e7eb;">
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
        <a href="{{ route('reports.withdrawal') }}" class="btn btn-sm mt-2" style="background: #f3f4f6; color: #6b7280; border: none; border-radius: 8px;">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
    </div>
</div>

<!-- Report Table -->
<div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 20px 24px; font-weight: 600; font-size: 15px;">
        <i class="bi bi-table"></i> Detail Pengambilan Stok
    </div>
    <div class="card-body p-0">
        @if($withdrawals->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color: #f9fafb; color: #0C7779; border-bottom: 2px solid #e5e7eb;">
                        <tr>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">No</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Tanggal</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Part Number</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Box Diambil</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">PCS Diambil</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Lokasi</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Operator</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Status</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withdrawals as $key => $withdrawal)
                            <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                <td style="padding: 16px 20px;">{{ $withdrawals->firstItem() + $key }}</td>
                                <td style="padding: 16px 20px;">
                                    <small class="text-muted">
                                        {{ $withdrawal->withdrawn_at->format('d/m/Y') }}<br>
                                        <strong style="color: #0C7779;">{{ $withdrawal->withdrawn_at->format('H:i') }}</strong>
                                    </small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <span class="badge" style="background-color: #f0f4f8; color: #0C7779;">
                                        {{ $withdrawal->part_number }}
                                    </span>
                                </td>
                                <td style="padding: 16px 20px; color: #1f2937; font-weight: 600;">{{ (int) $withdrawal->box_quantity }} Box</td>
                                <td style="padding: 16px 20px; color: #1f2937; font-weight: 700;">{{ $withdrawal->pcs_quantity }} PCS</td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280;">{{ $withdrawal->warehouse_location }}</small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280;">{{ $withdrawal->user->name }}</small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    @if($withdrawal->status === 'completed')
                                        <span class="badge" style="background-color: #10b981; color: white;">
                                            <i class="bi bi-check-circle"></i> Selesai
                                        </span>
                                    @else
                                        <span class="badge" style="background-color: #9ca3af; color: white;">
                                            <i class="bi bi-x-circle"></i> Dibatalkan
                                        </span>
                                    @endif
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small class="text-muted" title="{{ $withdrawal->notes ?? '-' }}">
                                        {{ $withdrawal->notes ? substr($withdrawal->notes, 0, 30) . '...' : '-' }}
                                    </small>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4" style="border-top: 1px solid #e5e7eb;">
                {{ $withdrawals->appends(request()->query())->links() }}
            </div>
        @else
            <div class="p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #e5e7eb;"></i>
                <x-empty-state 
                    icon="bi-box-arrow-right"
                    title="Belum Ada Data Pengambilan"
                    message="Data pengambilan stok akan muncul setelah ada transaksi pengambilan"
                />
            </div>
        @endif
    </div>
</div>

@endsection
