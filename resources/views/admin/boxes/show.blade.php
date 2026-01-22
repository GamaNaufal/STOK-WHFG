@extends('shared.layouts.app')

@section('title', 'Lihat Barcode Box - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <a href="{{ route('boxes.index') }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali ke Daftar
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header" style="background: #0C7779; color: white;">
                <i class="bi bi-barcode"></i> Barcode - {{ $box->box_number }}
            </div>
            <div class="card-body text-center p-5">
                <!-- Box Details -->
                <div class="mb-4">
                    <h3 class="fw-bold">{{ $box->box_number }}</h3>
                    <p class="text-muted mb-1">
                        <strong>Part Number:</strong> {{ $box->part_number }}
                    </p>
                    <p class="text-muted mb-1">
                        <strong>Part Name:</strong> {{ $box->part_name ?? '-' }}
                    </p>
                    <p class="text-muted mb-1">
                        <strong>Jumlah PCS:</strong> <span class="badge bg-info">{{ $box->pcs_quantity }}</span>
                    </p>
                    <p class="text-muted mb-1">
                        <strong>Tipe Box:</strong> {{ $box->type_box ?? '-' }}
                    </p>
                    <p class="text-muted small">
                        Dibuat: {{ $box->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>

                <hr>

                <!-- Barcode Display -->
                <div class="mt-4">
                    <div style="background: white; padding: 20px; border: 2px solid #0C7779; border-radius: 8px; display: inline-block;">
                        {!! $box->qr_code !!}
                    </div>
                    <p class="text-muted mt-3">
                        <small><strong>Barcode:</strong> {{ $box->box_number }}</small>
                    </p>
                </div>

                <hr>

                <!-- Barcode Info -->
                <div class="alert alert-info mt-4">
                    <strong><i class="bi bi-info-circle"></i> Informasi Barcode:</strong>
                    <div class="mt-2">
                        <p>Format: CODE128</p>
                        <p>Isi: <code>{{ $box->box_number }}</code></p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4">
                    <button onclick="window.print()" class="btn btn-info">
                        <i class="bi bi-printer"></i> Cetak Barcode
                    </button>
                    <a href="{{ route('boxes.index') }}" class="btn btn-secondary">
                        <i class="bi bi-box"></i> Daftar Box
                    </a>
                </div>

                <!-- Navigation Buttons -->
                <hr class="my-4">
                <div class="d-flex justify-content-between gap-2">
                    @php
                        $allBoxes = App\Models\Box::orderBy('id')->get();
                        $currentIndex = $allBoxes->search(function($item) use ($box) {
                            return $item->id === $box->id;
                        });
                        $previousBox = $currentIndex > 0 ? $allBoxes[$currentIndex - 1] : null;
                        $nextBox = $currentIndex < $allBoxes->count() - 1 ? $allBoxes[$currentIndex + 1] : null;
                    @endphp

                    @if ($previousBox)
                        <a href="{{ route('boxes.show', $previousBox) }}" class="btn btn-outline-secondary btn-sm" style="border: 2px solid #6c757d; color: #6c757d; border-radius: 8px; padding: 8px 16px; transition: all 0.3s ease;">
                            <i class="bi bi-chevron-left"></i> Sebelumnya ({{ $previousBox->box_number }})
                        </a>
                    @else
                        <button class="btn btn-outline-secondary btn-sm" disabled style="border: 2px solid #ddd; color: #ccc; border-radius: 8px; padding: 8px 16px; cursor: not-allowed;">
                            <i class="bi bi-chevron-left"></i> Sebelumnya
                        </button>
                    @endif

                    @if ($nextBox)
                        <a href="{{ route('boxes.show', $nextBox) }}" class="btn btn-outline-primary btn-sm" style="border: 2px solid #0C7779; color: #0C7779; border-radius: 8px; padding: 8px 16px; transition: all 0.3s ease;">
                            Berikutnya ({{ $nextBox->box_number }}) <i class="bi bi-chevron-right"></i>
                        </a>
                    @else
                        <button class="btn btn-outline-primary btn-sm" disabled style="border: 2px solid #ddd; color: #ccc; border-radius: 8px; padding: 8px 16px; cursor: not-allowed;">
                            Berikutnya <i class="bi bi-chevron-right"></i>
                        </button>
                    @endif
                </div>

                <style>
                    .btn-outline-secondary:hover:not(:disabled) {
                        background: #6c757d !important;
                        border-color: #6c757d !important;
                        color: white !important;
                        transform: translateX(-2px);
                        box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
                    }
                    .btn-outline-primary:hover:not(:disabled) {
                        background: #0C7779 !important;
                        border-color: #0C7779 !important;
                        color: white !important;
                        transform: translateX(2px);
                        box-shadow: 0 4px 8px rgba(12, 119, 121, 0.3);
                    }
                </style>
            </div>
        </div>
    </div>
</div>

<style>
    @media print {
        body {
            background: white;
        }
        .row:first-child,
        .card-header,
        .card-body hr,
        .alert-info,
        .btn {
            display: none;
        }
        .card {
            box-shadow: none;
            border: none;
        }
    }
</style>
@endsection
