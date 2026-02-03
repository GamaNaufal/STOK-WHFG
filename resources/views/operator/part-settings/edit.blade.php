@extends('shared.layouts.app')

@section('title', 'Edit No Part - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    <!-- Modern Header Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                    <i class="bi bi-pencil-square"></i> Edit No Part
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                    Perbarui informasi nomor part: {{ $partSetting->part_number }}
                </p>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0" style="border-left: 4px solid #249E94; border-radius: 12px; overflow: hidden;">
                <div class="card-header text-white" style="background-color: #249E94; border: none;">
                    <i class="bi bi-file-earmark-text"></i> Form Edit Data No Part
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('part-settings.update', $partSetting) }}">
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #0C7779; font-size: 15px;">
                                <i class="bi bi-barcode"></i> No Part
                            </label>
                            <input type="text" 
                                   name="part_number" 
                                   class="form-control form-control-lg @error('part_number') is-invalid @enderror" 
                                   value="{{ old('part_number', $partSetting->part_number) }}" 
                                   placeholder="Contoh: ABC123XYZ" 
                                   style="border: 2px solid #e5e7eb; border-radius: 8px;"
                                   required>
                            @error('part_number')
                                <div class="invalid-feedback">
                                    <i class="bi bi-exclamation-circle"></i> {{ $message }}
                                </div>
                            @enderror
                            <small class="form-text text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Nomor part harus tetap unik
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #0C7779; font-size: 15px;">
                                <i class="bi bi-box"></i> Qty Box
                            </label>
                            <input type="number" 
                                   name="qty_box" 
                                   class="form-control form-control-lg @error('qty_box') is-invalid @enderror" 
                                   value="{{ old('qty_box', $partSetting->qty_box) }}" 
                                   min="1" 
                                   max="4294967295" 
                                   placeholder="Contoh: 100"
                                   style="border: 2px solid #e5e7eb; border-radius: 8px;"
                                   required>
                            @error('qty_box')
                                <div class="invalid-feedback">
                                    <i class="bi bi-exclamation-circle"></i> {{ $message }}
                                </div>
                            @enderror
                            <small class="form-text text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Jumlah qty standar per box untuk part ini
                            </small>
                        </div>

                        <div class="d-flex gap-3 mt-4 pt-3" style="border-top: 2px solid #f0f0f0;">
                            <a href="{{ route('part-settings.index') }}" 
                               class="btn btn-lg" 
                               style="background-color: #e5e7eb; color: #374151; border: none; padding: 10px 24px; border-radius: 8px; transition: all 0.3s ease;"
                               onmouseover="this.style.backgroundColor='#d1d5db'"
                               onmouseout="this.style.backgroundColor='#e5e7eb'">
                                <i class="bi bi-x-circle"></i> Batal
                            </a>
                            <button type="submit" 
                                    class="btn btn-lg" 
                                    style="background-color: #249E94; color: white; border: none; padding: 10px 24px; border-radius: 8px; transition: all 0.3s ease; flex: 1;"
                                    onmouseover="this.style.backgroundColor='#0C7779'"
                                    onmouseout="this.style.backgroundColor='#249E94'">
                                <i class="bi bi-arrow-repeat"></i> Update Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
