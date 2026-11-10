@php
    $reservation = $box->fulfillment;
    $target = $reservation?->plannedItem?->pallet;
    $stored = (bool) $target?->ready_at;
@endphp
@if(in_array($box->expiry_label, ['Expired', 'Warning']))
<span class="fulfillment-status {{ $box->expiry_label === 'Expired' ? 'text-danger' : 'text-warning' }}">{{ $box->expiry_label }}</span>
@endif
<span class="fulfillment-status {{ $stored ? 'fulfillment-status-complete' : ($reservation ? 'fulfillment-status-partial' : '') }}">{{ $box->stock_category_label }}</span>
@if($reservation)
    <small class="fulfillment-customer">
        {{ $stored ? 'Rencana delivery' : 'Memiliki reservasi' }} · {{ $target?->palletPlan?->deliveryPlan?->plan_code ?? 'Rencana delivery' }}
        · Pallet {{ $target?->position }} · Lokasi {{ $target?->location_code ?? 'belum ditentukan' }}
    </small>
    @unless($stored)
        <small class="fulfillment-customer">
            @if($reservation->placed_at)
                Menunggu konfirmasi pallet siap
            @elseif($reservation->picking_started_at && !$reservation->picking_verified_at && !$reservation->awaitsQueueAcknowledgment())
                Picking sedang berjalan
            @else
                {{ $box->status_label }}
            @endif
        </small>
    @endunless
@elseif($box->state === 'received')
    <small class="fulfillment-customer">Menunggu penempatan cadangan</small>
@endif
