@extends('shared.layouts.app')

@section('title', 'Expired Box')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h4 mb-1">Expired Box</h1>
            <div class="text-muted small">Box berumur 9-12 bulan (warning) dan 12+ bulan (expired).</div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Warning (9-12 Bulan)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Box</th>
                                    <th>Part</th>
                                    <th>Pallet</th>
                                    <th>Lokasi</th>
                                    <th>Umur</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($warningBoxes as $box)
                                    @php
                                        $storedAt = $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at) : null;
                                        $age = $storedAt ? $storedAt->diffInMonths(now()) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $box->box_number }}</td>
                                        <td>{{ $box->part_number }}</td>
                                        <td>{{ $box->pallet_number ?? '-' }}</td>
                                        <td>{{ $box->warehouse_location ?? '-' }}</td>
                                        <td>{{ $age }} bulan</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('expired-box.handle', $box->id) }}" onsubmit="return confirm('Tandai handled? Box akan dihapus dari stok dan tidak bisa di-undo.');">
                                                @csrf
                                                <button class="btn btn-sm btn-danger">Mark Handled</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Expired (>=12 Bulan)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Box</th>
                                    <th>Part</th>
                                    <th>Pallet</th>
                                    <th>Lokasi</th>
                                    <th>Umur</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($expiredBoxes as $box)
                                    @php
                                        $storedAt = $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at) : null;
                                        $age = $storedAt ? $storedAt->diffInMonths(now()) : 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $box->box_number }}</td>
                                        <td>{{ $box->part_number }}</td>
                                        <td>{{ $box->pallet_number ?? '-' }}</td>
                                        <td>{{ $box->warehouse_location ?? '-' }}</td>
                                        <td>{{ $age }} bulan</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('expired-box.handle', $box->id) }}" onsubmit="return confirm('Tandai handled? Box akan dihapus dari stok dan tidak bisa di-undo.');">
                                                @csrf
                                                <button class="btn btn-sm btn-danger">Mark Handled</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <strong>History Handled</strong>
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead>
                    <tr>
                        <th>Box</th>
                        <th>Part</th>
                        <th>Pallet</th>
                        <th>Lokasi</th>
                        <th>Stored At</th>
                        <th>Umur</th>
                        <th>Handled By</th>
                        <th>Handled At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($handledHistory as $row)
                        <tr>
                            <td>{{ $row->box_number }}</td>
                            <td>{{ $row->part_number }}</td>
                            <td>{{ $row->pallet_number ?? '-' }}</td>
                            <td>{{ $row->warehouse_location ?? '-' }}</td>
                            <td>{{ optional($row->stored_at)->format('d M Y') ?? '-' }}</td>
                            <td>{{ $row->age_months }} bulan</td>
                            <td>{{ $row->handler?->name ?? '-' }}</td>
                            <td>{{ optional($row->handled_at)->format('d M Y H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">Belum ada history.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if(method_exists($handledHistory, 'links'))
                <div class="d-flex justify-content-end">{{ $handledHistory->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
