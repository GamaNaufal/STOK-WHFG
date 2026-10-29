<header class="plan-header"><div>
    <a class="plan-back" href="{{ route('export.pallet-plans.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Perencanaan pallet</a>
    <span class="plan-eyebrow">Pengiriman ekspor / {{ $planning?->status_label ?? 'Draft baru' }}</span>
    <h1>{{ $delivery->plan_code ?? 'Perencanaan pallet' }}</h1>
    <p>{{ $delivery->customer_name }} · Batas pengiriman {{ $delivery->delivery_deadline->format('d/m/Y') }} · {{ number_format($delivery->total_boxes, 0, ',', '.') }} box</p>
    @if(auth()->user()->canAccess('export_delivery_plans'))<a class="plan-back mb-0 mt-2" href="{{ route('export.delivery-plans.show', $delivery) }}">Lihat rencana delivery</a>@endif
</div></header>
@if($errors->any())<div class="alert alert-danger" role="alert"><strong>Perencanaan belum disimpan.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
@if($planning && $planning->status !== 'cancelled' && $planning->delivery_revision !== $delivery->revision)
    <div class="alert alert-warning" role="status">Rencana delivery telah direvisi. Pembagian lama dan reservasi lokasi tetap tersimpan. Sesuaikan semua alokasi sebelum finalisasi ulang.</div>
@endif
