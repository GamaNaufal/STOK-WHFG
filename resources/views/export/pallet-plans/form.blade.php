@extends('shared.layouts.app')
@section('title', 'Susun Perencanaan Pallet')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-pallet-planning.css') }}">
@endsection
@section('content')
@php
    $palletBootstrap = ['catalog' => $catalog, 'locations' => $locations, 'rows' => old('pallets', $rows) ?: [['items' => []]]];
@endphp
<div class="delivery-plan-page" id="pallet-planning-editor">
    @include('export.pallet-plans.header')
    @if($planning?->reservations->count())<p class="alert alert-info py-2">{{ $planning->reservations->count() }} lokasi masih direservasi. Lokasi yang dilepas dari susunan akan dibebaskan saat draft disimpan.</p>@endif
    @if($planning?->status === 'cancelled')<p class="alert alert-info py-2">Reservasi sebelumnya sudah dilepas. Periksa lokasi sebelum menggunakan pembagian ini kembali.</p>@endif
    @if(!$locations)<p class="alert alert-info py-2">Belum ada lokasi ekspor. Lengkapi Master Lokasi Ekspor untuk finalisasi. Draft tetap dapat disimpan.</p>@endif
    <form method="POST" action="{{ route('export.pallet-plans.update', $delivery) }}" id="pallet-planning-form" novalidate>
        @csrf @method('PUT')
        <input type="hidden" name="revision" value="{{ old('revision', $planning?->revision ?? 0) }}">
        <input type="hidden" name="delivery_revision" value="{{ old('delivery_revision', $delivery->revision) }}">
        <input type="hidden" name="pallets_json" id="pallets-json">
        <div class="pallet-workspace" id="pallet-workspace">
            <section class="plan-panel pallet-parts-panel" aria-labelledby="allocation-title">
                <div class="pallet-panel-heading"><h2 id="allocation-title">Kebutuhan part</h2><p id="pallet-part-instruction">Klik part untuk menambahkan ke pallet aktif.</p></div>
                <div class="pallet-part-tools">
                    <label class="form-label" for="pallet-part-search">Cari nomor part</label>
                    <div class="pallet-search-controls"><input class="form-control" id="pallet-part-search" type="search" autocomplete="off" placeholder="Ketik nomor part"><button class="btn btn-outline-secondary" type="button" id="pallet-clear-search" aria-label="Bersihkan pencarian part">Bersihkan</button></div>
                    <label class="pallet-filter-label"><input class="form-check-input" type="checkbox" id="pallet-incomplete-only"> Hanya belum selesai</label>
                    <p id="pallet-part-progress" class="plan-help"></p>
                </div>
                <div class="pallet-parts-scroll" tabindex="0" role="region" aria-label="Kebutuhan part dan sisa box">
                    <table class="pallet-demand-table"><thead><tr><th scope="col">Nomor part</th><th scope="col">Butuh</th><th scope="col">Alokasi</th><th scope="col">Sisa</th></tr></thead><tbody id="allocation-summary">
                        @foreach($summary as $row)
                            <tr data-part-id="{{ $row['part_setting_id'] }}" data-part-number="{{ $row['part_number'] }}">
                                <th scope="row"><button type="button" class="allocation-pick" data-part-id="{{ $row['part_setting_id'] }}"><i class="bi bi-plus-circle" aria-hidden="true"></i><span>{{ $row['part_number'] }}</span></button><small>{{ number_format($row['pcs_per_box'], 0, ',', '.') }} PCS / box</small></th>
                                <td>{{ number_format($row['required'], 0, ',', '.') }}</td><td class="allocation-allocated">{{ number_format($row['allocated'], 0, ',', '.') }}</td>
                                <td class="allocation-remaining">{{ $row['remaining'] < 0 ? 'Lebih ' : '' }}{{ number_format(abs($row['remaining']), 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody></table>
                    <p id="pallet-parts-empty" class="pallet-empty-filter" hidden>Tidak ada part yang cocok.</p>
                </div>
            </section>
            <section class="plan-panel pallet-editor-panel" aria-labelledby="pallet-editor-title">
                <div class="pallet-panel-heading pallet-editor-heading"><div><h2 id="pallet-editor-title">Susunan pallet</h2><p>Isi satu pallet, lalu pindah ke pallet berikutnya.</p></div>
                    <div class="pallet-bulk-add"><label for="pallet-add-count" class="form-label">Jumlah ditambahkan</label><div class="plan-inline-actions"><input class="form-control pallet-add-count" id="pallet-add-count" type="number" min="1" max="500" step="1" value="1"><button class="btn btn-outline-primary" type="button" id="pallet-add">Tambah pallet</button></div></div>
                </div>
                <div class="pallet-navigation"><button type="button" class="btn btn-outline-secondary" id="pallet-previous" aria-label="Pallet sebelumnya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button><div><label for="pallet-active" class="form-label">Pallet aktif</label><select class="form-select" id="pallet-active"></select></div><button type="button" class="btn btn-outline-secondary" id="pallet-next" aria-label="Pallet berikutnya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button></div>
                <div id="planned-pallets" class="pallet-editor-scroll"></div>
            </section>
        </div>
        <div class="plan-save-bar pallet-save-bar" id="pallet-save-bar">
            <div><strong><span id="pallet-total-count">0</span> pallet · <span id="pallet-total-boxes">0</span> box dialokasikan</strong><p id="pallet-save-help">Draft boleh belum lengkap. Finalisasi memerlukan alokasi tepat.</p><p id="pallet-editor-status" role="status" aria-live="polite"></p></div>
            <div class="plan-inline-actions"><button class="btn btn-outline-primary" type="submit" name="action" value="draft" id="pallet-save-draft">Simpan draft</button><button class="btn btn-primary" type="submit" name="action" value="finalize" id="pallet-finalize" disabled>Finalisasi &amp; reservasi lokasi</button></div>
        </div>
    </form>
    <template id="planned-pallet-template"><section class="planned-pallet"><div class="pallet-card-heading"><h3 class="pallet-title"></h3><button class="btn btn-sm btn-outline-danger pallet-remove" type="button">Hapus pallet</button></div><div class="pallet-location-field"><label class="form-label pallet-location-label">Lokasi pallet</label><select class="form-select pallet-location"><option value="">Pilih lokasi ekspor</option></select><p class="plan-help">Lokasi direservasi saat finalisasi. Satu lokasi untuk satu pallet.</p></div><div class="pallet-items"></div><div class="pallet-card-footer"><button type="button" class="btn btn-outline-primary pallet-add-part">Tambah part manual</button><span class="pallet-box-count"></span></div><p class="pallet-errors" aria-live="polite"></p></section></template>
    <template id="pallet-item-template"><div class="pallet-item"><div class="pallet-item-part"><label class="form-label pallet-part-label">Nomor part</label><select class="form-select pallet-part"><option value="">Pilih nomor part</option></select><small class="pallet-capacity">—</small></div><div class="pallet-item-quantity"><label class="form-label pallet-quantity-label">Jumlah box</label><input class="form-control pallet-quantity" type="number" min="1" max="4294967295" step="1" inputmode="numeric"><button class="pallet-fill-remaining" type="button" disabled>Alokasikan sisa</button></div><button class="btn btn-sm btn-outline-danger pallet-remove-part" type="button" aria-label="Hapus part"><i class="bi bi-trash" aria-hidden="true"></i></button><div class="pallet-item-progress"><span>Butuh <strong class="pallet-item-required">—</strong></span><span>Alokasi <strong class="pallet-item-allocated">—</strong></span><span class="pallet-item-remainder">Sisa <strong class="pallet-item-remaining">—</strong></span></div></div></template>
    <script type="application/json" id="pallet-bootstrap">@json($palletBootstrap)</script>
    <noscript><div class="alert alert-warning">Aktifkan JavaScript untuk menyusun pallet.</div></noscript>
</div>
@endsection
@section('scripts')@vite('resources/js/export-pallet-planning.js')@endsection
