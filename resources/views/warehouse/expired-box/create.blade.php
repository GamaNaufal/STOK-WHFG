@extends('shared.layouts.app')
@section('title', 'Transaksi penanganan baru')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">@endsection
@section('content')
@php($ageAsOf = now())
<div class="expired-page">
<header class="expired-page-header"><a href="{{ route($expiredRoutePrefix.'index') }}">Kembali ke daftar transaksi</a><span class="expired-transaction-status ms-2">{{ $scopeLabel }}</span><h1 class="mt-3">Transaksi penanganan baru</h1><p>Satu transaksi berisi satu nomor part dengan PCS per box yang sama. Pilih kelompok, lalu box yang akan diserahkan ke QA.</p></header>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
@php($boxGroups = $expiredBoxes->groupBy(fn ($box) => \App\Models\ExpiredBoxHandling::groupKey($box->part_number, (int) $box->pcs_quantity))->sortBy(fn ($group) => $group->first()->part_number))
<div class="expired-box-group-picker"><label for="box-group" class="form-label">Kelompok nomor part / PCS per box</label><select id="box-group" class="form-select" data-box-group><option value="">Pilih kelompok part</option>@foreach($boxGroups as $key => $group)<option value="{{ $key }}" @selected(request('group') === $key || (!request('group') && $boxGroups->count() === 1))>{{ $group->first()->part_number }} · {{ number_format($group->first()->pcs_quantity) }} PCS/box · {{ $group->count() }} box</option>@endforeach</select><small>PCS standar dan Box Not Full dipisahkan otomatis. Mengganti kelompok mengosongkan pilihan box sebelumnya.</small></div>
<div class="expired-box-picker-search"><label for="box-search" class="form-label">Cari box dalam kelompok</label><input class="form-control" type="search" id="box-search" data-box-filter placeholder="ID Box, pallet, atau lokasi" autocomplete="off" aria-describedby="box-search-help"><small id="box-search-help">Pencarian tidak menghapus pilihan dalam kelompok yang sama.</small><span data-filter-visible aria-live="polite"></span></div>
    <section class="expired-page-panel" aria-labelledby="expired-title">
        <header class="expired-page-panel-heading"><div><h2 id="expired-title">Pilih box untuk satu transaksi</h2><p>Termasuk box dari mekanisme Handle lama. Box assigned atau pick-locked harus diselesaikan dahulu.</p></div><span>{{ $expiredBoxes->count() }} box</span></header>
        <form id="bulk-start" method="GET" action="{{ route($expiredRoutePrefix.'bulk.review') }}" class="expired-bulk-toolbar" data-bulk-selection="start">
            <input type="hidden" name="action" value="start">
            <button type="button" class="btn btn-outline-secondary" data-select-all="start" hidden>Pilih semua yang tampil · maks. 50</button>
            <button type="button" class="btn btn-outline-secondary" data-select-none="start" hidden>Kosongkan pilihan</button>
            <span data-selection-count="start" aria-live="polite">Pilih box melalui kotak centang.</span>
            <button class="btn btn-primary" data-review="start">Lanjutkan dengan pilihan ini</button>
        </form>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box">
            <table class="table expired-page-table mb-0"><thead><tr><th>Pilih</th><th>ID Box</th><th>Part</th><th>Umur saat ini</th><th>Masuk asli</th><th>Asal pallet / lokasi</th></tr></thead><tbody>
            @forelse($expiredBoxes as $box)
                @php($blocked = !empty($box->delivery_id) || !empty($box->source_ready_at))
                <tr data-box-row data-group="{{ \App\Models\ExpiredBoxHandling::groupKey($box->part_number, (int) $box->pcs_quantity) }}"><td><label class="expired-bulk-check"><input type="checkbox" form="bulk-start" name="ids[]" value="{{ $box->id }}" data-bulk-item="start" data-blocked="{{ $blocked ? '1' : '' }}" @disabled($blocked)><span class="visually-hidden">Pilih box {{ $box->box_number }}</span></label></td><td><strong>{{ $box->box_number }}</strong>@if($blocked)<small class="d-block text-warning">Terikat {{ $box->delivery_code ?? 'permintaan' }} · revisi dahulu</small>@endif</td><td>{{ $box->part_number }}<small class="d-block">{{ number_format($box->pcs_quantity) }} PCS/box</small></td><td>@include('warehouse.expired-box.box-age')</td><td>{{ $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at)->format('d M Y H:i') : '—' }}</td><td>{{ $box->pallet_number ?? '—' }} / {{ $box->warehouse_location ?? 'Lokasi tidak aktif' }}</td></tr>
            @empty<tr><td colspan="6" class="text-center py-4">Tidak ada box yang menunggu penyerahan ke QA.</td></tr>@endforelse
            </tbody></table>
        </div>
        <p class="expired-page-filter-empty" data-box-filter-empty hidden>Tidak ada box yang cocok. Coba ID Box, part, pallet, atau lokasi lain.</p>
        <p class="expired-page-scroll-hint d-md-none">Geser tabel ke samping untuk melihat umur, tanggal masuk, dan lokasi.</p>
    </section>
</div>
@endsection
@section('scripts')<script src="{{ asset('js/expired-box-bulk.js') }}?v={{ filemtime(public_path('js/expired-box-bulk.js')) }}" defer></script>@endsection
