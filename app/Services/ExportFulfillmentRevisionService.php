<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Models\ExportPalletLocationReservation;
use App\Models\ExportPickingIssue;
use App\Models\ExportPlannedPallet;
use App\Models\ExportStockAllocationNotice;
use App\Models\ExportStockPallet;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Partial fulfillment may be revised; shipment readiness still requires exact physical contents. */
class ExportFulfillmentRevisionService
{
    public const MAX_QUANTITY = 4294967295;

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
        if (! $location || $location->current_pallet_id || $location->stockLocations()->exists()
            || ! ExportPalletLocationReservation::where('master_location_id', $location->id)->where('export_planned_pallet_id', $pallet->id)->exists()
            || ($physical && ((int) $physical->master_location_id !== $location->id || ! $location->is_occupied))
            || (! $physical && ($location->is_occupied || $location->stockPallet()->exists()))) {
            $this->fail('Lokasi fisik dan reservasi pallet tidak konsisten. Periksa stok ekspor.');
        }
        foreach ($items as $item) {
            $rows = $commitments->where('export_planned_pallet_item_id', $item->id);
            if ($rows->count() > $item->box_quantity) {
                $this->fail('Reservasi melebihi kebutuhan. Periksa pemenuhan sebelum merevisi.');
            }
            foreach ($rows as $f) {
                $box = $f->stockBox;
                if (! $box || $box->box_number !== $f->box_number || (int) $box->part_setting_id !== $item->part_setting_id
                    || (int) $f->part_setting_id !== $item->part_setting_id || $box->pcs_quantity !== $item->pcs_per_box || $f->pcs_quantity !== $item->pcs_per_box) {
                    $this->fail('Identitas, part atau PCS reservasi tidak sesuai. Periksa stok sebelum merevisi.');
                }
                if ($f->placed_at) {
                    if (! $physical || ! $f->placed_by || (int) $f->placed_location_id !== $location->id
                        || $box->state !== 'stored' || (int) $box->current_pallet_id !== $physical->id || ! $box->stored_at || ! $box->stored_by) {
                        $this->fail('Posisi box yang sudah ditempatkan tidak sesuai pallet permintaan.');
                    }
                } elseif ($f->placed_by || $f->placed_location_id || ! in_array($box->state, ['received', 'stored'], true)
                    || ($box->state === 'received' && $box->current_pallet_id)
                    || ($box->state === 'stored' && (! $box->currentPallet || $box->currentPallet->retired_at || ! $box->stored_at || ! $box->stored_by
                        || ($physical && (int) $box->current_pallet_id === $physical->id && ! ($f->merge_pending_verification && $f->allocation_notice_id && $f->picking_required))))) {
                    $this->fail('Posisi reservasi yang belum ditempatkan tidak konsisten.');
                }
            }
        }
        if ($physical && $physical->boxes()->count() !== $commitments->filter(fn ($f) => $f->placed_at
            || ($f->merge_pending_verification && (int) $f->stockBox?->current_pallet_id === $physical->id))->count()) {
            $this->fail('Pallet berisi box di luar permintaan. Pindahkan stok tambahan sebelum revisi.');
        }
        if (ExportPickingIssue::open()->where(fn ($q) => $q->where('export_planned_pallet_id', $pallet->id)
            ->orWhereIn('box_number', $commitments->pluck('box_number')))->exists()) {
            $this->fail('Selesaikan issue picking dan pengembaliannya sebelum revisi.');
        }

