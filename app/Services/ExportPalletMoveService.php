<?php

namespace App\Services;

use App\Models\ExportFulfillmentBox;
use App\Models\ExportMasterLocation;
use App\Models\ExportNotFullRequest;
use App\Models\ExportPickingIssue;
use App\Models\ExportStockBox;
use App\Models\ExportStockMovement;
use App\Models\ExportStockPallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportPalletMoveService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['pallet' => $message]);
    }

    public function locations(): Builder
    {
        return ExportMasterLocation::available()->whereNull('current_pallet_id')
            ->whereDoesntHave('stockLocations')->whereDoesntHave('stockPallet')
            ->whereNotIn('master_locations.id', ExportPickingIssue::open()->whereNotNull('return_location_id')->select('return_location_id'));
    }

    public function assertPickingIdle(ExportStockPallet $pallet): void
    {
        $incoming = $pallet->export_planned_pallet_id;
        if (ExportFulfillmentBox::whereNull('placed_at')
            ->where(fn ($query) => $query->whereNotNull('picking_started_at')->orWhereNotNull('picking_verified_at'))
            ->where(function ($query) use ($pallet, $incoming) {
                $query->whereHas('stockBox', fn ($box) => $box->where('current_pallet_id', $pallet->id));
                if ($incoming) {
                    $query->orWhereHas('plannedItem', fn ($item) => $item->where('export_planned_pallet_id', $incoming));
                }
            })->exists()) {
            $this->fail('Picking sudah dimulai untuk pallet ini. Selesaikan penempatan atau pengembalian box dahulu.');
        }
        if (ExportPickingIssue::open()->where(function ($query) use ($pallet, $incoming) {
            $query->where('return_pallet_id', $pallet->id)
                ->orWhere('return_location_id', $pallet->master_location_id)
                ->orWhereIn('export_stock_box_id', $pallet->boxes()->select('id'))
                ->orWhereIn('box_number', $pallet->boxes()->select('box_number'))
                ->orWhereIn('export_planned_pallet_id', ExportFulfillmentBox::whereNull('placed_at')
                    ->whereHas('stockBox', fn ($box) => $box->where('current_pallet_id', $pallet->id))
                    ->join('export_planned_pallet_items as move_item', 'move_item.id', '=', 'export_fulfillment_boxes.export_planned_pallet_item_id')
                    ->select('move_item.export_planned_pallet_id'));
            if ($incoming) {
                $query->orWhere('export_planned_pallet_id', $incoming);
            }
        })->exists()) {
            $this->fail('Selesaikan issue picking dan konfirmasi pengembalian sebelum memindahkan pallet.');
        }
    }

    public function inspect(ExportStockPallet $pallet): array
    {
        $pallet->loadMissing('location', 'plannedPallet.palletPlan.deliveryPlan', 'boxes.fulfillment');
        $source = $pallet->location;
        if ($pallet->retired_at || ! $source || ! $source->is_occupied || $source->current_pallet_id
            || $source->stockLocations()->exists() || $pallet->location_code !== $source->code
            || $pallet->boxes->isEmpty()
            || $pallet->boxes->contains(fn ($box) => $box->state !== 'stored' || ! $box->stored_at || ! $box->stored_by)) {
            $this->fail('Pallet tidak aktif atau lokasi fisik dan isi pallet tidak konsisten.');
        }
        $this->assertPickingIdle($pallet);
        $planned = $pallet->plannedPallet;
        $reservation = $source->reservation()->first();
        if ($planned) {
            $plan = $planned->palletPlan;
            $delivery = $plan?->deliveryPlan;
            if (! $delivery || $plan->completed_at || ! $plan->isFinalFor($delivery)
                || $planned->master_location_id !== $source->id || $planned->location_code !== $source->code
                || $reservation?->export_planned_pallet_id !== $planned->id
                || $reservation?->export_pallet_plan_id !== $plan->id) {
                $this->fail('Perencanaan final dan reservasi lokasi pallet tidak konsisten.');
            }
            if ($planned->ready_at) {
                app(ExportFulfillmentCompletionService::class)->contents($planned);
            } else {
                app(ExportFulfillmentRevisionService::class)->contents($planned);
            }
        } elseif ($reservation || $pallet->boxes->contains(fn ($box) => $box->fulfillment?->placed_at)) {
            $this->fail('Lokasi pallet cadangan terkait penempatan atau reservasi rencana yang tidak konsisten.');
        }

        return ['id' => $pallet->id, 'pallet_number' => $pallet->number, 'location' => $source->code,
            'location_id' => $source->id, 'total_box' => $pallet->boxes->count(),
            'total_pcs' => $pallet->boxes->sum('pcs_quantity'), 'ready' => (bool) $planned?->ready_at,
            'fingerprint' => hash('sha256', json_encode([$pallet->id, $source->id, $source->code, $planned?->id,
                $planned?->fulfillment_revision, $planned?->palletPlan?->revision,
                $pallet->boxes->sortBy('id')->map(fn ($box) => [$box->id, $box->revision])->values()->all()], JSON_THROW_ON_ERROR))];
    }

    public function move(array $data, User $actor): array
    {
        return app(ExportInventoryService::class)->transaction(function () use ($data, $actor) {
            $hash = hash('sha256', json_encode(['export_pallet_move', $data, $actor->id], JSON_THROW_ON_ERROR));
            $replay = ExportStockMovement::where('request_token', $data['request_token'])->first();
            if ($replay) {
                if ($replay->request_hash !== $hash) {
                    $this->fail('Token pemindahan sudah digunakan untuk data lain.');
                }
                return ['from' => $replay->from_location_code, 'to' => $replay->to_location_code];
            }
            $pallet = ExportStockPallet::lockForUpdate()->findOrFail($data['pallet_id']);
            $before = $this->inspect($pallet);
            if (! hash_equals($before['fingerprint'], $data['fingerprint'])) {
                $this->fail('Lokasi atau isi pallet sudah berubah. Pilih ulang pallet.');
            }
            if ($before['ready'] && empty($data['ready_confirmed'])) {
                $this->fail('Konfirmasikan bahwa status Siap akan dibuka dan perlu dikonfirmasi ulang.');
            }
            $target = $this->locations()->lockForUpdate()->find($data['location_id']);
            if (! $target) {
                $this->fail('Tujuan harus lokasi ekspor kosong yang tidak direservasi.');
            }

            $planned = $pallet->plannedPallet;
            $source = $pallet->location;
            $boxIds = $pallet->boxes->modelKeys();
            $itemIds = $planned ? $planned->items()->pluck('id') : collect();
            if ($planned) {
                $incomingIds = $planned->fulfillmentBoxes()->whereNull('placed_at')->pluck('export_stock_box_id');
                ExportStockBox::whereKey($incomingIds->diff($boxIds)->filter()->all())->increment('revision');
                $planned->fulfillmentBoxes()->whereNotNull('placed_at')->get()->each->update(['placed_location_id' => $target->id]);
                $source->reservation()->firstOrFail()->update(['master_location_id' => $target->id]);
                $planned->update(['master_location_id' => $target->id, 'location_code' => $target->code,
                    'ready_at' => null, 'ready_by' => null, 'fulfillment_revision' => $planned->fulfillment_revision + 1]);
            }
            foreach ($pallet->boxes->values() as $index => $box) {
                ExportStockMovement::create(['export_stock_box_id' => $box->id,
                    'request_token' => $index === 0 ? $data['request_token'] : (string) Str::uuid(),
                    'request_hash' => $hash, 'from_pallet_id' => $pallet->id, 'to_pallet_id' => $pallet->id,
                    'export_fulfillment_box_id' => $box->fulfillment?->id, 'from_location_code' => $source->code,
                    'to_location_code' => $target->code, 'placed_by' => $actor->id, 'placed_at' => now()]);
                $box->increment('revision');
            }
            $pallet->update(['master_location_id' => $target->id, 'location_code' => $target->code]);
            $source->update(['is_occupied' => false]);
            $target->update(['is_occupied' => true]);
            foreach (ExportNotFullRequest::where('status', 'prepared')->where(fn ($query) => $query
                ->whereIn('preparation->source_box_id', $boxIds)->orWhereIn('preparation->planned_item_id', $itemIds))->get() as $request) {
                $preparation = $request->toArray();
                $request->update(['status' => 'waiting', 'preparation' => null, 'prepared_by' => null, 'revision' => $request->revision + 1]);
                AuditService::record('export_not_full', 'updated', 'ExportNotFullRequest', $request->id, $actor->id,
                    $preparation, $request->toArray(), 'Lokasi sumber atau tujuan berubah. Siapkan ulang Box Not Full.');
            }
            AuditService::record('export_pallet_location_moved', 'moved', 'ExportStockPallet', $pallet->id, $actor->id,
                ['location_id' => $source->id, 'location' => $source->code, 'ready' => $before['ready']],
                ['pallet_number' => $pallet->number, 'location_id' => $target->id, 'location' => $target->code,
                    'planned_pallet_id' => $planned?->id, 'box_count' => $before['total_box'], 'pcs_quantity' => $before['total_pcs'],
                    'requires_ready_confirmation' => $before['ready']],
                'Pallet ekspor '.$pallet->number.' dipindahkan dari '.$source->code.' ke '.$target->code.'.');

            return ['from' => $source->code, 'to' => $target->code];
        });
    }
}
