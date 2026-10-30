<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Models\ExportPalletLocationReservation;
use App\Models\ExportPalletPlan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExportPalletPlanningService
{
    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    public function rows(?ExportPalletPlan $plan): array
    {
        $plan?->loadMissing('pallets.items');
        return $plan?->pallets->map(fn ($pallet) => [
            'id' => $pallet->id, 'master_location_id' => $pallet->master_location_id,
            'items' => $pallet->items->map(fn ($item) => $item->only(['part_setting_id', 'box_quantity']))->all(),
        ])->all() ?? [];
    }

    public function requirements(ExportDeliveryPlan $delivery, array $rows): array
    {
        $allocated = [];
        foreach ($rows as $pallet) {
            foreach ($pallet['items'] ?? [] as $item) {
                $id = (int) $item['part_setting_id'];
                $allocated[$id] = ($allocated[$id] ?? 0) + (int) $item['box_quantity'];
            }
        }
        $result = $delivery->items->map(fn ($item) => [
            'part_setting_id' => $item->part_setting_id, 'part_number' => $item->part_number,
            'pcs_per_box' => $item->pcs_per_box, 'required' => $item->box_quantity,
            'allocated' => $allocated[$item->part_setting_id] ?? 0,
            'remaining' => $item->box_quantity - ($allocated[$item->part_setting_id] ?? 0),
        ])->all();
        $known = $delivery->items->pluck('part_setting_id')->all();
        foreach ($allocated as $id => $quantity) {
            if (! in_array($id, $known)) {
                $result[] = ['part_setting_id' => $id, 'part_number' => '', 'pcs_per_box' => 0,
                    'required' => 0, 'allocated' => $quantity, 'remaining' => -$quantity];
            }
        }
        return $result;
    }

    public function snapshot(ExportPalletPlan $plan): array
    {
        $plan->load('pallets.items', 'reservations');
        return $plan->only(['export_delivery_plan_id', 'status', 'revision', 'delivery_revision', 'finalized_by', 'finalized_at']) + [
            'pallets' => $plan->pallets->map(fn ($pallet) => $pallet->only(['id', 'position', 'master_location_id', 'location_code']) + [
                'items' => $pallet->items->map(fn ($item) => $item->only(['part_setting_id', 'part_number', 'pcs_per_box', 'box_quantity']))->all(),
            ])->all(),
            'reserved_locations' => $plan->reservations->pluck('master_location_id')->all(),
        ];
    }

    public function save(int $deliveryId, array $data, User $actor): ExportPalletPlan
    {
        return app(ExportInventoryService::class)->transaction(function () use ($deliveryId, $data, $actor) {
            // All delivery/planning writes lock this parent first, including revisions and archive.
            $delivery = ExportDeliveryPlan::with('items')->lockForUpdate()->findOrFail($deliveryId);
            if ($delivery->revision !== (int) $data['delivery_revision']) {
                $this->fail('delivery_revision', 'Rencana delivery sudah berubah. Muat ulang sebelum menyimpan perencanaan.');
            }
            $plan = ExportPalletPlan::where('export_delivery_plan_id', $deliveryId)->lockForUpdate()->first();
            if (($plan?->revision ?? 0) !== (int) $data['revision']) {
                $this->fail('revision', 'Perencanaan sudah diubah pengguna lain. Muat ulang halaman.');
            }
            if ($plan?->status === 'final') {
                $this->fail('status', 'Buka revisi perencanaan sebelum mengubah hasil final.');
            }
            if ($plan) app(ExportFulfillmentService::class)->assertNoCommittedBoxes($plan);
            $before = $plan ? $this->snapshot($plan) : [];
            $existing = $plan?->pallets->keyBy('id') ?? collect();
            $catalog = $delivery->items->keyBy('part_setting_id');
            $historical = $plan?->pallets->flatMap->items->keyBy('part_setting_id') ?? collect();
            $rows = $data['pallets'];
            $final = $data['action'] === 'finalize';
            $locationIds = array_values(array_filter(array_map(fn ($row) => $row['master_location_id'] ?? null, $rows)));
            if (count($locationIds) !== count(array_unique($locationIds))) {
                $this->fail('pallets', 'Satu lokasi hanya boleh dipilih untuk satu pallet dalam perencanaan.');
            }
            // A consistent lock order also serializes finalization against master edits.
            $held = $plan?->reservations->pluck('master_location_id')->all() ?? [];
            $locations = ExportMasterLocation::whereIn('id', array_unique([...$locationIds, ...$held]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $claims = ExportPalletLocationReservation::whereIn('master_location_id', $locationIds)
                ->lockForUpdate()->get()->keyBy('master_location_id');
            $seenIds = [];
            $itemCount = 0;
            foreach ($rows as $i => $row) {
                $palletId = $row['id'] ?? null;
                if ($palletId && (! $existing->has($palletId) || in_array($palletId, $seenIds))) {
                    $this->fail("pallets.$i.id", 'Identitas pallet tidak sesuai dengan perencanaan ini.');
                }
                if ($palletId) $seenIds[] = $palletId;
                $locationId = !empty($row['master_location_id']) ? (int) $row['master_location_id'] : null;
                if ($locationId) {
                    $location = $locations->get($locationId);
                    if (! $location) $this->fail("pallets.$i.master_location_id", 'Pilih lokasi dari master ekspor.');
                    if ($location->is_occupied || $location->current_pallet_id || $location->stockLocations()->exists() || $location->stockPallet()->exists()) {
                        $this->fail("pallets.$i.master_location_id", "Lokasi {$location->code} sudah ditempati pallet fisik.");
                    }
                    $claim = $claims->get($locationId);
                    if ($claim && $claim->export_pallet_plan_id !== $plan?->id) {
                        $this->fail("pallets.$i.master_location_id", "Lokasi {$location->code} sudah direservasi perencanaan lain.");
                    }
                } elseif ($final) {
                    $this->fail("pallets.$i.master_location_id", 'Lengkapi lokasi setiap pallet sebelum finalisasi.');
                }
                if ($final && empty($row['items'])) $this->fail("pallets.$i.items", 'Pallet kosong tidak dapat difinalisasi.');
                $seenParts = [];
                foreach ($row['items'] as $j => $item) {
                    $itemCount++;
                    $partId = (int) $item['part_setting_id'];
                    if (in_array($partId, $seenParts)) $this->fail("pallets.$i.items.$j", 'Gabungkan part yang sama dalam satu baris pallet.');
                    $seenParts[] = $partId;
                    if (! $catalog->has($partId) && (! $historical->has($partId) || $final)) {
                        $this->fail("pallets.$i.items.$j", 'Part tidak terdapat pada rencana delivery terkini. Hapus alokasi lamanya.');
                    }
                }
            }
            if ($itemCount > 5000) $this->fail('pallets', 'Maksimal 5.000 baris alokasi dalam satu perencanaan.');
            if ($final) {
                foreach ($this->requirements($delivery, $rows) as $requirement) {
                    if ($requirement['remaining'] !== 0) {
                        $this->fail('pallets', 'Alokasi '.$requirement['part_number'].' harus tepat '.$requirement['required'].' box; saat ini '.$requirement['allocated'].' box.');
                    }
                }
            }
            $plan ??= new ExportPalletPlan(['export_delivery_plan_id' => $deliveryId, 'created_by' => $actor->id, 'revision' => 0]);
            $plan->fill(['status' => $final ? 'final' : 'draft', 'revision' => $plan->revision + 1,
                'entered_fulfillment' => $plan->entered_fulfillment || $final,
                'delivery_revision' => $delivery->revision, 'updated_by' => $actor->id,
                'finalized_by' => $final ? $actor->id : null, 'finalized_at' => $final ? now() : null])->save();
            // Release claims before rebuilding them; all affected location rows remain locked.
            $plan->reservations()->delete();
            $plan->pallets()->whereNotIn('id', $seenIds)->delete();
            $plan->pallets()->increment('position', 1000);
            foreach ($rows as $i => $row) {
                $locationId = !empty($row['master_location_id']) ? (int) $row['master_location_id'] : null;
                $pallet = !empty($row['id']) ? $plan->pallets()->findOrFail($row['id']) : $plan->pallets()->make();
                $pallet->fill(['position' => $i + 1, 'master_location_id' => $locationId,
                    'location_code' => $locationId ? $locations->get($locationId)->code : null])->save();
                $partIds = array_column($row['items'], 'part_setting_id');
                $pallet->items()->whereNotIn('part_setting_id', $partIds)->delete();
                foreach ($row['items'] as $item) {
                    $part = $catalog->get($item['part_setting_id']) ?? $historical->get($item['part_setting_id']);
                    $pallet->items()->updateOrCreate(['part_setting_id' => $item['part_setting_id']], [
                        'part_number' => $part->part_number, 'pcs_per_box' => $part->pcs_per_box,
                        'box_quantity' => $item['box_quantity'],
                    ]);
                }
                // Retain previously finalized locations during revisions; new ones claim only on finalization.
                if ($locationId && ($final || in_array($locationId, $held))) {
                    $plan->reservations()->create(['master_location_id' => $locationId,
                        'export_planned_pallet_id' => $pallet->id, 'created_at' => now()]);
                }
            }
            AuditService::record('export_pallet_plan', $final ? 'finalized' : 'saved', 'ExportPalletPlan', $plan->id,
                $actor->id, $before, $this->snapshot($plan), $final ? 'Perencanaan pallet difinalisasi dan lokasi direservasi.' : 'Draft perencanaan pallet disimpan.');
            if ($final) app(ExportStockAllocationService::class)->allocate($actor);
            return $plan;
        });
    }

    public function transition(int $deliveryId, int $revision, User $actor, bool $cancel = false): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($deliveryId, $revision, $actor, $cancel) {
            ExportDeliveryPlan::lockForUpdate()->findOrFail($deliveryId);
            $plan = ExportPalletPlan::where('export_delivery_plan_id', $deliveryId)->lockForUpdate()->firstOrFail();
            if ($plan->revision !== $revision) $this->fail('revision', 'Perencanaan sudah berubah. Muat ulang halaman.');
            if (!$cancel && $plan->status !== 'final') $this->fail('status', 'Hanya perencanaan final yang perlu dibuka untuk revisi.');
            if ($cancel && $plan->status === 'cancelled') $this->fail('status', 'Perencanaan sudah dibatalkan.');
            app(ExportFulfillmentService::class)->assertNoCommittedBoxes($plan);
            $before = $this->snapshot($plan);
            if ($cancel) {
                $ids = $plan->reservations->pluck('master_location_id');
                ExportMasterLocation::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $plan->reservations()->delete();
            }
            $plan->update(['status' => $cancel ? 'cancelled' : 'draft', 'revision' => $plan->revision + 1,
                'updated_by' => $actor->id, 'finalized_by' => null, 'finalized_at' => null]);
            AuditService::record('export_pallet_plan', $cancel ? 'cancelled' : 'reopened', 'ExportPalletPlan', $plan->id,
                $actor->id, $before, $this->snapshot($plan), $cancel ? 'Perencanaan dibatalkan dan reservasi lokasi dilepas.' : 'Perencanaan dibuka untuk revisi; reservasi lokasi tetap ditahan.');
        });
    }

    // Called inside the delivery transaction, while its parent row is already locked.
    public function invalidateForDeliveryRevision(ExportDeliveryPlan $delivery, User $actor): void
    {
        $plan = ExportPalletPlan::where('export_delivery_plan_id', $delivery->id)->lockForUpdate()->first();
        if (!$plan || $plan->status === 'cancelled') return;
        app(ExportFulfillmentService::class)->assertNoCommittedBoxes($plan);
        $before = $this->snapshot($plan);
        $plan->update(['status' => 'draft', 'revision' => $plan->revision + 1, 'updated_by' => $actor->id,
            'finalized_by' => null, 'finalized_at' => null]);
        AuditService::record('export_pallet_plan', 'delivery_revised', 'ExportPalletPlan', $plan->id,
            $actor->id, $before, $this->snapshot($plan), 'Delivery direvisi; pembagian dan reservasi tetap disimpan untuk disesuaikan.');
    }
}
