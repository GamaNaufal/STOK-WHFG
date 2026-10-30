@extends('export.print.layout')
@section('title', 'Rincian pallet '.($delivery->plan_code ?? 'RD-'.$delivery->id))
@section('print-help', $pallets->count().' pallet · A4 portrait · ukuran mengikuti rincian part')
@section('content')
    @foreach($pallets as $pallet)
        @php($boxCount = (int) $pallet->items->sum('box_quantity'))
        <table class="pallet-print-card">
            <colgroup><col style="width:9%"><col style="width:68%"><col style="width:23%"></colgroup>
            <thead>
                <tr><th colspan="3" class="pallet-card-identity"><span class="pallet-company">PT. YAMATOGOMU INDONESIA - EKSPOR · FINAL</span><span class="pallet-card-title">Pallet {{ $pallet->position }} <span>Lokasi: {{ $pallet->location_code }}</span></span><span class="pallet-delivery-code">{{ $delivery->plan_code ?? 'RD-'.$delivery->id }} · {{ $delivery->customer_name }}</span><span class="pallet-card-meta">Batas pengiriman: {{ $delivery->delivery_deadline->format('d/m/Y') }} · {{ number_format($boxCount, 0, ',', '.') }} box · Revisi D{{ $delivery->revision }}/P{{ $planning->revision }}</span>@if(\App\Support\ExportPalletLoad::level($boxCount) !== 'normal')<span class="pallet-load-note">{{ \App\Support\ExportPalletLoad::note($boxCount) }}</span>@endif</th></tr>
                <tr><th class="col-no">No.</th><th class="col-part">Nomor part</th><th class="number col-box">Jumlah box</th></tr>
            </thead>
            <tbody>@foreach($pallet->items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->part_number }}</td><td class="number"><strong>{{ number_format($item->box_quantity, 0, ',', '.') }}</strong></td></tr>@endforeach</tbody>
            <tfoot><tr><td colspan="3">{{ $pallet->items->count() }} part · Total {{ number_format($boxCount, 0, ',', '.') }} box · Dicetak {{ $printedAt->format('d/m/Y H:i') }}</td></tr></tfoot>
        </table>
    @endforeach
@endsection
