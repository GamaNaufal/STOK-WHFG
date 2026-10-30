@extends('shared.layouts.app')
@section('title', 'Revisi Pallet Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}"><link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">@endsection
@section('content')
<div class="delivery-plan-page fulfillment-page">
    <header class="plan-header"><div><a class="plan-back" href="{{ route('export.fulfillment.show', $delivery) }}">← Pemenuhan permintaan</a><span class="plan-eyebrow">Revisi pallet ekspor</span><h1>Pallet {{ $pallet->position }} · {{ $pallet->location_code }}</h1><p>{{ $delivery->plan_code }} · {{ $delivery->customer_name }}</p></div></header>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="alert alert-warning">Jumlah rencana delivery ikut dikurangi. Reservasi yang belum dipindahkan dilepas. Box yang sudah ditempatkan atau sudah diverifikasi pengambilannya wajib dikembalikan menjadi stok cadangan.</div>
    @unless($preview)
        @if($pallet->ready_at)<section class="plan-panel"><div class="plan-panel-body"><h2 class="fs-5">Koreksi kesiapan atau lokasi</h2><p>Buka ulang status siap untuk mengonfirmasi lokasi lagi. Jumlah box tetap sama.</p><form method="POST" action="{{ route('export.fulfillment.reopen',[$delivery,$pallet]) }}">@csrf<input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}"><input type="hidden" name="fulfillment_revision" value="{{ $pallet->fulfillment_revision }}"><input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><button class="btn btn-outline-secondary" type="submit">Buka ulang kesiapan pallet</button></form></div></section>@endif
        <form method="POST" action="{{ route('export.fulfillment.revise', [$delivery,$pallet]) }}" class="plan-panel">@csrf<input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}"><input type="hidden" name="fulfillment_revision" value="{{ $pallet->fulfillment_revision }}">
            <div class="plan-panel-heading"><h2>Jumlah box setelah revisi</h2></div><div class="plan-panel-body">
                <p>Masukkan jumlah yang tetap diperlukan, termasuk saat pallet belum penuh. Box terbaru dikembalikan agar box tertua tetap diprioritaskan untuk pengiriman FIFO. Isi semua part dengan 0 untuk menghapus pallet ini; jika tidak ada pallet tersisa, permintaan dibatalkan.</p>
                @foreach($items as $item)<div class="mb-3"><label class="form-label" for="quantity-{{ $item->id }}">{{ $item->part_number }} · saat ini {{ $item->box_quantity }} box</label><input id="quantity-{{ $item->id }}" class="form-control" type="number" name="quantities[{{ $item->id }}]" min="0" max="{{ $item->box_quantity }}" required value="{{ old('quantities.'.$item->id,$quantities[$item->id]) }}"></div>@endforeach
                <button class="btn btn-primary" type="submit">Tinjau revisi dan pengembalian</button>
            </div>
        </form>
    @else
        <section class="plan-panel"><div class="plan-panel-heading"><h2>Tinjauan revisi Pallet {{ $pallet->position }}</h2></div><div class="plan-panel-body">
            <p>Kebutuhan dikurangi {{ $preview['difference'] }} box. <strong>{{ $preview['returns']->count() }} box wajib dikembalikan</strong> dan {{ $preview['released']->count() }} reservasi dilepas tanpa perpindahan. Box terbaru dipilih terlebih dahulu.</p>
        </div>
        @include('export.fulfillment.return-list', ['returns'=>$preview['returns'], 'released'=>$preview['released']])
        <form method="POST" action="{{ route('export.fulfillment.reduce',[$delivery,$pallet]) }}" class="plan-panel-body">@csrf
            <input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}"><input type="hidden" name="fulfillment_revision" value="{{ $pallet->fulfillment_revision }}"><input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}"><input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
            @foreach($quantities as $id=>$quantity)<input type="hidden" name="quantities[{{ $id }}]" value="{{ $quantity }}">@endforeach
            @include('export.fulfillment.return-fields', ['returns'=>$preview['returns']])
            <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('export.fulfillment.revise',[$delivery,$pallet]) }}">Ubah jumlah</a><button class="btn btn-primary" type="submit">Konfirmasi dan simpan revisi</button></div>
        </form></section>
    @endunless
</div>
@endsection
