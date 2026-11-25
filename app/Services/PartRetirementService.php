<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportPickingIssue;
use App\Models\ExportStockBox;
use App\Models\Pallet;
use App\Models\PartSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class PartRetirementService
{
    public function blocker(PartSetting $part): ?string
    {
        $number = $part->part_number;
        $id = $part->id;

        if ($this->matchNumber(Box::identityHeld(), 'boxes.part_number', $number)->exists()
            || ExportStockBox::identityHeld()->where('part_setting_id', $id)->exists()
            || Pallet::whereHas('stockLocation')
                ->whereDoesntHave('boxes', fn ($boxes) => $boxes->withTrashed())
                ->whereHas('items', fn ($items) => $this->matchNumber($items, 'part_number', $number)
                    ->where(fn ($qty) => $qty->where('pcs_quantity', '>', 0)->orWhere('box_quantity', '>', 0)))
                ->exists()) {
            return 'masih ada stok Lokal atau Ekspor untuk part ini';
        }

        if ($this->matchNumber(DB::table('expired_box_handlings'), 'part_number', $number)
            ->whereNotIn('status', ['completed', 'rejected'])->exists()) {
            return 'masih ada penanganan QA atau packing yang belum selesai';
        }

        if ($this->matchNumber(DB::table('delivery_order_items as item'), 'item.part_number', $number)
            ->join('delivery_orders as delivery', 'delivery.id', '=', 'item.delivery_order_id')->whereNull('delivery.deleted_at')
            ->whereNotIn('delivery.status', ['completed', 'rejected', 'deleted'])->exists()
            || DB::table('export_delivery_plan_items as item')->join('export_delivery_plans as delivery', 'delivery.id', '=', 'item.export_delivery_plan_id')
                ->where('item.part_setting_id', $id)->whereNull('delivery.deleted_at')
                ->whereNotIn('delivery.status', ['completed', 'cancelled'])->exists()
            || ExportFulfillmentBox::where('part_setting_id', $id)
                ->whereHas('plannedItem.pallet.palletPlan.deliveryPlan', fn ($plan) => $plan->whereNotIn('status', ['completed', 'cancelled']))
                ->exists()) {
            return 'masih ada delivery atau rencana delivery yang belum selesai';
        }

        if ($this->matchNumber(DB::table('delivery_pick_items as item'), 'item.part_number', $number)
            ->join('delivery_pick_sessions as session', 'session.id', '=', 'item.pick_session_id')
            ->whereIn('session.status', ['pending', 'scanning', 'blocked', 'approved'])->exists()
            || ExportPickingIssue::open()->where('part_setting_id', $id)->exists()) {
            return 'masih ada sesi picking atau scan issue yang terbuka';
        }

        if ($this->matchNumber(DB::table('stock_withdrawals as withdrawal'), 'withdrawal.part_number', $number)
            ->join('delivery_pick_sessions as session', 'session.id', '=', 'withdrawal.pick_session_id')
            ->whereNull('withdrawal.deleted_at')->where('withdrawal.status', 'completed')
            ->where('session.status', 'completed')->where('session.completion_status', 'completed')
            ->where('session.redo_until', '>=', now())->exists()) {
            return 'masih ada pengiriman Lokal yang dapat di-Redo';
        }

        if ($this->matchNumber(DB::table('not_full_box_requests'), 'part_number', $number)->where('status', 'pending')->exists()
            || DB::table('export_not_full_requests as request')->join('export_delivery_plans as delivery', 'delivery.id', '=', 'request.export_delivery_plan_id')
                ->where('request.part_setting_id', $id)->whereIn('request.status', ['waiting', 'prepared'])
                ->whereNull('delivery.deleted_at')->whereNotIn('delivery.status', ['completed', 'cancelled'])->exists()) {
            return 'masih ada permintaan Box Not Full yang belum selesai';
        }

        if (DB::table('export_receiving_batch_items as item')->join('export_receiving_batches as batch', 'batch.id', '=', 'item.export_receiving_batch_id')
            ->where('item.part_setting_id', $id)->where('batch.status', 'draft')->exists()) {
            return 'masih ada draft penerimaan Ekspor untuk part ini';
        }

        return null;
    }

    private function matchNumber(Builder|QueryBuilder $query, string $column, string $number): Builder|QueryBuilder
    {
        $query->where($column, $number);

        // Older transaction tables may use a different collation from Master Part.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $query->whereRaw("CAST({$column} AS BINARY) = ?", [$number]);
        }

        return $query;
    }
}
