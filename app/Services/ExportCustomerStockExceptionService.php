<?php

namespace App\Services;

use App\Models\{ExportCustomerStockException, ExportDeliveryPlan, ExportFulfillmentBox, ExportPlannedPalletItem, ExportStockBox, User};
use Illuminate\Validation\ValidationException;

class ExportCustomerStockExceptionService
{
    private function fail(string $message): never { throw ValidationException::withMessages(['exception' => $message]); }

    public function context(int $deliveryId, int $itemId): array
    {
        $delivery = ExportDeliveryPlan::findOrFail($deliveryId);
        $plan = $delivery->palletPlan;
        $item = ExportPlannedPalletItem::with('pallet')->findOrFail($itemId);
        if (! $plan?->isFinalFor($delivery) || $plan->completed_at || $item->pallet->export_pallet_plan_id !== $plan->id || $item->pallet->ready_at) $this->fail('Pilih item pallet final yang belum siap dari delivery ini.');
        if (! app(ExportCustomerStockPolicy::class)->customerId($delivery)) $this->fail('Petakan customer delivery terlebih dahulu sebelum menggunakan pengecualian.');
        return [$delivery, $plan, $item];
    }

    public function sourceQuery(ExportPlannedPalletItem $item, int $sourceId)
    {
        return app(ExportStockQueryService::class)->freeBoxes(ExportStockBox::query())
            ->where('part_setting_id', $item->part_setting_id)->where('pcs_quantity', $item->pcs_per_box)
            ->whereHas('customerReservation', fn ($q) => $q->where('export_customer_id', $sourceId))
            ->whereNotIn('export_stock_boxes.id', \App\Models\ExportNotFullRequest::where('status', 'prepared')->whereNotNull('preparation->source_box_id')->select('preparation->source_box_id'))
            ->whereDoesntHave('currentPallet.boxes.fulfillment', fn ($q) => $q->whereNotNull('picking_started_at')->whereNull('placed_at'))
            ->with('currentPallet', 'customerReservation.customer')
            ->orderBy('first_received_at')->orderBy('id');
    }

    public function preview(int $deliveryId, int $itemId, ?int $sourceId = null): array
    {
        [$delivery, $plan, $item] = $this->context($deliveryId, $itemId);
        $remaining = max(0, $item->box_quantity - $item->fulfillmentBoxes()->count());
        $normal = app(ExportStockAllocationService::class)->revisionCandidates($item->pallet, $item->part_setting_id, $item->pcs_per_box, $remaining);
        $shortage = max(0, $remaining - $normal->count());
        $sources = app(ExportCustomerService::class)->options()->where('id', '!=', app(ExportCustomerStockPolicy::class)->customerId($delivery));
        $sourceId ??= $sources->first()?->id;
        $boxes = $sourceId ? $this->sourceQuery($item, $sourceId)->limit(100)->get() : collect();
        $active = ExportCustomerStockException::where('export_delivery_plan_id', $deliveryId)->where('export_planned_pallet_item_id', $itemId)->where('status', 'active')->get();
        return compact('delivery', 'plan', 'item', 'remaining', 'normal', 'shortage', 'sources', 'sourceId', 'boxes', 'active');
    }

