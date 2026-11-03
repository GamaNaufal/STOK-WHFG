<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportNotFullRequest;
use App\Models\ExportPickingIssue;
use App\Models\ExportPlannedPalletItem;
use App\Models\ExportStockBox;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportNotFullService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['not_full' => $message]);
    }

    public function pending(): \Illuminate\Database\Eloquent\Builder
    {
        return ExportNotFullRequest::where('status', '!=', 'cancelled')
            ->whereHas('delivery', fn ($q) => $q->whereNull('export_delivery_plans.deleted_at')->whereNotIn('status', ['completed', 'cancelled']))
            ->whereHas('item', fn ($q) => $q->where('not_full_pcs', '>', 0))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('export_fulfillment_boxes as f')
                ->join('export_planned_pallet_items as i', 'i.id', '=', 'f.export_planned_pallet_item_id')
                ->join('export_planned_pallets as p', 'p.id', '=', 'i.export_planned_pallet_id')
                ->join('export_pallet_plans as plan', 'plan.id', '=', 'p.export_pallet_plan_id')
                ->join('export_stock_boxes as b', 'b.id', '=', 'f.export_stock_box_id')
                ->whereColumn('plan.export_delivery_plan_id', 'export_not_full_requests.export_delivery_plan_id')->where('plan.status', 'final')
                ->whereColumn('i.part_setting_id', 'export_not_full_requests.part_setting_id')->whereColumn('i.pcs_per_box', 'export_not_full_requests.pcs_quantity')
                ->whereNotNull('f.placed_at')->whereNotNull('f.placed_by')->whereColumn('f.placed_location_id', 'p.master_location_id')
                ->whereColumn('b.pcs_quantity', 'i.pcs_per_box')->whereColumn('b.part_setting_id', 'i.part_setting_id')
                ->whereNull('b.deleted_at')->where('b.state', 'stored'));
    }

    private function useExisting(ExportNotFullRequest $r, User $actor): bool
    {
        $r->load('item', 'delivery.palletPlan');
        $target = $this->target($r);
        if (! $target || ! $target->fulfillmentBoxes()->exists()) {
            return false;
        }
        if ($r->status === 'prepared') {
            $before = $r->toArray();
            $r->update(['status' => 'waiting', 'preparation' => null, 'prepared_by' => null, 'revision' => $r->revision + 1]);
            $this->audit($r, 'stock_reused', $actor, $before, 'Stok yang sesuai sudah direservasi. Penyiapan box baru dilepas; konfirmasi penempatan melalui antrean existing.');
        }

        return true;
    }

    /** One current request per delivery line. The audit retains superseded preparations/approvals. */
    public function sync(ExportDeliveryPlan $delivery, User $actor): void
    {
        $items = $delivery->items()->get();
        $existing = ExportNotFullRequest::where('export_delivery_plan_id', $delivery->id)->get();
        foreach ($existing as $request) {
            $item = $items->firstWhere('id', $request->export_delivery_plan_item_id);
            if (! $item || ! $item->not_full_pcs || $delivery->trashed() || $delivery->status === 'cancelled') {
                if ($request->status !== 'cancelled') {
                    $before = $request->toArray();
                    $request->update(['status' => 'cancelled', 'revision' => $request->revision + 1, 'preparation' => null]);
                    $this->audit($request, 'cancelled', $actor, $before, 'Kebutuhan Not Full dibatalkan setelah revisi/arsip rencana.');
                }
            }
        }
        if ($delivery->trashed() || $delivery->status === 'cancelled') {
            return;
        }
        foreach ($items->where('not_full_pcs', '>', 0) as $item) {
            $request = $existing->firstWhere('export_delivery_plan_item_id', $item->id);
            $changed = ! $request || $request->pcs_quantity !== $item->not_full_pcs || $request->fixed_qty !== $item->pcs_per_box
                || $request->status === 'cancelled' || ($request->status === 'prepared' && ($request->preparation['delivery_revision'] ?? 0) !== $delivery->revision);
            if (! $changed) {
                continue;
            }
            $before = $request?->toArray() ?? [];
            $request ??= new ExportNotFullRequest(['export_delivery_plan_id' => $delivery->id, 'export_delivery_plan_item_id' => $item->id, 'requested_by' => $actor->id, 'revision' => 0]);
            $request->fill(['part_setting_id' => $item->part_setting_id, 'part_number' => $item->part_number,
                'pcs_quantity' => $item->not_full_pcs, 'fixed_qty' => $item->pcs_per_box, 'status' => 'waiting',
                'revision' => $request->revision + 1, 'preparation' => null, 'prepared_by' => null,
                'approved_by' => null, 'approved_at' => null, 'export_stock_box_id' => null])->save();
            $this->audit($request, $before ? 'updated' : 'created', $actor, $before, 'Request wajib Not Full ekspor dicatat dari sisa PCS rencana delivery.');
        }
    }

    private function audit(ExportNotFullRequest $request, string $action, User $actor, array $before, string $description): void
    {
        AuditService::record('export_not_full', $action, 'ExportNotFullRequest', $request->id, $actor->id,
            $before, $request->fresh()->toArray(), $description);
    }

    /** The destination is read from a final plan; it cannot be overridden by a request payload. */
    public function target(ExportNotFullRequest $request): ?ExportPlannedPalletItem
    {
        if ($request->status === 'cancelled' || ! $request->item || $request->item->not_full_pcs !== $request->pcs_quantity
            || $request->pcs_quantity < 1 || $request->pcs_quantity >= $request->fixed_qty) {
            return null;
        }
        $delivery = $request->delivery;
        $plan = $delivery?->palletPlan;
        if (! $delivery || $delivery->trashed() || ! $plan?->isFinalFor($delivery) || $plan->completed_at) {
            return null;
        }

        return ExportPlannedPalletItem::with('pallet')->where('part_setting_id', $request->part_setting_id)
            ->where('pcs_per_box', $request->pcs_quantity)->where('box_quantity', 1)
            ->whereHas('pallet', fn ($q) => $q->where('export_pallet_plan_id', $plan->id))->first();
    }

    public function state(ExportNotFullRequest $request, ?ExportPlannedPalletItem $target): string
    {
        if ($request->status === 'cancelled') {
            return 'Dibatalkan';
        }
        if ($target) {
            $f = $target->fulfillmentBoxes()->first();
            if ($f) {
                return app(ExportFulfillmentService::class)->boxState($f) === 'placed' ? 'Terpenuhi' : 'Direservasi · menunggu penempatan';
            }
        }

        return match ($request->status) {
            'prepared' => 'Menunggu approval', 'rejected' => 'Ditolak · wajib disiapkan kembali',
            'approved' => 'Approval tercatat', default => $target ? 'Menunggu penyiapan' : 'Menunggu perencanaan pallet final'
        };
    }

    public function source(ExportNotFullRequest $request): ?ExportStockBox
    {
        return ExportStockBox::with('currentPallet.location')->where('part_setting_id', $request->part_setting_id)
            ->where('pcs_quantity', $request->fixed_qty)->where('is_not_full', false)->where('state', 'stored')
            ->whereNotNull('stored_at')->whereNotNull('stored_by')->whereDoesntHave('fulfillment')
            ->whereHas('currentPallet', fn ($q) => $q->whereNull('retired_at')->whereNotNull('master_location_id'))
            ->whereHas('currentPallet.location', fn ($q) => $q->where('is_occupied', true)->whereNull('current_pallet_id')->whereDoesntHave('stockLocations'))
            ->whereDoesntHave('currentPallet.plannedPallet', fn ($q) => $q->whereNotNull('ready_at'))
            ->whereNotIn('box_number', ExportPickingIssue::open()->select('box_number'))
            ->orderBy('first_received_at')->orderBy('id')->first();
    }

    private function context(ExportNotFullRequest $request): ExportPlannedPalletItem
    {
        $request->load('item', 'delivery.palletPlan');
        $target = $this->target($request);
        if (! $target || $target->pallet->ready_at) {
            $this->fail('Box Not Full hanya dapat dibuat untuk pallet final yang belum dikonfirmasi siap.');
        }
        if ($target->fulfillmentBoxes()->exists()) {
            $this->fail('Kebutuhan ini sudah memiliki box direservasi/ditempatkan. Ikuti antrean penempatan; tidak perlu membuat box baru.');
        }

        return $target;
    }

    private function stamp(ExportNotFullRequest $request, ExportPlannedPalletItem $target): array
    {
        return ['delivery_revision' => $request->delivery->revision, 'plan_revision' => $request->delivery->palletPlan->revision,
            'pallet_revision' => $target->pallet->fulfillment_revision, 'planned_item_id' => $target->id, 'location_id' => $target->pallet->master_location_id,
            'pallet_number' => $target->pallet->position, 'location_code' => $target->pallet->location_code];
    }

    private function assertNewNumber(string $number, int $requestId): void
    {
        if (! preg_match('/^[0-9]{8}$/D', $number)) {
            $this->fail('ID box baru wajib tepat 8 angka.');
        }
        app(ExportPickingService::class)->assertReceiptAllowed($number);
        if (\App\Support\BoxIdentity::unavailable($number,$requestId) || ExportStockBox::withTrashed()->where('box_number', $number)->exists()
            || ExportNotFullRequest::whereKeyNot($requestId)->where('status', 'prepared')->where('preparation->box_number', $number)->exists()) {
            $this->fail('ID box sudah dipakai pada domestik/ekspor atau sedang disiapkan request lain.');
        }
    }

    public function prepare(int $id, array $data, User $actor): void
    {
        abort_unless(in_array($actor->role, ['admin', 'admin_warehouse'], true) && $actor->canAccess('not_full_request'), 403);
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            // Reserve full-box demand first. Preparation never holds a full box away from delivery reservations.
            app(ExportStockAllocationService::class)->allocate($actor);
            $r = ExportNotFullRequest::lockForUpdate()->findOrFail($id);
            if ($r->revision !== (int) $data['revision']) {
                $this->fail('Request berubah. Muat ulang sebelum menyiapkan.');
            }
            if ($this->useExisting($r, $actor)) {
                return;
            }
            $target = $this->context($r);
            $this->assertNewNumber($data['box_number'], $r->id);
            if (! in_array($data['mode'], ['external', 'split'], true)) {
                $this->fail('Pilih asal box Not Full.');
            }
            $source = $data['mode'] === 'split' ? $this->source($r) : null;
            if ($data['mode'] === 'split' && ! $source) {
                $this->fail('Tidak tersedia box penuh part ini yang bebas setelah reservasi kebutuhan penuh. Pilih opsi datang sudah Not Full atau tunggu stok.');
            }
            $before = $r->toArray();
            $r->update(['status' => 'prepared', 'prepared_by' => $actor->id, 'revision' => $r->revision + 1,
                'preparation' => $this->stamp($r, $target) + ['mode' => $data['mode'], 'box_number' => $data['box_number'],
                    'source_box_id' => $source?->id, 'source_revision' => $source?->revision, 'source_box_number' => $source?->box_number,
                    'source_location' => $source?->currentPallet?->location_code, 'notes' => $data['notes']], 'notes' => $data['notes']]);
            $this->audit($r, 'prepared', $actor, $before, 'Data Not Full disiapkan; box sumber tetap tunduk prioritas reservasi penuh dan diperiksa ulang saat approval.');
        });
    }

    public function approve(int $id, array $data, User $actor): void
    {
        abort_unless(in_array($actor->role, ['admin', 'supervisi'], true) && $actor->canAccess('not_full_approval'), 403);
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $r = ExportNotFullRequest::lockForUpdate()->findOrFail($id);
            if ($r->status === 'approved' && $r->revision === (int) $data['revision'] + 1) {
                return;
            }
            if ($r->status !== 'prepared' || $r->revision !== (int) $data['revision']) {
                $this->fail('Data approval berubah atau belum disiapkan. Muat ulang request.');
            }
            if (! in_array($data['physical_confirmed'] ?? null, [true, 1, '1', 'on'], true)) {
                $this->fail('Konfirmasikan box dan penempatan fisik sesuai rincian sebelum approval.');
            }
            app(ExportStockAllocationService::class)->allocate($actor);
            if ($this->useExisting($r, $actor)) {
                return;
            }
            $target = $this->context($r);
            $p = $r->preparation;
            foreach ($this->stamp($r, $target) as $key => $value) {
                if (($p[$key] ?? null) !== $value) {
                    $this->fail('Rencana/lokasi berubah. Siapkan ulang data Not Full untuk tujuan terbaru.');
                }
            }
            $this->assertNewNumber($p['box_number'], $r->id);
            $source = $p['mode'] === 'split' ? $this->source($r) : null;
            if ($p['mode'] === 'split' && (! $source || $source->id !== $p['source_box_id'] || $source->revision !== $p['source_revision'])) {
                $this->fail('Sumber FIFO berubah atau sudah direservasi untuk box penuh. Siapkan ulang; sumber yang terikat tidak boleh dipecah.');
            }
            $before = $r->toArray();
            $sourceBefore = $source?->toArray();
            if ($source) {
                app(ExportFulfillmentCompletionService::class)->assertPalletUnlocked($source->currentPallet);
                $source->update(['receipt_pcs_quantity' => $source->receipt_pcs_quantity ?? $source->pcs_quantity,
                    'pcs_quantity' => $source->pcs_quantity - $r->pcs_quantity, 'standard_pcs_quantity' => $r->fixed_qty,
                    'is_not_full' => true, 'revision' => $source->revision + 1]);
            }
            $box = ExportStockBox::create(['box_number' => $p['box_number'], 'part_setting_id' => $r->part_setting_id,
                'part_number' => $r->part_number, 'pcs_quantity' => $r->pcs_quantity, 'standard_pcs_quantity' => $r->fixed_qty,
                'receipt_pcs_quantity' => $source ? 0 : $r->pcs_quantity, 'origin' => $source ? 'split' : 'external',
                'source_box_id' => $source?->id, 'is_not_full' => true, 'state' => 'received', 'revision' => 1,
                'receive_token' => (string) Str::uuid(), 'first_received_at' => $source?->first_received_at ?? now(), 'received_by' => $actor->id]);
            ExportFulfillmentBox::create(['export_stock_box_id' => $box->id, 'box_number' => $box->box_number,
                'export_planned_pallet_item_id' => $target->id, 'part_setting_id' => $r->part_setting_id,
                'pcs_quantity' => $box->pcs_quantity, 'first_received_at' => $box->first_received_at, 'reserved_at' => now(), 'reserved_by' => $actor->id]);
            app(ExportStockReceivingService::class)->place($box->id, ['revision' => 1, 'request_token' => (string) Str::uuid()], $actor);
            $r->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(),
                'export_stock_box_id' => $box->id, 'revision' => $r->revision + 1]);
            $this->audit($r, 'approved', $actor, $before, 'Not Full ekspor disetujui, menjadi stok dan ditempatkan pada pallet rencana.');
            if ($source) {
                AuditService::record('export_not_full', 'split', 'ExportStockBox', $source->id, $actor->id,
                    $sourceBefore, $source->fresh()->toArray() + ['new_box_number' => $box->box_number, 'split_pcs' => $box->pcs_quantity],
                    'Box penuh FIFO dipecah. Sisa memakai ID/lokasi asal; kedua box mewarisi tanggal penerimaan sumber.');
            }
            app(ExportStockAllocationService::class)->allocate($actor, [$r->part_setting_id]);
        });
    }

    public function reject(int $id, array $data, User $actor): void
    {
        abort_unless(in_array($actor->role, ['admin', 'supervisi'], true) && $actor->canAccess('not_full_approval'), 403);
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $r = ExportNotFullRequest::lockForUpdate()->findOrFail($id);
            if ($r->status !== 'prepared' || $r->revision !== (int) $data['revision']) {
                $this->fail('Request berubah atau sudah diproses.');
            }
            $before = $r->toArray();
            $r->update(['status' => 'rejected', 'notes' => $data['reason'], 'preparation' => null, 'revision' => $r->revision + 1,
                'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit($r, 'rejected', $actor, $before, 'Penyiapan Not Full ditolak; kebutuhan delivery tetap wajib dipenuhi.');
        });
    }
}
