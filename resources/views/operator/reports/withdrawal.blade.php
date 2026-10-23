@extends('shared.layouts.app')

@section('title', 'Laporan Pengambilan Stok - Warehouse FG Yamato')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/report-withdrawal.css') }}?v={{ filemtime(public_path('css/pages/report-withdrawal.css')) }}">
@endsection

@section('content')
<header class="withdrawal-report-header">
    <div class="withdrawal-report-intro">
        <span class="withdrawal-report-eyebrow">Laporan stok</span>
        <h1>Laporan Pengambilan Stok</h1>
        <p>Telusuri pengambilan, pengembalian/redo, jumlah PCS, dan catatan operator.</p>
    </div>
    <div class="withdrawal-report-export">
        <span>Excel mengikuti tanggal dan status; filter part dan operator hanya untuk tabel.</span>
        <a href="{{ route('reports.withdrawal.export', array_filter($filters)) }}" class="btn btn-outline-primary">
            <i class="bi bi-download" aria-hidden="true"></i> Export Excel
        </a>
    </div>
</header>

<section class="withdrawal-report-overview mb-4" aria-labelledby="withdrawal-overview-title">
    <div class="withdrawal-report-overview-heading">
        <div>
            <span class="withdrawal-report-eyebrow">Ringkasan</span>
            <h2 id="withdrawal-overview-title">Seluruh histori pengambilan</h2>
        </div>
        <p>Angka ringkasan tidak berubah saat filter diterapkan.</p>
    </div>
    <div class="withdrawal-report-metrics">
        <dl class="withdrawal-report-metric withdrawal-report-metric-primary">
            <dt><i class="bi bi-stack" aria-hidden="true"></i> Total PCS Diambil</dt>
            <dd><span>{{ number_format($totalPcsWithdrawn) }}</span><small>PCS</small></dd>
        </dl>
        <dl class="withdrawal-report-metric">
            <dt><i class="bi bi-box-seam" aria-hidden="true"></i> Total Pengambilan</dt>
            <dd><span>{{ number_format($totalWithdrawals) }}</span><small>selesai</small></dd>
        </dl>
        <dl class="withdrawal-report-metric withdrawal-report-metric-reversed">
            <dt><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Total Dikembalikan/Redo</dt>
            <dd><span>{{ number_format($totalReversed) }}</span><small>transaksi</small></dd>
        </dl>
    </div>
</section>

@php
    $activeFilterCount = collect($filters)
        ->filter(fn ($value) => is_scalar($value) && filled($value))
        ->count();
@endphp
<section class="card withdrawal-report-filters mb-4" aria-labelledby="withdrawal-filter-title">
    <header class="withdrawal-report-filter-heading">
        <div>
            <h2 id="withdrawal-filter-title">Filter catatan</h2>
            <p>Kelima filter membatasi tabel. Excel mengikuti tanggal dan status; ringkasan mencakup seluruh histori.</p>
        </div>
        <span>{{ $activeFilterCount }} filter aktif</span>
    </header>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.withdrawal') }}" class="withdrawal-report-filter-grid">
            <div class="withdrawal-report-field">
                <label for="withdrawal-report-start" class="form-label">Dari tanggal</label>
                <input id="withdrawal-report-start" type="date" class="form-control" name="start_date" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="withdrawal-report-field">
                <label for="withdrawal-report-end" class="form-label">Sampai tanggal</label>
                <input id="withdrawal-report-end" type="date" class="form-control" name="end_date" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="withdrawal-report-field">
                <label for="withdrawal-report-status" class="form-label">Status</label>
                <select id="withdrawal-report-status" class="form-select" name="status">
                    <option value="">Semua status</option>
                    <option value="completed" {{ $filters['status'] === 'completed' ? 'selected' : '' }}>Selesai</option>
                    <option value="reversed" {{ $filters['status'] === 'reversed' ? 'selected' : '' }}>Dikembalikan/Redo</option>
                </select>
            </div>
            <div class="withdrawal-report-field">
                <label for="withdrawal-report-part" class="form-label">Nomor part</label>
                <input id="withdrawal-report-part" type="text" class="form-control" name="part_number" placeholder="Cari nomor part" value="{{ $filters['part_number'] ?? '' }}">
            </div>
            <div class="withdrawal-report-field">
                <label for="withdrawal-report-operator" class="form-label">Operator</label>
                <select id="withdrawal-report-operator" class="form-select" name="user_id">
                    <option value="">Semua operator</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ $filters['user_id'] == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="withdrawal-report-filter-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Terapkan</button>
                <a href="{{ route('reports.withdrawal') }}" class="btn btn-outline-secondary">Atur ulang</a>
            </div>
        </form>
    </div>
