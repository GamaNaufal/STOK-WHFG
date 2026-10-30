@extends('shared.layouts.app')
@section('title', 'Stok Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page">
    <header class="plan-header"><div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Stok ekspor</h1><p>Stok fisik dan stok cadangan ekspor.</p></div><a class="btn btn-outline-primary" href="{{ route('export.receiving.index') }}">Penerimaan Transfer Box Ekspor</a></header>
    <div class="fulfillment-summary"><div><span>Box tersimpan</span><strong>{{ $storedCount }}</strong></div><div><span>Menunggu penempatan / pemindahan</span><strong>{{ $pendingCount }}</strong></div><div><span>Hasil pencarian stok</span><strong>{{ $boxes->total() }}</strong></div></div>
    <section class="plan-panel"><div class="plan-panel-heading"><h2>Daftar stok ekspor</h2><span>{{ $boxes->total() }} box</span></div>
        <form method="GET" action="{{ route('export.stock.index') }}" class="receiving-stock-search stock-export-filters" aria-describedby="stock-filter-help">
            <div><label class="form-label" for="receiving-stock-search">Cari nomor box atau part</label><input class="form-control" id="receiving-stock-search" name="q" type="search" value="{{ $search }}" maxlength="100"></div>
            <div><label class="form-label" for="stock-status">Status stok</label><select class="form-select" id="stock-status" name="status"><option value="">Semua stok</option>@foreach(['stored'=>'Tersimpan','pending'=>'Menunggu penempatan','spare'=>'Stok cadangan','reserved'=>'Memiliki reservasi'] as $key=>$label)<option value="{{ $key }}" @selected($status===$key)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="form-label" for="stock-location">Lokasi fisik</label><select class="form-select" id="stock-location" name="location_id"><option value="">Semua lokasi</option>@foreach($filterLocations as $pallet)<option value="{{ $pallet->master_location_id }}" @selected($filters['location_id']===$pallet->master_location_id)>{{ $pallet->location_code }}</option>@endforeach</select></div>
            <div>
                <label class="form-label" for="stock-pallet">Pallet fisik</label>
                <select class="form-select" id="stock-pallet" name="pallet_id">
                    <option value="">Semua pallet</option>
                    @foreach($filterPallets as $pallet)
                        <option value="{{ $pallet->id }}" @selected($filters['pallet_id']===$pallet->id)>{{ $pallet->display_name }} · {{ $pallet->location_code }}@if($pallet->plannedPallet?->palletPlan?->deliveryPlan) · {{ $pallet->plannedPallet->palletPlan->deliveryPlan->plan_code }}@endif{{ ' ('.$pallet->boxes_count.' box)' }}</option>
                    @endforeach
                </select>
            </div>
            <p id="stock-filter-help">Pilih pallet atau lokasi untuk meninjau box yang berada di sana saat ini. Excel memuat seluruh hasil filter, termasuk halaman berikutnya.</p>
            <div class="stock-export-actions"><div class="plan-inline-actions"><button class="btn btn-primary" type="submit">Cari stok</button>@if($search!==''||$status!==''||$filters['pallet_id']||$filters['location_id'])<a class="btn btn-outline-secondary" href="{{ route('export.stock.index') }}">Reset</a>@endif</div><button class="btn btn-outline-primary" type="submit" formaction="{{ route('export.stock.export') }}"><i class="bi bi-download" aria-hidden="true"></i> Export Excel</button></div>
        </form>
        <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Stok box ekspor"><table class="table plan-table"><thead><tr><th>Nomor box / part</th><th>Pertama diterima</th><th>Lokasi fisik</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
            @forelse($boxes as $box)<tr><th scope="row">{{ $box->box_number }}<small class="fulfillment-customer">{{ $box->part_number }} · {{ $box->pcs_quantity }} PCS</small></th><td>{{ $box->first_received_at->format('d/m/Y H:i') }}</td><td>{{ $box->currentPallet ? $box->currentPallet->display_name.' · '.$box->currentPallet->location_code : 'Menunggu penempatan' }}</td><td>@include('export.receiving.box-status')</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-export-stock-box="{{ route('export.stock.show', $box) }}" aria-label="Lihat box ekspor {{ $box->box_number }}" aria-haspopup="dialog" aria-controls="export-stock-box-modal">Lihat box</button></td></tr>@empty<tr><td colspan="5" class="plan-empty"><strong>{{ $search!==''||$status!==''||$filters['pallet_id']||$filters['location_id'] ? 'Tidak ada box yang cocok' : 'Belum ada stok ekspor' }}</strong><span>Ubah atau reset filter untuk melihat stok lain. Box baru masuk setelah daftar scan diproses pada Penerimaan Transfer Box Ekspor.</span></td></tr>@endforelse
        </tbody></table></div>@if($boxes->hasPages())<div class="plan-panel-body">{{ $boxes->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
@include('export.stock.box-modal')
@endsection
@section('scripts')@vite('resources/js/export-stock-receiving.js')@endsection
