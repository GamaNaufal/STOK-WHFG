@extends('shared.layouts.app')
@php
    $isExport = $isExport ?? false;
    $routePrefix = $routePrefix ?? 'locations';
@endphp

@section('title', 'Edit Lokasi Penyimpanan')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/master-locations.css') }}">
@endsection

@section('content')
@php($isOccupied = (bool) $location->is_occupied)
<div class="location-page location-edit-page">
    <header class="location-page-header">
        <a href="{{ route($routePrefix.'.index') }}" class="location-back-link">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar lokasi
        </a>
        <span class="location-page-eyebrow">{{ $isExport ? 'Master data / Ekspor' : 'Master data / Domestik' }}</span>
        <h1>Ubah kode lokasi{{ $isExport ? " ekspor" : "" }}</h1>
        <p>Periksa kondisi lokasi sebelum menyimpan perubahan kode.</p>
    </header>

    <div class="row g-3 location-edit-layout">
        <div class="col-lg-7">
            <section class="card border-0 shadow-sm location-form-card" aria-labelledby="location-edit-title">
                <div class="location-form-heading">
                    <span class="location-form-icon"><i class="bi bi-pencil" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="location-edit-title">Kode lokasi</h2>
                        <p>Perubahan hanya dapat disimpan ketika lokasi kosong.</p>
                    </div>
                </div>
                <div class="location-form-body">
                    @if($isOccupied)
                        <div class="location-form-lock" id="location-edit-lock" role="status">
                            <i class="bi bi-lock" aria-hidden="true"></i>
                            <div>
                                <strong>Lokasi sedang terisi</strong>
                                <span>Pindahkan pallet dari lokasi ini terlebih dahulu. Sistem menolak perubahan kode selama masih terisi.</span>
                            </div>
                        </div>
                    @endif
                    <form action="{{ route($routePrefix.'.update', $location->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="location-form-field">
                            <label for="code" class="form-label">Kode lokasi</label>
                            <input type="text" class="form-control @error('code') is-invalid @enderror"
                                id="code" name="code" value="{{ $isOccupied ? $location->code : old('code', $location->code) }}"
                                maxlength="255" required @if($isOccupied) readonly @endif
                                aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}"
                                aria-describedby="location-edit-help @if($isOccupied) location-edit-lock @endif @error('code') location-edit-error @enderror">
                            <p class="location-form-help" id="location-edit-help">Kode disimpan dalam huruf kapital</p>
                            @error('code')
                                <div class="invalid-feedback" id="location-edit-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="location-form-actions">
                            <a href="{{ route($routePrefix.'.index') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
                            <button type="submit" class="btn btn-primary location-form-submit" @if($isOccupied) disabled @endif>
                                <i class="bi bi-check-lg" aria-hidden="true"></i> Simpan perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-lg-5">
            <aside class="location-context" aria-labelledby="location-context-title">
                <span class="location-form-eyebrow">Detail saat ini</span>
                <h2 id="location-context-title">{{ $location->code }}</h2>
                <span class="location-list-status {{ $isOccupied ? 'is-occupied' : 'is-available' }}">
                    <span class="location-list-status-dot" aria-hidden="true"></span>
                    {{ $isOccupied ? 'Terisi' : 'Kosong' }}
                </span>
                @if($isOccupied)
                    <p>
                        @if($location->currentPallet)
                            Pallet <strong>{{ $location->currentPallet->pallet_number }}</strong> masih menempati lokasi ini.
                        @else
                            Sistem menandai lokasi ini terisi, tetapi data pallet tidak tersedia. Periksa kondisi lokasinya sebelum mengubah kode.
                        @endif
                    </p>
                @else
                    <p>Lokasi kosong dapat diubah. Jika pallet menempatinya sebelum Anda menyimpan, sistem akan menolak perubahan.</p>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