</section>

<section class="withdrawal-report-results" aria-labelledby="withdrawal-results-title">
    <header class="withdrawal-report-results-heading">
        <div>
            <h2 id="withdrawal-results-title">Hasil pengambilan stok</h2>
            <p>Menampilkan {{ $withdrawals->firstItem() ?? 0 }}–{{ $withdrawals->lastItem() ?? 0 }} dari {{ number_format($withdrawals->total()) }} pencatatan sesuai filter.</p>
        </div>
        <span id="withdrawal-table-hint"><i class="bi bi-arrow-left-right" aria-hidden="true"></i> Geser tabel untuk melihat semua kolom</span>
    </header>
    @if($withdrawals->count() > 0)
        <div class="table-responsive withdrawal-report-table-scroll" tabindex="0" role="region" aria-label="Tabel hasil pengambilan stok" aria-describedby="withdrawal-table-hint">
            <table class="table table-hover withdrawal-report-table mb-0">
                <caption class="visually-hidden">Riwayat pengambilan stok sesuai filter, termasuk status dan catatan transaksi.</caption>
                <thead>
                    <tr>
                        <th scope="col" class="withdrawal-report-entry-cell">Catatan</th>
                        <th scope="col">Part Number</th>
                        <th scope="col">ID Box</th>
                        <th scope="col" class="withdrawal-report-number">Box Diambil</th>
                        <th scope="col" class="withdrawal-report-number">PCS Diambil</th>
                        <th scope="col">Lokasi</th>
                        <th scope="col">Operator</th>
                        <th scope="col">Status</th>
                        <th scope="col">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($withdrawals as $key => $withdrawal)
                        <tr>
                            <th scope="row" class="withdrawal-report-entry-cell">
                                <span class="withdrawal-report-entry-number">#{{ $withdrawals->firstItem() + $key }}</span>
                                <span class="withdrawal-report-entry-date">{{ $withdrawal->withdrawn_at->format('d/m/Y') }}</span>
                                <span class="withdrawal-report-entry-time">{{ $withdrawal->withdrawn_at->format('H:i') }}</span>
                            </th>
                            <td><span class="withdrawal-report-part-label">{{ $withdrawal->part_number }}</span></td>
                            <td class="withdrawal-report-box-id">{{ $withdrawal->box->box_number ?? $withdrawal->box_id ?? '-' }}</td>
                            <td class="withdrawal-report-number">{{ (int) $withdrawal->box_quantity }} <span>Box</span></td>
                            <td class="withdrawal-report-number withdrawal-report-pcs">{{ number_format((int) $withdrawal->pcs_quantity) }} <span>PCS</span></td>
                            <td class="withdrawal-report-secondary">{{ $withdrawal->warehouse_location }}</td>
                            <td class="withdrawal-report-secondary">{{ $withdrawal->user?->name ?? '-' }}</td>
                            <td>
                                @if($withdrawal->status === 'completed')
                                    <span class="withdrawal-report-status is-completed">Selesai</span>
                                @elseif($withdrawal->status === 'reversed')
                                    <span class="withdrawal-report-status is-reversed">Dikembalikan/Redo</span>
                                @else
                                    <span class="withdrawal-report-status">{{ ucfirst((string) $withdrawal->status) }}</span>
                                @endif
                            </td>
                            <td class="withdrawal-report-notes">{{ $withdrawal->notes ?: '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="withdrawal-report-pagination">
            {{ $withdrawals->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="withdrawal-report-empty">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            <h3>Tidak ada data pengambilan stok</h3>
            <p>{{ $activeFilterCount > 0 ? 'Tidak ada pencatatan yang sesuai dengan filter saat ini.' : 'Data pengambilan stok akan muncul setelah ada transaksi.' }}</p>
            @if($activeFilterCount > 0)
                <a href="{{ route('reports.withdrawal') }}" class="btn btn-outline-secondary">Atur ulang filter</a>
            @endif
        </div>
    @endif
</section>

@endsection
