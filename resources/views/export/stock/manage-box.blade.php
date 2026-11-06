@extends('export.stock.admin-layout')
@section('title', 'Kelola box ekspor '.$box->box_number)
@section('stock-back', route('export.stock.index', ['box' => $box->id]))

@section('stock-header')
<header class="plan-header">
    <div><span class="plan-eyebrow">Kelola stok ekspor</span><h1>ID Box {{ $box->box_number }}</h1><p>{{ $box->part_number }} · {{ number_format($box->pcs_quantity, 0, ',', '.') }} PCS · {{ $box->currentPallet?->location_code ?? 'Belum ditempatkan' }}</p></div>
    <a class="btn btn-outline-primary" href="{{ route('export.stock.box-history', ['box_number' => $box->box_number]) }}"><i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat box</a>
</header>
@endsection

@section('stock-body')
@if($blocked)
    <p class="alert alert-warning" role="status">{{ $blocked }}</p>
@else
    @if($box->reactivated_until)
        <p class="alert alert-info">Data box hasil packing mengikuti histori QA dan packing.</p>
    @else
        <section class="plan-panel" aria-labelledby="box-edit-title">
            <div class="plan-panel-heading"><h2 id="box-edit-title">Edit data box</h2></div>
            <form class="plan-panel-body" method="POST" action="{{ route('export.stock.box-update', $box) }}" data-export-stock-confirm
                data-confirm-title="Simpan perubahan box?" data-confirm-message="Perubahan akan diterapkan pada ID Box {{ $box->box_number }}." data-confirm-button="Ya, simpan">
                @csrf @method('PATCH')
                <input type="hidden" name="revision" value="{{ $box->revision }}">
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label" for="edit-export-part">Nomor part</label>
                        <input class="form-control" id="edit-export-part" name="part_number" list="export-box-parts" maxlength="100" required @readonly($box->is_not_full) value="{{ !$box->is_not_full && is_string(old('part_number')) ? old('part_number') : $box->part_number }}">
                        <datalist id="export-box-parts">@foreach($parts as $part)<option value="{{ $part->part_number }}">{{ $part->qty_box }} PCS/box</option>@endforeach</datalist>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="edit-export-pcs">Jumlah PCS</label>
                        <input class="form-control" id="edit-export-pcs" name="pcs_quantity" type="number" min="1" max="4294967295" step="1" required @readonly($box->is_not_full) value="{{ !$box->is_not_full && is_scalar(old('pcs_quantity')) ? old('pcs_quantity') : $box->pcs_quantity }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="edit-export-date">Waktu masuk (WIB)</label>
                        <input class="form-control" id="edit-export-date" name="first_received_at" type="datetime-local" min="1970-01-02T00:00" max="{{ now()->format('Y-m-d\TH:i') }}" required value="{{ is_string(old('first_received_at')) ? old('first_received_at') : $box->first_received_at->format('Y-m-d\TH:i') }}">
                    </div>
                </div>
                <p class="plan-help mb-3">Waktu masuk memengaruhi FIFO dan umur stok. Perubahan PCS mengikuti standar part dan alur persetujuan Box Not Full.</p>
                @include('export.stock.admin-confirmation', ['reasonId' => 'box-edit-reason', 'method' => 'PATCH'])
                <button class="btn btn-primary" type="submit">Simpan perubahan</button>
            </form>
        </section>
    @endif
    <section class="plan-panel" aria-labelledby="box-delete-title">
        <div class="plan-panel-heading"><h2 id="box-delete-title">Hapus box</h2></div>
        <form class="plan-panel-body" method="POST" action="{{ route('export.stock.box-delete', $box) }}" data-export-stock-confirm data-delete
            data-confirm-title="Hapus ID Box {{ $box->box_number }}?" data-confirm-message="Box akan keluar dari stok aktif. Jika ini box terakhir pada pallet cadangan, lokasinya akan dibebaskan." data-confirm-button="Ya, hapus box">
            @csrf @method('DELETE')
            <input type="hidden" name="revision" value="{{ $box->revision }}">
            <p class="plan-help mb-3">ID Box, jejak perpindahan, dan alasan penghapusan akan tersedia di riwayat.</p>
            @include('export.stock.admin-confirmation', ['reasonId' => 'box-delete-reason', 'method' => 'DELETE'])
            <button class="btn btn-outline-danger" type="submit">Hapus box</button>
        </form>
    </section>
@endif
@endsection
