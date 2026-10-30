@extends('shared.layouts.app')
@section('title', 'Batalkan Permintaan Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}"><link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">@endsection
@section('content')
<div class="delivery-plan-page fulfillment-page">
    <header class="plan-header"><div><a class="plan-back" href="{{ route('export.fulfillment.show', $delivery) }}">← Pemenuhan permintaan</a><span class="plan-eyebrow">Pembatalan permintaan ekspor</span><h1>{{ $delivery->plan_code }} · {{ $delivery->customer_name }}</h1><p>{{ $delivery->total_boxes }} box · {{ $preview['previews']->count() }} pallet</p></div></header>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="alert alert-warning">Seluruh permintaan akan dibatalkan. Kembalikan box yang sudah ditempatkan atau sudah diverifikasi pengambilannya sebelum konfirmasi. Box tetap sebagai stok cadangan; pembatalan tidak dicatat sebagai pengiriman.</div>
    <section class="plan-panel"><div class="plan-panel-heading"><h2>Tinjauan pengembalian dan reservasi</h2><span>{{ $preview['returns']->count() }} pengembalian fisik · {{ $preview['released']->count() }} reservasi dilepas</span></div>
        @include('export.fulfillment.return-list', ['returns'=>$preview['returns'], 'released'=>$preview['released']])
        <form method="POST" action="{{ route('export.fulfillment.cancel', $delivery->id) }}" class="plan-panel-body">@csrf
            <input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}"><input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}"><input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
            <label class="form-label" for="cancel-reason">Alasan pembatalan</label><textarea id="cancel-reason" name="reason" class="form-control mb-3" maxlength="500" required rows="3">{{ old('reason') }}</textarea>
            @include('export.fulfillment.return-fields', ['returns'=>$preview['returns']])
            <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('export.fulfillment.show', $delivery) }}">Kembali</a><button class="btn btn-danger" type="submit">Konfirmasi pembatalan permintaan</button></div>
        </form>
    </section>
</div>
@endsection
