@extends('shared.layouts.app')
@section('title', 'Hasil Perencanaan Pallet')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
<div class="delivery-plan-page">
    @include('export.pallet-plans.header')
    @include('export.pallet-plans.stock-allocation-modal')
    <div class="plan-inline-actions mb-3">
        @if($printReady)<a class="btn btn-outline-primary" href="{{ route('export.pallet-plans.print', $delivery) }}" target="_blank" rel="noopener">Cetak semua pallet</a>@if(auth()->user()->canAccess('export_delivery_plans'))<a class="btn btn-outline-primary" href="{{ route('export.delivery-plans.print', $delivery) }}" target="_blank" rel="noopener">Cetak rencana delivery</a>@endif @endif
        @if($planning->status === 'final' && auth()->user()->canAccess('export_fulfillment'))<a class="btn btn-primary" href="{{ route('export.fulfillment.show', $delivery) }}">Pantau pemenuhan permintaan</a>@endif
        @if($planning->status !== 'final')<a class="btn btn-primary" href="{{ route('export.pallet-plans.edit', $delivery) }}">{{ $planning->status === 'cancelled' ? 'Susun ulang perencanaan' : 'Lanjutkan perencanaan' }}</a>@endif
        @foreach(['reopen' => 'Buka revisi', 'cancel' => 'Batalkan perencanaan'] as $action => $label)
            @if(($action === 'reopen' && $planning->status === 'final') || ($action === 'cancel' && $planning->status !== 'cancelled'))
                <form method="POST" action="{{ route('export.pallet-plans.transition', $delivery) }}" class="pallet-transition-form">@csrf<input type="hidden" name="revision" value="{{ $planning->revision }}"><input type="hidden" name="action" value="{{ $action }}"><button type="button" class="btn btn-outline-secondary pallet-transition" data-action="{{ $action }}">{{ $label }}</button></form>
            @endif
        @endforeach
    </div>
    @unless($printReady)<p class="plan-help mb-3">Cetak delivery dan rincian pallet tersedia setelah perencanaan final.</p>@endunless
    <p class="plan-help mb-3">{{ $planning->pallets->count() }} pallet · {{ $planning->reservations->count() }} lokasi direservasi · Revisi {{ $planning->revision }}@if($planning->finalized_at) · Difinalisasi {{ $planning->finalizedBy?->name }} pada {{ $planning->finalized_at->format('d/m/Y H:i') }}@endif</p>
    @if($planning->status === 'cancelled')<p class="alert alert-info">Perencanaan dibatalkan. Pembagian tersimpan sebagai riwayat, seluruh reservasi lokasi sudah dilepas.</p>@endif
    @include('export.pallet-plans.summary')
    @foreach($planning->pallets as $pallet)
        <section class="plan-panel"><div class="plan-panel-heading"><h2>Pallet {{ $pallet->position }}</h2><div class="plan-inline-actions"><span>Lokasi {{ $pallet->location_code ?? 'belum ditentukan' }}</span><x-export-pallet-load :boxes="$pallet->items->sum('box_quantity')" />@if($printReady)<a class="btn btn-sm btn-outline-primary" href="{{ route('export.pallet-plans.print-pallet', ['deliveryPlan' => $delivery->id, 'pallet' => $pallet->id]) }}" target="_blank" rel="noopener" aria-label="Cetak pallet {{ $pallet->position }}">Cetak pallet</a>@endif</div></div>
            <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Isi pallet {{ $pallet->position }}"><table class="table plan-table"><thead><tr><th>Nomor part</th><th>Jumlah box</th><th>PCS / box</th></tr></thead><tbody>@forelse($pallet->items as $item)<tr><th scope="row">{{ $item->part_number }}</th><td>{{ number_format($item->box_quantity, 0, ',', '.') }}</td><td>{{ number_format($item->pcs_per_box, 0, ',', '.') }}</td></tr>@empty<tr><td colspan="3">Pallet belum diisi.</td></tr>@endforelse</tbody></table></div>
        </section>
    @endforeach
</div>
@endsection
@section('scripts')
<script>
const stockReview = document.getElementById('stock-allocation-modal');
if (stockReview) bootstrap.Modal.getOrCreateInstance(stockReview).show();
document.querySelector('[data-stock-ack-form]')?.addEventListener('submit', function (event) {
    if (this.dataset.submitting) { event.preventDefault(); return; }
    this.dataset.submitting = 'true'; this.setAttribute('aria-busy', 'true');
});
window.addEventListener('pageshow', () => document.querySelectorAll('[data-stock-ack-form]').forEach(form => {
    delete form.dataset.submitting; form.removeAttribute('aria-busy');
}));
document.querySelectorAll('.pallet-transition').forEach(button=>button.addEventListener('click',()=>{
    const cancel=button.dataset.action==='cancel';
    WarehouseAlert.confirm({title:cancel?'Batalkan perencanaan pallet?':'Buka revisi perencanaan?',
        message:cancel?'Pembagian tetap tersimpan sebagai riwayat. Seluruh reservasi lokasi akan dilepas.':'Perencanaan kembali menjadi draft. Reservasi lokasi tetap ditahan untuk rencana ini.',
        confirmText:cancel?'Batalkan perencanaan':'Buka revisi',onConfirm:()=>button.closest('form').submit()});
}));
</script>
@endsection
