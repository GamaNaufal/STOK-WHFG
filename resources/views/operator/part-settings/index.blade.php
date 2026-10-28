@extends('shared.layouts.app')
@php
    $isExport = $isExport ?? false;
    $routePrefix = $routePrefix ?? 'part-settings';
@endphp

@section('title', 'Master No Part & Qty')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/master-parts.css') }}">
@endsection

@section('content')
<div class="part-page" id="master-parts"
    data-search-url="{{ route($routePrefix.'.search') }}"
    data-base-url="{{ route($routePrefix.'.index') }}"
    data-csrf-token="{{ csrf_token() }}">
    <header class="part-page-header">
        <div>
            <span class="part-page-eyebrow">{{ $isExport ? 'Master data / Ekspor' : 'Master data / Domestik' }}</span>
            <h1>Master No Part & Qty{{ $isExport ? " Ekspor" : "" }}</h1>
            <p>Kelola nomor part dan kapasitas standar PCS untuk setiap box.</p>
        </div>
        <a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary part-primary-action">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah nomor part
        </a>
    </header>

    <section class="part-search-panel" aria-labelledby="part-search-title">
        <div>
            <h2 id="part-search-title">Cari nomor part</h2>
            <p id="part-search-help">Pencarian mencakup seluruh master data dan menampilkan maksimal 50 hasil pertama.</p>
        </div>
        <div class="part-search-controls">
            <label class="visually-hidden" for="part-search">Cari nomor part</label>
            <div class="part-search-input">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" id="part-search" class="form-control" placeholder="Ketik nomor part" autocomplete="off" aria-describedby="part-search-help">
            </div>
            <button type="button" class="btn btn-outline-secondary" id="part-search-clear" hidden>Bersihkan</button>
        </div>
    </section>

    <section class="card border-0 shadow-sm part-results" aria-labelledby="part-results-title">
        <div class="part-results-heading">
            <div>
                <span class="part-page-eyebrow">Referensi operasional</span>
                <h2 id="part-results-title">Daftar nomor part</h2>
                <p id="part-result-status" role="status" aria-live="polite">Menampilkan {{ $parts->firstItem() ?? 0 }}–{{ $parts->lastItem() ?? 0 }} dari {{ $parts->total() }} nomor part</p>
            </div>
            <span class="part-count">{{ number_format($parts->total(), 0, ',', '.') }} total</span>
        </div>
        <div class="table-responsive part-table-scroll" tabindex="0" role="region" aria-label="Daftar nomor part, geser untuk melihat semua kolom">
            <table class="table table-hover align-middle part-table">
                <thead>
                    <tr>
                        <th scope="col">Nomor part</th>
                        <th scope="col">Kapasitas standar</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody id="part-table-body">
                    @forelse($parts as $part)
                        <tr>
                            <th scope="row" class="part-table-code">{{ $part->part_number }}</th>
                            <td><span class="part-quantity"><strong>{{ number_format($part->qty_box, 0, ',', '.') }}</strong> PCS / box</span></td>
                            <td>
                                <div class="part-row-actions">
                                    <a href="{{ route($routePrefix.'.edit', $part) }}" class="btn btn-sm btn-outline-primary" aria-label="Edit nomor part {{ $part->part_number }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-delete-part"
                                        data-delete-url="{{ route($routePrefix.'.destroy', $part) }}"
                                        data-part-number="{{ $part->part_number }}"
                                        aria-label="Hapus nomor part {{ $part->part_number }}">
                                        <i class="bi bi-trash" aria-hidden="true"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="part-table-empty">
                                <i class="bi bi-box-seam" aria-hidden="true"></i>
                                @if($parts->total() > 0)
                                    <strong>Halaman ini kosong</strong>
                                    <span>Nomor part tersedia di halaman sebelumnya.</span>
                                    <a href="{{ route($routePrefix.'.index') }}">Kembali ke halaman pertama</a>
                                @else
                                    <strong>Belum ada nomor part</strong>
                                    <span>Tambahkan nomor part beserta kapasitas PCS per box untuk memulai.</span>
                                    <a href="{{ route($routePrefix.'.create') }}">Tambah nomor part</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="part-results-footer" id="part-pagination" @if($parts->count() === 0) hidden @endif>
            <span>Menampilkan {{ $parts->firstItem() ?? 0 }}–{{ $parts->lastItem() ?? 0 }} dari {{ $parts->total() }}</span>
            @if($parts->hasPages())
                {{ $parts->onEachSide(1)->links('pagination::bootstrap-5') }}
            @endif
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/pages/master-parts.js') }}"></script>
@endsection
