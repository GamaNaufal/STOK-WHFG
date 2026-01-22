@extends('shared.layouts.app')

@section('title', 'Buat Box QR Baru - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h2">
            <i class="bi bi-plus-circle"></i> Buat Box QR Baru
        </h1>
        <p class="text-muted">Buat kode QR unik untuk setiap box dengan data part number dan jumlah PCS</p>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Terjadi kesalahan!</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($message = Session::get('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> {{ $message }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-header" style="background: #0C7779; color: white;">
                <i class="bi bi-qr-code"></i> Form Input Box
            </div>
            <div class="card-body p-4">
                <form action="{{ route('boxes.store') }}" method="POST">
                    @csrf

                    <!-- No Box -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-tag"></i> Nomor Box <span class="text-danger">(Auto-Generated)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text" style="background: #0C7779; color: white; border: 1px solid #dee2e6;">
                                <i class="bi bi-hash"></i>
                            </span>
                            <input type="text" class="form-control form-control-lg" 
                                   value="{{ $nextBoxNumber }}" disabled>
                            <span class="input-group-text text-muted" style="border: 1px solid #dee2e6;">
                                Format: BOX-YYYYMMDD-NNN
                            </span>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-info-circle"></i> Nomor box dibuat otomatis berdasarkan tanggal hari ini dengan urutan increment
                        </small>
                    </div>

                    <!-- Part Number -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-diagram-2"></i> Part Number <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-lg @error('part_number') is-invalid @enderror" 
                                name="part_number" required>
                            <option value="">-- Pilih Part Number --</option>
                            @foreach($availableParts as $part)
                                <option value="{{ $part }}" {{ old('part_number') == $part ? 'selected' : '' }}>
                                    {{ $part }}
                                </option>
                            @endforeach
                        </select>
                        @error('part_number')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- PCS Quantity -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-calculator"></i> Jumlah PCS dalam Box <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control form-control-lg @error('pcs_quantity') is-invalid @enderror" 
                               name="pcs_quantity" placeholder="Contoh: 100, 500, 1000" 
                               value="{{ old('pcs_quantity') }}" min="1" required>
                        @error('pcs_quantity')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Preview QR Data -->
                    <div class="alert alert-info mb-4">
                        <strong><i class="bi bi-info-circle"></i> Data yang akan di-encode ke QR:</strong>
                        <div class="mt-2" id="qrPreview">
                            <small class="text-muted">Lengkapi form untuk melihat preview</small>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('boxes.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-lg" style="background: #0C7779; color: white; border: none;">
                            <i class="bi bi-check-circle"></i> Buat Box & Generate QR
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const boxNumber = '{{ $nextBoxNumber }}';
    const partNumberSelect = document.querySelector('select[name="part_number"]');
    const pcsQuantityInput = document.querySelector('input[name="pcs_quantity"]');
    const qrPreview = document.getElementById('qrPreview');

    function updatePreview() {
        const partNumber = partNumberSelect.value;
        const pcsQuantity = pcsQuantityInput.value;

        if (partNumber && pcsQuantity) {
            const qrData = `${boxNumber}|${partNumber}|${pcsQuantity}`;
            qrPreview.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>📦 Box Number:</strong> <code>${boxNumber}</code><br>
                        <strong>📋 Part Number:</strong> <code>${partNumber}</code><br>
                        <strong>📊 PCS Quantity:</strong> <code>${pcsQuantity}</code>
                    </div>
                    <div class="col-md-6">
                        <strong>🔗 QR Code Data:</strong><br>
                        <code style="word-break: break-all; background: #f8f9fa; padding: 8px; border-radius: 4px; display: block;">${qrData}</code>
                    </div>
                </div>
            `;
        } else {
            qrPreview.innerHTML = '<small class="text-muted">Lengkapi Part Number dan Jumlah PCS untuk melihat preview</small>';
        }
    }

    partNumberSelect.addEventListener('change', updatePreview);
    pcsQuantityInput.addEventListener('input', updatePreview);
    
    // Initial preview on load
    updatePreview();
});
</script>
@endsection
