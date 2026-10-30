@if($box->fulfillment?->placed_at)
    <span class="fulfillment-status fulfillment-status-complete">{{ $box->status_label }}</span>
@elseif($box->fulfillment?->awaitsQueueAcknowledgment())
    <span class="fulfillment-status fulfillment-status-paused">{{ $box->status_label }}</span>
@elseif($box->fulfillment)
    <span class="fulfillment-status fulfillment-status-partial">{{ $box->status_label }}</span>
@elseif($box->state === 'stored')
    <span class="fulfillment-status">{{ $box->status_label }}</span>
@else
    <span class="fulfillment-status fulfillment-status-paused">{{ $box->status_label }}</span>
@endif
