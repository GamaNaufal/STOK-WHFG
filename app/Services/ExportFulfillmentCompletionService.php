<?php

namespace App\Services;

use App\Models\{ExportDeliveryPlan, ExportFulfillmentBox, ExportMasterLocation, ExportPalletLocationReservation, ExportPickingIssue, ExportPlannedPallet, ExportStockBox, ExportStockMovement, ExportStockPallet, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportFulfillmentCompletionService
{
    private function fail(string $message): never { throw ValidationException::withMessages(['fulfillment' => $message]); }

    public function assertPalletUnlocked(?ExportStockPallet $pallet): void
    {
        if ($pallet?->export_planned_pallet_id && ExportPlannedPallet::whereKey($pallet->export_planned_pallet_id)->whereNotNull('ready_at')->exists()) {
            $this->fail('Pallet sudah siap dan dikunci. Gunakan Revisi pallet pada pemenuhan permintaan.');
        }
    }

    private function context(int $deliveryId, ?int $palletId, array $data): array
    {
        $delivery = ExportDeliveryPlan::with('items')->lockForUpdate()->findOrFail($deliveryId);
        $plan = $delivery->palletPlan()->lockForUpdate()->firstOrFail();
        if (!$plan->isFinalFor($delivery) || $plan->completed_at) $this->fail('Permintaan sudah selesai atau perencanaan belum final.');
        if ($plan->revision !== (int) $data['revision']) $this->fail('Permintaan berubah. Muat ulang sebelum melanjutkan.');
        $pallet = $palletId ? $plan->pallets()->whereKey($palletId)->lockForUpdate()->firstOrFail() : null;
        if ($pallet && $pallet->fulfillment_revision !== (int) $data['fulfillment_revision']) $this->fail('Pallet berubah. Muat ulang rincian pallet.');
        return [$delivery, $plan, $pallet];
    }

    private function run(string $action, int $deliveryId, ?int $palletId, array $data, User $actor, callable $work): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($action, $deliveryId, $palletId, $data, $actor, $work) {
            $hash = hash('sha256', json_encode([$action, $deliveryId, $palletId, $data, $actor->id], JSON_THROW_ON_ERROR));
            $previous = DB::table('export_fulfillment_actions')->where('request_token', $data['request_token'])->first();
            if ($previous) {
                if ($previous->request_hash !== $hash) $this->fail('Token konfirmasi sudah dipakai untuk data lain.');
                return;
            }
            [$delivery, $plan, $pallet] = $this->context($deliveryId, $palletId, $data);
            $ids = $plan->pallets()->pluck('id');
            if (ExportPickingIssue::open()->where(fn ($q) => $q->whereIn('export_planned_pallet_id', $ids)->orWhere('reported_by', $actor->id))->exists()) {
                $this->fail('Selesaikan issue picking dan konfirmasi pengembalian sebelum melanjutkan.');
            }
            $work($delivery, $plan, $pallet);
            DB::table('export_fulfillment_actions')->insert(['request_token' => $data['request_token'], 'request_hash' => $hash,
                'action' => $action, 'export_delivery_plan_id' => $deliveryId, 'created_by' => $actor->id, 'created_at' => now()]);
        });
    }

    /** Validate both requirement confirmations and all actual contents; surplus stock cannot leave with the pallet. */
    public function contents(ExportPlannedPallet $pallet): array
    {
        $items = $pallet->items()->get();
        $commitments = $pallet->fulfillmentBoxes()->with('stockBox')->get();
        $physical = ExportStockPallet::where('export_planned_pallet_id', $pallet->id)->whereNull('retired_at')->first();
        if (!$physical || (int) $physical->master_location_id !== $pallet->master_location_id || $items->sum('box_quantity') < 1) $this->fail('Pallet belum memiliki penempatan fisik yang sesuai.');
        $location = ExportMasterLocation::find($pallet->master_location_id);
        if (!$location || !$location->is_occupied || $location->current_pallet_id || $location->stockLocations()->exists()
            || !ExportPalletLocationReservation::where('master_location_id', $location->id)->where('export_planned_pallet_id', $pallet->id)->exists()) $this->fail('Lokasi fisik dan reservasi pallet tidak konsisten. Periksa stok ekspor.');
        foreach ($items as $item) {
            $rows = $commitments->where('export_planned_pallet_item_id', $item->id);
            if ($rows->count() !== $item->box_quantity) $this->fail('Seluruh kebutuhan setiap part harus terpenuhi tepat sebelum konfirmasi/revisi.');
            foreach ($rows as $f) {
                $box = $f->stockBox;
                if (!$f->placed_at || !$f->placed_by || (int) $f->placed_location_id !== $pallet->master_location_id
                    || !$box || $box->state !== 'stored' || (int) $box->current_pallet_id !== $physical->id
                    || (int) $f->part_setting_id !== $item->part_setting_id || (int) $box->part_setting_id !== $item->part_setting_id
                    || $box->pcs_quantity !== $item->pcs_per_box || $f->pcs_quantity !== $item->pcs_per_box
                    || $box->box_number !== $f->box_number || !$box->stored_at || !$box->stored_by) $this->fail('Identitas, part, PCS, atau posisi box belum sesuai. Periksa penempatan pallet.');
            }
        }
        if ($physical->boxes()->count() !== $commitments->count()) $this->fail('Pallet berisi box di luar permintaan. Pindahkan stok tambahan sebelum konfirmasi.');
        if (ExportPickingIssue::open()->whereIn('export_stock_box_id', $commitments->pluck('export_stock_box_id'))->exists()) $this->fail('Ada box pallet yang masih ditahan issue picking.');
        return [$physical, $items, $commitments];
    }

    public function ready(int $deliveryId, int $palletId, array $data, User $actor): void
    {
        $this->run('ready', $deliveryId, $palletId, $data, $actor, function ($delivery, $plan, $pallet) use ($data, $actor) {
            if ($pallet->ready_at) $this->fail('Pallet sudah dikonfirmasi siap.');
            [$physical, $items, $commitments] = $this->contents($pallet);
            $sourceId = $pallet->master_location_id;
            $targetId = (int) $data['location_id'];
            $locations = ExportMasterLocation::whereIn('id', [$sourceId, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $locations->get($targetId);
            if (!$target) $this->fail('Pilih lokasi master ekspor.');
            if ($sourceId !== $targetId && ($target->is_occupied || $target->current_pallet_id || $target->stockLocations()->exists()
                || $target->stockPallet()->exists() || $target->reservation()->exists())) $this->fail('Tujuan harus lokasi kosong atau lokasi pallet semula.');
            $claim = ExportPalletLocationReservation::where('export_planned_pallet_id', $pallet->id)->first();
            if (!$claim || (int) $claim->master_location_id !== $sourceId) $this->fail('Reservasi lokasi pallet sudah berubah.');
            $before = ['location' => $pallet->location_code, 'status' => 'terpenuhi'];
            if ($sourceId !== $targetId) {
                foreach ($commitments as $f) {
                    $box = $f->stockBox;
                    ExportStockMovement::create(['export_stock_box_id' => $box->id, 'request_token' => (string) Str::uuid(),
                        'request_hash' => hash('sha256', $data['request_token'].':'.$box->id), 'from_pallet_id' => $physical->id, 'to_pallet_id' => $physical->id,
                        'export_fulfillment_box_id' => $f->id, 'from_location_code' => $pallet->location_code, 'to_location_code' => $target->code,
                        'placed_by' => $actor->id, 'placed_at' => now()]);
                    $box->increment('revision');
                    $f->update(['placed_location_id' => $targetId]);
                }
                $physical->update(['master_location_id' => $targetId, 'location_code' => $target->code]);
                $claim->update(['master_location_id' => $targetId]);
                $locations->get($sourceId)->update(['is_occupied' => false]);
            }
            $target->update(['is_occupied' => true]);
            $pallet->update(['master_location_id' => $targetId, 'location_code' => $target->code, 'ready_at' => now(),
                'ready_by' => $actor->id, 'fulfillment_revision' => $pallet->fulfillment_revision + 1]);
            AuditService::record('export_fulfillment', 'ready', 'ExportPlannedPallet', $pallet->id, $actor->id, $before,
                ['plan_code' => $delivery->plan_code, 'pallet_number' => $pallet->position, 'location' => $target->code,
                    'status' => 'siap', 'total_boxes' => $commitments->count()], 'Pallet ekspor dikonfirmasi siap; lokasi fisik dikonfirmasi dan isi dikunci.');
        });
    }

    public function reopen(int $deliveryId, int $palletId, array $data, User $actor): void
    {
        $this->run('reopened', $deliveryId, $palletId, $data, $actor, function ($delivery, $plan, $pallet) use ($actor) {
            if (!$pallet->ready_at) $this->fail('Pallet belum dikonfirmasi siap.');
            $this->contents($pallet);
            $pallet->update(['ready_at' => null, 'ready_by' => null, 'fulfillment_revision' => $pallet->fulfillment_revision + 1]);
            AuditService::record('export_fulfillment', 'reopened', 'ExportPlannedPallet', $pallet->id, $actor->id,
                ['status' => 'siap'], ['plan_code' => $delivery->plan_code, 'pallet_number' => $pallet->position,
                    'location' => $pallet->location_code, 'status' => 'menunggu konfirmasi ulang'], 'Kesiapan pallet dibuka ulang untuk koreksi lokasi atau revisi; stok dan jumlah tidak berubah.');
        });
    }

    public function reduction(ExportPlannedPallet $pallet, array $quantities): array
    {
        [$physical, $items, $commitments] = $this->contents($pallet);
        $known = $items->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $sent = array_map('intval', array_keys($quantities)); sort($sent);
        if ($known !== $sent) $this->fail('Rincian part berubah. Muat ulang revisi.');
        $selected = collect(); $remaining = 0;
        foreach ($items as $item) {
            $quantity = (int) $quantities[$item->id];
            if ($quantity < 0 || $quantity > $item->box_quantity) $this->fail('Revisi ini hanya untuk mengurangi box, bukan menambah kebutuhan.');
            $remaining += $quantity;
            $rows = $commitments->where('export_planned_pallet_item_id', $item->id)->sortBy(fn ($f) => [$f->first_received_at->format('Y-m-d H:i:s.u'), $f->export_stock_box_id]);
            $selected = $selected->concat($rows->take($item->box_quantity - $quantity));
        }
        if (!$remaining || $selected->isEmpty()) $this->fail('Kurangi minimal satu box dan sisakan minimal satu box pada pallet.');
        $fingerprint = hash('sha256', json_encode($selected->map(fn ($f) => [$f->id, $f->stockBox->revision, $f->stockBox->current_pallet_id])->values()->all(), JSON_THROW_ON_ERROR));
        return compact('physical', 'items', 'selected', 'fingerprint');
    }

    public function reduce(int $deliveryId, int $palletId, array $data, User $actor): void
    {
        $this->run('reduced', $deliveryId, $palletId, $data, $actor, function ($delivery, $plan, $pallet) use ($data, $actor) {
            $preview = $this->reduction($pallet, $data['quantities']);
            if (!hash_equals($preview['fingerprint'], $data['fingerprint'])) $this->fail('Rekomendasi box berubah. Tinjau ulang FIFO sebelum mengembalikan.');
            $before = app(ExportDeliveryPlanService::class)->snapshot($delivery);
            $dest = explode(':', $data['destination'], 2);
            if (!in_array($dest[0], ['pallet', 'location'], true) || !ctype_digit($dest[1] ?? '')) $this->fail('Pilih pallet existing atau lokasi kosong untuk pengembalian.');
            if ($dest[0] === 'pallet' && (int) $dest[1] === $preview['physical']->id) $this->fail('Box yang dikurangi harus dipindahkan dari pallet permintaan ini.');
            $pallet->update(['ready_at' => null, 'ready_by' => null, 'fulfillment_revision' => $pallet->fulfillment_revision + 1]);
            $returned = []; $reusable = [];
            foreach ($preview['selected'] as $f) {
                $box = $f->stockBox;
                $returned[] = ['box_number' => $box->box_number, 'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity,
                    'first_received_at' => $box->first_received_at->toDateTimeString(), 'from_location' => $pallet->location_code,
                    'pallet_number' => $pallet->position];
                $f->delete();
                $placement = ['revision' => $box->revision, 'request_token' => (string) Str::uuid(), $dest[0].'_id' => (int) $dest[1]];
                $moved = app(ExportStockReceivingService::class)->place($box->id, $placement, $actor, $reusable);
                if ($dest[0] === 'location') $reusable[(int) $dest[1]] = $moved->current_pallet_id;
                $returned[array_key_last($returned)]['warehouse_location'] = $moved->currentPallet->location_code;
            }
            foreach ($preview['items'] as $item) {
                $quantity = (int) $data['quantities'][$item->id];
                $difference = $item->box_quantity - $quantity;
                $line = $delivery->items->firstWhere('part_setting_id', $item->part_setting_id);
                if (!$line || $line->box_quantity < $difference) $this->fail('Jumlah delivery tidak sesuai dengan perencanaan.');
                $line->update(['box_quantity' => $line->box_quantity - $difference,
                    'pcs_quantity' => ($line->box_quantity - $difference) * $line->pcs_per_box]);
                if ($quantity === 0) $item->delete(); else $item->update(['box_quantity' => $quantity]);
            }
            $delivery->load('items');
            $delivery->update(['revision' => $delivery->revision + 1, 'total_boxes' => $delivery->items->sum('box_quantity'),
                'total_pcs' => $delivery->items->sum('pcs_quantity'), 'updated_by' => $actor->id]);
            $plan->update(['revision' => $plan->revision + 1, 'delivery_revision' => $delivery->revision, 'updated_by' => $actor->id]);
            AuditService::record('export_fulfillment', 'reduced', 'ExportPlannedPallet', $pallet->id, $actor->id, $before,
                app(ExportDeliveryPlanService::class)->snapshot($delivery) + ['pallet_number' => $pallet->position, 'boxes' => $returned],
                'Revisi pallet: box FIFO dikembalikan menjadi stok cadangan setelah konfirmasi penempatan; kebutuhan delivery dikurangi.');
        });
    }

    public function complete(int $deliveryId, array $data, User $actor): void
    {
        $this->run('completed', $deliveryId, null, $data, $actor, function ($delivery, $plan) use ($actor) {
            $pallets = $plan->pallets()->get();
            if ($pallets->isEmpty() || $pallets->contains(fn ($p) => !$p->ready_at)) $this->fail('Konfirmasikan semua pallet siap sebelum menyelesaikan permintaan.');
            $snapshot = app(ExportDeliveryPlanService::class)->snapshot($delivery);
            $snapshot['pallets'] = []; $snapshot['boxes'] = [];
            foreach ($pallets as $pallet) {
                [$physical, , $rows] = $this->contents($pallet);
                $snapshot['pallets'][] = ['pallet_number' => $pallet->position, 'location' => $pallet->location_code,
                    'total_boxes' => $rows->count(), 'ready_at' => $pallet->ready_at->toDateTimeString(), 'ready_by' => $pallet->ready_by];
                foreach ($rows as $f) {
                    $box = $f->stockBox;
                    $snapshot['boxes'][] = ['box_number' => $box->box_number, 'part_number' => $box->part_number,
                        'pcs_quantity' => $box->pcs_quantity, 'warehouse_location' => $pallet->location_code,
                        'pallet_number' => $pallet->position, 'first_received_at' => $box->first_received_at->toDateTimeString()];
                    $box->update(['state' => 'shipped', 'current_pallet_id' => null, 'revision' => $box->revision + 1]);
                    $box->delete();
                }
                ExportMasterLocation::whereKey($pallet->master_location_id)->update(['is_occupied' => false]);
                $physical->update(['master_location_id' => null, 'export_planned_pallet_id' => null, 'retired_at' => now()]);
            }
            $plan->reservations()->delete();
            $plan->update(['completed_at' => now(), 'completed_by' => $actor->id]);
            $delivery->update(['status' => 'completed', 'updated_by' => $actor->id]);
            $delivery->delete();
            AuditService::record('export_fulfillment', 'completed', 'ExportDeliveryPlan', $delivery->id, $actor->id, $snapshot,
                ['plan_code' => $delivery->plan_code, 'customer_name' => $delivery->customer_name, 'status' => 'completed',
                    'total_boxes' => count($snapshot['boxes']), 'deleted' => true, 'completed_at' => now()->toDateTimeString()],
                'Seluruh box permintaan ekspor dikonfirmasi sudah keluar gudang. Stok/pallet dikeluarkan dari daftar aktif dan lokasi dibebaskan.');
        });
    }
}
