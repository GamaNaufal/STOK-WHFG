@extends('shared.layouts.app')
@section('title', 'Merge Pallet Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-merge.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/merge-scope-switch.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page export-merge-page">
    <header class="mb-4">
        <p class="text-muted mb-1">Warehouse / Ekspor</p>
        <h1>Merge Pallet Ekspor</h1>
        <p class="mb-0">Gabungkan seluruh box dari beberapa pallet. Tinjau perubahan rencana sebelum mengonfirmasi pemindahan fisik.</p>
        @include('shared.merge-scope-switch', ['scope' => 'export'])
    </header>
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Merge belum diproses.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p class="mb-0 mt-2">Periksa pilihan dan tinjau ulang data sebelum melanjutkan.</p></div>@endif
    <div class="alert alert-info">Pallet permintaan harus berada dalam <strong>rencana delivery yang sama</strong>. Stok cadangan boleh digabung; reservasi delivery lain tetap dilindungi.</div>
    <form method="POST" action="{{ route('export.merge.preview') }}" id="export-merge-form">@csrf
        <section class="plan-panel"><div class="plan-panel-heading"><h2>1. Pilih minimal 2 pallet sumber</h2><span id="merge-selection-count" role="status" aria-live="polite">0 pallet dipilih</span></div>
            <div class="plan-panel-body"><label class="form-label" for="merge-search">Cari pallet, lokasi, part, kode delivery atau customer</label><input type="search" id="merge-search" class="form-control" placeholder="Ketik untuk menyaring daftar…" autocomplete="off"></div>
            <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Pallet ekspor tersedia"><table class="table plan-table align-middle"><thead><tr><th>Pilih</th><th>Pallet / lokasi</th><th>Rencana delivery</th><th>Isi pallet</th><th>Status</th></tr></thead><tbody>
                @forelse($sources as $source)
                    @php($d=$source->plannedPallet?->palletPlan?->deliveryPlan)
                    @php($parts=$source->boxes->groupBy('part_number')->map(fn($rows,$part)=>$part.' ('.$rows->count().' box)')->implode(', '))
                    <tr data-merge-source data-search="{{ mb_strtolower($source->display_name.' '.$source->location_code.' '.$d?->plan_code.' '.$d?->customer_name.' '.$parts) }}">
                        <td><input class="form-check-input merge-source-check" type="checkbox" name="pallet_ids[]" value="{{ $source->id }}" id="source-{{ $source->id }}" data-location="{{ $source->master_location_id }}" @checked(in_array($source->id,old('pallet_ids',[])))></td>
                        <th scope="row"><label for="source-{{ $source->id }}">{{ $source->display_name }}<span class="d-block text-primary">Lokasi {{ $source->location_code }}</span></label></th>
                        <td>@if($d)<strong>{{ $d->plan_code }}</strong><small class="d-block">{{ $d->customer_name }}</small>@else Stok cadangan @endif</td>
                        <td><strong>{{ $source->boxes->count() }} box</strong><small class="d-block">{{ $parts }}</small></td>
                        <td>@if($source->plannedPallet?->ready_at)<span class="badge text-bg-warning">Siap · perlu konfirmasi ulang</span>@else<span class="text-muted">{{ $d ? 'Pallet permintaan' : 'Cadangan' }}</span>@endif</td>
                    </tr>
                @empty<tr><td colspan="5" class="text-center py-5">Belum ada pallet ekspor berisi stok yang dapat digabung.</td></tr>@endforelse
                <tr id="merge-no-match" hidden><td colspan="5" class="text-center py-4">Tidak ada pallet yang sesuai pencarian.</td></tr>
            </tbody></table></div>
        </section>
        <section class="plan-panel"><div class="plan-panel-heading"><h2>2. Tentukan hasil merge</h2></div><div class="plan-panel-body">
            <div class="row g-4"><div class="col-lg-5"><label for="merge-location-search" class="form-label">Cari lokasi tujuan</label><input type="search" id="merge-location-search" class="form-control mb-2" placeholder="Ketik kode lokasi…" aria-controls="merge-location"><label for="merge-location" class="form-label">Lokasi tujuan</label><select id="merge-location" class="form-select" name="location_id" required size="6">
                @foreach($locations as $location)<option value="{{ $location->id }}" data-code="{{ mb_strtolower($location->code) }}" data-source="{{ $location->stockPallet?->id }}" data-available="{{ !$location->is_occupied && !$location->reservation ? '1':'0' }}" @selected((string)old('location_id')===(string)$location->id)>{{ $location->code }} · {{ !$location->is_occupied && !$location->reservation ? 'Kosong' : 'Terisi / direservasi' }}</option>@endforeach
            </select><p class="plan-help mt-2">Pilih lokasi salah satu pallet sumber atau lokasi ekspor kosong. Lokasi lain tidak dapat dipilih.</p></div>
            <fieldset class="col-lg-7"><legend class="fs-6 fw-semibold">Jika stok cadangan cocok dengan sisa kebutuhan yang belum direservasi</legend>
                <label class="merge-option"><input type="radio" name="spare_mode" value="fill" @checked(old('spare_mode','fill')==='fill')><span><strong>Penuhi kebutuhan lebih dahulu</strong><small>Isi sisa kebutuhan pada pallet yang dipilih. Hanya kelebihan box atau part baru yang menambah delivery.</small></span></label>
                <label class="merge-option"><input type="radio" name="spare_mode" value="add" @checked(old('spare_mode')==='add')><span><strong>Tambahkan seluruhnya sebagai kebutuhan baru</strong><small>Kebutuhan lama tetap ada. Seluruh stok cadangan yang digabung menambah jumlah delivery.</small></span></label>
                <p class="plan-help">Contoh: sisa 5 box, cadangan masuk 8 box. Pilihan pertama menambah 3 box; pilihan kedua menambah 8 box.</p>
            </fieldset></div>
        </div></section>
        <div class="merge-actions"><span class="plan-help">Pemeriksaan FIFO dan reservasi dilakukan sebelum pratinjau.</span><button type="submit" class="btn btn-primary" disabled>Tinjau merge <i class="bi bi-arrow-right" aria-hidden="true"></i></button></div>
    </form>
</div>
@endsection
@section('scripts')<script src="{{ asset('js/pages/export-merge.js') }}" defer></script>@endsection
