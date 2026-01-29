@extends('shared.layouts.app')

@section('title', 'Operational Warehouse Reports')

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
                <div class="col-md-3">
                    <label class="form-label">Quick Period</label>
                    <select name="period" class="form-select">
                        <option value="today" {{ request('period') === 'today' ? 'selected' : '' }}>Hari Ini</option>
                        <option value="week" {{ request('period', 'week') === 'week' ? 'selected' : '' }}>Minggu Ini</option>
                        <option value="month" {{ request('period') === 'month' ? 'selected' : '' }}>Bulan Ini</option>
                        <option value="all" {{ request('period') === 'all' ? 'selected' : '' }}>All Time</option>
                    </select>
                    <small class="text-muted">Jika isi date range, akan override.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary w-100">Apply</button>
                    <a href="{{ route('dashboard', ['period' => 'week']) }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="text-muted">Fulfillment Rate (Order Full)</h6>
                    <div class="display-6 fw-bold">{{ $fulfillmentRate }}%</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="text-muted">Active Boxes</h6>
                    <div class="display-6 fw-bold">{{ $currentHandling->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="text-muted">Scan Issues</h6>
                    <div class="display-6 fw-bold">{{ $issueSummary->sum('total') }}</div>
                </div>
            </div>
        </div>
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
                    <canvas id="fulfillmentChart" height="140"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Scan Mismatch Summary</h6>
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
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>A. Current Handling Report (Barang Aktif)</strong>
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

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>B. Inbound vs Outbound Matching Report</strong>
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

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong>C. Processing Time (Lead Time)</strong>
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
            <strong>D. Warehouse Throughput</strong>
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
            <strong>E. Scan Mismatch Report</strong>
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
            <strong>G. Schedule Fulfillment Performance</strong>
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
            <strong>I. Audit Report</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end mb-3">
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
                    <button class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="{{ url()->current() }}?period={{ request('period', 'week') }}" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
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
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    const throughputLabels = @json(collect($throughputDays)->pluck('date'));
    const inboundData = @json(collect($throughputDays)->pluck('inbound_pcs'));
    const outboundData = @json(collect($throughputDays)->pluck('outbound_pcs'));

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

    const peakLabels = @json($peakHours->pluck('hour'));
    const peakInbound = @json($peakHours->pluck('inbound_pcs'));
    const peakOutbound = @json($peakHours->pluck('outbound_pcs'));

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

    new Chart(document.getElementById('fulfillmentChart'), {
        type: 'doughnut',
        data: {
            labels: ['Full', 'Partial'],
            datasets: [{
                data: [{{ $fulfillmentRate }}, {{ 100 - $fulfillmentRate }}],
                backgroundColor: ['#16a34a', '#f97316']
            }]
        },
        options: { responsive: true }
    });
</script>
@endsection
