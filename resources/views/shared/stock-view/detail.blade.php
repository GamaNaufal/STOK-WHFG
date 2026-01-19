@extends('shared.layouts.app')

@section('title', 'Detail Stok - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <a href="{{ route('stock-view.index') }}" class="btn btn-outline-secondary mb-3">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h1 class="h2">
            <i class="bi bi-box2-heart"></i> Detail Pallet - {{ $pallet->pallet_number }}
        </h1>
    </div>
</div>

<div class="row">
    <!-- Pallet Info Card -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-header" style="background: #0C7779; color: white;">
                <i class="bi bi-info-circle"></i> Informasi Pallet
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted small">No Pallet</label>
                    <p class="fs-5 fw-bold">{{ $pallet->pallet_number }}</p>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Total Items</label>
                    <p class="fs-5 fw-bold">{{ $pallet->items->count() }} Item(s)</p>
                </div>
                <div class="mb-0">
                    <label class="form-label text-muted small">Tanggal Input</label>
                    <p class="fs-5 fw-bold">{{ $pallet->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Location Info Card -->
    <div class="col-md-8 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header" style="background: #249E94; color: white;">
                <i class="bi bi-pin-map"></i> Lokasi Penyimpanan
            </div>
            <div class="card-body">
                @if($pallet->stockLocation)
                    <div class="alert border-0" role="alert" style="background: #e0f5f3; border-left: 4px solid #0C7779;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small">Lokasi Gudang</label>
                                    <p class="fs-4 fw-bold" style="color: #0C7779;">{{ $pallet->stockLocation->warehouse_location }}</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-0">
                                    <label class="form-label text-muted small">Tanggal Penyimpanan</label>
                                    <p class="fs-6">{{ $pallet->stockLocation->stored_at->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning border-0" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> Pallet ini belum memiliki lokasi penyimpanan.
                        <br>
                        <a href="{{ route('stock-input.index') }}" class="btn btn-sm mt-2" style="background: #3BC1A8; color: white; border: none;">
                            <i class="bi bi-plus-circle"></i> Input Lokasi
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Items in Pallet -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header" style="background: #3BC1A8; color: white;">
                <i class="bi bi-list"></i> Item-item dalam Pallet
            </div>
            <div class="card-body">
                @if($pallet->items && $pallet->items->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead style="background: #f5f7fa;">
                                <tr>
                                    <th style="color: #0C7779;">No Part</th>
                                    <th style="color: #0C7779;">Jumlah Box</th>
                                    <th style="color: #0C7779;">Jumlah PCS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pallet->items as $item)
                                    <tr>
                                        <td><strong>{{ $item->part_number }}</strong></td>
                                        <td>{{ $item->box_quantity }} Box</td>
                                        <td>{{ $item->pcs_quantity }} PCS</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-info mb-0">
                        Tidak ada item dalam pallet ini
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
