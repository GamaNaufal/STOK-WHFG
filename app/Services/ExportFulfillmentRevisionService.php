<?php

namespace App\Services;

use App\Models\{ExportDeliveryPlan, ExportMasterLocation, ExportPalletLocationReservation, ExportPlannedPallet, ExportPickingIssue, ExportStockPallet, User};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Partial fulfillment may be revised; shipment readiness still requires exact physical contents. */
class ExportFulfillmentRevisionService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['fulfillment' => $message]);
    }

    public function contents(ExportPlannedPallet $pallet): array
    {
        $items = $pallet->items()->get();
        $commitments = $pallet->fulfillmentBoxes()->with('stockBox.currentPallet')->get();
        $physical = ExportStockPallet::where('export_planned_pallet_id', $pallet->id)->whereNull('retired_at')->first();
        $location = ExportMasterLocation::find($pallet->master_location_id);
        if (!$location || $location->current_pallet_id || $location->stockLocations()->exists()
            || !ExportPalletLocationReservation::where('master_location_id', $location->id)->where('export_planned_pallet_id', $pallet->id)->exists()
            || ($physical && ((int) $physical->master_location_id !== $location->id || !$location->is_occupied))
            || (!$physical && ($location->is_occupied || $location->stockPallet()->exists()))) {
            $this->fail('Lokasi fisik dan reservasi pallet tidak konsisten. Periksa stok ekspor.');
        }
        foreach ($items as $item) {
            $rows = $commitments->where('export_planned_pallet_item_id', $item->id);
            if ($rows->count() > $item->box_quantity) $this->fail('Reservasi melebihi kebutuhan. Periksa pemenuhan sebelum merevisi.');
            foreach ($rows as $f) {
                $box = $f->stockBox;
                if (!$box || $box->box_number !== $f->box_number || (int) $box->part_setting_id !== $item->part_setting_id
                    || (int) $f->part_setting_id !== $item->part_setting_id || $box->pcs_quantity !== $item->pcs_per_box || $f->pcs_quantity !== $item->pcs_per_box) {
                    $this->fail('Identitas, part atau PCS reservasi tidak sesuai. Periksa stok sebelum merevisi.');
                }
                if ($f->placed_at) {
                    if (!$physical || !$f->placed_by || (int) $f->placed_location_id !== $location->id
                        || $box->state !== 'stored' || (int) $box->current_pallet_id !== $physical->id || !$box->stored_at || !$box->stored_by) {
                        $this->fail('Posisi box yang sudah ditempatkan tidak sesuai pallet permintaan.');
                    }
                } elseif ($f->placed_by || $f->placed_location_id || !in_array($box->state, ['received', 'stored'], true)
                    || ($box->state === 'received' && $box->current_pallet_id)
                    || ($box->state === 'stored' && (!$box->currentPallet || $box->currentPallet->retired_at || !$box->stored_at || !$box->stored_by
                        || ($physical && (int) $box->current_pallet_id === $physical->id)))) {
                    $this->fail('Posisi reservasi yang belum ditempatkan tidak konsisten.');
                }
            }
        }
        if ($physical && $physical->boxes()->count() !== $commitments->whereNotNull('placed_at')->count()) {
            $this->fail('Pallet berisi box di luar permintaan. Pindahkan stok tambahan sebelum revisi.');
        }
        if (ExportPickingIssue::open()->where(fn ($q) => $q->where('export_planned_pallet_id', $pallet->id)
            ->orWhereIn('box_number', $commitments->pluck('box_number')))->exists()) $this->fail('Selesaikan issue picking dan pengembaliannya sebelum revisi.');
        return [$physical, $items, $commitments];
    }

    public function preview(ExportPlannedPallet $pallet, array $quantities): array
    {
        [$physical, $items, $commitments] = $this->contents($pallet);
        $known = $items->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sent = array_map('intval', array_keys($quantities)); sort($sent);
        if ($known !== $sent) $this->fail('Rincian part berubah. Muat ulang revisi.');
        $selected = collect(); $difference = 0;
        foreach ($items as $item) {
            $quantity = (int) $quantities[$item->id];
            if ($quantity < 0 || $quantity > $item->box_quantity) $this->fail('Revisi ini hanya untuk mengurangi box, bukan menambah kebutuhan.');
            $difference += $item->box_quantity - $quantity;
            $rows = $commitments->where('export_planned_pallet_item_id', $item->id)
                ->sortByDesc(fn ($f) => [$f->first_received_at->format('Y-m-d H:i:s.u'), $f->export_stock_box_id]);
            $selected = $selected->concat($rows->take(max(0, $rows->count() - $quantity)));
        }
        if ($difference < 1) $this->fail('Kurangi minimal satu box dari kebutuhan pallet.');
        // A verified pick may already be in the operator's hands even before placement.
        $returns = $selected->filter(fn ($f) => $f->placed_at || $f->picking_verified_at)->values();
        $released = $selected->reject(fn ($f) => $f->placed_at || $f->picking_verified_at)->values();
        $fingerprint = hash('sha256', json_encode([$pallet->id, $quantities,
            $items->map(fn ($i) => [$i->id, $i->box_quantity])->all(),
            $commitments->map(fn ($f) => [$f->id, $f->placed_at?->toJSON(), $f->picking_verified_at?->toJSON(),
                $f->stockBox->revision, $f->stockBox->state, $f->stockBox->current_pallet_id])->all(),
        ], JSON_THROW_ON_ERROR));
        return compact('physical', 'items', 'selected', 'returns', 'released', 'fingerprint', 'quantities', 'difference');
    }

    /** Caller holds the inventory mutex, validates versions/fingerprint and records the action token. */
    public function apply(ExportDeliveryPlan $delivery, ExportPlannedPallet $pallet, array $preview, ?string $destination, User $actor, array &$reusable): array
    {
        $dest = explode(':', $destination ?? '', 2);
        if ($preview['returns']->isNotEmpty()) {
            if (!in_array($dest[0], ['pallet', 'location'], true) || !ctype_digit($dest[1] ?? '')) $this->fail('Pilih pallet existing atau lokasi kosong untuk pengembalian.');
            if ($dest[0] === 'pallet' && (int) $dest[1] === $preview['physical']?->id) $this->fail('Box yang dikurangi harus dipindahkan dari pallet permintaan ini.');
        }
        $pallet->update(['ready_at' => null, 'ready_by' => null, 'fulfillment_revision' => $pallet->fulfillment_revision + 1]);
        $returned = []; $released = [];
        foreach ($preview['selected'] as $f) {
            $box = $f->stockBox;
            $row = ['box_number' => $box->box_number, 'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity,
                'first_received_at' => $box->first_received_at->toDateTimeString(),
                'from_location' => $box->currentPallet?->location_code, 'pallet_number' => $pallet->position];
            $f->delete();
            if ($preview['returns']->contains('id', $f->id)) {
                if (!$f->placed_at && $dest[0] === 'pallet' && (int) $dest[1] === (int) $box->current_pallet_id) {
                    // The stored position is still the source while a picked box is in transit.
                    app(ExportFulfillmentCompletionService::class)->assertPalletUnlocked($box->currentPallet);
                    $box->increment('revision');
                    $moved = $box->fresh('currentPallet');
                } else {
                    $placement = ['revision' => $box->revision, 'request_token' => (string) Str::uuid(), $dest[0].'_id' => (int) $dest[1]];
                    $moved = app(ExportStockReceivingService::class)->place($box->id, $placement, $actor, $reusable);
                }
                if ($dest[0] === 'location') $reusable[(int) $dest[1]] = $moved->current_pallet_id;
                $returned[] = $row + ['warehouse_location' => $moved->currentPallet->location_code];
            } else {
                $box->increment('revision');
                $released[] = $row + ['warehouse_location' => $box->currentPallet?->location_code];
            }
        }
        foreach ($preview['items'] as $item) {
            $quantity = (int) $preview['quantities'][$item->id];
            $difference = $item->box_quantity - $quantity;
            $line = $delivery->items()->where('part_setting_id', $item->part_setting_id)->lockForUpdate()->first();
            if (!$line || $line->box_quantity < $difference) $this->fail('Jumlah delivery tidak sesuai dengan perencanaan.');
            $next = $line->box_quantity - $difference;
            $line->update(['box_quantity' => $next, 'pcs_quantity' => $next * $line->pcs_per_box]);
            if ($quantity === 0) $item->delete(); else $item->update(['box_quantity' => $quantity]);
        }
        if (!$pallet->items()->exists()) {
            $physical = $preview['physical']?->fresh();
            if ($physical?->boxes()->exists()) $this->fail('Pallet masih berisi stok. Konfirmasikan pengembalian seluruh box.');
            if ($physical) {
                $physical->update(['master_location_id' => null, 'export_planned_pallet_id' => null, 'retired_at' => now()]);
                ExportMasterLocation::whereKey($pallet->master_location_id)->update(['is_occupied' => false]);
            }
            ExportPalletLocationReservation::where('export_planned_pallet_id', $pallet->id)->delete();
            $pallet->delete();
        }
        $delivery->load('items');
        $delivery->update(['total_boxes' => $delivery->items->sum('box_quantity'), 'total_pcs' => $delivery->items->sum('pcs_quantity'), 'updated_by' => $actor->id]);
        return ['boxes' => $returned, 'released_boxes' => $released,
            'returned_box_quantity' => count($returned), 'returned_pcs_quantity' => array_sum(array_column($returned, 'pcs_quantity'))];
    }
}
