<?php

namespace App\Services;

use App\Models\{ExportReceivingBatch, ExportStockBox};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ExportReceivingTransactionService
{
    public static function code(string $kind, int $id): string
    {
        return ($kind === 'batch' ? 'TRX-' : 'BOX-').str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public function queue(): Builder
    {
        $physicalPending = "(box.state = 'received' OR (fulfillment.id IS NOT NULL AND fulfillment.placed_at IS NULL))";
        $queueReady = '(fulfillment.allocation_notice_id IS NULL OR EXISTS (SELECT 1 FROM export_stock_allocation_notices notice WHERE notice.id = fulfillment.allocation_notice_id AND notice.acknowledged_at IS NOT NULL))';
        $pending = "($physicalPending AND $queueReady)";
        $batches = DB::table('export_receiving_batches as batch')
            ->join('export_receiving_batch_items as item', 'item.export_receiving_batch_id', '=', 'batch.id')
            ->join('export_stock_boxes as box', 'box.id', '=', 'item.export_stock_box_id')
            ->leftJoin('export_fulfillment_boxes as fulfillment', 'fulfillment.export_stock_box_id', '=', 'box.id')
            ->where('batch.status', 'processed')->whereNull('box.deleted_at')
            ->selectRaw("'batch' as kind, batch.id as transaction_id, batch.processed_at as received_at, batch.created_by as actor_id")
            ->selectRaw("COUNT(*) as total_boxes, SUM(CASE WHEN $pending THEN 1 ELSE 0 END) as pending_boxes")
            ->selectRaw("SUM(CASE WHEN $physicalPending AND NOT $queueReady THEN 1 ELSE 0 END) as waiting_boxes")
            ->selectRaw("MIN(CASE WHEN $pending THEN box.first_received_at END) as fifo_at, MIN(CASE WHEN $pending THEN box.id END) as fifo_id")
            ->groupBy('batch.id', 'batch.processed_at', 'batch.created_by')->havingRaw('SUM(CASE WHEN '.$pending.' THEN 1 ELSE 0 END) > 0');
        $legacy = DB::table('export_stock_boxes as box')
            ->leftJoin('export_fulfillment_boxes as fulfillment', 'fulfillment.export_stock_box_id', '=', 'box.id')
            ->whereNull('box.deleted_at')->whereRaw($pending)->whereNotExists(fn ($query) => $query->selectRaw('1')->from('export_receiving_batch_items as item')
                ->whereColumn('item.export_stock_box_id', 'box.id'))
            ->selectRaw("'legacy' as kind, box.id as transaction_id, box.first_received_at as received_at, box.received_by as actor_id")
            ->selectRaw('1 as total_boxes, 1 as pending_boxes, 0 as waiting_boxes, box.first_received_at as fifo_at, box.id as fifo_id');
        return DB::query()->fromSub($batches->unionAll($legacy), 'receipt')
            ->leftJoin('users as actor', 'actor.id', '=', 'receipt.actor_id')
            ->select('receipt.*', 'actor.name as operator_name')->orderBy('receipt.fifo_at')->orderBy('receipt.fifo_id');
    }

    public function batchBoxes(ExportReceivingBatch $batch)
    {
        return ExportStockBox::whereIn('id', $batch->items()->select('export_stock_box_id'))
            ->with(ExportStockViewService::RELATIONS)->orderBy('first_received_at')->orderBy('id')->get();
    }
}
