@extends('shared.layouts.app')

@section('title', 'Edit Lokasi Penyimpanan')

@section('content')
<div class="row mb-4">
    <div class="col-12 text-center text-md-start">
        <h1 class="h3 mb-2 text-gray-800">
            <i class="bi bi-pencil-square"></i> Edit Lokasi Penyimpanan
        </h1>
        <p class="text-muted">Perbarui kode lokasi (Master Location)</p>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 16px; border-radius: 12px 12px 0 0;">
                <h6 class="m-0 fw-bold"><i class="bi bi-pencil-square"></i> Form Edit Lokasi</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('locations.update', $location->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label for="code" class="form-label fw-bold">Kode Lokasi</label>
                        <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $location->code) }}" required>
                        <div class="form-text">Format bebas tapi disarankan konsisten (Rak-Baris-Posisi).</div>
                        @error('code')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary" style="background-color: #0C7779; border: none;">
                            <i class="bi bi-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection