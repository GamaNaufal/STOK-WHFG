@extends('shared.layouts.app')

@section('title', 'Operational Warehouse Reports')

@section('styles')
<style>
    .popover.popover-wide {
        max-width: 520px;
    }
    .popover.popover-wide .popover-body {
        max-width: 520px;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Operational Warehouse Reports</h1>
            <div class="text-muted small">Periode: {{ $rangeLabel }} @if($start && $end) ({{ $start->format('d M Y') }} - {{ $end->format('d M Y') }}) @endif</div>
        </div>
        <a href="{{ route('reports.operational.export', request()->query()) }}" class="btn btn-success">
            <i class="bi bi-download"></i> Export Excel
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4" id="audit-report">
        <div class="card-body">
            <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
                <div class="col-lg-2 col-md-4">
                    <label class="form-label">Quick Period</label>
                    <select name="period" class="form-select">
                        <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="week" {{ request('period', 'week') === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                        <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                        <option value="all" {{ request('period') === 'all' ? 'selected' : '' }}>All Time</option>
                    </select>
                    <small class="text-muted">Jika isi date range, akan override.</small>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label">Group By</label>
                    <select name="group_by" class="form-select">
                        <option value="day" {{ request('group_by', 'day') === 'day' ? 'selected' : '' }}>Harian</option>
                        <option value="week" {{ request('group_by') === 'week' ? 'selected' : '' }}>Mingguan</option>
                        <option value="month" {{ request('group_by') === 'month' ? 'selected' : '' }}>Bulanan</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
                <div class="col-lg-3 col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
                <div class="col-lg-2 col-md-4 d-flex gap-2">
                    <button class="btn btn-primary w-100">Apply</button>
                    <a href="{{ route('dashboard', ['period' => 'week']) }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @php
        $activeBoxPreview = $currentHandling->sortByDesc('created_at')->take(5);
        $activePallets = $currentHandling->groupBy('pallet_number')->map(function ($rows) {
            $first = $rows->first();
            $firstIn = optional($rows->sortBy('created_at')->first()->created_at)->format('d M Y H:i');
            $lastIn = optional($rows->sortByDesc('created_at')->first()->created_at)->format('d M Y H:i');
            return [
                'pallet_number' => $first->pallet_number,
                'total_boxes' => $rows->count(),
                'total_pcs' => $rows->sum('pcs_quantity'),
                'location' => $first->warehouse_location ?? 'Unknown',
                'first_in' => $firstIn,
                'last_in' => $lastIn,
                'last_in_sort' => $rows->max('created_at'),
            ];
        });
        $activePalletPreview = $activePallets->sortByDesc('last_in_sort')->values()->take(5);
        $fulfillmentPreview = $fulfillmentRows->sortBy('rate')->take(5);
        $scanPreview = $issueList->take(5);
    @endphp

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="left" data-popover-target="fulfillment-popover" data-popover-title="Fulfillment (Top 5)">
                <div class="card-body position-relative">
                    <h6 class="text-muted">Fulfillment Rate (Order Full)</h6>
                    <div class="display-6 fw-bold">{{ $fulfillmentRate }}%</div>
                    <a href="#fulfillment-report" class="stretched-link" aria-label="Lihat detail fulfillment"></a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="left" data-popover-target="active-box-popover" data-popover-title="Active Boxes (Top 5)">
                <div class="card-body position-relative">
                    <h6 class="text-muted">Active Boxes</h6>
                    <div class="display-6 fw-bold">{{ $currentHandling->count() }}</div>
                    <a href="#current-handling" class="stretched-link" aria-label="Lihat detail active boxes"></a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="left" data-popover-target="active-pallet-popover" data-popover-title="Active Pallets (Top 5)">
                <div class="card-body position-relative">
                    <h6 class="text-muted">Active Pallets</h6>
                    <div class="display-6 fw-bold">{{ $activePallets->count() }}</div>
                    <a href="#active-pallets" class="stretched-link" aria-label="Lihat detail active pallets"></a>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 h-100" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="left" data-popover-target="scan-issues-popover" data-popover-title="Scan Issues (Top 5)">
                <div class="card-body position-relative">
                    <h6 class="text-muted">Scan Issues</h6>
                    <div class="display-6 fw-bold">{{ $issueSummary->sum('total') }}</div>
                    <a href="#scan-issues" class="stretched-link" aria-label="Lihat detail scan issues"></a>
                </div>
            </div>
        </div>
    </div>

    <div id="fulfillment-popover" class="d-none">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Rate</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($fulfillmentPreview as $row)
                    <tr>
                        <td>#{{ $row['order_id'] }}</td>
                        <td>{{ $row['customer'] }}</td>
                        <td>{{ $row['rate'] }}%</td>
                        <td>{{ $row['status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div id="active-box-popover" class="d-none">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Box</th>
                    <th>Part</th>
                    <th>PCS</th>
                    <th>Pallet</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activeBoxPreview as $row)
                    <tr>
                        <td>{{ $row->box_number }}</td>
                        <td>{{ $row->part_number }}</td>
                        <td>{{ $row->pcs_quantity }}</td>
                        <td>{{ $row->pallet_number }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div id="active-pallet-popover" class="d-none">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Pallet</th>
                    <th>Boxes</th>
                    <th>PCS</th>
                    <th>Lokasi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activePalletPreview as $row)
                    <tr>
                        <td>{{ $row['pallet_number'] }}</td>
                        <td>{{ $row['total_boxes'] }}</td>
                        <td>{{ $row['total_pcs'] }}</td>
                        <td>{{ $row['location'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div id="scan-issues-popover" class="d-none">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Scanned</th>
                    <th>Status</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scanPreview as $row)
                    <tr>
                        <td>#{{ $row['order_id'] ?? '-' }}</td>
                        <td>{{ $row['scanned_code'] ?? '-' }}</td>
                        <td>{{ $row['status'] ?? '-' }}</td>
                        <td>{{ $row['created_at'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Inbound vs Outbound Trend</h6>
                    <canvas id="trendChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Peak Hours</h6>
                    <canvas id="peakChart" height="140"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Schedule Fulfillment Performance</h6>
                    <div id="fulfillmentChart" style="height: 260px;"></div>
                    <div class="collapse mt-3" id="fulfillment-detail">
                        <div class="card card-body border-0 shadow-sm">
                            <div id="fulfillment-full" class="d-none">
                                <div class="text-muted small mb-2">Order Full (Top 5)</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Customer</th>
                                                <th>Rate</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($fulfillmentRows->where('status', 'Full')->take(5) as $row)
                                                <tr>
                                                    <td>#{{ $row['order_id'] }}</td>
                                                    <td>{{ $row['customer'] }}</td>
                                                    <td>{{ $row['rate'] }}%</td>
                                                    <td>{{ $row['status'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="fulfillment-partial" class="d-none">
                                <div class="text-muted small mb-2">Order Partial (Top 5)</div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Customer</th>
                                                <th>Rate</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($fulfillmentRows->where('status', 'Partial')->take(5) as $row)
                                                <tr>
                                                    <td>#{{ $row['order_id'] }}</td>
                                                    <td>{{ $row['customer'] }}</td>
                                                    <td>{{ $row['rate'] }}%</td>
                                                    <td>{{ $row['status'] }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Scan Mismatch Summary</h6>
                    @php
                        $scanTotal = $issueSummary->sum('total');
                        $scanTypes = $issueSummary->count();
                        $scanAvg = $scanTypes ? round($scanTotal / $scanTypes, 1) : 0;
                        $topIssue = $scanTypes ? $issueSummary->sortByDesc('total')->first() : null;
                        $daysInRange = ($start && $end) ? max(1, $start->diffInDays($end) + 1) : null;
                        $scanPerDay = $daysInRange ? round($scanTotal / $daysInRange, 1) : null;
                        $lastIssueAt = $issueList->first()['created_at'] ?? null;
                        $recentIssues = $issueList->take(5);
                    @endphp
                    <div class="text-muted small mb-2">Ringkasan mismatch dari periode terpilih.</div>
                    <ul class="list-group list-group-flush">
                        @forelse($issueSummary as $row)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ $row->issue_type }}</span>
                                <span class="fw-bold">{{ $row->total }}</span>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Tidak ada data.</li>
                        @endforelse
                    </ul>
                    <div class="mt-3">
                        <div class="text-muted small mb-2">5 mismatch terbaru</div>
                        <ul class="list-unstyled small mb-0">
                            @forelse($recentIssues as $row)
                                <li class="d-flex justify-content-between border-bottom py-1">
                                    <span>#{{ $row['order_id'] ?? '-' }} · {{ $row['scanned_code'] ?? '-' }}</span>
                                    <span class="text-muted">{{ $row['created_at'] ?? '-' }}</span>
                                </li>
                            @empty
                                <li class="text-muted">Belum ada mismatch.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4" id="current-handling">
        <div class="card-header bg-white">
            <strong>Current Handling Report (Barang Aktif)</strong>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Box</th>
                        <th>Part</th>
                        <th>PCS</th>
                        <th>Pallet</th>
                        <th>Lokasi</th>
                        <th>Tanggal Masuk</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($currentHandling as $row)
                        <tr>
                            <td>{{ $row->box_number }}</td>
                            <td>{{ $row->part_number }}</td>
                            <td>{{ $row->pcs_quantity }}</td>
                            <td>{{ $row->pallet_number }}</td>
                            <td>{{ $row->warehouse_location ?? 'Unknown' }}</td>
                            <td>{{ optional($row->created_at)->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4" id="active-pallets">
        <div class="card-header bg-white">
            <strong>Active Pallet Report (Pallet Aktif)</strong>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Pallet</th>
                        <th>Total Box</th>
                        <th>Total PCS</th>
                        <th>Lokasi</th>
                        <th>First In</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activePallets as $row)
                        <tr>
                            <td>{{ $row['pallet_number'] }}</td>
                            <td>{{ $row['total_boxes'] }}</td>
                            <td>{{ $row['total_pcs'] }}</td>
                            <td>{{ $row['location'] }}</td>
                            <td>{{ $row['first_in'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4" id="fulfillment-report">
        <div class="card-header bg-white">
            <strong>Inbound vs Outbound Matching Report</strong>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Delivery Date</th>
                        <th>Required</th>
                        <th>Fulfilled</th>
                        <th>Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($matchingReport as $row)
                        <tr>
                            <td>#{{ $row['order_id'] }}</td>
                            <td>{{ $row['customer'] }}</td>
                            <td>{{ $row['delivery_date'] }}</td>
                            <td>{{ $row['required'] }}</td>
                            <td>{{ $row['fulfilled'] }}</td>
                            <td>{{ $row['rate'] }}%</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4" id="scan-issues">
        <div class="card-header bg-white">
            <strong>Processing Time (Lead Time)</strong>
        </div>
        <div class="card-body">
            <div class="text-muted small mb-2">
                Catatan: Scan di sini adalah scan saat pengambilan (picking/delivery), bukan scan saat input stok.
                Durasi Proses = waktu dari mulai picking sampai selesai. Durasi Scan = waktu dari scan pertama sampai scan terakhir.
            </div>
            <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Order</th>
                        <th>Started</th>
                        <th>Completed</th>
                        <th>Durasi Proses (menit)</th>
                        <th>Durasi Scan (menit)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($processingReport as $row)
                        <tr>
                            <td>{{ $row['session_id'] }}</td>
                            <td>#{{ $row['order_id'] }}</td>
                            <td>{{ $row['started_at'] }}</td>
                            <td>{{ $row['completed_at'] }}</td>
                            <td>{{ $row['duration_min'] ?? '-' }}</td>
                            <td>{{ $row['scan_duration_min'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>Warehouse Throughput</strong>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Inbound PCS</th>
                        <th>Outbound PCS</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($throughputDays as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['inbound_pcs'] }}</td>
                            <td>{{ $row['outbound_pcs'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>Scan Mismatch Report</strong>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Scanned</th>
                        <th>Issue Type</th>
                        <th>Status</th>
                        <th>Keterangan Admin</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issueList as $row)
                        <tr>
                            <td>#{{ $row['order_id'] }}</td>
                            <td>{{ $row['scanned_code'] }}</td>
                            <td>{{ $row['issue_type'] }}</td>
                            <td>{{ $row['status'] }}</td>
                            <td>{{ $row['notes'] ?? '-' }}</td>
                            <td>{{ $row['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>Schedule Fulfillment Performance</strong>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Delivery Date</th>
                        <th>Required</th>
                        <th>Fulfilled</th>
                        <th>Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fulfillmentRows as $row)
                        <tr>
                            <td>#{{ $row['order_id'] }}</td>
                            <td>{{ $row['customer'] }}</td>
                            <td>{{ $row['delivery_date'] }}</td>
                            <td>{{ $row['required'] }}</td>
                            <td>{{ $row['fulfilled'] }}</td>
                            <td>{{ $row['rate'] }}%</td>
                            <td>{{ $row['status'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>Audit Report</strong>
        </div>
        <div class="card-body">
            <form id="audit-filter-form" method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="audit_type" class="form-select">
                        <option value="">All</option>
                        @foreach($auditTypes as $type)
                            <option value="{{ $type }}" {{ request('audit_type') === $type ? 'selected' : '' }}>{{ $typeLabels[$type] ?? $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Action</label>
                    <select name="audit_action" class="form-select">
                        <option value="">All</option>
                        @foreach($auditActions as $action)
                            <option value="{{ $action }}" {{ request('audit_action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">User ID</label>
                    <select name="audit_user" class="form-select">
                        <option value="">All</option>
                        @foreach($auditUsers as $user)
                            <option value="{{ $user->id }}" {{ request('audit_user') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100" type="submit">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="{{ url()->current() }}?period={{ request('period', 'week') }}" class="btn btn-outline-secondary w-100" id="audit-filter-reset">Reset</a>
                </div>
            </form>
            <div id="audit-report-content">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Summary</h6>
                        @php
                            $typeLabels = [
                                'stock_input' => 'Input Stok',
                                'stock_withdrawal' => 'Pengambilan Stok',
                                'delivery_pickup' => 'Delivery Pickup',
                                'delivery_redo' => 'Delivery Redo',
                                'pallet_merged' => 'Pallet Merge',
                                'box_pallet_moved' => 'Box Dipindahkan',
                                'other' => 'Lainnya',
                            ];
                        @endphp
                        <ul class="list-group list-group-flush">
                            @forelse($auditSummary as $row)
                                <li class="list-group-item d-flex justify-content-between">
                                    <span>{{ $typeLabels[$row->type] ?? $row->type }}</span>
                                    <span class="fw-bold">{{ $row->total }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted">Tidak ada data.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <div class="text-muted small mb-2">
                    User = nama operator/admin yang melakukan aksi.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Action</th>
                                <th>Model</th>
                                <th>Description</th>
                                <th>User</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditLogs as $log)
                                <tr>
                                    <td>{{ $typeLabels[$log->type] ?? $log->type }}</td>
                                    <td>{{ $log->action }}</td>
                                    <td>{{ $log->model }}</td>
                                    <td>{{ $log->description }}</td>
                                    <td>{{ $log->user?->name ?? 'User #' . $log->user_id }}</td>
                                    <td>{{ $log->created_at->format('d M Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(method_exists($auditLogs, 'links'))
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Menampilkan {{ $auditLogs->firstItem() ?? 0 }}–{{ $auditLogs->lastItem() ?? 0 }} dari {{ $auditLogs->total() }} data
                        </div>
                        {{ $auditLogs->onEachSide(1)->fragment('audit-report')->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/pattern-fill.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script type="application/json" id="throughput-labels">{{ json_encode(collect($throughputDays)->pluck('date')) }}</script>
<script type="application/json" id="throughput-inbound">{{ json_encode(collect($throughputDays)->pluck('inbound_pcs')) }}</script>
<script type="application/json" id="throughput-outbound">{{ json_encode(collect($throughputDays)->pluck('outbound_pcs')) }}</script>
<script type="application/json" id="peak-labels">{{ json_encode($peakHours->pluck('hour')) }}</script>
<script type="application/json" id="peak-inbound">{{ json_encode($peakHours->pluck('inbound_pcs')) }}</script>
<script type="application/json" id="peak-outbound">{{ json_encode($peakHours->pluck('outbound_pcs')) }}</script>
<script type="application/json" id="delivery-labels">{{ json_encode(collect($deliveryTrend)->pluck('label')) }}</script>
<script type="application/json" id="delivery-plan">{{ json_encode(collect($deliveryTrend)->pluck('planned_qty')) }}</script>
<script type="application/json" id="delivery-actual">{{ json_encode(collect($deliveryTrend)->pluck('actual_qty')) }}</script>
<script>
    const throughputLabels = JSON.parse(document.getElementById('throughput-labels').textContent || '[]');
    const inboundData = JSON.parse(document.getElementById('throughput-inbound').textContent || '[]');
    const outboundData = JSON.parse(document.getElementById('throughput-outbound').textContent || '[]');

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: throughputLabels,
            datasets: [
                {
                    label: 'Inbound PCS',
                    data: inboundData,
                    borderColor: '#0C7779',
                    backgroundColor: 'rgba(12,119,121,0.2)',
                    tension: 0.3,
                },
                {
                    label: 'Outbound PCS',
                    data: outboundData,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249,115,22,0.2)',
                    tension: 0.3,
                }
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    const peakLabels = JSON.parse(document.getElementById('peak-labels').textContent || '[]');
    const peakInbound = JSON.parse(document.getElementById('peak-inbound').textContent || '[]');
    const peakOutbound = JSON.parse(document.getElementById('peak-outbound').textContent || '[]');

    new Chart(document.getElementById('peakChart'), {
        type: 'bar',
        data: {
            labels: peakLabels,
            datasets: [
                { label: 'Inbound', data: peakInbound, backgroundColor: '#0C7779' },
                { label: 'Outbound', data: peakOutbound, backgroundColor: '#f97316' }
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    const deliveryLabels = JSON.parse(document.getElementById('delivery-labels').textContent || '[]');
    const deliveryPlan = JSON.parse(document.getElementById('delivery-plan').textContent || '[]');
    const deliveryActual = JSON.parse(document.getElementById('delivery-actual').textContent || '[]');

    Highcharts.chart('fulfillmentChart', {
        chart: { type: 'column' },
        title: { text: null },
        xAxis: {
            categories: deliveryLabels,
            crosshair: true
        },
        yAxis: {
            min: 0,
            title: { text: 'QTY' }
        },
        tooltip: {
            shared: true,
            valueSuffix: ' QTY'
        },
        plotOptions: {
            column: {
                pointPadding: 0.1,
                borderWidth: 0
            }
        },
        series: [
            {
                name: 'Rencana Sales',
                data: deliveryPlan,
                color: {
                    pattern: {
                        path: {
                            d: 'M 0 0 L 10 10',
                            stroke: '#f97316',
                            strokeWidth: 2
                        },
                        width: 10,
                        height: 10,
                        color: 'rgba(249,115,22,0.2)'
                    }
                }
            },
            {
                name: 'Aktual Delivery',
                data: deliveryActual,
                color: '#0C7779'
            }
        ],
        credits: { enabled: false },
        legend: { align: 'center', verticalAlign: 'bottom' }
    });

    const popoverTriggers = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggers.forEach((el) => {
        const targetId = el.getAttribute('data-popover-target');
        const title = el.getAttribute('data-popover-title') || '';
        let content = '';
        if (targetId) {
            const template = document.getElementById(targetId);
            if (template) {
                content = template.innerHTML;
            }
        }
        new bootstrap.Popover(el, {
            html: true,
            sanitize: false,
            trigger: 'hover focus',
            placement: 'left',
            customClass: 'popover-wide',
            title,
            content,
            container: 'body'
        });
    });

    const auditForm = document.getElementById('audit-filter-form');
    const auditContent = document.getElementById('audit-report-content');
    const auditReset = document.getElementById('audit-filter-reset');

    const loadAuditReport = async (url) => {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) return;
        const html = await response.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const nextContent = doc.getElementById('audit-report-content');
        const nextForm = doc.getElementById('audit-filter-form');
        if (nextContent && nextForm) {
            auditContent.innerHTML = nextContent.innerHTML;
            auditForm.innerHTML = nextForm.innerHTML;
            window.history.replaceState({}, '', url);
        }
    };

    if (auditForm) {
        auditForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const url = new URL(auditForm.action);
            const formData = new FormData(auditForm);
            formData.forEach((value, key) => {
                if (value !== '') {
                    url.searchParams.set(key, value);
                } else {
                    url.searchParams.delete(key);
                }
            });
            loadAuditReport(url.toString());
        });
    }

    if (auditReset) {
        auditReset.addEventListener('click', (event) => {
            event.preventDefault();
            loadAuditReport(auditReset.href);
        });
    }

    if (auditContent) {
        auditContent.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (!link || !link.href) return;
            if (!link.href.includes('page=')) return;
            event.preventDefault();
            loadAuditReport(link.href);
        });
    }
</script>
@endsection
