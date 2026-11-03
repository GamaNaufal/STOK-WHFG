<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportMasterLocation;
use App\Models\ExportNotFullRequest;
use App\Models\ExportPalletLocationReservation;
use App\Models\ExportPickingIssue;
use App\Models\ExportPlannedPalletItem;
use App\Models\ExportStockBox;
use App\Models\ExportStockMovement;
use App\Models\ExportStockPallet;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Physical consolidation and the corresponding final plan revision share the inventory mutex. */
class ExportPalletMergeService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['merge' => $message]);
    }

    public function authorize(User $actor): void
    {
        abort_unless(in_array($actor->role, ['export_operator', 'admin_warehouse', 'admin'], true)
            && $actor->canAccess('export_merge_pallet'), 403);
    }

    public function preview(array $data, User $actor): array
    {
        $this->authorize($actor);

        return app(ExportInventoryService::class)->transaction(fn () => $this->context($data, $actor));
    }

    private function context(array $data, User $actor): array
    {
        $ids = array_values(array_unique(array_map('intval', $data['pallet_ids'] ?? [])));
        sort($ids);
        if (count($ids) < 2 || count($ids) > 50) {
            $this->fail('Pilih 2 sampai 50 pallet ekspor yang berbeda.');
        }
        $mode = $data['spare_mode'] ?? 'fill';
        if (! in_array($mode, ['fill', 'add'], true)) {
            $this->fail('Pilih perlakuan stok cadangan.');
        }
        $sources = ExportStockPallet::with(['location', 'plannedPallet.palletPlan.deliveryPlan',
            'boxes.fulfillment.plannedItem.pallet.palletPlan', 'boxes.part'])->whereIn('id', $ids)
            ->whereNull('retired_at')->orderBy('id')->lockForUpdate()->get();
        if ($sources->count() !== count($ids)) {
            $this->fail('Pallet sumber berubah atau sudah tidak aktif. Pilih ulang.');
        }
        $boxes = $sources->flatMap->boxes->sortBy('id')->values();
        foreach ($sources as $source) {
            if (! $source->location || ! $source->location->is_occupied || $source->location->current_pallet_id
                || $source->location->stockLocations()->exists() || $source->boxes->isEmpty()) {
                $this->fail('Pallet/lokasi sumber tidak konsisten atau kosong. Periksa stok ekspor.');
            }
            if ($source->export_planned_pallet_id && ! $source->plannedPallet) {
                $this->fail('Hubungan rencana pallet sumber tidak tersedia.');
            }
            if (! $source->export_planned_pallet_id && ExportPalletLocationReservation::where('master_location_id', $source->master_location_id)->exists()) {
                $this->fail('Lokasi cadangan sedang direservasi rencana lain.');
            }
            foreach ($source->boxes as $box) {
                if ($box->state !== 'stored' || ! $box->stored_at || ! $box->stored_by || ! $box->first_received_at) {
                    $this->fail('ID Box '.$box->box_number.' belum selesai ditempatkan. Selesaikan penerimaan dahulu.');
                }
            }
        }
        $pallets = $sources->pluck('plannedPallet')->filter()->sortBy('position')->values();
        $planIds = $pallets->pluck('export_pallet_plan_id')->unique();
        if ($planIds->count() > 1) {
            $this->fail('Lintas rencana delivery tidak boleh digabung, termasuk customer yang sama.');
        }
        $survivor = $pallets->first();
        $plan = $survivor?->palletPlan;
        $delivery = $plan?->deliveryPlan;
        if ($plan && (! $delivery || ! $plan->isFinalFor($delivery) || $plan->completed_at || $delivery->status === 'cancelled')) {
            $this->fail('Merge permintaan hanya tersedia pada perencanaan final yang belum selesai/dibatalkan.');
        }
        if ($plan) {
            $delivery->load('items');
            $planner = app(ExportPalletPlanningService::class);
            foreach ($planner->requirements($delivery, $planner->rows($plan)) as $requirement) {
                if ($requirement['remaining'] !== 0) {
                    $this->fail('Kebutuhan delivery dan alokasi pallet tidak konsisten. Periksa rencana sebelum merge.');
                }
            }
        }
        $items = $pallets->flatMap(fn ($p) => $p->items()->withCount('fulfillmentBoxes')->get());
        foreach ($pallets as $pallet) {
            app(ExportFulfillmentRevisionService::class)->contents($pallet);
        }
        $commitments = ExportFulfillmentBox::with('stockBox.currentPallet', 'plannedItem.pallet.palletPlan')
            ->where(fn ($q) => $q->whereIn('export_stock_box_id', $boxes->pluck('id')->all())
                ->orWhereIn('export_planned_pallet_item_id', $items->pluck('id')->all()))->orderBy('id')->get();
        foreach ($commitments as $f) {
            if (! $f->placed_at && $f->picking_verified_at) {
                $this->fail('Pengambilan sudah berjalan. Selesaikan penempatan/pengembalian sebelum merge.');
            }
            if ($plan && (int) $f->plannedItem->pallet->export_pallet_plan_id !== $plan->id) {
                $this->fail('ID Box '.$f->box_number.' sudah direservasi ke delivery lain. Reservasi tersebut tidak boleh dipindahkan.');
            }
            if ($plan && ! $pallets->contains('id', $f->plannedItem->export_planned_pallet_id)) {
                $this->fail('ID Box '.$f->box_number.' direservasi ke pallet tujuan lain. Sertakan pallet tujuan itu dalam merge.');
            }
        }
        if ($commitments->map(fn ($f) => $f->plannedItem->pallet->export_pallet_plan_id)->merge($planIds)->unique()->count() > 1) {
            $this->fail('Lintas rencana delivery tidak boleh digabung, termasuk stok cadangan yang sudah direservasi ke delivery berbeda.');
        }
        if (ExportPickingIssue::open()->where(fn ($q) => $q->whereIn('export_stock_box_id', $boxes->pluck('id')->all())
            ->orWhereIn('box_number', $commitments->pluck('box_number')->merge($boxes->pluck('box_number')))
            ->orWhereIn('export_planned_pallet_id', $pallets->pluck('id')->all())
            ->orWhereIn('return_pallet_id', $ids)->orWhere('reported_by', $actor->id))->exists()) {
            $this->fail('Selesaikan issue picking, approval dan konfirmasi pengembalian sebelum merge.');
        }
        $location = ExportMasterLocation::lockForUpdate()->find($data['location_id'] ?? 0);
        if (! $location || $location->current_pallet_id || $location->stockLocations()->exists()) {
            $this->fail('Pilih lokasi ekspor yang tersedia.');
        }
        $claim = ExportPalletLocationReservation::where('master_location_id', $location->id)->first();
        $occupant = ExportStockPallet::where('master_location_id', $location->id)->first();
        if (($occupant && ! $sources->contains('id', $occupant->id)) || ($location->is_occupied && ! $occupant)
            || ($claim && ! $pallets->contains('id', $claim->export_planned_pallet_id))) {
            $this->fail('Lokasi tujuan harus lokasi salah satu sumber atau lokasi ekspor kosong tanpa reservasi lain.');
        }

        $requirements = $items->groupBy(fn ($i) => ExportBoxRequirements::key($i->part_setting_id, $i->pcs_per_box))
            ->map(fn ($rows) => ['part_setting_id' => $rows->first()->part_setting_id, 'part_number' => $rows->first()->part_number,
                'pcs_per_box' => $rows->first()->pcs_per_box, 'before' => $rows->sum('box_quantity'),
                'reserved' => $rows->sum('fulfillment_boxes_count'), 'incoming' => 0, 'added' => 0]);
        $free = $boxes->filter(fn ($b) => ! $b->fulfillment);
        if ($plan) {
            foreach ($free->groupBy(fn ($b) => ExportBoxRequirements::key($b->part_setting_id, $b->pcs_quantity)) as $key => $rows) {
                $first = $rows->first();
                $r = $requirements->get($key, ['part_setting_id' => $first->part_setting_id, 'part_number' => $first->part_number,
                    'pcs_per_box' => $first->pcs_quantity, 'before' => 0, 'reserved' => 0, 'incoming' => 0, 'added' => 0]);
                $r['incoming'] = $rows->count();
                $r['added'] = $mode === 'add' ? $rows->count() : max(0, $rows->count() - ($r['before'] - $r['reserved']));
                $requirements->put($key, $r);
                // Existing commitments stay intact. New commitments must use the oldest free exact-capacity stock.
                $fifo = ExportStockBox::where('part_setting_id', $first->part_setting_id)->where('pcs_quantity', $first->pcs_quantity)
                    ->whereIn('state', ['received', 'stored'])->whereDoesntHave('fulfillment')
                    ->whereNotIn('box_number', ExportPickingIssue::open()->select('box_number'))
                    ->orderBy('first_received_at')->orderBy('id')->limit($rows->count())->get();
                if ($missing = $fifo->first(fn ($b) => ! $rows->contains('id', $b->id))) {
                    $this->fail('FIFO: ambil ID Box '.$missing->box_number.' terlebih dahulu dari '.($missing->currentPallet?->display_name ?? 'antrean penerimaan')
                        .' · '.($missing->currentPallet?->location_code ?? 'belum ditempatkan').'. Pallet yang dipilih melewati stok lebih tua.');
                }
            }
        }
        $requirements = $requirements->map(fn ($r) => $r + ['after' => $r['before'] + $r['added']]);
        $requests = $delivery ? ExportNotFullRequest::where('export_delivery_plan_id', $delivery->id)->orderBy('id')->get() : collect();
        $fingerprint = hash('sha256', json_encode([$ids, $mode, $sources->toArray(), $items->toArray(), $commitments->toArray(),
            $location->toArray(), $claim?->toArray(), $delivery?->toArray(), $plan?->toArray(), $requests->toArray(), $requirements->all()], JSON_THROW_ON_ERROR));

        return compact('sources', 'boxes', 'pallets', 'survivor', 'plan', 'delivery', 'items', 'commitments', 'free',
            'location', 'mode', 'requirements', 'fingerprint') + ['added_boxes' => $requirements->sum('added'),
                'ready_count' => $pallets->whereNotNull('ready_at')->count(), 'result_boxes' => $boxes->count(),
                'required_boxes' => $requirements->sum('after')];
    }

    public function merge(array $data, User $actor): array
    {
        $this->authorize($actor);

        return app(ExportInventoryService::class)->transaction(function () use ($data, $actor) {
            $ids = array_values(array_unique(array_map('intval', $data['pallet_ids'])));
            sort($ids);
            $hash = hash('sha256', json_encode([$ids, (int) $data['location_id'], $data['spare_mode'], $data['fingerprint']], JSON_THROW_ON_ERROR));
            if ($replay = DB::table('export_pallet_merges')->where('request_token', $data['request_token'])->first()) {
                if ($replay->request_hash !== $hash || (int) $replay->created_by !== $actor->id) {
                    $this->fail('Token merge sudah digunakan untuk data lain.');
                }

                return json_decode($replay->snapshot, true, 512, JSON_THROW_ON_ERROR);
            }
            $c = $this->context($data, $actor);
            if (! hash_equals($c['fingerprint'], $data['fingerprint'])) {
                $this->fail('Stok, lokasi atau rencana sudah berubah. Tinjau ulang sebelum merge.');
            }
            if (! ($data['physical_confirmed'] ?? false)) {
                $this->fail('Konfirmasikan seluruh box dipindahkan secara fisik sesuai pratinjau.');
            }
            if ($c['ready_count'] && ! ($data['ready_confirmed'] ?? false)) {
                $this->fail('Setujui pembatalan status siap dan konfirmasi ulang pallet hasil merge.');
            }
            if ($c['added_boxes'] && ! ($data['demand_confirmed'] ?? false)) {
                $this->fail('Setujui penambahan kebutuhan rencana delivery yang ditampilkan.');
            }
            $before = ['pallets' => $c['sources']->map(fn ($s) => $s->only(['id', 'master_location_id', 'location_code', 'export_planned_pallet_id'])
                + ['pallet_number' => $s->plannedPallet?->position, 'box_quantity' => $s->boxes->count()])->all(),
                'boxes' => $c['boxes']->map(fn ($b) => $b->only(['box_number', 'part_number', 'pcs_quantity', 'first_received_at'])
                    + ['warehouse_location' => $c['sources']->firstWhere('id', $b->current_pallet_id)->location_code])->all(),
                'planning' => $c['plan'] ? app(ExportPalletPlanningService::class)->snapshot($c['plan']) : null];
            $plan = $c['plan'];
            $delivery = $c['delivery'];
            $target = $c['survivor'];
            $location = $c['location'];

            // Retain physical sources for movement history; release unique links before creating the result.
            $sourceLocations = $c['sources']->pluck('master_location_id')->all();
            foreach ($c['sources'] as $source) {
                $source->update(['master_location_id' => null, 'export_planned_pallet_id' => null, 'retired_at' => now()]);
            }
            foreach ($c['pallets'] as $pallet) {
                ExportPalletLocationReservation::where('export_planned_pallet_id', $pallet->id)->delete();
            }
            $result = ExportStockPallet::create(['master_location_id' => $location->id, 'initial_location_id' => $location->id,
                'location_code' => $location->code, 'export_planned_pallet_id' => $target?->id, 'created_by' => $actor->id]);
            $mergedItems = collect();
            if ($target) {
                $target->update(['master_location_id' => $location->id, 'location_code' => $location->code,
                    'ready_at' => null, 'ready_by' => null, 'fulfillment_revision' => $target->fulfillment_revision + 1]);
                foreach ($c['requirements'] as $key => $r) {
                    $rows = $c['items']->filter(fn ($i) => ExportBoxRequirements::key($i->part_setting_id, $i->pcs_per_box) === $key);
                    $item = $rows->firstWhere('export_planned_pallet_id', $target->id) ?? $rows->first();
                    if ($item) {
                        $item->update(['export_planned_pallet_id' => $target->id, 'box_quantity' => $r['after']]);
                    } else {
                        $item = $target->items()->create(['part_setting_id' => $r['part_setting_id'], 'part_number' => $r['part_number'],
                            'pcs_per_box' => $r['pcs_per_box'], 'box_quantity' => $r['after']]);
                    }
                    ExportFulfillmentBox::whereIn('export_planned_pallet_item_id', $rows->pluck('id')->all())->update(['export_planned_pallet_item_id' => $item->id]);
                    foreach ($rows as $old) {
                        if ($old->id !== $item->id) {
                            $old->delete();
                        }
                    }
                    $mergedItems->put($key, $item);
                }
                ExportPalletLocationReservation::create(['master_location_id' => $location->id, 'export_pallet_plan_id' => $plan->id,
                    'export_planned_pallet_id' => $target->id, 'created_at' => now()]);
                foreach ($c['pallets'] as $pallet) {
                    if ($pallet->id !== $target->id) {
                        $pallet->delete();
                    }
                }
            }
            foreach ($c['boxes'] as $box) {
                $source = $c['sources']->firstWhere('id', $box->current_pallet_id);
                $f = $box->fulfillment;
                if ($target && ! $f) {
                    $item = $mergedItems->get(ExportBoxRequirements::key($box->part_setting_id, $box->pcs_quantity));
                    $f = ExportFulfillmentBox::create(['export_stock_box_id' => $box->id, 'box_number' => $box->box_number,
                        'export_planned_pallet_item_id' => $item->id, 'part_setting_id' => $box->part_setting_id,
                        'pcs_quantity' => $box->pcs_quantity, 'first_received_at' => $box->first_received_at,
                        'reserved_at' => now(), 'reserved_by' => $actor->id]);
                }
                $wasMixed = $source->boxes->pluck('part_setting_id')->unique()->count() > 1;
                if ($f && ! $f->placed_at && $f->allocation_notice_id && $wasMixed) {
                    $f->update(['picking_required' => true]);
                }
                if ($target && $f) {
                    $mustScan = ! $f->placed_at && $f->allocation_notice_id && ($f->picking_required || $wasMixed);
                    if ($mustScan) {
                        $f->update(['merge_pending_verification' => true]);
                    } else {
                        $f->update(['placed_at' => now(), 'placed_by' => $actor->id, 'placed_location_id' => $location->id]);
                    }
                }
                ExportStockMovement::create(['export_stock_box_id' => $box->id, 'request_token' => (string) Str::uuid(),
                    'request_hash' => $hash, 'from_pallet_id' => $source->id, 'to_pallet_id' => $result->id,
                    'export_fulfillment_box_id' => $f?->id, 'from_location_code' => $source->location_code,
                    'to_location_code' => $location->code, 'placed_by' => $actor->id, 'placed_at' => now()]);
                $box->update(['current_pallet_id' => $result->id, 'revision' => $box->revision + 1, 'stored_at' => now(), 'stored_by' => $actor->id]);
            }
            // Invalidate old placement forms and certificates, including reservations physically elsewhere.
            foreach ($c['commitments'] as $old) {
                $f = $old->fresh();
                if (! $f->placed_at) {
                    $f->update(['picking_verified_at' => null, 'picking_verified_by' => null, 'picking_verified_revision' => null, 'picking_source_pallet_id' => null]);
                    if (! $c['boxes']->contains('id', $f->export_stock_box_id)) {
                        $f->stockBox->increment('revision');
                    }
                }
            }
            foreach ($sourceLocations as $oldId) {
                ExportMasterLocation::whereKey($oldId)->update(['is_occupied' => (int) $oldId === $location->id]);
            }
            $location->update(['is_occupied' => true]);
            app(ExportPickingService::class)->requireForSource($result->id);
            foreach (ExportNotFullRequest::where('status', 'prepared')->whereIn('preparation->source_box_id', $c['boxes']->pluck('id'))->get() as $request) {
                $preparationBefore = $request->toArray();
                $request->update(['status' => 'waiting', 'preparation' => null, 'prepared_by' => null, 'revision' => $request->revision + 1]);
                AuditService::record('export_not_full', 'updated', 'ExportNotFullRequest', $request->id, $actor->id,
                    $preparationBefore, $request->fresh()->toArray(), 'Sumber Not Full berpindah melalui merge; data wajib disiapkan dan disetujui ulang.');
            }
            if ($plan) {
                $this->updateDelivery($delivery, $plan, $actor);
                $plan->update(['revision' => $plan->revision + 1, 'delivery_revision' => $delivery->revision, 'updated_by' => $actor->id]);
                $unreviewed = \App\Models\ExportStockAllocationNotice::where('export_pallet_plan_id', $plan->id)->whereNull('acknowledged_at')->pluck('id');
                if ($unreviewed->isNotEmpty()) {
                    $notice = app(ExportStockAllocationNoticeService::class)->create($plan, $actor);
                    ExportFulfillmentBox::whereIn('allocation_notice_id', $unreviewed)->update(['allocation_notice_id' => $notice->id]);
                }
                app(ExportNotFullService::class)->sync($delivery, $actor);
                foreach ($plan->fresh()->pallets as $pallet) {
                    app(ExportFulfillmentRevisionService::class)->contents($pallet);
                }
            }
            $snapshot = ['result_pallet_id' => $result->id, 'pallet_number' => $target?->position, 'location_code' => $location->code,
                'plan_code' => $delivery?->plan_code, 'delivery_id' => $delivery?->id, 'plan_revision' => $plan?->revision,
                'box_quantity' => $c['boxes']->count(), 'required_boxes' => $c['required_boxes'], 'added_boxes' => $c['added_boxes'],
                'spare_mode' => $c['mode'], 'ready_cancelled' => $c['ready_count'], 'requirements' => $c['requirements']->values()->all(),
                'boxes' => $c['boxes']->map(fn ($b) => $b->only(['box_number', 'part_number', 'pcs_quantity', 'first_received_at']) + ['warehouse_location' => $location->code])->all(),
                'planning' => $plan ? app(ExportPalletPlanningService::class)->snapshot($plan) : null];
            $snapshot = json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $mergeId = DB::table('export_pallet_merges')->insertGetId(['request_token' => $data['request_token'], 'request_hash' => $hash,
                'created_by' => $actor->id, 'result_pallet_id' => $result->id, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'created_at' => now()]);
            AuditService::record('export_pallet_merge', 'merged', 'ExportStockPallet', $result->id, $actor->id, $before,
                $snapshot + ['merge_id' => $mergeId], 'Merge fisik pallet ekspor; reservasi, komposisi box dan tanggal FIFO dipertahankan. Cetak ulang panduan revisi terbaru.');

            return $snapshot;
        });
    }

    private function updateDelivery(ExportDeliveryPlan $delivery, $plan, User $actor): void
    {
        $rows = ExportPlannedPalletItem::whereHas('pallet', fn ($p) => $p->where('export_pallet_plan_id', $plan->id))->get();
        foreach ($rows->groupBy('part_setting_id') as $partId => $items) {
            $line = $delivery->items()->where('part_setting_id', $partId)->first();
            if (! $line) {
                $part = PartSetting::findOrFail($partId);
                $line = $delivery->items()->create(['part_setting_id' => $partId, 'position' => ($delivery->items()->max('position') ?? 0) + 1,
                    'part_number' => $part->part_number, 'pcs_per_box' => max($part->qty_box, $items->max('pcs_per_box')),
                    'pcs_quantity' => 0, 'box_quantity' => 0, 'not_full_pcs' => 0]);
            }
            ExportBoxRequirements::updateComposition($line, $items->groupBy('pcs_per_box')->map(fn ($g, $pcs) => ['pcs_per_box' => (int) $pcs, 'box_quantity' => $g->sum('box_quantity')])->values()->all());
        }
        $delivery->load('items');
        $delivery->update(['revision' => $delivery->revision + 1, 'total_boxes' => $delivery->items->sum('box_quantity'),
            'total_pcs' => $delivery->items->sum('pcs_quantity'), 'updated_by' => $actor->id]);
    }
}
