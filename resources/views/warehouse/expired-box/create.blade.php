@extends('shared.layouts.app')
@section('title', 'Transaksi penanganan baru')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">@endsection
@section('content')
@php($ageAsOf = now())
<div class="expired-page">
<header class="expired-page-header"><a href="{{ route('expired-box.index') }}">Kembali ke daftar transaksi</a><h1 class="mt-3">Transaksi penanganan baru</h1><p>Pilih box yang diserahkan bersamaan. Satu pilihan menjadi satu transaksi QA dan packing.</p></header>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
<div class="expired-box-picker-search"><label for="box-search" class="form-label">Cari box yang akan diserahkan</label><input class="form-control" type="search" id="box-search" data-box-filter placeholder="ID box, part, pallet, atau lokasi" autocomplete="off" aria-describedby="box-search-help"><small id="box-search-help">Daftar langsung tersaring. Box yang sudah dipilih tetap tersimpan saat Anda mencari box lain.</small><span data-filter-visible aria-live="polite"></span></div>
    <section class="expired-page-panel" aria-labelledby="expired-title">
        <header class="expired-page-panel-heading"><div><h2 id="expired-title">Pilih box untuk satu transaksi</h2><p>Termasuk box dari mekanisme Handle lama. Box assigned atau pick-locked harus diselesaikan dahulu.</p></div><span>{{ $expiredBoxes->count() }} box</span></header>
        <form id="bulk-start" method="GET" action="{{ route('expired-box.bulk.review') }}" class="expired-bulk-toolbar" data-bulk-selection="start">
            <input type="hidden" name="action" value="start">
            <button type="button" class="btn btn-outline-secondary" data-select-all="start" hidden>Pilih semua yang tampil · maks. 50</button>
            <button type="button" class="btn btn-outline-secondary" data-select-none="start" hidden>Kosongkan pilihan</button>
            <span data-selection-count="start" aria-live="polite">Pilih box melalui kotak centang.</span>
            <button class="btn btn-primary" data-review="start">Lanjutkan dengan pilihan ini</button>
        </form>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box">
            <table class="table expired-page-table mb-0"><thead><tr><th>Pilih</th><th>ID box</th><th>Part</th><th>Umur saat ini</th><th>Masuk asli</th><th>Asal pallet / lokasi</th></tr></thead><tbody>
            @forelse($expiredBoxes as $box)
                <tr data-box-row><td><label class="expired-bulk-check"><input type="checkbox" form="bulk-start" name="ids[]" value="{{ $box->id }}" data-bulk-item="start"><span class="visually-hidden">Pilih box {{ $box->box_number }}</span></label></td><td><strong>{{ $box->box_number }}</strong></td><td>{{ $box->part_number }}</td><td>@include('warehouse.expired-box.box-age')</td><td>{{ $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at)->format('d M Y H:i') : '—' }}</td><td>{{ $box->pallet_number ?? '—' }} / {{ $box->warehouse_location ?? 'Lokasi tidak aktif' }}</td></tr>
            @empty<tr><td colspan="6" class="text-center py-4">Tidak ada box yang menunggu penyerahan ke QA.</td></tr>@endforelse
            </tbody></table>
        </div>
        <p class="expired-page-filter-empty" data-box-filter-empty hidden>Tidak ada box yang cocok. Coba ID box, part, pallet, atau lokasi lain.</p>
        <p class="expired-page-scroll-hint d-md-none">Geser tabel ke samping untuk melihat umur, tanggal masuk, dan lokasi.</p>
    </section>
</div>
@endsection
@section('scripts')<script src="{{ asset('js/expired-box-bulk.js') }}" defer></script>@endsection
