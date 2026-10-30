<div class="plan-table-scroll" role="region" tabindex="0" aria-label="{{ $showPhysical ? 'Stok box ekspor' : 'Isi box pallet' }}">
    <table class="table plan-table">
        <thead><tr><th>Nomor box / part</th><th>Pertama diterima</th>@if($showPhysical)<th>Lokasi fisik</th>@endif<th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse($boxes as $box)
                <tr><th scope="row">{{ $box->box_number }}<small class="fulfillment-customer">{{ $box->part_number }} · {{ $box->pcs_quantity }} PCS</small></th><td>{{ $box->first_received_at->format('d/m/Y H:i') }}</td>@if($showPhysical)<td>{{ $box->currentPallet ? $box->currentPallet->display_name.' · '.$box->currentPallet->location_code : 'Menunggu penempatan' }}</td>@endif<td>@include('export.receiving.box-status')</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-export-stock-box="{{ route('export.stock.show', $box) }}" aria-label="Lihat box ekspor {{ $box->box_number }}" aria-haspopup="dialog" aria-controls="export-stock-box-modal">Lihat box</button></td></tr>
            @empty
                <tr><td colspan="{{ $showPhysical ? 5 : 4 }}">@include('export.stock.empty', ['hasFilters' => $hasFilters ?? true])</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
