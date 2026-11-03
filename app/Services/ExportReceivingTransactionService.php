<?php

namespace App\Services;

use App\Models\ExportPlannedPallet;
use App\Models\ExportReceivingBatch;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockBox;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ExportReceivingTransactionService
{
    public static function code(string $kind, int $id, ?int $palletId = null): string
    {
        $number = str_pad((string) $id, 6, '0', STR_PAD_LEFT);

        return match ($kind) {
            'batch' => 'TRX-'.$number,
            'recommendation' => 'FIFO-'.$number.'-'.str_pad((string) $palletId, 6, '0', STR_PAD_LEFT),
            default => 'BOX-'.$number,
        };
    }

    public function queue(string $sort = 'newest'): Builder
    {
        $physicalPending = "(box.state = 'received' OR (fulfillment.id IS NOT NULL AND fulfillment.placed_at IS NULL))";
        $queueReady = '(fulfillment.allocation_notice_id IS NULL OR EXISTS (SELECT 1 FROM export_stock_allocation_notices notice WHERE notice.id = fulfillment.allocation_notice_id AND notice.acknowledged_at IS NOT NULL))';
        $receiptPending = "($physicalPending AND fulfillment.allocation_notice_id IS NULL)";
        $emptyTarget = 'NULL as planned_pallet_id, NULL as plan_code, NULL as pallet_position, NULL as location_code, NULL as customer_name';
        $batches = DB::table('export_receiving_batches as batch')
            ->join('export_receiving_batch_items as item', 'item.export_receiving_batch_id', '=', 'batch.id')
            ->join('export_stock_boxes as box', 'box.id', '=', 'item.export_stock_box_id')
            ->leftJoin('export_fulfillment_boxes as fulfillment', 'fulfillment.export_stock_box_id', '=', 'box.id')
            ->where('batch.status', 'processed')->whereNull('box.deleted_at')
            ->selectRaw("'batch' as kind, batch.id as transaction_id, batch.processed_at as received_at, batch.created_by as actor_id")
            ->selectRaw('batch.created_at as created_at')
            ->selectRaw("COUNT(*) as total_boxes, SUM(CASE WHEN $receiptPending THEN 1 ELSE 0 END) as pending_boxes")
            ->selectRaw("SUM(CASE WHEN $physicalPending AND NOT $queueReady THEN 1 ELSE 0 END) as waiting_boxes")
            ->selectRaw("SUM(CASE WHEN $physicalPending AND fulfillment.allocation_notice_id IS NOT NULL AND $queueReady THEN 1 ELSE 0 END) as recommendation_boxes")
            ->selectRaw("MIN(CASE WHEN $receiptPending THEN box.first_received_at END) as fifo_at, MIN(CASE WHEN $receiptPending THEN box.id END) as fifo_id")
            ->selectRaw($emptyTarget)
            ->groupBy('batch.id', 'batch.processed_at', 'batch.created_by', 'batch.created_at')
            ->havingRaw('SUM(CASE WHEN '.$receiptPending.' THEN 1 ELSE 0 END) > 0');
        $legacy = DB::table('export_stock_boxes as box')
            ->leftJoin('export_fulfillment_boxes as fulfillment', 'fulfillment.export_stock_box_id', '=', 'box.id')
            ->whereNull('box.deleted_at')->whereRaw($receiptPending)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('export_receiving_batch_items as item')->whereColumn('item.export_stock_box_id', 'box.id'))
            ->selectRaw("'legacy' as kind, box.id as transaction_id, box.first_received_at as received_at, box.received_by as actor_id")
            ->selectRaw('box.created_at as created_at')
            ->selectRaw('1 as total_boxes, 1 as pending_boxes, 0 as waiting_boxes, 0 as recommendation_boxes, box.first_received_at as fifo_at, box.id as fifo_id')
            ->selectRaw($emptyTarget);
        // A recommendation transaction is identified by its review wave AND destination pallet.
        // This also groups existing notices without migrating or rewriting receipt history.
        $recommendations = DB::table('export_fulfillment_boxes as fulfillment')
            ->join('export_stock_boxes as box', 'box.id', '=', 'fulfillment.export_stock_box_id')
            ->join('export_stock_allocation_notices as notice', 'notice.id', '=', 'fulfillment.allocation_notice_id')
            ->join('export_planned_pallet_items as item', 'item.id', '=', 'fulfillment.export_planned_pallet_item_id')
            ->join('export_planned_pallets as pallet', 'pallet.id', '=', 'item.export_planned_pallet_id')
            ->join('export_pallet_plans as plan', 'plan.id', '=', 'pallet.export_pallet_plan_id')
            ->join('export_delivery_plans as delivery', 'delivery.id', '=', 'plan.export_delivery_plan_id')
            ->whereColumn('notice.export_pallet_plan_id', 'plan.id')
            ->whereNotNull('notice.acknowledged_at')->where('plan.status', 'final')
            ->whereColumn('plan.delivery_revision', 'delivery.revision')->whereNull('delivery.deleted_at')->whereNull('box.deleted_at')
            ->selectRaw("'recommendation' as kind, notice.id as transaction_id, notice.acknowledged_at as received_at, COALESCE(notice.acknowledged_by, notice.created_by) as actor_id")
            ->selectRaw('notice.created_at as created_at')
            ->selectRaw("COUNT(*) as total_boxes, SUM(CASE WHEN $physicalPending THEN 1 ELSE 0 END) as pending_boxes")
            ->selectRaw('0 as waiting_boxes, 0 as recommendation_boxes')
            ->selectRaw("MIN(CASE WHEN $physicalPending THEN box.first_received_at END) as fifo_at, MIN(CASE WHEN $physicalPending THEN box.id END) as fifo_id")
            ->selectRaw('pallet.id as planned_pallet_id, delivery.plan_code, pallet.position as pallet_position, pallet.location_code, delivery.customer_name')
            ->groupBy('notice.id', 'notice.acknowledged_at', 'notice.acknowledged_by', 'notice.created_by', 'notice.created_at',
                'pallet.id', 'pallet.position', 'pallet.location_code', 'delivery.plan_code', 'delivery.customer_name')
            ->havingRaw('SUM(CASE WHEN '.$physicalPending.' THEN 1 ELSE 0 END) > 0');

        return DB::query()->fromSub($batches->unionAll($legacy)->unionAll($recommendations), 'receipt')
            ->leftJoin('users as actor', 'actor.id', '=', 'receipt.actor_id')->select('receipt.*', 'actor.name as operator_name')
            ->orderBy('receipt.created_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('receipt.transaction_id', $sort === 'oldest' ? 'asc' : 'desc')
            ->orderBy('receipt.kind')->orderBy('receipt.pallet_position')->orderBy('receipt.planned_pallet_id');
    }

    public function batchBoxes(ExportReceivingBatch $batch)
    {
        return ExportStockBox::whereIn('id', $batch->items()->select('export_stock_box_id'))
            ->with(ExportStockViewService::RELATIONS)->orderBy('first_received_at')->orderBy('id')->get();
    }

    public function recommendationQuery(ExportStockAllocationNotice $notice, ExportPlannedPallet $pallet): EloquentBuilder
    {
        abort_unless((int) $notice->export_pallet_plan_id === (int) $pallet->export_pallet_plan_id, 404);

        return ExportStockBox::whereHas('fulfillment', fn ($q) => $q->where('allocation_notice_id', $notice->id)
            ->whereHas('plannedItem', fn ($item) => $item->where('export_planned_pallet_id', $pallet->id)));
    }

    public function recommendationBoxes(ExportStockAllocationNotice $notice, ExportPlannedPallet $pallet)
    {
        return $this->recommendationQuery($notice, $pallet)
            ->with(ExportStockViewService::RELATIONS)->orderBy('first_received_at')->orderBy('id')->get();
    }
}
