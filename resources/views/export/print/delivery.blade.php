@extends('export.print.layout')
@section('title', 'Rencana delivery '.($delivery->plan_code ?? 'RD-'.$delivery->id))
@section('print-help', 'A4 portrait')
@section('content')
    <header class="delivery-print-heading"><p>PT. YAMATOGOMU INDONESIA - WAREHOUSE FG EKSPOR</p><h1>Rencana delivery</h1><strong>{{ $delivery->plan_code ?? 'RD-'.$delivery->id }}</strong><span class="final-label">PERENCANAAN FINAL</span></header>
    <table class="print-meta"><tbody>
        <tr><th>Customer</th><td>{{ $delivery->customer_name }}</td><th>Batas pengiriman</th><td>{{ $delivery->delivery_deadline->format('d/m/Y') }}</td></tr>
        <tr><th>Revisi delivery</th><td>{{ $delivery->revision }}</td><th>Revisi pallet</th><td>{{ $planning->revision }} · {{ $planning->pallets_count }} pallet</td></tr>
        <tr><th>Difinalisasi</th><td>{{ $planning->finalized_at?->format('d/m/Y H:i') }} · {{ $planning->finalizedBy?->name ?? 'Operator' }}</td><th>Dicetak</th><td>{{ $printedAt->format('d/m/Y H:i') }}</td></tr>
    </tbody></table>
    <table class="delivery-print-table">
        <colgroup><col style="width:7%"><col style="width:39%"><col style="width:20%"><col style="width:17%"><col style="width:17%"></colgroup>
        <thead><tr><th colspan="5" class="table-identity">{{ $delivery->plan_code ?? 'RD-'.$delivery->id }} · {{ $delivery->customer_name }} · Pengiriman {{ $delivery->delivery_deadline->format('d/m/Y') }}</th></tr><tr><th class="col-no">No.</th><th class="col-part">Nomor part</th><th class="number col-pcs">Jumlah PCS</th><th class="number col-capacity">PCS / box</th><th class="number col-box">Jumlah box</th></tr></thead>
        <tbody>@foreach($delivery->items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->part_number }}</td><td class="number">{{ number_format($item->pcs_quantity, 0, ',', '.') }}</td><td class="number">{{ number_format($item->pcs_per_box, 0, ',', '.') }}</td><td class="number"><strong>{{ number_format($item->box_quantity, 0, ',', '.') }}</strong></td></tr>@endforeach</tbody>
    </table>
    <p class="delivery-print-total">Total PCS: <strong>{{ number_format($delivery->total_pcs, 0, ',', '.') }}</strong> &nbsp; Total box: <strong>{{ number_format($delivery->total_boxes, 0, ',', '.') }}</strong></p>
    <p class="print-footnote">Gunakan cetakan dari revisi terbaru. Kapasitas dan jumlah mengikuti rencana delivery yang difinalisasi.</p>
@endsection
