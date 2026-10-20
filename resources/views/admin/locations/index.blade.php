@extends('shared.layouts.app')

@section('title', 'Kelola Lokasi Penyimpanan')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/master-locations.css') }}">
@endsection

@section('content')
<div class="location-page">
    <header class="location-page-header">
        <span class="location-page-eyebrow">Master data / Penyimpanan</span>
        <h1>Lokasi penyimpanan</h1>
        <p>Kelola kode posisi gudang dan lihat pallet yang sedang menempatinya.</p>
    </header>

<div class="row g-3 location-page-layout">
    <div class="col-lg-4">
        <section class="card border-0 shadow-sm location-form-card" aria-labelledby="location-create-title">
            <div class="location-form-heading">
                <span class="location-form-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                <div>
                    <h2 id="location-create-title">Tambah lokasi</h2>
                    <p>Daftarkan satu kode posisi penyimpanan baru.</p>
                </div>
            </div>
            <div class="location-form-body">
                <form action="{{ route('locations.store') }}" method="POST">
                    @csrf
                    <div class="location-form-field">
                        <label for="code" class="form-label">Kode lokasi</label>
                        <input type="text" class="form-control @error('code') is-invalid @enderror"
                            id="code" name="code" value="{{ old('code') }}" placeholder="Contoh: A-1-1"
                            maxlength="255" required aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}"
                            aria-describedby="location-create-help @error('code') location-create-error @enderror">
                        <p class="location-form-help" id="location-create-help">Kode disimpan dalam huruf kapital</p>
                        @error('code')
                            <div class="invalid-feedback" id="location-create-error" role="alert">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="location-form-outcome">
                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                        <span>Setelah disimpan, lokasi baru tersedia untuk penempatan pallet.</span>
                    </div>
                    <button type="submit" class="btn btn-primary location-form-submit">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah lokasi
                    </button>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-8">
        <section class="card border-0 shadow-sm location-list" aria-labelledby="location-list-title">
            <div class="location-list-heading">
                <div>
                    <span class="location-list-eyebrow">Master Location</span>
                    <h2 id="location-list-title">Daftar lokasi</h2>
                    <p>Kode lokasi, pemakaian, dan pallet yang menempatinya.</p>
                </div>
                <span class="location-list-count">{{ number_format($locations->total(), 0, ',', '.') }} lokasi</span>
            </div>
            <div class="table-responsive location-list-scroll" tabindex="0" role="region" aria-label="Daftar lokasi penyimpanan, geser untuk melihat seluruh kolom">
                <table class="table table-hover align-middle location-list-table">
                    <thead>
                        <tr>
                            <th scope="col">Kode lokasi</th>
                            <th scope="col">Kondisi</th>
                            <th scope="col">Pallet aktif</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $location)
                            @php
                                $isOccupied = (bool) $location->is_occupied;
                                $deletionBlocked = $isOccupied && $location->current_pallet_id;
                            @endphp
                            <tr>
                                <th scope="row" class="location-list-code">{{ $location->code }}</th>
                                <td>
                                    <span class="location-list-status {{ $isOccupied ? 'is-occupied' : 'is-available' }}">
                                        <span class="location-list-status-dot" aria-hidden="true"></span>
                                        {{ $isOccupied ? 'Terisi' : 'Kosong' }}
                                    </span>
                                </td>
                                <td class="location-list-pallet">
                                    @if($location->currentPallet)
                                        {{ $location->currentPallet->pallet_number }}
                                    @elseif($isOccupied)
                                        <span class="location-list-muted">Data pallet tidak tersedia</span>
                                    @else
                                        <span class="location-list-muted">Belum ditempati</span>
                                    @endif
                                </td>
                                <td>
                                    <div>
                                        <div class="location-list-actions">
                                            <a href="{{ route('locations.edit', $location->id) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit lokasi {{ $location->code }}">
                                                <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger js-delete-location"
                                                aria-label="Hapus lokasi {{ $location->code }}"
                                                data-delete-url="{{ route('locations.destroy', $location->id) }}"
                                                data-location-code="{{ $location->code }}"
                                                @if($deletionBlocked) disabled @endif>
                                                <i class="bi bi-trash" aria-hidden="true"></i> Hapus
                                            </button>
                                        </div>
                                        @if($deletionBlocked)
                                            <small class="location-list-action-note">Pindahkan pallet sebelum mengubah atau menghapus.</small>
                                        @elseif($isOccupied)
                                            <small class="location-list-action-note">Periksa data pallet lokasi ini sebelum mengubah.</small>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="location-list-empty">
                                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                    @if($locations->total() > 0)
                                        <strong>Halaman ini kosong</strong>
                                        <span>Lokasi tersedia di halaman sebelumnya.</span>
                                        <a href="{{ route('locations.index') }}">Kembali ke halaman pertama</a>
                                    @else
                                        <strong>Belum ada lokasi</strong>
                                        <span>Tambahkan kode lokasi melalui formulir Tambah Lokasi Baru.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($locations->count() > 0)
                <div class="location-list-footer">
                    <p>Menampilkan {{ $locations->firstItem() }}–{{ $locations->lastItem() }} dari {{ $locations->total() }} lokasi</p>
                    @if($locations->hasPages())
                        {{ $locations->onEachSide(1)->links('pagination::bootstrap-5') }}
                    @endif
                </div>
            @endif
        </section>
    </div>
</div>
</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const codeInput = document.getElementById('code');
        if (codeInput && window.matchMedia('(min-width: 992px) and (pointer: fine)').matches) {
            codeInput.focus();
        }
    });
</script>
@endpush

@section('scripts')
<script>
    (function () {
        const buttons = document.querySelectorAll('.js-delete-location');
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const deleteUrl = btn.getAttribute('data-delete-url') || '#';
                const code = btn.getAttribute('data-location-code') || '-';
                const safeCode = code.replace(/[&<>"']/g, (character) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
                })[character]);

                WarehouseAlert.delete({
                    title: 'Hapus Lokasi?',
                    itemName: `lokasi ${safeCode}`,
                    warningItems: [
                        'Kode ini tidak lagi tersedia untuk penempatan pallet baru.',
                        'Tindakan ini <strong>tidak dapat dibatalkan</strong>.'
                    ],
                    confirmText: 'Ya, Hapus',
                    onConfirm: () => {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = deleteUrl;
                        form.innerHTML = `
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="_method" value="DELETE">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    })();
</script>
@endsection
