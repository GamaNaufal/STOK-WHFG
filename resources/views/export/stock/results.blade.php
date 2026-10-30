<div class="plan-panel-heading">
    <h2>{{ $filters['mode']==='pallet' ? 'Stok berdasarkan pallet' : 'Daftar stok ekspor' }}</h2>
    <span>{{ $resultCount }} box
        @if($palletGroups)
            · {{ $palletGroups->total() }} kelompok
        @endif
    </span>
</div>
@if($filters['mode']==='pallet')
    <div class="plan-table-scroll stock-pallet-overview" role="region" tabindex="0" aria-label="Daftar pallet ekspor">
        <table class="table plan-table">
            <thead><tr><th>Pallet</th><th>Lokasi</th><th>Rencana delivery</th><th>Jumlah sesuai filter</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($palletGroups as $group)
                    @php($pallet = $group->currentPallet)
                    @php($contentsUrl = route('export.stock.pallet-boxes', ['pallet' => $pallet?->id ?? 'unplaced'] + $filters))
                    <tr>
                        <th scope="row">{{ $pallet?->display_name ?? 'Belum ditempatkan' }}</th>
                        <td>{{ $pallet?->location_code ?? 'Belum ada lokasi fisik' }}</td>
                        <td>{{ $pallet?->plannedPallet?->palletPlan?->deliveryPlan?->plan_code ?? ($pallet ? 'Stok cadangan' : 'Belum ada pallet fisik') }}</td>
                        <td><strong>{{ $group->matched_box_count }} box · {{ number_format($group->matched_pcs_quantity, 0, ',', '.') }} PCS</strong>@if($pallet && $hasFilters)<small class="fulfillment-customer">dari {{ $pallet->boxes_count }} box fisik di pallet</small>@endif</td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ $contentsUrl }}" data-export-stock-pallet="{{ $contentsUrl }}" aria-label="Lihat detail {{ $pallet?->display_name ?? 'box belum ditempatkan' }}{{ $pallet ? ' di lokasi '.$pallet->location_code : '' }}" aria-haspopup="dialog" aria-controls="export-stock-box-modal">Lihat detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5">@include('export.stock.empty')</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($palletGroups->hasPages())<div class="plan-panel-body">{{ $palletGroups->links('pagination::bootstrap-5') }}</div>@endif
@else
    @include('export.stock.table', ['showPhysical' => true])
    @if($boxes->hasPages())<div class="plan-panel-body">{{ $boxes->links('pagination::bootstrap-5') }}</div>@endif
@endif
