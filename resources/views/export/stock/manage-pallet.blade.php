@extends('export.stock.admin-layout')
@section('title', 'Kelola pallet ekspor '.$pallet->number)
@section('stock-back', route('export.stock.pallet-boxes', $pallet))

@section('stock-header')
<header class="plan-header">
    <div><span class="plan-eyebrow">Kelola pallet cadangan</span><h1>{{ $pallet->number }}</h1><p>{{ $pallet->location_code }} · {{ $pallet->capacityLabel($count) }}</p></div>
</header>
@endsection

@section('stock-body')
<section class="plan-panel" aria-labelledby="pallet-limit-title">
    <div class="plan-panel-heading"><h2 id="pallet-limit-title">Edit batas maksimal box</h2></div>
    <form class="plan-panel-body" method="POST" action="{{ route('export.stock.pallet-limit', $pallet) }}" data-export-stock-confirm
        data-confirm-title="Ubah batas pallet {{ $pallet->number }}?" data-confirm-message="Batas baru akan dipakai saat operator menempatkan box berikutnya." data-confirm-button="Ya, simpan batas">
        @csrf @method('PATCH')
        <input type="hidden" name="fingerprint" value="{{ $fingerprint }}">
        <div class="mb-3">
            <label class="form-label" for="edit-export-limit">Maksimal box</label>
            <input class="form-control" id="edit-export-limit" name="box_limit" type="number" min="0" max="4294967295" step="1" required
                value="{{ is_scalar(old('box_limit')) ? old('box_limit') : ($pallet->box_limit ?? 0) }}" aria-describedby="edit-export-limit-help">
            <p class="plan-help mb-0" id="edit-export-limit-help">Isi 0 untuk kapasitas bebas. Batas positif minimal {{ $count }} box sesuai isi pallet saat ini.</p>
        </div>
        @include('export.stock.admin-confirmation', ['reasonId' => 'pallet-edit-reason', 'method' => 'PATCH'])
        <button class="btn btn-primary" type="submit">Simpan batas</button>
    </form>
</section>
<section class="plan-panel" aria-labelledby="pallet-delete-title">
    <div class="plan-panel-heading"><h2 id="pallet-delete-title">Hapus pallet dan isinya</h2></div>
    @if($deleteBlocked)
        <div class="plan-panel-body"><p class="alert alert-warning mb-0" role="status">{{ $deleteBlocked }}</p></div>
    @else
        <form class="plan-panel-body" method="POST" action="{{ route('export.stock.pallet-delete', $pallet) }}" data-export-stock-confirm data-delete
            data-confirm-title="Hapus pallet {{ $pallet->number }}?" data-confirm-message="Pallet dan seluruh {{ $count }} box di dalamnya akan keluar dari stok aktif. Lokasi pallet akan dibebaskan." data-confirm-button="Ya, hapus pallet dan isi">
            @csrf @method('DELETE')
            <input type="hidden" name="fingerprint" value="{{ $fingerprint }}">
            <p class="plan-help mb-3">Aksi ini mencakup seluruh {{ $count }} box pada pallet. Riwayat setiap ID Box tetap dapat dibuka.</p>
            @include('export.stock.admin-confirmation', ['reasonId' => 'pallet-delete-reason', 'method' => 'DELETE'])
            <button class="btn btn-outline-danger" type="submit">Hapus pallet dan isi</button>
        </form>
    @endif
</section>
@endsection
