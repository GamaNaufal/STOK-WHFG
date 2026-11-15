@extends('export.stock.admin-layout')
@section('title', 'Riwayat box ekspor')
@section('scripts')@endsection

@section('stock-header')
<header class="plan-header">
    <div><span class="plan-eyebrow">Stok ekspor</span><h1>Riwayat box</h1><p>Cari jejak penerimaan, perpindahan, dan perubahan stok berdasarkan ID Box.</p></div>
</header>
@endsection

@section('stock-body')
<form class="plan-panel plan-panel-body mb-3" method="GET" action="{{ route('export.stock.box-history') }}">
    <label class="form-label" for="history-export-box">ID Box (8 angka)</label>
    <div class="receiving-field-action">
        <input class="form-control" id="history-export-box" name="box_number" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" required value="{{ $number }}">
        <button class="btn btn-primary" type="submit">Cari riwayat</button>
    </div>
</form>
@if($box)
    @if($uses->count() > 1)
        <form class="plan-panel plan-panel-body mb-3" method="GET" action="{{ route('export.stock.box-history') }}">
            <input type="hidden" name="box_number" value="{{ $number }}">
            <label class="form-label" for="history-export-use">Pilih riwayat penggunaan ID Box</label>
            <p class="plan-help" id="history-export-use-help">Nomor ini pernah digunakan kembali. Setiap pilihan memiliki penerimaan dan transaksi yang terpisah.</p>
            <div class="receiving-field-action">
                <select class="form-select" name="box_id" id="history-export-use" aria-describedby="history-export-use-help">
                    @foreach($uses as $use)
                        <option value="{{ $use->id }}" @selected($use->id === $box->id)>Penggunaan {{ $use->identity_cycle }} · {{ $use->part_number }} · {{ $use->pcs_quantity }} PCS · {{ $use->created_at?->format('d/m/Y H:i') }}{{ $use->trashed() ? ' · Diarsipkan' : '' }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-primary" type="submit">Lihat penggunaan</button>
            </div>
        </form>
    @endif
    <section class="plan-panel" aria-labelledby="export-box-timeline">
        <div class="plan-panel-heading">
            <div><h2 id="export-box-timeline">ID Box {{ $box->box_number }}</h2><p class="plan-help mb-0">Penggunaan {{ $box->identity_cycle }} · {{ $box->part_number }} · {{ number_format($box->pcs_quantity, 0, ',', '.') }} PCS</p></div>
            @if($box->trashed())
                <span class="badge text-bg-secondary">{{ $box->state === 'shipped' ? 'Sudah keluar gudang' : 'Diarsipkan' }}</span>
            @else
                <a class="btn btn-outline-primary" href="{{ route('export.stock.box-manage', $box) }}">Kelola box</a>
            @endif
        </div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Aliran box ekspor">
            <table class="table plan-table">
                <thead><tr><th scope="col">Waktu (WIB)</th><th scope="col">Kejadian</th><th scope="col">Operator</th><th scope="col">Rincian</th></tr></thead>
                <tbody>
                @foreach($events as $event)
                    <tr>
                        <td>{{ $event['at']?->format('d/m/Y H:i:s') ?? '—' }}</td>
                        <th scope="row">{{ $event['label'] }}</th>
                        <td>{{ $event['actor'] ?? '—' }}</td>
                        <td>{{ $event['description'] }}@if($event['detail'])<small class="d-block text-muted">{{ $event['detail'] }}</small>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@elseif($number !== '')
    <p class="alert alert-info" role="status">ID Box {{ $number }} belum tercatat di stok ekspor.</p>
@endif
@endsection
