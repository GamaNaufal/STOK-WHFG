<?php

namespace App\Services;

use App\Models\ExportFulfillmentBox;
use App\Models\ExportPickingIssue;
use App\Models\ExportPickingScan;
use App\Models\ExportPlannedPallet;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportPickingService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['picking' => $message]);
    }

    public function pending(): Builder
    {
        return ExportFulfillmentBox::whereNull('placed_at')->whereHas('stockBox', fn ($b) => $b->whereNotNull('current_pallet_id')->queueReady())
            ->whereHas('plannedItem.pallet.palletPlan', fn ($p) => $p->where('status', 'final')
                ->whereHas('deliveryPlan', fn ($d) => $d->whereColumn('export_delivery_plans.revision', 'export_pallet_plans.delivery_revision')));
    }

    public function required(): Builder
    {
        return $this->pending()->requiresPickingScan();
    }

    public function tasks(): Builder
    {
        $groups = $this->pending()->whereNotNull('export_fulfillment_boxes.allocation_notice_id')
            ->join('export_planned_pallet_items as picking_item', 'picking_item.id', '=', 'export_fulfillment_boxes.export_planned_pallet_item_id')
            ->selectRaw('picking_item.export_planned_pallet_id, export_fulfillment_boxes.allocation_notice_id as notice_id, COUNT(*) as pending_box_count')
            ->groupBy('picking_item.export_planned_pallet_id', 'export_fulfillment_boxes.allocation_notice_id');

        return ExportPlannedPallet::join('export_pallet_plans as picking_plan', 'picking_plan.id', '=', 'export_planned_pallets.export_pallet_plan_id')
            ->join('export_delivery_plans as picking_delivery', 'picking_delivery.id', '=', 'picking_plan.export_delivery_plan_id')
            ->joinSub($groups, 'picking_group', fn ($join) => $join->on('picking_group.export_planned_pallet_id', '=', 'export_planned_pallets.id'))
            ->select('export_planned_pallets.*', 'picking_group.notice_id', 'picking_group.pending_box_count')
            ->with('palletPlan.deliveryPlan')->withCount(['fulfillmentBoxes as required_scan_count' => fn ($f) => $f->whereColumn('export_fulfillment_boxes.allocation_notice_id', 'picking_group.notice_id')
            ->whereIn('export_fulfillment_boxes.id', $this->required()->select('export_fulfillment_boxes.id'))])
            ->orderBy('picking_delivery.delivery_deadline')->orderBy('picking_delivery.id')->orderBy('export_planned_pallets.position')->orderBy('picking_group.notice_id');
    }

    public function rows(int $palletId, ?int $noticeId = null, bool $recommendationsOnly = false): Collection
    {
        return $this->enrich($this->pending()->whereHas('plannedItem', fn ($i) => $i->where('export_planned_pallet_id', $palletId))
            ->when($noticeId !== null, fn ($query) => $query->where('allocation_notice_id', $noticeId))
            ->when($recommendationsOnly, fn ($query) => $query->whereNotNull('allocation_notice_id'))
            ->with(['stockBox.currentPallet.plannedPallet', 'plannedItem.pallet'])->orderBy('first_received_at')->orderBy('id')->get());
    }

    public function enrich(Collection $rows): Collection
    {
        (new \Illuminate\Database\Eloquent\Collection($rows->all()))->loadMissing('stockBox.currentPallet.plannedPallet');
        $required = ExportFulfillmentBox::whereKey($rows->pluck('id')->all())->requiresPickingScan()->pluck('id')->flip();
        foreach ($rows as $f) {
            $f->setAttribute('requires_scan', $required->has($f->id));
            $f->setAttribute('picking_ready', $this->verified($f));
            $f->syncOriginalAttributes(['requires_scan', 'picking_ready']);
        }

        return $rows;
    }

    public function verified(ExportFulfillmentBox $f): bool
    {
        $box = $f->stockBox;

        return $box && $f->picking_verified_at && (int) $f->picking_source_pallet_id === (int) $box->current_pallet_id
            && $f->picking_verified_revision === $box->revision && (int) $f->part_setting_id === (int) $box->part_setting_id
            && $f->pcs_quantity === $box->pcs_quantity;
    }

    public function startFingerprint(Collection $rows): string
    {
        return hash('sha256', json_encode($rows->sortBy('id')->map(fn ($row) => [
            $row->id, $row->stockBox->revision, $row->stockBox->current_pallet_id,
            $row->plannedItem->pallet->fulfillment_revision,
        ])->values()->all(), JSON_THROW_ON_ERROR));
    }

    public function start(int $palletId, int $noticeId, string $fingerprint, User $actor): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($palletId, $noticeId, $fingerprint, $actor) {
            $this->assertTarget(ExportPlannedPallet::findOrFail($palletId));
            $rows = $this->rows($palletId, $noticeId, recommendationsOnly: true);
            foreach ($rows as $row) {
                $row->stockBox->assertEligible();
            }
            if ($rows->isEmpty() || ! hash_equals($this->startFingerprint($rows), $fingerprint)) {
                $this->fail('Daftar atau lokasi picking berubah. Muat ulang panduan sebelum mulai.');
            }
            if (ExportPickingIssue::open()->where(fn ($query) => $query->where('reported_by', $actor->id)
                ->orWhere('export_planned_pallet_id', $palletId)->orWhereIn('box_number', $rows->pluck('box_number')))->exists()) {
                $this->fail('Selesaikan issue picking sebelum mulai.');
            }
            $ids = $rows->whereNull('picking_started_at')->pluck('id');
            if ($ids->isNotEmpty()) {
                ExportFulfillmentBox::whereKey($ids->all())->update(['picking_started_at' => now()]);
                AuditService::record('export_picking', 'started', 'ExportPlannedPallet', $palletId, $actor->id,
                    [], ['allocation_notice_id' => $noticeId, 'box_count' => $ids->count()], 'Picking ekspor dimulai.');
            }
        });
    }

    public function block(User $actor, ?int $palletId = null, ?string $boxNumber = null): ?ExportPickingIssue
    {
        return ExportPickingIssue::open()->where(function ($q) use ($actor, $palletId, $boxNumber) {
            $q->where('reported_by', $actor->id);
            if ($palletId) {
                $q->orWhere('export_planned_pallet_id', $palletId);
            }
            if ($boxNumber) {
                $q->orWhere('box_number', $boxNumber);
            }
        })->orderBy('id')->first();
    }

    public function assertReceiptAllowed(string $number): void
    {
        if (ExportPickingIssue::open()->where('box_number', $number)->exists()) {
            $this->fail('Box '.$number.' sedang ditahan oleh issue picking ekspor. Selesaikan approval dan konfirmasi pengembaliannya.');
        }
    }

    public function assertCanPlace(ExportStockBox $box, ?ExportFulfillmentBox $f, User $actor): void
    {
        $palletId = $f?->plannedItem?->export_planned_pallet_id;
        if ($issue = $this->block($actor, $palletId, $box->box_number)) {
            $this->fail('Pengambilan diblokir oleh issue ekspor #'.$issue->id.'. Approval admin dan konfirmasi penempatan pengembalian harus diselesaikan dahulu.');
        }
        if (! $f || $f->allocation_notice_id === null || ! $box->current_pallet_id) {
            return;
        }
        $f->setRelation('stockBox', $box);
        if ($this->verified($f)) {
            return;
        }
        if (ExportFulfillmentBox::whereKey($f->id)->requiresPickingScan()->exists()) {
            $this->fail('Scan verifikasi pengambilan box '.$box->box_number.' sebelum konfirmasi penempatan.');
        }
        if (! $f->picking_started_at) {
            $this->fail('Klik Mulai Picking pada transaksi rekomendasi sebelum mengambil dan menempatkan box.');
        }
    }

    public function requireForSource(int $palletId): void
    {
        if (! ExportStockPallet::whereKey($palletId)->mixed()->exists()) {
            return;
        }
        ExportFulfillmentBox::whereNull('placed_at')->whereNotNull('allocation_notice_id')->whereHas('stockBox', fn ($b) => $b->where('current_pallet_id', $palletId))
            ->update(['picking_required' => true]);
    }

    private function assertTarget(ExportPlannedPallet $pallet): void
    {
        $plan = $pallet->palletPlan;
        $delivery = $plan?->deliveryPlan;
        if (! $delivery || ! $plan->isFinalFor($delivery)) {
            $this->fail('Rencana pallet berubah atau belum final. Muat ulang rekomendasi.');
        }
    }

    // The helper never creates an issue, certifies a pick, or changes inventory.
    public function seek(int $palletId, string $number, ?int $noticeId = null): array
    {
        $pallet = ExportPlannedPallet::findOrFail($palletId);
        $this->assertTarget($pallet);
        $rows = $this->rows($palletId, $noticeId, recommendationsOnly: true);
        if ($rows->isEmpty()) {
            return ['success' => false, 'blocked' => false, 'message' => 'Sesi Cari Box sudah tidak aktif. Buka antrean rekomendasi yang masih menunggu penempatan.'];
        }
        $row = $rows->firstWhere('box_number', $number);
        if (! $row) {
            return ['success' => false, 'blocked' => false, 'message' => 'NG · Box tidak termasuk rekomendasi pengambilan ini. Silakan scan ulang.'];
        }
        $source = $row->stockBox->currentPallet;
        $row->stockBox->assertEligible();

        return ['success' => true, 'blocked' => false, 'message' => 'OK · '.$number.' · '.$row->stockBox->part_number.' · ambil dari '.$source->display_name.' di lokasi '.$source->location_code.'.',
            'box_number' => $number, 'source' => $source->display_name.' · '.$source->location_code];
    }

    public function scan(int $palletId, array $data, User $actor, ?int $noticeId = null): array
    {
        return app(ExportInventoryService::class)->transaction(function () use ($palletId, $data, $actor, $noticeId) {
            $replay = ExportPickingScan::where('request_token', $data['request_token'])->first();
            if ($replay) {
                if ((int) $replay->created_by !== $actor->id || (int) $replay->export_planned_pallet_id !== $palletId || $replay->box_number !== $data['box_number']) {
                    $this->fail('Token scan sudah dipakai untuk permintaan lain.');
                }
                $replayNotice = $replay->fulfillmentBox?->allocation_notice_id ?? data_get($replay->issue?->context, 'allocation_notice_id');
                if ($noticeId !== null && (int) $replayNotice !== $noticeId) {
                    $this->fail('Token scan berasal dari transaksi rekomendasi lain atau sudah kedaluwarsa.');
                }

                return ['success' => $replay->outcome !== 'wrong', 'blocked' => $replay->issue && ! $replay->issue->isClosed(), 'message' => 'Permintaan scan sebelumnya sudah tercatat.', 'replay' => true];
            }
            $pallet = ExportPlannedPallet::findOrFail($palletId);
            $this->assertTarget($pallet);
            if ($issue = $this->block($actor, $palletId)) {
                return ['success' => false, 'blocked' => true, 'message' => 'Proses diblokir oleh issue #'.$issue->id.'. Selesaikan approval dan konfirmasi pengembalian.'];
            }
            $rows = $this->rows($palletId, $noticeId);
            if (! $rows->contains('requires_scan', true)) {
                $this->fail('Tidak ada pengambilan yang perlu diverifikasi pada tujuan ini.');
            }
            $row = $rows->firstWhere('box_number', $data['box_number']);
            if ($row) {
                $box = $row->stockBox;
                $box->assertEligible();
                if ($box->state !== 'stored' || ! $box->currentPallet || $box->currentPallet->retired_at
                    || (int) $row->part_setting_id !== (int) $box->part_setting_id || $row->pcs_quantity !== $box->pcs_quantity) {
                    $this->fail('Identitas atau posisi box berubah. Periksa kembali stok dan rekomendasi.');
                }
                if ($row->requires_scan) {
                    ExportFulfillmentBox::whereKey($row->id)->update(['picking_required' => true, 'picking_source_pallet_id' => $box->current_pallet_id,
                        'picking_verified_revision' => $box->revision, 'picking_verified_at' => now(), 'picking_verified_by' => $actor->id]);
                }
                $outcome = 'verified';
                $issue = null;
                $message = 'OK · Box '.$box->box_number.' sesuai rekomendasi. Tujuan Pallet '.$pallet->position.' · '.$pallet->location_code.'.';
            } else {
                $box = ExportStockBox::with('currentPallet.plannedPallet')->where('box_number', $data['box_number'])->first();
                $source = $box?->currentPallet;
                $knownSource = $source && ! $source->retired_at && $source->master_location_id;
                $issue = ExportPickingIssue::create(['export_planned_pallet_id' => $palletId, 'export_stock_box_id' => $box?->id,
                    'box_number' => $data['box_number'], 'reported_by' => $actor->id,
                    'issue_type' => ! $box ? 'unknown_box' : ($knownSource ? 'wrong_box' : 'missing_location'),
                    'return_pallet_id' => $knownSource ? $source->id : null, 'return_location_id' => $knownSource ? $source->master_location_id : null,
                    'return_location_code' => $knownSource ? $source->location_code : null, 'registration_token' => (string) Str::uuid(),
                    'context' => ['plan_code' => $pallet->palletPlan->deliveryPlan->plan_code, 'customer' => $pallet->palletPlan->deliveryPlan->customer_name,
                        'allocation_notice_id' => $noticeId,
                        'target_pallet' => $pallet->position, 'target_location' => $pallet->location_code,
                        'source_pallet' => $knownSource ? $source->display_name : null, 'box_revision' => $box?->revision]]);
                $box?->fulfillment?->update(['picking_verified_at' => null, 'picking_verified_by' => null, 'picking_verified_revision' => null]);
                $outcome = 'wrong';
                $message = 'NG · Proses diblokir. '.($knownSource ? 'Kembalikan box ke '.$source->display_name.' · '.$source->location_code.'. ' : 'Admin harus melengkapi data dan tujuan pengembalian. ').'Tunggu approval admin, kemudian konfirmasikan penempatan pengembalian.';
            }
            $scan = ExportPickingScan::create(['request_token' => $data['request_token'], 'export_planned_pallet_id' => $palletId,
                'export_fulfillment_box_id' => $row?->id, 'export_picking_issue_id' => $issue?->id,
                'created_by' => $actor->id, 'box_number' => $data['box_number'], 'outcome' => $outcome]);
            AuditService::record('export_picking', $outcome, 'ExportPickingScan', $scan->id, $actor->id, [],
                ['box_number' => $scan->box_number, 'pallet_id' => $palletId, 'allocation_notice_id' => $noticeId, 'issue_id' => $issue?->id], $message);

            return ['success' => ! $issue, 'blocked' => (bool) $issue, 'message' => $message];
        });
    }
}
