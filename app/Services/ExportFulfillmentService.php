<?php

namespace App\Services;

use App\Models\ExportPalletPlan;
use App\Models\ExportFulfillmentBox;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExportFulfillmentService
{
    public const STATES = [
        'waiting' => 'Belum dimulai', 'partial' => 'Dalam pemenuhan',
        'complete' => 'Terpenuhi', 'paused' => 'Perlu revisi', 'attention' => 'Perlu diperiksa',
    ];

    /** Count confirmations per requirement, so excess on one part cannot fill another. */
    public function itemMetrics(): Builder
    {
        $matches = 'box.part_setting_id = item.part_setting_id AND box.pcs_quantity = item.pcs_per_box';
        $placed = "($matches AND box.placed_at IS NOT NULL AND box.placed_by IS NOT NULL AND box.placed_location_id = pallet.master_location_id)";
        $reserved = "($matches AND box.placed_at IS NULL AND box.placed_by IS NULL AND box.placed_location_id IS NULL)";

        return DB::table('export_planned_pallet_items as item')
            ->join('export_planned_pallets as pallet', 'pallet.id', '=', 'item.export_planned_pallet_id')
            ->leftJoin('export_fulfillment_boxes as box', 'box.export_planned_pallet_item_id', '=', 'item.id')
            ->select(['item.id as item_id', 'item.part_setting_id', 'item.part_number', 'item.pcs_per_box',
                'item.box_quantity as required_boxes', 'pallet.id as pallet_id', 'pallet.export_pallet_plan_id as plan_id',
                'pallet.position', 'pallet.master_location_id', 'pallet.location_code'])
            ->selectRaw("COUNT(CASE WHEN $placed THEN 1 END) as fulfilled_boxes")
            ->selectRaw("COUNT(CASE WHEN $reserved THEN 1 END) as reserved_boxes")
            ->selectRaw("COUNT(box.id) - COUNT(CASE WHEN $placed OR $reserved THEN 1 END) as invalid_boxes")
            ->selectRaw('MAX(box.placed_at) as last_placed_at')
            ->groupBy(['item.id', 'item.part_setting_id', 'item.part_number', 'item.pcs_per_box', 'item.box_quantity',
                'pallet.id', 'pallet.export_pallet_plan_id', 'pallet.position', 'pallet.master_location_id', 'pallet.location_code']);
    }

    /** Finalized plans enter automatically. Previously finalized drafts remain visible on hold. */
    public function overview(bool $includeArchived = false): Builder
    {
        $totals = DB::query()->fromSub($this->itemMetrics(), 'items')->select('plan_id')
            ->selectRaw('SUM(required_boxes) as required_boxes, SUM(fulfilled_boxes) as fulfilled_boxes, SUM(reserved_boxes) as reserved_boxes, SUM(invalid_boxes) as invalid_boxes')
            ->selectRaw('SUM(CASE WHEN fulfilled_boxes + reserved_boxes > required_boxes THEN fulfilled_boxes + reserved_boxes - required_boxes ELSE 0 END) as excess_boxes')
            ->selectRaw('SUM(CASE WHEN required_boxes > fulfilled_boxes THEN required_boxes - fulfilled_boxes ELSE 0 END) as remaining_boxes')
            ->selectRaw('SUM(CASE WHEN required_boxes > fulfilled_boxes + reserved_boxes THEN required_boxes - fulfilled_boxes - reserved_boxes ELSE 0 END) as unreserved_boxes')
            ->selectRaw('SUM(CASE WHEN fulfilled_boxes > required_boxes THEN required_boxes ELSE fulfilled_boxes END) as covered_boxes')
            ->groupBy('plan_id');
        $pallets = DB::table('export_planned_pallets')->select('export_pallet_plan_id')->selectRaw('COUNT(*) as pallet_count')->groupBy('export_pallet_plan_id');
        $plans = ExportPalletPlan::query()->join('export_delivery_plans as delivery', 'delivery.id', '=', 'export_pallet_plans.export_delivery_plan_id')
            ->leftJoinSub($totals, 'totals', 'totals.plan_id', '=', 'export_pallet_plans.id')
            ->leftJoinSub($pallets, 'pallets', 'pallets.export_pallet_plan_id', '=', 'export_pallet_plans.id')
            ->when(!$includeArchived, fn ($q) => $q->whereNull('delivery.deleted_at'))->where('export_pallet_plans.status', '<>', 'cancelled')
            ->where(fn ($query) => $query->where('export_pallet_plans.status', 'final')->orWhere('export_pallet_plans.entered_fulfillment', true))
            ->select(['export_pallet_plans.id as plan_id', 'delivery.id as delivery_id', 'delivery.plan_code',
                'delivery.customer_name', 'delivery.delivery_deadline', 'export_pallet_plans.revision',
                'export_pallet_plans.status as planning_status', 'export_pallet_plans.completed_at', 'export_pallet_plans.completed_by',
                'delivery.deleted_at as archived_at', 'delivery.created_by'])
            ->selectRaw('COALESCE(pallets.pallet_count, 0) as pallet_count')
            ->selectRaw('COALESCE(totals.required_boxes, 0) as required_boxes, COALESCE(totals.fulfilled_boxes, 0) as fulfilled_boxes, COALESCE(totals.reserved_boxes, 0) as reserved_boxes, COALESCE(totals.invalid_boxes, 0) as invalid_boxes, COALESCE(totals.excess_boxes, 0) as excess_boxes')
            ->selectRaw('COALESCE(totals.remaining_boxes, 0) as remaining_boxes, COALESCE(totals.unreserved_boxes, 0) as unreserved_boxes')
            ->selectRaw('COALESCE(totals.covered_boxes, 0) as covered_boxes')
            ->selectRaw("CASE
                WHEN export_pallet_plans.status <> 'final' OR export_pallet_plans.delivery_revision <> delivery.revision THEN 'paused'
                WHEN COALESCE(totals.invalid_boxes, 0) > 0 OR COALESCE(totals.excess_boxes, 0) > 0 THEN 'attention'
                WHEN totals.required_boxes > 0 AND totals.fulfilled_boxes = totals.required_boxes THEN 'complete'
                WHEN COALESCE(totals.fulfilled_boxes, 0) + COALESCE(totals.reserved_boxes, 0) > 0 THEN 'partial'
                ELSE 'waiting' END as fulfillment_status");

        return DB::query()->fromSub($plans->toBase(), 'overview');
    }

    public function displayMetrics(object $row): array
    {
        $required = (int) $row->required_boxes;
        $fulfilled = (int) $row->fulfilled_boxes;
        $reserved = (int) $row->reserved_boxes;
        $invalid = (int) $row->invalid_boxes;
        $excess = (int) ($row->excess_boxes ?? max(0, $fulfilled + $reserved - $required));
        return ['required' => $required, 'fulfilled' => $fulfilled, 'reserved' => $reserved,
            'remaining' => (int) ($row->remaining_boxes ?? max(0, $required - $fulfilled)),
            'unreserved' => (int) ($row->unreserved_boxes ?? max(0, $required - $fulfilled - $reserved)),
            'invalid' => $invalid, 'excess' => $excess,
            'covered' => (int) ($row->covered_boxes ?? min($fulfilled, $required)),
            'percent' => $required ? (int) floor(($row->covered_boxes ?? min($fulfilled, $required)) * 100 / $required) : 0];
    }

    public function assertNoCommittedBoxes(ExportPalletPlan $plan): void
    {
        if (\App\Models\ExportPickingIssue::open()->whereIn('export_planned_pallet_id', $plan->pallets()->select('id'))->exists()) {
            throw ValidationException::withMessages(['picking' => 'Selesaikan approval dan penempatan pengembalian issue picking sebelum merevisi rencana.']);
        }
        $exists = DB::table('export_fulfillment_boxes as box')
            ->join('export_planned_pallet_items as item', 'item.id', '=', 'box.export_planned_pallet_item_id')
            ->join('export_planned_pallets as pallet', 'pallet.id', '=', 'item.export_planned_pallet_id')
            ->where('pallet.export_pallet_plan_id', $plan->id)->exists();
        $physicalStock = \App\Models\ExportStockPallet::whereHas('plannedPallet', fn ($q) => $q->where('export_pallet_plan_id', $plan->id))
            ->whereHas('boxes')->exists();
        if ($exists || $physicalStock) {
            throw ValidationException::withMessages(['fulfillment' =>
                'Rencana sudah memiliki reservasi atau penempatan box. Pelepasan reservasi dan pengembalian box harus diselesaikan sebelum revisi atau pembatalan.']);
        }
    }

    public function boxState(ExportFulfillmentBox $box): string
    {
        $item = $box->plannedItem;
        if ((int) $box->part_setting_id !== (int) $item->part_setting_id || $box->pcs_quantity !== $item->pcs_per_box) return 'invalid';
        if ($box->placed_at === null) {
            return $box->placed_by === null && $box->placed_location_id === null ? 'reserved' : 'invalid';
        }
        return $box->placed_by !== null && $box->placed_location_id !== null
            && (int) $box->placed_location_id === (int) $item->pallet->master_location_id ? 'placed' : 'invalid';
    }
}