        return [$physical, $items, $commitments];
    }

    public function preview(ExportPlannedPallet $pallet, array $quantities, array $additions = []): array
    {
        [$physical, $items, $commitments] = $this->contents($pallet);
        if ($items->pluck('id')->sort()->values()->all() !== $this->partIds($quantities)) {
            $this->fail('Rincian part berubah. Muat ulang revisi.');
        }

        $parts = PartSetting::whereKey($this->partIds($additions))->get()->keyBy('id');
        if ($parts->count() !== count($additions)) {
            $this->fail('Nomor part tambahan tidak tersedia di master.');
        }
        $changes = [];
        $selected = collect();
        foreach ($items as $item) {
            $quantity = (int) $quantities[$item->id];
            $before = $item->box_quantity;
            if ($quantity < 0 || $quantity > self::MAX_QUANTITY) {
                $this->fail('Jumlah box revisi di luar batas.');
            }
            $key = ExportBoxRequirements::key($item->part_setting_id, $item->pcs_per_box);
            $changes[$key] = ['id' => $item->id, 'part_id' => $item->part_setting_id, 'part_number' => $item->part_number,
                'pcs' => $item->pcs_per_box, 'before' => $before, 'after' => $quantity];
            if ($quantity < $before) {
                $rows = $commitments->where('export_planned_pallet_item_id', $item->id)
                    ->sortByDesc(fn ($f) => [$f->first_received_at->format('Y-m-d H:i:s.u'), $f->export_stock_box_id]);
                $selected = $selected->concat($rows->take(max(0, $rows->count() - $quantity)));
            }
        }
        foreach ($parts as $part) {
            $quantity = (int) $additions[$part->id];
            $pcs = (int) $part->qty_box;
            $key = ExportBoxRequirements::key($part->id, $pcs);
            if ($pcs < 1 || $quantity < 1 || $quantity > self::MAX_QUANTITY || isset($changes[$key])) {
                $this->fail('Gunakan baris yang sudah ada untuk mengubah part dengan PCS/box yang sama. Part baru harus memiliki jumlah dan PCS/box yang valid.');
            }
            $changes[$key] = ['id' => null, 'part_id' => $part->id, 'part_number' => $part->part_number,
                'pcs' => $pcs, 'before' => 0, 'after' => $quantity];
        }
        $total = array_sum(array_column($changes, 'after'));
        $changes = array_filter($changes, fn ($row) => $row['before'] !== $row['after']);
        if ($changes === []) {
            $this->fail('Ubah jumlah box atau tambahkan nomor part sebelum meninjau revisi.');
        }
        $pallet->loadMissing('palletPlan.deliveryPlan');
        $this->deliveryChanges($pallet->palletPlan->deliveryPlan, $changes);

        $allocator = app(ExportStockAllocationService::class);
        $added = $difference = $recommended = 0;
        foreach ($changes as &$change) {
            $delta = $change['after'] - $change['before'];
            $added += max(0, $delta);
            $difference += max(0, -$delta);
            $change['candidates'] = $delta > 0
                ? $allocator->revisionCandidates($pallet, $change['part_id'], $change['pcs'], $delta)
                : collect();
            $recommended += $change['candidates']->count();
        }
        unset($change);
        if ($recommended && ExportStockAllocationNotice::where('export_pallet_plan_id', $pallet->export_pallet_plan_id)
            ->where('plan_revision', $pallet->palletPlan->revision)->whereNull('acknowledged_at')
            ->whereHas('boxes', fn ($query) => $query->whereNull('placed_at')->whereNotIn('id', $selected->pluck('id')))->exists()) {
            $this->fail('Tinjau dan aktifkan alokasi FIFO sebelumnya di Perencanaan Pallet sebelum menyimpan tambahan dari stok.');
        }

        // A verified pick may already be in the operator's hands even before placement.
        $returns = $selected->filter(fn ($f) => $f->placed_at || $f->picking_started_at || $f->picking_verified_at || $f->merge_pending_verification)->values();
        $returnedIds = $returns->pluck('id')->flip();
        $released = $selected->reject(fn ($f) => $returnedIds->has($f->id))->values();
        $state = [$pallet->id, $quantities,
            $items->map(fn ($i) => [$i->id, $i->box_quantity])->all(),
            $commitments->map(fn ($f) => [$f->id, $f->placed_at?->toJSON(), $f->picking_started_at?->toJSON(), $f->picking_verified_at?->toJSON(),
                $f->stockBox->revision, $f->stockBox->state, $f->stockBox->current_pallet_id])->all(),
        ];
        if ($added) {
            $state[] = array_map(fn ($row) => [$row['part_id'], $row['part_number'], $row['pcs'], $row['after'],
                $row['candidates']->map(fn ($box) => [$box->id, $box->revision, $box->first_received_at->toJSON(), $box->current_pallet_id, $box->currentPallet?->location_code])->all()], $changes);
        }
        $fingerprint = hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));

        return compact('physical', 'items', 'selected', 'returns', 'released', 'fingerprint', 'quantities', 'additions',
            'changes', 'difference', 'added', 'recommended', 'total');
    }

    private function partIds(array $values): array
    {
        $ids = [];
        foreach (array_keys($values) as $id) {
            if (! is_int($id) || $id < 1) {
                $this->fail('Nomor part pada revisi tidak valid.');
            }
            $ids[] = $id;
        }
        sort($ids);

        return $ids;
    }

    /** Compute exact PCS/box deltas against the current delivery totals. */
    private function deliveryChanges(ExportDeliveryPlan $delivery, array $changes): array
    {
        $lines = $delivery->items()->get()->keyBy('part_setting_id');
        $updates = [];
        $groups = collect($changes)->groupBy('part_id');
        $newParts = $groups->keys()->diff($lines->keys());
        $position = $lines->max('position') ?? 0;
        if ($position + $newParts->count() > 65535) {
            $this->fail('Jumlah part rencana delivery sudah mencapai batas.');
        }
        foreach ($groups as $partId => $rows) {
            $line = $lines->get($partId);
            $composition = collect($line ? ExportBoxRequirements::composition($line) : [])->keyBy('pcs_per_box');
            foreach ($rows as $row) {
                $count = ($composition->get($row['pcs'])['box_quantity'] ?? 0) + $row['after'] - $row['before'];
                if ($count < 0 || (! $line && $row['id'])) {
                    $this->fail('Jumlah delivery tidak sesuai dengan perencanaan.');
                }
                $composition->put($row['pcs'], ['pcs_per_box' => $row['pcs'], 'box_quantity' => $count]);
            }
            if ($composition->sum('box_quantity') > self::MAX_QUANTITY
                || $composition->contains(fn ($row) => $row['box_quantity'] > intdiv(self::MAX_QUANTITY, $row['pcs_per_box']))
                || $composition->sum(fn ($row) => $row['box_quantity'] * $row['pcs_per_box']) > self::MAX_QUANTITY) {
                $this->fail('Jumlah box atau PCS untuk satu part melebihi batas rencana delivery.');
            }
            $first = $rows->first();
            $attributes = [];
            if (! $line) {
                $attributes = ['part_setting_id' => $partId, 'position' => ++$position, 'part_number' => $first['part_number'],
                    'pcs_per_box' => $first['pcs'], 'pcs_quantity' => 0, 'box_quantity' => 0, 'not_full_pcs' => 0];
            } elseif ($line->box_quantity === 0) {
                // A removed part can be added again after its master capacity has changed.
                $attributes = ['pcs_per_box' => $first['pcs']];
            }
            $updates[] = ['line' => $line, 'composition' => $composition->values()->all(), 'attributes' => $attributes];
        }

        return $updates;
    }

    /** Caller holds the inventory mutex, validates versions/fingerprint and records the action token. */
    public function apply(ExportDeliveryPlan $delivery, ExportPlannedPallet $pallet, array $preview, ?string $destination, User $actor, array &$reusable): array
    {
        $dest = explode(':', $destination ?? '', 2);
        if ($preview['returns']->isNotEmpty()) {
            if (! in_array($dest[0], ['pallet', 'location'], true) || ! ctype_digit($dest[1] ?? '')) {
                $this->fail('Pilih pallet existing atau lokasi kosong untuk pengembalian.');
            }
            if ($dest[0] === 'pallet' && ExportStockPallet::whereKey((int) $dest[1])->whereNotNull('export_planned_pallet_id')->exists()) {
                $this->fail('Pilih pallet stok cadangan atau lokasi kosong untuk box yang dikurangi.');
            }
        }
        $pallet->update(['ready_at' => null, 'ready_by' => null, 'fulfillment_revision' => $pallet->fulfillment_revision + 1]);
        $returned = [];
        $released = [];
        foreach ($preview['selected'] as $f) {
            $box = $f->stockBox;
            $row = ['box_number' => $box->box_number, 'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity,
                'first_received_at' => $box->first_received_at->toDateTimeString(),
                'from_location' => $box->currentPallet?->location_code, 'pallet_number' => $pallet->position];
            $f->delete();
            if ($preview['returns']->contains('id', $f->id)) {
                if (! $f->placed_at && $dest[0] === 'pallet' && (int) $dest[1] === (int) $box->current_pallet_id) {
                    // The stored position is still the source while a picked box is in transit.
                    app(ExportFulfillmentCompletionService::class)->assertPalletUnlocked($box->currentPallet);
                    $box->increment('revision');
                    $moved = $box->fresh('currentPallet');
                } else {
                    $placement = ['revision' => $box->revision, 'request_token' => (string) Str::uuid(), $dest[0].'_id' => (int) $dest[1]];
                    $moved = app(ExportStockReceivingService::class)->place($box->id, $placement, $actor, $reusable, allowExpiredReturn: true);
                }
                if ($dest[0] === 'location') {
                    $reusable[(int) $dest[1]] = $moved->current_pallet_id;
                }
                $returned[] = $row + ['warehouse_location' => $moved->currentPallet->location_code];
            } else {
                $box->increment('revision');
                $released[] = $row + ['warehouse_location' => $box->currentPallet?->location_code];
            }
        }
        foreach ($this->deliveryChanges($delivery, $preview['changes']) as $update) {
            $line = $update['line']?->fill($update['attributes']) ?? $delivery->items()->create($update['attributes']);
            ExportBoxRequirements::updateComposition($line, $update['composition']);
        }
        $items = $preview['items']->keyBy('id');
        foreach ($preview['changes'] as $row) {
            $item = $items->get($row['id']);
            if ($row['after'] === 0) {
                $item->delete();
            } elseif ($item) {
                $item->update(['box_quantity' => $row['after']]);
            } else {
                $pallet->items()->create(['part_setting_id' => $row['part_id'], 'part_number' => $row['part_number'],
                    'pcs_per_box' => $row['pcs'], 'box_quantity' => $row['after']]);
            }
        }
        if (! $pallet->items()->exists()) {
            $physical = $preview['physical']?->fresh();
            if ($physical?->boxes()->exists()) {
                $this->fail('Pallet masih berisi stok. Konfirmasikan pengembalian seluruh box.');
            }
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
