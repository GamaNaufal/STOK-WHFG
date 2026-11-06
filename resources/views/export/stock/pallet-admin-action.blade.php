@if($physicalPallet && !$physicalPallet->retired_at && !$physicalPallet->export_planned_pallet_id && auth()->user()->canAccess('export_stock_manage'))
    <a class="btn btn-outline-primary" href="{{ route('export.stock.pallet-manage', $physicalPallet) }}">Kelola pallet</a>
@endif
