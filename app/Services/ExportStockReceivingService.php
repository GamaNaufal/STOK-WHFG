<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportMasterLocation;
use App\Models\ExportPalletLocationReservation;
use App\Models\ExportPalletPlan;
use App\Models\ExportPartSetting;
use App\Models\ExportStockBox;
use App\Models\ExportStockMovement;
use App\Models\ExportStockPallet;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ExportStockReceivingService
{
    private function fail(string $key, string $message): never { throw ValidationException::withMessages([$key => $message]); }

    public function part(string $number, bool $lock = false): ExportPartSetting
    {
        $query = ExportPartSetting::where('part_number', $number);
        $part = ($lock ? $query->lockForUpdate() : $query)->first();
        if (!$part || $part->part_number !== $number) $this->fail('part_number', 'Nomor part belum terdaftar pada master ekspor.');
        if ($part->qty_box < 1) $this->fail('part_number', 'PCS per box pada master ekspor belum valid.');
        return $part;
    }

    public function receive(array $data, User $actor, bool $allocate = true, ?\DateTimeInterface $receivedAt = null): ExportStockBox
    {
        return app(ExportInventoryService::class)->transaction(function () use ($data, $actor, $allocate, $receivedAt) {
            $replay = ExportStockBox::where('receive_token', $data['receive_token'])->first();
            if ($replay) {
                if ($replay->box_number !== $data['box_number'] || $replay->part_number !== $data['part_number']
                    || (int) $replay->received_by !== $actor->id) $this->fail('receive_token', 'Permintaan penerimaan sudah dipakai untuk data lain.');
                return $replay;
            }
            if (ExportStockBox::where('box_number', $data['box_number'])->exists()
                || ExportFulfillmentBox::where('box_number', $data['box_number'])->exists()) {
                $this->fail('box_number', 'Nomor box ini sudah tercatat pada ekspor. Periksa daftar penerimaan/penempatannya.');
            }
            $part = $this->part($data['part_number'], true);
            if (isset($data['expected_pcs_quantity']) && (int) $data['expected_pcs_quantity'] !== (int) $part->qty_box) {
                $this->fail('batch', 'Kapasitas part '.$part->part_number.' berubah setelah scan. Hapus baris tersebut dan scan ulang sebelum memproses.');
            }
            $box = ExportStockBox::create([
                'box_number' => $data['box_number'], 'part_setting_id' => $part->id, 'part_number' => $part->part_number,
                'pcs_quantity' => $part->qty_box, 'state' => 'received', 'revision' => 1,
                'receive_token' => $data['receive_token'], 'first_received_at' => $receivedAt ?? now(), 'received_by' => $actor->id,
            ]);
            AuditService::record('export_stock_receipt', 'received', 'ExportStockBox', $box->id, $actor->id,
                [], $box->only(['box_number', 'part_setting_id', 'part_number', 'pcs_quantity', 'state', 'first_received_at']),
                'Box baru diterima untuk ekspor; menunggu konfirmasi penempatan.');
            if ($allocate) app(ExportStockAllocationService::class)->allocate($actor, [(int) $part->id]);
            return $box->fresh();
        });
    }

    /** Batch rows originate from the server's validated scan draft, never client PCS/dates. */
    public function receiveMany(array $rows, User $actor): \Illuminate\Database\Eloquent\Collection
    {
        return app(ExportInventoryService::class)->transaction(function () use ($rows, $actor) {
            $boxes = new \Illuminate\Database\Eloquent\Collection();
            foreach ($rows as $row) $boxes->push($this->receive($row, $actor, false, $row['scanned_at'] ?? null));
            app(ExportStockAllocationService::class)->allocate($actor, $boxes->pluck('part_setting_id')->map(fn ($id) => (int) $id)->unique()->values()->all());
            $boxes->each(fn ($box) => $box->refresh());
            return $boxes;
        });
    }

    private function palletAt(ExportMasterLocation $location, User $actor, ?int $plannedId, bool $allowExisting): ExportStockPallet
    {
        if ($location->current_pallet_id || $location->stockLocations()->exists()) $this->fail('location_id', 'Lokasi tidak tersedia untuk stok ekspor.');
        $physical = ExportStockPallet::where('master_location_id', $location->id)->whereNull('retired_at')->lockForUpdate()->first();
        if ($physical) {
            if (!$allowExisting || ($plannedId !== null && (int) $physical->export_planned_pallet_id !== $plannedId)) {
                $this->fail('location_id', 'Lokasi sudah ditempati pallet ekspor lain. Pilih pallet yang tersedia.');
            }
            return $physical;
        }
        if ($location->is_occupied) $this->fail('location_id', 'Lokasi tercatat terisi. Periksa penempatan sebelum melanjutkan.');
        return ExportStockPallet::create(['master_location_id' => $location->id, 'initial_location_id' => $location->id,
            'location_code' => $location->code, 'export_planned_pallet_id' => $plannedId, 'created_by' => $actor->id]);
    }

    public function place(int $boxId, array $data, User $actor, array $reusableLocations = []): ExportStockBox
    {
        return app(ExportInventoryService::class)->transaction(function () use ($boxId, $data, $actor, $reusableLocations) {
            $locationId = !empty($data['location_id']) ? (int) $data['location_id'] : null;
            $palletId = !empty($data['pallet_id']) ? (int) $data['pallet_id'] : null;
            $hash = hash('sha256', json_encode([$boxId, (int) $data['revision'], $locationId, $palletId], JSON_THROW_ON_ERROR));
            $replay = ExportStockMovement::where('request_token', $data['request_token'])->first();
            if ($replay) {
                if ($replay->request_hash !== $hash || (int) $replay->placed_by !== $actor->id) $this->fail('request_token', 'Konfirmasi ini sudah dipakai untuk data lain.');
                return ExportStockBox::findOrFail($boxId);
            }
            $box = ExportStockBox::with(['currentPallet', 'fulfillment.plannedItem.pallet'])->findOrFail($boxId);
            $commitment = $box->fulfillment;
            $planned = $commitment?->plannedItem->pallet;
            if ($planned) {
                $plan = ExportPalletPlan::findOrFail($planned->export_pallet_plan_id);
                $delivery = ExportDeliveryPlan::whereKey($plan->export_delivery_plan_id)->lockForUpdate()->first();
                $plan = ExportPalletPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
                if (!$delivery || $plan->status !== 'final' || $plan->delivery_revision !== $delivery->revision) {
                    $this->fail('allocation', 'Perencanaan tujuan sedang berubah. Periksa pemenuhan sebelum menempatkan box.');
                }
            }
            $box = ExportStockBox::whereKey($boxId)->lockForUpdate()->firstOrFail();
            if ($box->revision !== (int) $data['revision']) $this->fail('revision', 'Data penempatan box sudah berubah. Muat ulang dan ikuti tujuan terbaru.');
            if (!in_array($box->state, ['received', 'stored'], true)) $this->fail('box', 'Box tidak tersedia untuk penempatan.');
            $source = $box->current_pallet_id ? ExportStockPallet::findOrFail($box->current_pallet_id) : null;
            if (($box->state === 'stored' && !$source) || ($box->state === 'received' && $source)) {
                $this->fail('box', 'Status penerimaan dan lokasi fisik box tidak konsisten.');
            }
            $selected = null;
            if ($planned) {
                if ($locationId || $palletId) $this->fail('location_id', 'Box sudah direservasi. Ikuti lokasi pallet yang ditentukan sistem.');
                if ($commitment->placed_at) $this->fail('box', 'Penempatan box ini sudah dikonfirmasi.');
                if ($commitment->box_number !== $box->box_number || (int) $commitment->plannedItem->part_setting_id !== (int) $box->part_setting_id
                    || (int) $commitment->part_setting_id !== (int) $box->part_setting_id || $commitment->pcs_quantity !== $box->pcs_quantity
                    || (int) $planned->items()->whereKey($commitment->export_planned_pallet_item_id)->value('pcs_per_box') !== $box->pcs_quantity) {
                    $this->fail('allocation', 'Part atau PCS box tidak sesuai dengan reservasi.');
                }
                $locationId = $planned->master_location_id;
            } else {
                if ((!$locationId && !$palletId) || ($locationId && $palletId)) $this->fail('location_id', 'Pilih satu lokasi kosong atau satu pallet ekspor yang sudah ada.');
                // Only reuse a physical pallet created/verified within this bulk confirmation.
                if ($locationId && isset($reusableLocations[$locationId])) $palletId = $reusableLocations[$locationId];
                $selected = $palletId ? ExportStockPallet::whereKey($palletId)->whereNull('retired_at')->first() : null;
                if ($palletId && (!$selected || !$selected->master_location_id)) $this->fail('pallet_id', 'Pallet ekspor tidak tersedia.');
                $locationId ??= $selected->master_location_id;
            }
            $locations = ExportMasterLocation::whereIn('id', array_filter([$locationId, $source?->master_location_id]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $location = $locations->get($locationId);
            if (!$location) $this->fail('location_id', 'Pilih lokasi dari master ekspor.');
            $claim = ExportPalletLocationReservation::where('master_location_id', $location->id)->lockForUpdate()->first();
            if ($planned) {
                if (!$claim || $claim->export_planned_pallet_id !== $planned->id || $claim->export_pallet_plan_id !== $plan->id) {
                    $this->fail('allocation', 'Reservasi lokasi pallet tujuan sudah berubah. Muat ulang.');
                }
                $target = $this->palletAt($location, $actor, $planned->id, true);
            } else {
                if (!$palletId && $claim) $this->fail('location_id', 'Lokasi sedang direservasi. Pilih lokasi kosong atau pallet ekspor existing.');
                $target = $this->palletAt($location, $actor, $selected?->export_planned_pallet_id, (bool) $selected);
                if ($selected && $target->id !== $selected->id) $this->fail('pallet_id', 'Lokasi pallet sudah berubah. Pilih ulang.');
                if ($claim && (int) $target->export_planned_pallet_id !== $claim->export_planned_pallet_id) $this->fail('pallet_id', 'Pallet tidak sesuai dengan reservasi lokasi.');
                if ($source?->id === $target->id) $this->fail('pallet_id', 'Box sudah berada pada pallet ini.');
            }
            if ($source && (!$source->master_location_id || $source->retired_at || !$locations->has($source->master_location_id)
                || $locations->get($source->master_location_id)->current_pallet_id
                || $locations->get($source->master_location_id)->stockLocations()->exists())) {
                $this->fail('box', 'Lokasi asal box tidak konsisten. Periksa stok ekspor.');
            }
            $moment = now();
            ExportStockMovement::create(['export_stock_box_id' => $box->id, 'request_token' => $data['request_token'],
                'request_hash' => $hash, 'from_pallet_id' => $source?->id, 'to_pallet_id' => $target->id,
                'export_fulfillment_box_id' => $commitment?->id, 'from_location_code' => $source?->location_code,
                'to_location_code' => $target->location_code, 'placed_by' => $actor->id, 'placed_at' => $moment]);
            $before = $box->only(['current_pallet_id', 'state', 'revision']);
            $box->update(['current_pallet_id' => $target->id, 'state' => 'stored', 'revision' => $box->revision + 1,
                'stored_at' => $moment, 'stored_by' => $actor->id]);
            $location->update(['is_occupied' => true]);
            if ($commitment) $commitment->update(['placed_at' => $moment, 'placed_by' => $actor->id, 'placed_location_id' => $location->id]);
            if ($source && $source->id !== $target->id && !$source->boxes()->exists()) {
                $sourceLocation = $locations->get($source->master_location_id);
                $source->update(['master_location_id' => null, 'export_planned_pallet_id' => null, 'retired_at' => $moment]);
                $sourceLocation->update(['is_occupied' => false]);
            }
            AuditService::record('export_stock_placement', 'placed', 'ExportStockBox', $box->id, $actor->id,
                $before, $box->only(['current_pallet_id', 'state', 'revision']) + [
                    'box_number' => $box->box_number, 'from_location' => $source?->location_code,
                    'to_location' => $location->code, 'fulfillment_id' => $commitment?->id,
                    'first_received_at' => $box->first_received_at->toDateTimeString()],
                'Penempatan fisik box ekspor dikonfirmasi.');
            return $box->fresh();
        });
    }

    public function placeMany(array $rows, User $actor): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($rows, $actor) {
            $reusable = [];
            foreach ($rows as $row) {
                $this->place((int) $row['id'], $row, $actor, $reusable);
                if (!empty($row['location_id'])) {
                    $movement = ExportStockMovement::where('request_token', $row['request_token'])->firstOrFail();
                    $pallet = ExportStockPallet::findOrFail($movement->to_pallet_id);
                    if (!$pallet->retired_at && (int) $pallet->master_location_id === (int) $row['location_id']) {
                        $reusable[(int) $row['location_id']] = $pallet->id;
                    }
                }
            }
        });
    }
}