    public function create(int $deliveryId, array $data, User $actor): array
    {
        abort_unless($actor->canAccess('export_stock_customer_exception'), 403);
        foreach (array_keys($data['boxes']) as $key) if (! preg_match('/^[1-9][0-9]*$/D', (string) $key)) $this->fail('ID stok pada pilihan tidak valid.');
        return app(ExportCustomerReservationService::class)->request($data['request_token'], 'exception_create', ['delivery_id' => $deliveryId] + $data, $actor, function () use ($deliveryId, $data, $actor) {
            [$delivery, $plan, $item] = $this->context($deliveryId, (int) $data['item_id']);
            if ($delivery->revision !== (int) $data['delivery_revision'] || $plan->revision !== (int) $data['plan_revision'] || $item->pallet->fulfillment_revision !== (int) $data['pallet_revision']) $this->fail('Rencana berubah, tinjau ulang pengecualian.');
            $sourceId = (int) $data['source_customer_id'];
            app(ExportCustomerService::class)->active($sourceId);
            if ($sourceId === app(ExportCustomerStockPolicy::class)->customerId($delivery)) $this->fail('Customer sumber harus berbeda dari customer delivery.');
            if (trim($data['reason'] ?? '') === '') $this->fail('Alasan penggunaan stok customer lain wajib diisi.');
            $previousNotice = \App\Models\ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->where('plan_revision', $plan->revision)->lockForUpdate()->first();
            $plan->increment('revision');
            if ($previousNotice && ! $previousNotice->acknowledged_at) {
                // Preserve pending membership, but reject an ACK from the old review revision.
                $previousNotice->update(['plan_revision' => $plan->revision]);
                $notice = $previousNotice;
            } else {
                $notice = app(ExportStockAllocationNoticeService::class)->create($plan, $actor);
            }
            // Consume normal stock first under the same inventory lock; never borrow beyond a current shortage.
            app(ExportStockAllocationService::class)->allocate($actor, [(int) $item->part_setting_id], $notice);
            $shortage = max(0, $item->box_quantity - $item->fulfillmentBoxes()->count());
            $ids = array_map('intval', array_keys($data['boxes']));
            if (! $ids || count($ids) > $shortage) $this->fail('Jumlah box pengecualian melebihi kekurangan terkini. Tinjau ulang stok sendiri dan umum.');
            $boxes = $this->sourceQuery($item, $sourceId)->limit(count($ids))->lockForUpdate()->get();
            if ($boxes->count() !== count($ids) || $boxes->pluck('id')->diff($ids)->isNotEmpty()) $this->fail('Stok sumber berubah atau melewati FIFO. Pilih box tertua yang tersedia.');
            foreach ($boxes as $box) {
                app(ExportCustomerReservationService::class)->assertEditable($box);
                if ($box->revision !== (int) ($data['boxes'][$box->id] ?? -1)) $this->fail('Box sumber berubah, muat ulang tinjauan.');
                $snapshot = ['box_number' => $box->box_number, 'identity_cycle' => $box->identity_cycle, 'owner_customer_id' => $sourceId,
                    'owner_customer_name' => $box->customer_owner_label, 'recipient_customer_id' => app(ExportCustomerStockPolicy::class)->customerId($delivery),
                    'recipient_customer_name' => $delivery->customer_name, 'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity];
                $exception = ExportCustomerStockException::updateOrCreate(['export_stock_box_id' => $box->id], ['export_customer_id' => $sourceId,
                    'export_delivery_plan_id' => $delivery->id, 'export_planned_pallet_item_id' => $item->id, 'fulfillment_id' => null,
                    'identity_cycle' => $box->identity_cycle, 'status' => 'active', 'revision' => ((int) ExportCustomerStockException::where('export_stock_box_id', $box->id)->value('revision')) + 1,
                    'authorized_by' => $actor->id, 'reason' => trim($data['reason']), 'snapshot' => $snapshot]);
                app(ExportStockAllocationService::class)->reserve($delivery, $item->pallet, $item, $box, $actor, $notice);
                app(ExportCustomerReservationService::class)->event($box, 'exception_created', $actor->id, [], $exception->fresh()->toArray(), $data['reason']);
            }
            return ['count' => $boxes->count(), 'notice_id' => $notice->id];
        });
    }

    public function cancel(int $deliveryId, int $id, array $data, User $actor): array
    {
        abort_unless($actor->canAccess('export_stock_customer_exception'), 403);
        return app(ExportCustomerReservationService::class)->request($data['request_token'], 'exception_cancel', ['delivery_id' => $deliveryId, 'exception_id' => $id] + $data, $actor, function () use ($deliveryId, $id, $data, $actor) {
            $exception = ExportCustomerStockException::where('export_delivery_plan_id', $deliveryId)->lockForUpdate()->findOrFail($id);
            if ($exception->status !== 'active' || $exception->revision !== (int) ($data['revision'] ?? 0)) $this->fail('Pengecualian berubah, selesai atau dibatalkan. Muat ulang sebelum melanjutkan.');
            [$delivery, $plan, $item] = $this->context($deliveryId, $exception->export_planned_pallet_item_id);
            $f = ExportFulfillmentBox::lockForUpdate()->findOrFail($exception->fulfillment_id);
            $box = ExportStockBox::lockForUpdate()->findOrFail($exception->export_stock_box_id);
            if ($f->placed_at || $f->picking_started_at) $this->fail('Box sudah diproses. Batalkan melalui revisi delivery dan konfirmasi pengembalian fisik.');
            app(ExportFulfillmentCompletionService::class)->assertPalletUnlocked($box->currentPallet);
            $f->delete();
            $box->increment('revision');
            app(DeliveryAnnouncementService::class)->sync($delivery);
            return ['count' => 1];
        });
    }
}
