@extends('shared.layouts.app')
@php
    $isExport = $isExport ?? false;
    $routePrefix = $routePrefix ?? 'part-settings';
@endphp

@section('title', 'Edit No Part')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/master-parts.css') }}">
@endsection

@section('content')
<div class="part-page part-form-page">
    <header class="part-page-header">
        <div>
            <a href="{{ route($routePrefix.'.index') }}" class="part-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar nomor part</a>
            <span class="part-page-eyebrow">{{ $isExport ? 'Master data / Ekspor' : 'Master data / Domestik' }}</span>
            <h1>Ubah nomor part & Qty{{ $isExport ? " Ekspor" : "" }}</h1>
            <p>Periksa identitas part dan kapasitas box sebelum menyimpan perubahan.</p>
        </div>
    </header>

    <div class="row g-3 part-form-layout">
        <div class="col-lg-7">
            <section class="card border-0 shadow-sm part-form-card" aria-labelledby="part-form-title">
                <div class="part-form-heading">
                    <span class="part-form-icon"><i class="bi bi-pencil" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="part-form-title">Data nomor part</h2>
                        <p>Perubahan kapasitas akan menjadi acuan validasi berikutnya.</p>
                    </div>
                </div>
                <form id="partSettingForm" method="POST" action="{{ route($routePrefix.'.update', $partSetting) }}" class="part-form-body">
                    @csrf
                    @method('PUT')
                    @if(session('error'))
                        <div class="part-form-warning" role="alert"><i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ session('error') }}</span></div>
                    @endif
                    <div class="part-form-field">
                        <label for="partNumberInput" class="form-label">Nomor part</label>
                        <input type="text" name="part_number" id="partNumberInput"
                            class="form-control @error('part_number') is-invalid @enderror"
                            value="{{ old('part_number', $partSetting->part_number) }}" placeholder="Contoh: ABC123XYZ"
                            maxlength="100" required aria-invalid="{{ $errors->has('part_number') ? 'true' : 'false' }}"
                            aria-describedby="part-number-help @error('part_number') part-number-error @enderror">
                        <p class="part-form-help" id="part-number-help">{{ $isExport ? "Nomor part harus unik pada master ekspor." : "Nomor part yang sudah dipakai di transaksi atau histori tidak dapat diganti." }}</p>
                        @error('part_number')
                            <div class="invalid-feedback" id="part-number-error" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="part-form-field">
                        <label for="qtyBoxInput" class="form-label">PCS standar per box</label>
                        <div class="part-number-input">
                            <input type="number" name="qty_box" id="qtyBoxInput"
                                class="form-control @error('qty_box') is-invalid @enderror"
                                value="{{ old('qty_box', $partSetting->qty_box) }}" min="1" max="4294967295" step="1" inputmode="numeric"
                                placeholder="Contoh: 100" required aria-invalid="{{ $errors->has('qty_box') ? 'true' : 'false' }}"
                                aria-describedby="qty-box-help @error('qty_box') qty-box-error @enderror">
                            <span aria-hidden="true">PCS</span>
                        </div>
                        <p class="part-form-help" id="qty-box-help">Jumlah PCS untuk satu box penuh.</p>
                        @error('qty_box')
                            <div class="invalid-feedback d-block" id="qty-box-error" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="part-form-actions">
                        <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary">Batal</a>
                        <button type="submit" class="btn btn-primary part-primary-action"><i class="bi bi-check-lg" aria-hidden="true"></i> Simpan perubahan</button>
                    </div>
                </form>
            </section>
        </div>
        <div class="col-lg-5">
            <aside class="part-context" aria-labelledby="part-context-title">
                <span class="part-page-eyebrow">Data saat ini</span>
                <h2 id="part-context-title">{{ $partSetting->part_number }}</h2>
                <span class="part-quantity"><strong>{{ number_format($partSetting->qty_box, 0, ',', '.') }}</strong> PCS / box</span>
                <p>{{ $isExport ? "PCS per box dapat diubah setelah seluruh rencana delivery yang memakai part ini selesai atau dibatalkan, termasuk rencana draft." : "Qty per box tetap dapat diperbarui meskipun nomor part sudah digunakan dalam transaksi atau histori." }}</p>
            </aside>
        </div>
    </div>
</div>
@endsection
