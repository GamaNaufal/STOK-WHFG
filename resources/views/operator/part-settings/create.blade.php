@extends('shared.layouts.app')
@php
    $isExport = $isExport ?? false;
    $routePrefix = $routePrefix ?? 'part-settings';
@endphp

@section('title', 'Tambah No Part')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/master-parts.css') }}">
@endsection

@section('content')
<div class="part-page part-form-page">
    <header class="part-page-header">
        <div>
            <a href="{{ route($routePrefix.'.index') }}" class="part-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar nomor part</a>
            <span class="part-page-eyebrow">{{ $isExport ? 'Master data / Ekspor' : 'Master data / Domestik' }}</span>
            <h1>Tambah nomor part{{ $isExport ? " ekspor" : "" }}</h1>
            <p>Tentukan identitas part dan jumlah PCS standar dalam satu box.</p>
        </div>
    </header>

    <div class="row g-3 part-form-layout">
        <div class="col-lg-7">
            <section class="card border-0 shadow-sm part-form-card" aria-labelledby="part-form-title">
                <div class="part-form-heading">
                    <span class="part-form-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="part-form-title">Data nomor part</h2>
                        <p>Isi kedua field sebelum menambahkan master part.</p>
                    </div>
                </div>
                <form id="partSettingForm" method="POST" action="{{ route($routePrefix.'.store') }}" class="part-form-body">
                    @csrf
                    <div class="part-form-field">
                        <label for="partNumberInput" class="form-label">Nomor part</label>
                        <input type="text" name="part_number" id="partNumberInput"
                            class="form-control @error('part_number') is-invalid @enderror"
                            value="{{ old('part_number') }}" placeholder="Contoh: ABC123XYZ"
                            maxlength="100" required aria-invalid="{{ $errors->has('part_number') ? 'true' : 'false' }}"
                            aria-describedby="part-number-help @error('part_number') part-number-error @enderror">
                        <p class="part-form-help" id="part-number-help">Nomor part harus unik pada lingkup {{ $isExport ? "ekspor" : "domestik" }}.</p>
                        @error('part_number')
                            <div class="invalid-feedback" id="part-number-error" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="part-form-field">
                        <label for="qtyBoxInput" class="form-label">PCS standar per box</label>
                        <div class="part-number-input">
                            <input type="number" name="qty_box" id="qtyBoxInput"
                                class="form-control @error('qty_box') is-invalid @enderror"
                                value="{{ old('qty_box') }}" min="1" max="4294967295" step="1" inputmode="numeric"
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
                        <button type="submit" class="btn btn-primary part-primary-action"><i class="bi bi-check-lg" aria-hidden="true"></i> Tambah nomor part</button>
                    </div>
                </form>
            </section>
        </div>
        <div class="col-lg-5">
            <aside class="part-context" aria-labelledby="part-context-title">
                <span class="part-page-eyebrow">Acuan operasional</span>
                <h2 id="part-context-title">Kapasitas box penuh</h2>
                @if($isExport)
                    <p>Kapasitas ini berlaku untuk master ekspor. Nomor part yang sama dapat memiliki kapasitas box domestik yang berbeda.</p>
                @else
                    <p>Nilai PCS per box ini dipakai untuk validasi Input Stok dan input box baru pada Delivery Assign.</p>
                    <p>Jika PCS aktual lebih kecil, gunakan alur Request Box Not Full dan approval Supervisi.</p>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
