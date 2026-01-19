@extends('shared.layouts.app')

@section('title', 'Laporan Input Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2">
                    <i class="bi bi-file-earmark-pdf"></i> Laporan Input Stok
                </h1>
                <p class="text-muted">Riwayat lengkap input stok masuk dengan filter tanggal dan lokasi</p>
            </div>
            <a href="{{ route('reports.stock-input.export', array_filter($filters)) }}" class="btn btn-lg" style="background-color: #249E94; color: white; border: none;">
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
                        <p class="text-muted mb-1">Total Pallet</p>
                        <h3 class="mb-0" style="color: #0C7779;">{{ $totalPallets }}</h3>
                    </div>
                    <i class="bi bi-box2" style="font-size: 1.8rem; color: #0C7779; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #249E94;">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1">Total PCS Masuk</p>
                        <h3 class="mb-0" style="color: #249E94;">{{ number_format($totalItems) }}</h3>
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
                        <p class="text-muted mb-1">Total Box</p>
                        <h3 class="mb-0" style="color: #3BC1A8;">{{ number_format((int) $totalBoxes) }}</h3>
                    </div>
                    <i class="bi bi-box" style="font-size: 1.8rem; color: #3BC1A8; opacity: 0.3;"></i>
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
            <div class="col-md-3">
                <label class="form-label fw-bold">Part Number</label>
                <input type="text" class="form-control" name="part_number" placeholder="Cari part number..." value="{{ $filters['part_number'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Lokasi Warehouse</label>
                <select class="form-select" name="warehouse_location">
                    <option value="">-- Semua Lokasi --</option>
                    @foreach($locations as $location)
                        <option value="{{ $location }}" {{ $filters['warehouse_location'] === $location ? 'selected' : '' }}>
                            {{ $location }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn w-100" style="background-color: #0C7779; color: white; border: none;">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
        <a href="{{ route('reports.stock-input') }}" class="btn btn-sm btn-secondary mt-2">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
    </div>
</div>

<!-- Report Table -->
<div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
    <div class="card-header text-white" style="background-color: #0C7779;">
        <i class="bi bi-table"></i> Detail Input Stok
    </div>
    <div class="card-body p-0">
        @if($pallets->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color: #f5f7fa; color: #0C7779;">
                        <tr>
                            <th>No</th>
                            <th>Tanggal Input</th>
                            <th>Pallet Number</th>
                            <th>Part Number</th>
                            <th>Box</th>
                            <th>PCS</th>
                            <th>Lokasi</th>
                            <th>Total Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $counter = $pallets->firstItem();
                        @endphp
                        @foreach($pallets as $pallet)
                            @foreach($pallet->items as $item)
                                <tr>
                                    <td>{{ $counter }}</td>
                                    <td>
                                        <small class="text-muted">
                                            {{ $pallet->created_at->format('d/m/Y') }}<br>
                                            <strong>{{ $pallet->created_at->format('H:i') }}</strong>
                                        </small>
                                    </td>
                                    <td>
                                        <strong>{{ $pallet->pallet_number }}</strong>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: #e0f5f3; color: #0C7779;">
                                            {{ $item->part_number }}
                                        </span>
                                    </td>
                                    <td>{{ (int) $item->box_quantity }}</td>
                                    <td>
                                        <strong>{{ $item->pcs_quantity }} PCS</strong>
                                    </td>
                                    <td>
                                        @if($pallet->stockLocation)
                                            <small>{{ $pallet->stockLocation->warehouse_location }}</small><br>
                                            <span class="text-muted" style="font-size: 0.75rem;">
                                                {{ $pallet->stockLocation->stored_at->format('d/m/Y H:i') }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $pallet->items->count() }}</td>
                                </tr>
                                @php $counter++; @endphp
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-3">
                {{ $pallets->appends(request()->query())->links() }}
            </div>
        @else
            <div class="p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                <h5 class="mt-3 text-muted">Tidak ada data input stok</h5>
            </div>
        @endif
    </div>
</div>

@endsection
