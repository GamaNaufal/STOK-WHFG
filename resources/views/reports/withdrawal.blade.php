@extends('shared.layouts.app')

@section('title', 'Laporan Pengambilan Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2">
                    <i class="bi bi-file-earmark-pdf"></i> Laporan Pengambilan Stok
                </h1>
                <p class="text-muted">Riwayat lengkap pengambilan stok dengan filter tanggal dan operator</p>
            </div>
            <a href="{{ route('reports.withdrawal.export', array_filter($filters)) }}" class="btn btn-lg" style="background-color: #249E94; color: white; border: none;">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1">Total Pengambilan</p>
                        <h3 class="mb-0" style="color: #0C7779;">{{ $totalWithdrawals }}</h3>
                    </div>
                    <i class="bi bi-box-seam" style="font-size: 1.8rem; color: #0C7779; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1">Total PCS Diambil</p>
                        <h3 class="mb-0" style="color: #249E94;">{{ number_format($totalPcsWithdrawn) }}</h3>
                    </div>
                    <i class="bi bi-stack" style="font-size: 1.8rem; color: #249E94; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #3BC1A8;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1">Total Pembatalan</p>
                        <h3 class="mb-0" style="color: #3BC1A8;">{{ $totalReversed }}</h3>
                    </div>
                    <i class="bi bi-exclamation-circle" style="font-size: 1.8rem; color: #3BC1A8; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Form -->
<div class="card shadow-sm border-0 mb-4" style="border-left: 4px solid #0C7779;">
    <div class="card-header text-white" style="background-color: #0C7779;">
        <i class="bi bi-funnel"></i> Filter Laporan
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label fw-bold">Dari Tanggal</label>
                <input type="date" class="form-control" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Sampai Tanggal</label>
                <input type="date" class="form-control" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Status</label>
                <select class="form-select" name="status">
                    <option value="">-- Semua Status --</option>
                    <option value="completed" {{ $filters['status'] === 'completed' ? 'selected' : '' }}>Selesai</option>
                    <option value="reversed" {{ $filters['status'] === 'reversed' ? 'selected' : '' }}>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Part Number</label>
                <input type="text" class="form-control" name="part_number" placeholder="Cari part number..." value="{{ $filters['part_number'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Operator</label>
                <select class="form-select" name="user_id">
                    <option value="">-- Semua Operator --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ $filters['user_id'] == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn w-100" style="background-color: #0C7779; color: white; border: none;">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
        <a href="{{ route('reports.withdrawal') }}" class="btn btn-sm btn-secondary mt-2">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
    </div>
</div>

<!-- Report Table -->
<div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
    <div class="card-header text-white" style="background-color: #0C7779;">
        <i class="bi bi-table"></i> Detail Pengambilan Stok
    </div>
    <div class="card-body p-0">
        @if($withdrawals->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color: #f5f7fa; color: #0C7779;">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Part Number</th>
                            <th>Box Diambil</th>
                            <th>PCS Diambil</th>
                            <th>Lokasi</th>
                            <th>Operator</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($withdrawals as $key => $withdrawal)
                            <tr>
                                <td>{{ $withdrawals->firstItem() + $key }}</td>
                                <td>
                                    <small class="text-muted">
                                        {{ $withdrawal->withdrawn_at->format('d/m/Y') }}<br>
                                        <strong>{{ $withdrawal->withdrawn_at->format('H:i') }}</strong>
                                    </small>
                                </td>
                                <td>
                                    <strong>{{ $withdrawal->part_number }}</strong>
                                </td>
                                <td>{{ (int) $withdrawal->box_quantity }} Box</td>
                                <td>{{ $withdrawal->pcs_quantity }} PCS</td>
                                <td>{{ $withdrawal->warehouse_location }}</td>
                                <td>
                                    <small>{{ $withdrawal->user->name }}</small>
                                </td>
                                <td>
                                    @if($withdrawal->status === 'completed')
                                        <span class="badge" style="background-color: #249E94; color: white;">
                                            <i class="bi bi-check-circle"></i> Selesai
                                        </span>
                                    @else
                                        <span class="badge" style="background-color: #9ca3af; color: white;">
                                            <i class="bi bi-x-circle"></i> Dibatalkan
                                        </span>
                                    @endif
                                </td>
                                <td>
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
            <div class="p-3">
                {{ $withdrawals->appends(request()->query())->links() }}
            </div>
        @else
            <div class="p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                <h5 class="mt-3 text-muted">Tidak ada data pengambilan</h5>
            </div>
        @endif
    </div>
</div>

@endsection
