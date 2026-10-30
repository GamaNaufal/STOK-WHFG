<div class="stock-pallet-detail-heading">
    <h3>{{ $physicalPallet?->display_name ?? 'Belum ditempatkan' }}</h3>
    <p>{{ $physicalPallet ? 'Lokasi '.$physicalPallet->location_code : 'Box belum memiliki pallet fisik.' }}</p>
    @if($physicalPallet?->plannedPallet?->palletPlan?->deliveryPlan)
        <p>{{ $physicalPallet->plannedPallet->palletPlan->deliveryPlan->plan_code }}</p>
    @endif
</div>
<div class="stock-pallet-contents">@include('export.stock.pallet-boxes')</div>
