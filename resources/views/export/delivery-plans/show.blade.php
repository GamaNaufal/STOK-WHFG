@extends('shared.layouts.app')
@section('title', 'Rencana Delivery - '.$plan->customer_name)
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
<div class="delivery-plan-page">
    <header class="plan-header">
        <div><a class="plan-back" href="{{ route('export.delivery-plans.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Rencana delivery</a><span class="plan-eyebrow">Pengiriman ekspor / Draft</span><h1>{{ $plan->customer_name }}</h1><p>Kode rencana <strong>{{ $plan->plan_code ?? '—' }}</strong></p><p>Batas pengiriman {{ $plan->delivery_deadline->format('d/m/Y') }}</p></div>
        <a class="btn btn-primary" href="{{ route('export.delivery-plans.edit', $plan) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Ubah rencana</a>
    </header>
    <section class="plan-panel">
        @if(auth()->user()->canAccess('export_pallet_plans'))
            <div class="plan-panel-body"><a class="btn btn-outline-primary" href="{{ route('export.pallet-plans.show', $plan) }}">Perencanaan Pallet</a></div>
        @endif
        <div class="plan-panel-heading"><div><h2>Kebutuhan pengiriman</h2><p>Kapasitas box disimpan saat part ditambahkan ke rencana.</p></div><span>{{ $plan->items->count() }} nomor part</span></div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Kebutuhan part rencana delivery">
            <table class="table plan-table"><thead><tr><th scope="col">No</th><th scope="col">Nomor part</th><th scope="col">Jumlah PCS</th><th scope="col">PCS / box</th><th scope="col">Jumlah box</th></tr></thead>
                <tbody>@foreach($plan->items as $item)
                    <tr><td>{{ $loop->iteration }}</td><th scope="row">{{ $item->part_number }}</th><td>{{ number_format($item->pcs_quantity, 0, ',', '.') }}</td><td>{{ number_format($item->pcs_per_box, 0, ',', '.') }}</td><td><strong>{{ number_format($item->box_quantity, 0, ',', '.') }}</strong></td></tr>
                @endforeach</tbody>
            </table>
        </div>
        <div class="plan-totals"><span>Total PCS <strong>{{ number_format($plan->total_pcs, 0, ',', '.') }}</strong></span><span>Total box <strong>{{ number_format($plan->total_boxes, 0, ',', '.') }}</strong></span></div>
    </section>
    <p class="plan-help">Dibuat oleh {{ $plan->creator->name }} · {{ $plan->created_at->format('d/m/Y H:i') }} · Revisi {{ $plan->revision }}
        @if($plan->source_type !== 'manual') · Sumber {{ $plan->source_type === 'photo' ? 'foto (riwayat)' : 'Excel' }}: {{ $plan->source_name }} @endif
    </p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if($plan->palletPlan && $plan->palletPlan->status !== 'cancelled')
        <p class="plan-help">Batalkan perencanaan pallet terlebih dahulu sebelum mengarsipkan rencana delivery.</p>
    @else
    <form method="POST" action="{{ route('export.delivery-plans.destroy', $plan) }}" id="archive-plan-form">
        @csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $plan->revision }}">
        <button type="button" class="btn btn-outline-danger" id="archive-plan">Arsipkan rencana</button>
    </form>
    @endif
</div>
@endsection
@section('scripts')
<script>
document.getElementById('archive-plan')?.addEventListener('click', function () {
    WarehouseAlert.confirm({
        title: 'Arsipkan rencana delivery?',
        message: 'Rencana ini akan dikeluarkan dari daftar aktif. Riwayat tetap tersimpan.',
        confirmText: 'Arsipkan',
        onConfirm: () => document.getElementById('archive-plan-form').submit()
    });
});
</script>
@endsection
