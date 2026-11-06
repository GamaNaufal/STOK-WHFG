<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportPickingScan;
use App\Models\ExportStockBox;
use App\Models\User;
use App\Support\AuditLogPresenter;
use Illuminate\Support\Collection;

class ExportStockHistoryService
{
    public function events(ExportStockBox $box): Collection
    {
        $logs = AuditLog::where('model', 'ExportStockBox')->where('model_id', $box->id)
            ->whereIn('type', ['export_stock_receipt', 'export_stock_reservation', 'export_stock_admin', 'export_expired_box', 'export_not_full'])
            ->with('user')->get();

        $events = $logs->map(fn ($log) => [
            'at' => $log->created_at,
            'label' => AuditLogPresenter::actionLabel($log->action),
            'actor' => $log->user?->name,
            'description' => $log->description,
            'detail' => $this->auditDetail($log),
            'order' => $log->id,
            'priority' => $log->type === 'export_stock_receipt' ? 0 : 3,
        ]);

        if (! $logs->contains('type', 'export_stock_receipt')) {
            $sourceNumber = $box->source_box_id ? ExportStockBox::withTrashed()->whereKey($box->source_box_id)->value('box_number') : null;
            $origin = match ($box->origin) {
                'split' => 'Dibuat dari pemecahan Box Not Full.',
                'packing' => 'ID Box baru diterima dari packing.',
                default => $box->is_not_full ? 'Box Not Full dari luar disetujui.' : 'ID Box mulai tercatat pada stok ekspor.',
            };
            $events->push([
                'at' => $box->created_at ?? $box->first_received_at,
                'label' => 'Box tercatat',
                'actor' => $box->receiver?->name,
                'description' => $origin,
                'detail' => $sourceNumber ? 'ID Box sumber '.$sourceNumber : null,
                'order' => 0, 'priority' => 0,
            ]);
        }

        foreach ($box->movements()->with('actor')->get() as $move) {
            $events->push([
                'at' => $move->placed_at,
                'label' => $move->from_pallet_id ? 'Dipindahkan' : 'Penempatan awal',
                'actor' => $move->actor?->name,
                'description' => ($move->from_location_code ?? 'Belum ditempatkan').' → '.$move->to_location_code,
                'detail' => $move->to_pallet_id ? 'ID pallet tujuan EX-'.str_pad((string) $move->to_pallet_id, 6, '0', STR_PAD_LEFT) : null,
                'order' => $move->id, 'priority' => 2,
            ]);
        }

        foreach (ExportPickingScan::where('box_number', $box->box_number)->where('outcome', 'verified')->with('actor')->get() as $scan) {
            $events->push([
                'at' => $scan->created_at, 'label' => 'Picking terverifikasi', 'actor' => $scan->actor?->name,
                'description' => 'ID Box dikonfirmasi pada sesi picking ekspor.', 'detail' => null,
                'order' => $scan->id, 'priority' => 1,
            ]);
        }

        if ($box->state === 'shipped') {
            $plan = $box->fulfillment()->with('plannedItem.pallet.palletPlan')->first()?->plannedItem?->pallet?->palletPlan;
            $delivery = $plan ? ExportDeliveryPlan::withTrashed()->find($plan->export_delivery_plan_id) : null;
            $events->push([
                'at' => $plan?->completed_at ?? $box->deleted_at,
                'label' => 'Keluar gudang',
                'actor' => $plan?->completed_by ? User::find($plan->completed_by)?->name : null,
                'description' => 'Pengiriman ekspor dikonfirmasi selesai.',
                'detail' => $delivery ? 'Delivery '.$delivery->plan_code.' · '.$delivery->customer_name : null,
                'order' => 0, 'priority' => 4,
            ]);
        }

        return $events->sortByDesc(fn ($event) => [$event['at']?->format('Y-m-d H:i:s.u'), $event['priority'], $event['order']])->values();
    }

    private function auditDetail(AuditLog $log): ?string
    {
        $before = $log->getOldValuesArray();
        $after = $log->getNewValuesArray();
        $details = [];
        foreach (['part_number' => 'Part', 'pcs_quantity' => 'PCS', 'first_received_at' => 'Waktu masuk'] as $key => $label) {
            if (isset($before[$key], $after[$key]) && $before[$key] !== $after[$key]) {
                $details[] = $label.' '.$before[$key].' → '.$after[$key];
            }
        }
        if (! empty($before['warehouse_location'])) {
            $details[] = 'Lokasi '.$before['warehouse_location'];
        }
        if (! empty($after['recommendation_transaction'])) {
            $details[] = 'Transaksi '.$after['recommendation_transaction'];
        }
        if ($newBox = $after['new_box_number'] ?? $after['replacement_box_number'] ?? null) {
            $details[] = 'ID Box hasil '.$newBox;
        }
        if (is_string($after['reason'] ?? null) && $after['reason'] !== '') {
            $details[] = 'Alasan '.$after['reason'];
        }

        return $details ? implode(' · ', $details) : null;
    }
}
