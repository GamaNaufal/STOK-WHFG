@extends('shared.layouts.app')

@section('title', 'Laporan Input Stok - Warehouse FG Yamato')

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
                    <i class="bi bi-file-earmark-pdf"></i> Laporan Input Stok
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Riwayat lengkap input stok masuk dengan filter tanggal dan lokasi
                </p>
            </div>
            <a href="{{ route('reports.stock-input.export', array_filter($filters)) }}" class="btn btn-light btn-lg" style="border-radius: 8px; padding: 12px 28px; font-weight: 600;">
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
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Pencatatan Input</p>
                        <h2 class="fw-bold" style="color: #0C7779; margin: 0; font-size: 2.5rem;">{{ $totalRecords }}</h2>
                    </div>
                    <i class="bi bi-box2" style="font-size: 2.5rem; color: #0C7779; opacity: 0.2;"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm" style="background: #f9fafb; border-radius: 12px;">
            <div class="card-body" style="padding: 24px;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total PCS Masuk</p>
                        <h2 class="fw-bold" style="color: #249E94; margin: 0; font-size: 2.5rem;">{{ number_format($totalItems) }}</h2>
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
                        <p class="text-muted mb-2" style="font-size: 13px; font-weight: 600;">Total Box</p>
                        <h2 class="fw-bold" style="color: #3BC1A8; margin: 0; font-size: 2.5rem;">{{ number_format((int) $totalBoxes) }}</h2>
                    </div>
                    <i class="bi bi-box" style="font-size: 2.5rem; color: #3BC1A8; opacity: 0.2;"></i>
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
            <div class="col-md-3">
                <label class="form-label fw-bold" style="font-size: 13px;">Part Number</label>
                <input type="text" class="form-control" name="part_number" placeholder="Cari part number..." value="{{ $filters['part_number'] ?? '' }}" style="border-radius: 8px; border: 1px solid #e5e7eb;">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold" style="font-size: 13px;">Lokasi Warehouse</label>
                <select class="form-select" name="warehouse_location" style="border-radius: 8px; border: 1px solid #e5e7eb;">
                    <option value="">-- Semua Lokasi --</option>
                    @foreach($locations as $location)
                        <option value="{{ $location }}" {{ $filters['warehouse_location'] === $location ? 'selected' : '' }}>
                            {{ $location }}
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
        <a href="{{ route('reports.stock-input') }}" class="btn btn-sm mt-2" style="background: #f3f4f6; color: #6b7280; border: none; border-radius: 8px;">
            <i class="bi bi-arrow-clockwise"></i> Reset
        </a>
    </div>
</div>

<!-- Report Table -->
<div class="card shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 20px 24px; font-weight: 600; font-size: 15px;">
        <i class="bi bi-table"></i> Detail Input Stok
    </div>
    <div class="card-body p-0">
        @if($stockInputs->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead style="background-color: #f9fafb; color: #0C7779; border-bottom: 2px solid #e5e7eb;">
                        <tr>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">No</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Tanggal Input</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Pallet Number</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Part Number</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Box</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">PCS</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Lokasi</th>
                            <th style="padding: 16px 20px; font-weight: 700; font-size: 13px; text-transform: uppercase;">Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $counter = $stockInputs->firstItem();
                        @endphp
                        @foreach($stockInputs as $input)
                            <tr style="border-bottom: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                <td style="padding: 16px 20px;">{{ $counter }}</td>
                                <td style="padding: 16px 20px;">
                                    <small class="text-muted">
                                        {{ $input->stored_at->format('d/m/Y') }}<br>
                                        <strong style="color: #0C7779;">{{ $input->stored_at->format('H:i') }}</strong>
                                    </small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <strong style="color: #1f2937;">{{ $input->pallet?->pallet_number ?? '-' }}</strong>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <span class="badge" style="background-color: #f0f4f8; color: #0C7779;">
                                        {{ $input->palletItem?->part_number ?? $input->pallet?->items?->first()?->part_number ?? '-' }}
                                    </span>
                                </td>
                                <td style="padding: 16px 20px; color: #1f2937; font-weight: 600;">{{ (int) $input->box_quantity }}</td>
                                <td style="padding: 16px 20px; color: #1f2937; font-weight: 700;">{{ $input->pcs_quantity }} PCS</td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280;">{{ $input->warehouse_location }}</small>
                                </td>
                                <td style="padding: 16px 20px;">
                                    <small style="color: #6b7280;">{{ $input->user->name }}</small>
                                </td>
                            </tr>
                            @php $counter++; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="p-4" style="border-top: 1px solid #e5e7eb;">
                {{ $stockInputs->appends(request()->query())->links() }}
            </div>
        @else
            <div class="p-5 text-center">
                <i class="bi bi-inbox" style="font-size: 3rem; color: #e5e7eb;"></i>
                <h5 class="mt-3 text-muted" style="font-weight: 600;">Tidak ada data input stok</h5>
            </div>
        @endif
    </div>
</div>

@endsection
