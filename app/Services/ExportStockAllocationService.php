<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportPalletPlan;
use App\Models\ExportStockBox;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExportStockAllocationService
{
    /** Caller holds ExportInventoryService's lock. Existing commitments are retained. */
    public function allocate(User $actor, ?array $partIds = null): int
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
            if (!$plan || $plan->delivery_revision !== $delivery->revision) continue;
            foreach ($plan->pallets as $pallet) foreach ($pallet->items as $item) {
                if ($partIds !== null && !in_array($item->part_setting_id, $partIds, true)) continue;
                $remaining = $item->box_quantity - $item->fulfillment_boxes_count;
                if ($remaining <= 0) continue;
                $boxes = ExportStockBox::where('part_setting_id', $item->part_setting_id)
                    ->where('pcs_quantity', $item->pcs_per_box)->whereIn('state', ['received', 'stored'])
                    ->whereDoesntHave('fulfillment')->with('currentPallet')
                    ->orderBy('first_received_at')->orderBy('id')->limit($remaining)->lockForUpdate()->get();
                if ($boxes->isEmpty()) continue;
                $claim = $plan->reservations->firstWhere('export_planned_pallet_id', $pallet->id);
                if (!$pallet->location || !$claim || $claim->master_location_id !== $pallet->master_location_id) {
                    throw ValidationException::withMessages(['allocation' => 'Reservasi lokasi pallet tidak sesuai. Periksa perencanaan sebelum menerima box.']);
                }
                foreach ($boxes as $box) {
                    if (($box->state === 'stored' && (!$box->currentPallet || $box->currentPallet->retired_at || !$box->stored_at || !$box->stored_by))
                        || ($box->state === 'received' && $box->current_pallet_id !== null)) {
                        throw ValidationException::withMessages(['allocation' => 'Penempatan stok ekspor tidak konsisten. Periksa box '.$box->box_number.'.']);
                    }
                    $alreadyThere = $box->state === 'stored'
                        && (int) $box->currentPallet->export_planned_pallet_id === $pallet->id
                        && (int) $box->currentPallet->master_location_id === $pallet->master_location_id;
                    $commitment = ExportFulfillmentBox::create([
                        'export_stock_box_id' => $box->id, 'box_number' => $box->box_number,
                        'export_planned_pallet_item_id' => $item->id, 'part_setting_id' => $box->part_setting_id,
                        'pcs_quantity' => $box->pcs_quantity, 'first_received_at' => $box->first_received_at,
                        'reserved_at' => now(), 'reserved_by' => $actor->id,
                        'placed_at' => $alreadyThere ? $box->stored_at : null,
                        'placed_by' => $alreadyThere ? $box->stored_by : null,
                        'placed_location_id' => $alreadyThere ? $pallet->master_location_id : null,
                    ]);
                    $box->increment('revision');
                    AuditService::record('export_stock_reservation', 'reserved', 'ExportStockBox', $box->id, $actor->id,
                        [], ['box_number' => $box->box_number, 'commitment_id' => $commitment->id,
                            'delivery_id' => $delivery->id, 'planned_pallet_id' => $pallet->id,
                            'first_received_at' => $box->first_received_at->toDateTimeString(), 'already_at_target' => $alreadyThere],
                        'Box ekspor direservasi sesuai tenggat, nomor pallet, dan FIFO.');
                    $allocated++;
                }
            }
        }
        return $allocated;
    }
}
