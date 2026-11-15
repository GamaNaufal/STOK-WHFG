<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportNotFullRequest;
use App\Models\ExportPalletPlan;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ExportDeliveryPlanDeletionService
{
    public function preview(int $id, User $actor): array
    {
        abort_unless($actor->canDeleteExportDeliveryPlan(), 403, 'Hanya Admin Utama, Admin Warehouse, dan Supervisi yang dapat menghapus rencana delivery.');

        return app(ExportInventoryService::class)->transaction(function () use ($id, $actor) {
            $delivery = ExportDeliveryPlan::with('items')->lockForUpdate()->findOrFail($id);
            $planning = ExportPalletPlan::where('export_delivery_plan_id', $id)->lockForUpdate()->first();
            $delivery->setRelation('palletPlan', $planning);
            $review = $this->review($delivery, $actor);
            $review['token'] = $review['can_delete'] ? Crypt::encryptString(json_encode([
                'actor' => $actor->id, 'delivery' => $id, 'expires' => now()->addMinutes(15)->timestamp,
                'fingerprint' => $review['fingerprint'], 'request_token' => (string) Str::uuid(),
            ], JSON_THROW_ON_ERROR)) : null;
            $review['delivery'] = $delivery;

            return $review;
        });
    }

    private function review(ExportDeliveryPlan $delivery, User $actor): array
    {
        $planning = $delivery->palletPlan;
        $boxes = $planning ? ExportFulfillmentBox::whereHas('plannedItem.pallet', fn ($q) => $q->where('export_pallet_plan_id', $planning->id))
            ->with(['stockBox.currentPallet', 'plannedItem.pallet'])->orderBy('id')->get() : collect();
        $rows = $boxes->map(fn ($box) => [
            'id' => $box->id, 'box_number' => $box->box_number, 'part_number' => $box->plannedItem->part_number,
            'pcs' => $box->pcs_quantity, 'source' => $box->stockBox?->currentPallet
                ? $box->stockBox->currentPallet->display_name.' · '.$box->stockBox->currentPallet->number : 'Belum ditempatkan',
            'source_location' => $box->stockBox?->currentPallet?->location_code ?? 'Belum ada lokasi tercatat',
            'target' => 'Pallet '.$box->plannedItem->pallet->position, 'target_location' => $box->plannedItem->pallet->location_code,
            'physical_return' => (bool) ($box->placed_at || $box->picking_started_at || $box->picking_verified_at || $box->merge_pending_verification),
            'box_revision' => $box->stockBox?->revision, 'state' => $box->stockBox?->state,
            'current_pallet_id' => $box->stockBox?->current_pallet_id,
        ])->all();
        $locations = $planning ? $planning->reservations()->with('pallet')->orderBy('master_location_id')->get()
            ->map(fn ($claim) => ['id' => $claim->master_location_id, 'pallet' => $claim->pallet?->position, 'location' => $claim->pallet?->location_code])->all() : [];
        $notFull = ExportNotFullRequest::where('export_delivery_plan_id', $delivery->id)->where('status', '!=', 'cancelled')
            ->with('item')->orderBy('id')->get()->map(fn ($request) => [
                'id' => $request->id, 'part_number' => $request->item?->part_number, 'pcs' => $request->pcs_quantity,
                'boxes' => $request->required_boxes, 'revision' => $request->revision, 'status' => $request->status,
                'preparation' => $request->preparation,
            ])->all();
        $reason = $this->blockReason($delivery, $actor);
        $cancellation = null;
        if ($boxes->isNotEmpty() && $delivery->status === 'draft' && $planning?->isFinalFor($delivery) && ! $planning->completed_at
            && $actor->canAccess('export_pallet_cancel') && $actor->canAccess('export_fulfillment_cancel')) {
            try {
                $cancellation = app(ExportFulfillmentCompletionService::class)->cancellation($delivery);
                $reason = $cancellation['returns']->isNotEmpty()
                    ? 'Ada box yang sudah dipicking atau ditempatkan. Selesaikan pengembalian fisik melalui pembatalan permintaan sebelum menghapus rencana.' : null;
                if ($planning->pallets()->whereNotNull('ready_at')->exists()) {
                    $reason = 'Ada pallet yang sudah siap. Buka ulang kesiapan dan tinjau pengembalian fisik terlebih dahulu.';
                }
            } catch (ValidationException $error) {
                $reason = implode(' ', \Illuminate\Support\Arr::flatten($error->errors()));
            }
        } elseif ($boxes->isNotEmpty() && ! $actor->canAccess('export_fulfillment_cancel')) {
            $reason = 'Pelepasan reservasi box memerlukan izin Batalkan seluruh permintaan ekspor.';
        }
        $fingerprint = hash('sha256', json_encode([
            $delivery->id, $delivery->revision, $delivery->status,
            $planning?->only(['id', 'revision', 'status', 'completed_at']), $rows, $locations, $notFull,
            $cancellation['fingerprint'] ?? null,
        ], JSON_THROW_ON_ERROR));

        return ['rows' => $rows, 'locations' => $locations, 'not_full' => $notFull, 'reason' => $reason,
            'can_delete' => $reason === null, 'fingerprint' => $fingerprint,
            'cancellation_fingerprint' => $cancellation['fingerprint'] ?? null];
    }

    public function blockReason(ExportDeliveryPlan $delivery, User $actor): ?string
    {
        $planning = $delivery->palletPlan;
        if ($delivery->status !== 'draft' || $planning?->completed_at) {
            return 'Rencana yang sudah selesai atau dibatalkan tidak dapat dihapus melalui tindakan ini. Riwayat pengiriman tetap dipertahankan.';
        }
        if (! $planning) {
            return null;
        }
        if ($planning->status !== 'cancelled' && ! $actor->canAccess('export_pallet_cancel')) {
            return 'Penghapusan rencana ini memerlukan izin Batalkan perencanaan pallet untuk melepas reservasi lokasi.';
        }
        try {
            app(ExportFulfillmentService::class)->assertNoCommittedBoxes($planning);
        } catch (ValidationException $error) {
            return 'Rencana masih memiliki reservasi, penempatan box, atau issue picking. Gunakan pembatalan permintaan untuk meninjau pelepasan dan pengembalian box terlebih dahulu.';
        }
        if ($planning->pallets()->whereNotNull('ready_at')->exists()) {
            return 'Pallet sudah ditandai siap. Buka ulang kesiapan dan selesaikan pembatalan permintaan terlebih dahulu.';
        }

        return null;
    }

    public function delete(int $id, array $data, User $actor): void
    {
        abort_unless($actor->canDeleteExportDeliveryPlan(), 403, 'Hanya Admin Utama, Admin Warehouse, dan Supervisi yang dapat menghapus rencana delivery.');
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $delivery = ExportDeliveryPlan::with('items')->lockForUpdate()->findOrFail($id);
            abort_unless($delivery->status === 'draft' && $delivery->revision === (int) $data['revision'], 409, 'Rencana sudah berubah. Muat ulang halaman.');
            $planning = ExportPalletPlan::where('export_delivery_plan_id', $id)->lockForUpdate()->first();
            $delivery->setRelation('palletPlan', $planning);
            if (! empty($data['review_token'])) {
                try {
                    $token = json_decode(Crypt::decryptString($data['review_token']), true, 512, JSON_THROW_ON_ERROR);
                } catch (\Throwable $error) {
                    throw ValidationException::withMessages(['review_token' => 'Tinjauan penghapusan tidak valid. Buka kembali popup Hapus rencana.']);
                }
                $review = $this->review($delivery, $actor);
                if (($token['actor'] ?? null) !== $actor->id || ($token['delivery'] ?? null) !== $id
                    || ($token['expires'] ?? 0) < now()->timestamp || ! hash_equals($review['fingerprint'], $token['fingerprint'] ?? '') || ! $review['can_delete']) {
                    throw ValidationException::withMessages(['review_token' => 'Data box, lokasi, atau izin berubah. Buka dan periksa kembali tinjauan penghapusan.']);
                }
                if ($review['rows']) {
                    if (trim($data['reason'] ?? '') === '') {
                        throw ValidationException::withMessages(['reason' => 'Isi alasan penghapusan dan pelepasan reservasi box.']);
                    }
                    $before = app(ExportDeliveryPlanService::class)->snapshot($delivery);
                    app(ExportFulfillmentCompletionService::class)->cancel($id, [
                        'revision' => $planning->revision, 'fingerprint' => $review['cancellation_fingerprint'],
                        'request_token' => $token['request_token'], 'reason' => $data['reason'],
                    ], $actor);
                    AuditService::record('export_delivery_plan', 'deleted', 'ExportDeliveryPlan', $id, $actor->id,
                        $before, ['released_boxes' => $review['rows'], 'released_locations' => $review['locations']],
                        'Rencana dihapus setelah tinjauan pelepasan reservasi, posisi stok dan FIFO dipertahankan.');

                    return;
                }
            }
            if ($reason = $this->blockReason($delivery, $actor)) {
                throw ValidationException::withMessages(['pallet_plan' => $reason]);
            }
            $before = app(ExportDeliveryPlanService::class)->snapshot($delivery);
            if ($planning && $planning->status !== 'cancelled') {
                if ((int) ($data['pallet_revision'] ?? 0) !== $planning->revision) {
                    throw ValidationException::withMessages(['pallet_plan' => 'Perencanaan pallet sudah berubah. Muat ulang dan periksa kembali sebelum menghapus rencana.']);
                }
                app(ExportPalletPlanningService::class)->transition($id, $planning->revision, $actor, true);
            }
            $delivery->delete();
            app(DeliveryAnnouncementService::class)->sync($delivery);
            app(ExportNotFullService::class)->sync($delivery, $actor);
            AuditService::record('export_delivery_plan', 'deleted', 'ExportDeliveryPlan', $delivery->id,
                $actor->id, $before, [], 'Rencana delivery dihapus dari daftar aktif, riwayat tetap tersimpan.');
        });
    }
}
