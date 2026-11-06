<?php

namespace App\Services;

use App\Models\ExportFulfillmentBox;
use App\Models\ExportMasterLocation;
use App\Models\ExportNotFullRequest;
use App\Models\ExportPickingIssue;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ExportStockAdminService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['stock' => $message]);
    }

    private function assertSparePallet(ExportStockPallet $pallet): ExportMasterLocation
    {
        if ($pallet->export_planned_pallet_id) {
            $this->fail('Pallet ini terhubung ke perencanaan. Gunakan alur revisi pallet ekspor.');
        }
        $location = $pallet->location;
        if ($pallet->retired_at || ! $location || ! $location->is_occupied || $location->current_pallet_id
            || $location->code !== $pallet->location_code || $location->stockLocations()->exists() || $location->reservation()->exists()) {
            $this->fail('Pallet cadangan atau lokasi fisiknya sudah berubah. Muat ulang stok ekspor.');
        }

        return $location;
    }

    private function assertMutableBoxes(Collection $boxes, ?ExportStockPallet $pallet): void
    {
        if ($pallet) {
            $this->assertSparePallet($pallet);
            app(ExportPalletMoveService::class)->assertPickingIdle($pallet);
        }
        if ($boxes->contains(fn ($box) => ! in_array($box->state, ['received', 'stored'], true)
            || $box->expired_status === 'handled'
            || (($box->state === 'stored') !== ($box->current_pallet_id !== null)))) {
            $this->fail('Aksi hanya tersedia untuk stok cadangan aktif di luar penanganan QA atau packing.');
        }

        $ids = $boxes->pluck('id');
        if (ExportFulfillmentBox::whereIn('export_stock_box_id', $ids)->exists()) {
            $this->fail('Ada box yang sudah direservasi. Selesaikan revisi atau pemenuhan permintaan dahulu.');
        }
        if (ExportPickingIssue::open()->where(fn ($query) => $query->whereIn('export_stock_box_id', $ids)
            ->orWhereIn('box_number', $boxes->pluck('box_number')))->exists()) {
            $this->fail('Selesaikan issue picking dan pengembaliannya dahulu.');
        }
        if (ExportNotFullRequest::where('status', 'prepared')->whereIn('preparation->source_box_id', $ids)->exists()) {
            $this->fail('Ada box yang sedang disiapkan sebagai sumber Box Not Full.');
        }
    }

    public function boxBlockReason(ExportStockBox $box): ?string
    {
        try {
            $this->assertMutableBoxes(collect([$box]), $box->currentPallet);
        } catch (ValidationException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function inspectPallet(ExportStockPallet $pallet): array
    {
        $boxes = $pallet->boxes()->orderBy('id')->get();
        $deleteBlocked = null;
        try {
            $this->assertMutableBoxes($boxes, $pallet);
        } catch (ValidationException $exception) {
            $deleteBlocked = $exception->getMessage();
        }

        return ['count' => $boxes->count(), 'fingerprint' => $this->fingerprint($pallet, $boxes), 'deleteBlocked' => $deleteBlocked];
    }

    private function fingerprint(ExportStockPallet $pallet, Collection $boxes): string
    {
        return hash('sha256', json_encode([$pallet->id, $pallet->master_location_id, $pallet->box_limit,
            $boxes->map(fn ($box) => [$box->id, $box->revision])->all()], JSON_THROW_ON_ERROR));
    }

    private function assertPalletVersion(ExportStockPallet $pallet, Collection $boxes, string $expected): void
    {
        if (! hash_equals($this->fingerprint($pallet, $boxes), $expected)) {
            $this->fail('Isi, lokasi, atau kapasitas pallet sudah berubah. Tinjau ulang sebelum mengonfirmasi.');
        }
    }

    private function box(int $id, int $revision): ExportStockBox
    {
        $box = ExportStockBox::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($box->revision !== $revision) {
            $this->fail('Data box sudah berubah. Tinjau ulang sebelum mengonfirmasi.');
        }
        $this->assertMutableBoxes(collect([$box]), $box->currentPallet);

        return $box;
    }

    private function snapshot(ExportStockBox $box, ?ExportStockPallet $pallet): array
    {
        return $box->only(['box_number', 'part_setting_id', 'part_number', 'pcs_quantity', 'standard_pcs_quantity', 'state', 'current_pallet_id'])
            + ['first_received_at' => $box->first_received_at->toDateTimeString(), 'warehouse_location' => $pallet?->location_code];
    }

    public function updateBox(int $id, array $data, User $actor): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $box = $this->box($id, (int) $data['revision']);
            if ($box->reactivated_until !== null) {
                $this->fail('Data box hasil packing mengikuti histori QA dan packing.');
            }

            $part = app(ExportStockReceivingService::class)->part(trim($data['part_number']), true);
            $pcs = (int) $data['pcs_quantity'];
            $contentsChanged = (int) $box->part_setting_id !== $part->id || $box->pcs_quantity !== $pcs;
            if ($contentsChanged && ($box->is_not_full || $pcs !== (int) $part->qty_box)) {
                $this->fail('PCS harus sesuai standar part. Perubahan box Not Full mengikuti alur Request Box Not Full.');
            }
            $receivedAt = Carbon::createFromFormat('!Y-m-d\TH:i', $data['first_received_at']);
            if ($receivedAt->isFuture() || $receivedAt->lt(Carbon::create(1970, 1, 2, 0, 0, 0))) {
                $this->fail('Waktu masuk harus valid dan tidak boleh melewati waktu sekarang.');
            }
            $dateChanged = $box->first_received_at->format('Y-m-d\TH:i') !== $data['first_received_at'];
            if (! $contentsChanged && ! $dateChanged) {
                $this->fail('Ubah nomor part, jumlah PCS, atau waktu masuk sebelum menyimpan.');
            }

            $pallet = $box->currentPallet;
            $before = $this->snapshot($box, $pallet);
            if ($contentsChanged) {
                $box->fill(['part_setting_id' => $part->id, 'part_number' => $part->part_number,
                    'pcs_quantity' => $pcs, 'standard_pcs_quantity' => (int) $part->qty_box,
                    'receipt_pcs_quantity' => $box->receipt_pcs_quantity ?? $box->pcs_quantity]);
            }
            if ($dateChanged) {
                $box->first_received_at = $receivedAt;
                $box->expired_status = match ($box->expiry_label) {
                    'Expired' => 'expired', 'Warning' => 'warning', default => 'active',
                };
            }
            $box->revision++;
            $box->save();
            if ($contentsChanged && $pallet) {
                app(ExportPickingService::class)->requireForSource($pallet->id);
            }
            AuditService::record('export_stock_admin', 'updated', 'ExportStockBox', $box->id, $actor->id,
                $before, $this->snapshot($box, $pallet) + ['reason' => $data['reason']], 'Data stok box ekspor dikoreksi.');
        });
    }

    private function removeBox(ExportStockBox $box, ?ExportStockPallet $pallet, string $reason, User $actor): void
    {
        $before = $this->snapshot($box, $pallet);
        $box->update(['state' => 'removed', 'current_pallet_id' => null, 'revision' => $box->revision + 1]);
        $box->delete();
        AuditService::record('export_stock_admin', 'deleted', 'ExportStockBox', $box->id, $actor->id,
            $before, ['state' => 'removed', 'reason' => $reason, 'deleted_at' => $box->deleted_at->toDateTimeString()],
            'Box ekspor dihapus dari stok aktif.');
    }

    private function retire(ExportStockPallet $pallet): void
    {
        $location = $pallet->location;
        $pallet->update(['master_location_id' => null, 'retired_at' => now()]);
        $location->update(['is_occupied' => false]);
    }

    public function deleteBox(int $id, array $data, User $actor): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $box = $this->box($id, (int) $data['revision']);
            $pallet = $box->currentPallet;
            $this->removeBox($box, $pallet, $data['reason'], $actor);
            if ($pallet && ! $pallet->boxes()->exists()) {
                $this->retire($pallet);
            }
        });
    }

    public function updatePalletLimit(int $id, array $data, User $actor): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $pallet = ExportStockPallet::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->assertSparePallet($pallet);
            $boxes = $pallet->boxes()->orderBy('id')->get(['id', 'revision']);
            $this->assertPalletVersion($pallet, $boxes, $data['fingerprint']);
            $limit = (int) $data['box_limit'] ?: null;
            if ($limit !== null && $limit < $boxes->count()) {
                $this->fail('Pallet berisi '.$boxes->count().' box. Isi batas minimal sebesar jumlah box saat ini atau gunakan 0.');
            }
            $before = $pallet->box_limit;
            if ($before === $limit) {
                $this->fail('Batas maksimal box masih sama.');
            }

            $pallet->update(['box_limit' => $limit]);
            AuditService::record('export_stock_admin', 'updated', 'ExportStockPallet', $pallet->id, $actor->id,
                ['box_limit' => $before], ['box_limit' => $limit, 'box_count' => $boxes->count(),
                    'warehouse_location' => $pallet->location_code, 'reason' => $data['reason']], 'Kapasitas pallet cadangan ekspor diperbarui.');
        });
    }

    public function deletePallet(int $id, array $data, User $actor): int
    {
        return app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $pallet = ExportStockPallet::whereKey($id)->lockForUpdate()->firstOrFail();
            $boxes = $pallet->boxes()->orderBy('id')->lockForUpdate()->get();
            $this->assertMutableBoxes($boxes, $pallet);
            $this->assertPalletVersion($pallet, $boxes, $data['fingerprint']);
            foreach ($boxes as $box) {
                $this->removeBox($box, $pallet, $data['reason'], $actor);
            }
            $this->retire($pallet);
            AuditService::record('export_stock_admin', 'deleted', 'ExportStockPallet', $pallet->id, $actor->id,
                ['box_limit' => $pallet->box_limit, 'warehouse_location' => $pallet->location_code, 'box_count' => $boxes->count(),
                    'box_numbers' => $boxes->pluck('box_number')->all()],
                ['reason' => $data['reason'], 'retired_at' => $pallet->retired_at->toDateTimeString()],
                'Pallet cadangan dan seluruh isinya dihapus dari stok aktif.');

            return $boxes->count();
        });
    }
}
