@extends('shared.layouts.app')

@section('title', 'Lihat Kode QR Box - Warehouse FG Yamato')

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
                <i class="bi bi-qr-code"></i> Kode QR - {{ $box->box_number }}
            </div>
            <div class="card-body text-center p-5">
                <!-- Box Details -->
                <div class="mb-4">
                    <h3 class="fw-bold">{{ $box->box_number }}</h3>
                    <p class="text-muted mb-1">
                        <strong>Part Number:</strong> {{ $box->part_number }}
                    </p>
                    <p class="text-muted mb-1">
                        <strong>Jumlah PCS:</strong> <span class="badge bg-info">{{ $box->pcs_quantity }}</span>
                    </p>
                    <p class="text-muted small">
                        Dibuat: {{ $box->created_at->format('d/m/Y H:i') }}
                    </p>
                </div>

                <hr>

                <!-- QR Code Display -->
                <div class="mt-4">
                    <img src="{{ $box->qr_code }}" alt="QR Code" class="img-fluid" style="max-width: 300px; border: 2px solid #0C7779; padding: 10px;">
                </div>

                <hr>

                <!-- QR Data -->
                <div class="alert alert-info mt-4">
                    <strong><i class="bi bi-info-circle"></i> Data dalam QR Code:</strong>
                    <div class="mt-2">
                        <code>{{ $box->box_number }}|{{ $box->part_number }}|{{ $box->pcs_quantity }}</code>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4">
                    <button onclick="window.print()" class="btn btn-info">
                        <i class="bi bi-printer"></i> Cetak QR Code
                    </button>
                    <a href="{{ route('boxes.index') }}" class="btn btn-secondary">
                        <i class="bi bi-box"></i> Daftar Box
                    </a>
                </div>
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
