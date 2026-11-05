<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportPalletPlan;
use App\Models\ExportPickingIssue;
use App\Models\ExportPlannedPallet;
use App\Models\ExportPlannedPalletItem;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExportStockAllocationService
{
    /** Caller holds ExportInventoryService's lock. Existing commitments are retained. */
    public function allocate(User $actor, ?array $partIds = null, ?ExportStockAllocationNotice $notice = null): int
    {
        $deliveries = ExportDeliveryPlan::whereHas('palletPlan', fn ($q) => $q->where('status', 'final'))
            ->orderBy('id')->lockForUpdate()->get();
        $plans = ExportPalletPlan::whereIn('export_delivery_plan_id', $deliveries->modelKeys())
            ->where('status', 'final')->with(['reservations', 'pallets.location',
                'pallets.items' => fn ($q) => $q->withCount('fulfillmentBoxes')])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('export_delivery_plan_id');
        $allocated = 0;
        foreach ($deliveries->sortBy(fn ($d) => [$d->delivery_deadline->format('Y-m-d'), $d->id]) as $delivery) {
            $plan = $plans->get($delivery->id);
            if (! $plan || $plan->delivery_revision !== $delivery->revision) {
                continue;
            }
            foreach ($plan->pallets as $pallet) {
                foreach ($pallet->items as $item) {
                    if ($pallet->ready_at) {
                        continue;
                    }
                    if ($partIds !== null && ! in_array($item->part_setting_id, $partIds, true)) {
                        continue;
                    }
                    $remaining = $item->box_quantity - $item->fulfillment_boxes_count;
                    if ($remaining <= 0) {
                        continue;
                    }
                    $boxes = $this->availableBoxes($item->part_setting_id, $item->pcs_per_box)
                        ->limit($remaining)->lockForUpdate()->get();
                    if ($boxes->isEmpty()) {
                        continue;
                    }
                    $claim = $plan->reservations->firstWhere('export_planned_pallet_id', $pallet->id);
                    if (! $pallet->location || ! $claim || $claim->master_location_id !== $pallet->master_location_id) {
                        throw ValidationException::withMessages(['allocation' => 'Reservasi lokasi pallet tidak sesuai. Periksa perencanaan sebelum menerima box.']);
                    }
                    foreach ($boxes as $box) {
                        $this->reserve($delivery, $pallet, $item, $box, $actor, $notice);
                        $allocated++;
                    }
                }
            }
        }

        if ($notice) {
            $this->persistPickingRequirements($notice);
        }

        return $allocated;
    }

    private function availableBoxes(int $partId, int $pcs): Builder
    {
        return ExportStockBox::where('part_setting_id', $partId)->where('pcs_quantity', $pcs)->eligible()
            ->whereDoesntHave('fulfillment')->whereNotIn('box_number', ExportPickingIssue::open()->select('box_number'))
            ->with('currentPallet')->orderBy('first_received_at')->orderBy('id');
    }

    /** Preview only the added need. Leave FIFO stock for earlier unmet demand. */
    public function revisionCandidates(ExportPlannedPallet $pallet, int $partId, int $pcs, int $count): Collection
    {
        $plan = $pallet->palletPlan;
        $delivery = $plan->deliveryPlan;
        $deadline = $delivery->delivery_deadline->format('Y-m-d');
        $prior = ExportPlannedPalletItem::where('part_setting_id', $partId)->where('pcs_per_box', $pcs)
            ->whereHas('pallet', function ($row) use ($pallet, $plan, $delivery, $deadline) {
                $row->whereNull('ready_at')->where(function ($priority) use ($pallet, $plan, $delivery, $deadline) {
                    $priority->where(fn ($same) => $same->where('export_pallet_plan_id', $plan->id)->where('position', '<', $pallet->position))
                        ->orWhereHas('palletPlan', fn ($other) => $other->where('status', 'final')->whereHas('deliveryPlan',
                            fn ($d) => $d->whereColumn('export_delivery_plans.revision', 'export_pallet_plans.delivery_revision')
                                ->where(fn ($order) => $order->where('delivery_deadline', '<', $deadline)
                                    ->orWhere(fn ($tie) => $tie->where('delivery_deadline', $deadline)->where('id', '<', $delivery->id)))));
                });
            })
            ->withCount('fulfillmentBoxes')->get()
            ->sum(fn ($row) => max(0, $row->box_quantity - $row->fulfillment_boxes_count));

        return $this->availableBoxes($partId, $pcs)
            ->whereDoesntHave('currentPallet', fn ($source) => $source->whereNotNull('export_planned_pallet_id'))
            ->offset($prior)->limit($count)->get();
    }

    /** Commit the already validated preview while holding the inventory mutex. */
    public function reserveRevision(ExportDeliveryPlan $delivery, ExportPlannedPallet $pallet, array $changes, User $actor, ExportStockAllocationNotice $notice): void
    {
        $items = $pallet->items()->get()->keyBy(fn ($item) => ExportBoxRequirements::key($item->part_setting_id, $item->pcs_per_box));
        foreach ($changes as $key => $change) {
            foreach ($change['candidates'] as $box) {
                $this->reserve($delivery, $pallet, $items->get($key), $box, $actor, $notice);
            }
        }
        $this->persistPickingRequirements($notice);
    }

    private function persistPickingRequirements(ExportStockAllocationNotice $notice): void
    {
        // Keep the requirement even if another destination is revised after allocation.
        $requiredIds = $notice->boxes()->whereNull('placed_at')->requiresPickingScan()->pluck('id');
        ExportFulfillmentBox::whereKey($requiredIds->all())->update(['picking_required' => true]);
    }

    private function reserve(ExportDeliveryPlan $delivery, ExportPlannedPallet $pallet, ExportPlannedPalletItem $item, ExportStockBox $box, User $actor, ?ExportStockAllocationNotice $notice): void
    {
        if (($box->state === 'stored' && (! $box->currentPallet || $box->currentPallet->retired_at || ! $box->stored_at || ! $box->stored_by))
            || ($box->state === 'received' && $box->current_pallet_id !== null)) {
            throw ValidationException::withMessages(['allocation' => 'Penempatan stok ekspor tidak konsisten. Periksa box '.$box->box_number.'.']);
        }
        $alreadyThere = $box->state === 'stored'
            && (int) $box->currentPallet->export_planned_pallet_id === $pallet->id
            && (int) $box->currentPallet->master_location_id === $pallet->master_location_id;
        $noticeId = ! $alreadyThere && (int) $notice?->export_pallet_plan_id === (int) $pallet->export_pallet_plan_id
            ? $notice?->id : null;
        $commitment = ExportFulfillmentBox::create([
            'allocation_notice_id' => $noticeId,
            'export_stock_box_id' => $box->id, 'box_number' => $box->box_number,
            'export_planned_pallet_item_id' => $item->id, 'part_setting_id' => $box->part_setting_id,
            'pcs_quantity' => $box->pcs_quantity, 'first_received_at' => $box->first_received_at,
            'reserved_at' => now(), 'reserved_by' => $actor->id,
            'placed_at' => $alreadyThere ? $box->stored_at : null,
            'placed_by' => $alreadyThere ? $box->stored_by : null,
            'placed_location_id' => $alreadyThere ? $pallet->master_location_id : null,
            'picking_required' => $noticeId && $box->current_pallet_id && ExportStockPallet::whereKey($box->current_pallet_id)->mixed()->exists(),
        ]);
        $box->increment('revision');
        AuditService::record('export_stock_reservation', 'reserved', 'ExportStockBox', $box->id, $actor->id,
            [], ['box_number' => $box->box_number, 'commitment_id' => $commitment->id,
                'delivery_id' => $delivery->id, 'planned_pallet_id' => $pallet->id,
                'allocation_notice_id' => $commitment->allocation_notice_id,
                'recommendation_transaction' => $commitment->allocation_notice_id ? ExportReceivingTransactionService::code('recommendation', $commitment->allocation_notice_id, $pallet->id) : null,
                'first_received_at' => $box->first_received_at->toDateTimeString(), 'already_at_target' => $alreadyThere],
            'Box ekspor direservasi sesuai tenggat, nomor pallet, dan FIFO.');
    }
}
