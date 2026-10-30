@if($box->fulfillment?->placed_at)
    <span class="fulfillment-status fulfillment-status-complete">Terpenuhi</span>
@elseif($box->fulfillment)
    <span class="fulfillment-status fulfillment-status-partial">Direservasi · menunggu penempatan</span>
@elseif($box->state === 'stored')
    <span class="fulfillment-status">Stok cadangan</span>
@else
    <span class="fulfillment-status fulfillment-status-paused">Pilih penempatan cadangan</span>
@endif
