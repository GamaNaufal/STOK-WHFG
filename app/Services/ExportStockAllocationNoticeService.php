<?php

namespace App\Services;

use App\Models\{ExportDeliveryPlan, ExportPalletPlan, ExportStockAllocationNotice, User};
use Illuminate\Validation\ValidationException;

class ExportStockAllocationNoticeService
{
    /** Called only within the finalization inventory transaction. */
    public function create(ExportPalletPlan $plan, User $actor): ExportStockAllocationNotice
    {
        $notice = ExportStockAllocationNotice::create(['export_pallet_plan_id' => $plan->id,
            'plan_revision' => $plan->revision, 'created_by' => $actor->id]);
        AuditService::record('export_allocation_notice', 'created', 'ExportStockAllocationNotice', $notice->id, $actor->id,
            [], ['plan_id' => $plan->id, 'plan_revision' => $plan->revision], 'Hasil pencarian stok FIFO menunggu tinjauan operator.');
        return $notice;
    }

    public function acknowledge(int $deliveryId, int $noticeId, int $revision, User $actor): ExportStockAllocationNotice
    {
        return app(ExportInventoryService::class)->transaction(function () use ($deliveryId, $noticeId, $revision, $actor) {
            $delivery = ExportDeliveryPlan::whereKey($deliveryId)->lockForUpdate()->firstOrFail();
            $plan = ExportPalletPlan::where('export_delivery_plan_id', $delivery->id)->lockForUpdate()->firstOrFail();
            $notice = ExportStockAllocationNotice::whereKey($noticeId)->where('export_pallet_plan_id', $plan->id)->lockForUpdate()->firstOrFail();
            if (!$plan->isFinalFor($delivery) || $plan->revision !== $revision || $notice->plan_revision !== $plan->revision) {
                throw ValidationException::withMessages(['allocation' => 'Perencanaan sudah berubah. Buka dan tinjau hasil FIFO terbaru.']);
            }
            if ($notice->acknowledged_at) return $notice;
            $notice->update(['acknowledged_at' => now(), 'acknowledged_by' => $actor->id]);
            AuditService::record('export_allocation_notice', 'acknowledged', 'ExportStockAllocationNotice', $notice->id, $actor->id,
                ['acknowledged_at' => null], ['plan_id' => $plan->id, 'plan_revision' => $plan->revision,
                    'box_count' => $notice->boxes()->whereNull('placed_at')->count()], 'Operator meninjau stok FIFO dan mengaktifkan tugas pada antrean.');
            return $notice;
        });
    }
}
