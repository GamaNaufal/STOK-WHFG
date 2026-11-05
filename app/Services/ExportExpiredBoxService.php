<?php

namespace App\Services;

use App\Models\ExportStockBox;
use Illuminate\Support\Facades\DB;

class ExportExpiredBoxService
{
    public function getExpirableBoxesQuery()
    {
        return DB::table('export_stock_boxes as b')
            ->leftJoin('export_stock_pallets as p', 'p.id', '=', 'b.current_pallet_id')
            ->leftJoin('export_planned_pallets as source_plan', 'source_plan.id', '=', 'p.export_planned_pallet_id')
            ->leftJoin('export_fulfillment_boxes as f', 'f.export_stock_box_id', '=', 'b.id')
            ->leftJoin('export_planned_pallet_items as i', 'i.id', '=', 'f.export_planned_pallet_item_id')
            ->leftJoin('export_planned_pallets as pp', 'pp.id', '=', 'i.export_planned_pallet_id')
            ->leftJoin('export_pallet_plans as plan', 'plan.id', '=', 'pp.export_pallet_plan_id')
            ->leftJoin('export_delivery_plans as d', 'd.id', '=', 'plan.export_delivery_plan_id')
            ->whereNull('b.deleted_at')->whereIn('b.state', ['received', 'stored'])
            ->select(['b.id', 'b.box_number', 'b.part_number', 'b.pcs_quantity', 'b.reactivated_until',
                'b.expired_status', 'b.is_not_full', 'source_plan.ready_at as source_ready_at', 'source_plan.position as source_position', 'p.location_code as warehouse_location', 'p.master_location_id',
                'p.id as pallet_id', 'b.first_received_at as stored_at', 'd.id as delivery_id', 'd.plan_code as delivery_code'])
            ->selectRaw("CASE WHEN p.id IS NULL THEN NULL ELSE 'Pallet ekspor' END as pallet_number");
    }

    public function syncStatuses(): void
    {
        // Eligibility is also checked from dates at every operational write, independently of this refresh.
        ExportStockBox::whereIn('state', ['received', 'stored'])->orderBy('id')->chunkById(300, function ($boxes) {
            foreach ($boxes as $box) {
                $status = match ($box->expiry_label) {
                    'Expired' => 'expired', 'Warning' => 'warning', default => 'active'
                };
                ExportStockBox::whereKey($box->id)->whereIn('state', ['received', 'stored'])
                    ->where('expired_status', '!=', $status)->update(['expired_status' => $status]);
            }
        });
    }
}
