<?php

namespace App\Services;

use App\Models\{ExportFulfillmentBox, ExportMasterLocation, ExportPickingIssue, ExportStockBox, ExportStockMovement, ExportStockPallet, User};
use Illuminate\Validation\ValidationException;

class ExportPickingIssueService
{
    private function fail(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }

    private function destination(?string $value): array
    {
        if (!$value || !preg_match('/^(pallet|location):([1-9][0-9]*)$/', $value, $matches)) $this->fail('destination', 'Tentukan lokasi kosong atau pallet ekspor tujuan pengembalian.');
        $pallet = $matches[1] === 'pallet' ? ExportStockPallet::whereKey($matches[2])->whereNull('retired_at')->first() : null;
        if ($matches[1] === 'pallet' && (!$pallet || !$pallet->master_location_id)) $this->fail('destination', 'Pallet ekspor tidak lagi tersedia. Pilih ulang.');
        $location = ExportMasterLocation::whereKey($pallet?->master_location_id ?? $matches[2])->first();
        if (!$location || $location->current_pallet_id || $location->stockLocations()->exists()) $this->fail('destination', 'Tujuan harus berasal dari lokasi ekspor yang tersedia.');
        if (!$pallet && ($location->is_occupied || $location->stockPallet()->exists() || $location->reservation()->exists())) $this->fail('destination', 'Lokasi sudah terisi atau direservasi. Pilih pallet existing atau lokasi kosong lain.');
        if ($pallet && $location->reservation && (int) $location->reservation->export_planned_pallet_id !== (int) $pallet->export_planned_pallet_id) $this->fail('destination', 'Pallet tidak sesuai reservasi lokasi. Minta admin memeriksa tujuan.');
        return [$pallet, $location];
    }

    public function approve(int $id, array $data, User $actor): ExportPickingIssue
    {
        return app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $issue = ExportPickingIssue::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($issue->status === 'returned' || $issue->revision !== (int) $data['revision']) $this->fail('revision', 'Issue sudah berubah atau selesai. Muat ulang halaman approval.');
            $box = ExportStockBox::with('currentPallet.location')->where('box_number', $issue->box_number)->first();
            $source = $box?->currentPallet;
            $knownSource = $source && !$source->retired_at && $source->location;
            if ($knownSource) {
                [$pallet, $location] = [$source, $source->location];
            } else {
                [$pallet, $location] = $this->destination($data['destination'] ?? null);
            }
            $part = !$box ? app(ExportStockReceivingService::class)->part(trim($data['part_number'] ?? ''), true) : null;
            $legacy = !$box ? ExportFulfillmentBox::where('box_number', $issue->box_number)->first() : null;
            if ($legacy && ((int) $legacy->part_setting_id !== $part->id || $legacy->pcs_quantity !== (int) $part->qty_box)) $this->fail('part_number', 'Nomor ini memiliki komitmen ekspor lama. Part dan kapasitas harus sesuai histori komitmen tersebut.');
            $before = $issue->only(['status', 'revision', 'return_pallet_id', 'return_location_id', 'part_setting_id', 'pcs_quantity']);
            $issue->update(['export_stock_box_id' => $box?->id, 'issue_type' => !$box ? 'unknown_box' : ($knownSource ? 'wrong_box' : 'missing_location'),
                'return_pallet_id' => $pallet?->id, 'return_location_id' => $location->id, 'return_location_code' => $location->code,
                'part_setting_id' => $part?->id ?? $box->part_setting_id, 'pcs_quantity' => $part?->qty_box ?? $box->pcs_quantity,
                'approved_by' => $actor->id, 'approved_at' => now(), 'notes' => $data['notes'],
                'status' => 'awaiting_return', 'revision' => $issue->revision + 1]);
            AuditService::record('export_picking_issue', 'approved', 'ExportPickingIssue', $issue->id, $actor->id, $before,
                $issue->only(['status', 'revision', 'return_pallet_id', 'return_location_id', 'part_setting_id', 'pcs_quantity', 'notes']),
                'Approval ekspor dicatat. Blokir tetap aktif sampai operator mengonfirmasi penempatan pengembalian.');
            return $issue->fresh();
        });
    }

    public function confirmReturn(int $id, array $data, User $actor): ExportPickingIssue
    {
        return app(ExportInventoryService::class)->transaction(function () use ($id, $data, $actor) {
            $issue = ExportPickingIssue::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $issue->reported_by === $actor->id, 403, 'Konfirmasi pengembalian dilakukan oleh operator pelapor.');
            if ($issue->status === 'returned') {
                if ($issue->confirmation_token === $data['request_token']) return $issue;
                $this->fail('issue', 'Pengembalian sudah dikonfirmasi. Muat ulang.');
            }
            if ($issue->status !== 'awaiting_return') $this->fail('issue', 'Tunggu approval admin sebelum konfirmasi pengembalian.');
            if ($issue->revision !== (int) $data['revision']) $this->fail('revision', 'Arahan admin berubah. Muat ulang dan periksa tujuan terbaru.');
            if (ExportStockMovement::where('request_token', $data['request_token'])->exists()
                || ExportPickingIssue::where('confirmation_token', $data['request_token'])->whereKeyNot($issue->id)->exists()) $this->fail('request_token', 'Token konfirmasi sudah dipakai untuk tindakan lain. Muat ulang.');
            $destination = $issue->return_pallet_id ? 'pallet:'.$issue->return_pallet_id : 'location:'.$issue->return_location_id;
            [$target, $location] = $this->destination($destination);
            if ($location->id !== (int) $issue->return_location_id || $location->code !== $issue->return_location_code) $this->fail('destination', 'Tujuan pengembalian berubah. Minta admin memperbarui approval.');

            $box = ExportStockBox::where('box_number', $issue->box_number)->lockForUpdate()->first();
            if (!$box) {
                $part = $issue->part;
                if (!$part || (int) $part->qty_box !== $issue->pcs_quantity) $this->fail('part_number', 'Kapasitas part berubah. Minta admin memperbarui approval sebelum konfirmasi.');
                $box = ExportStockBox::create(['box_number' => $issue->box_number, 'part_setting_id' => $part->id, 'part_number' => $part->part_number,
                    'pcs_quantity' => $issue->pcs_quantity, 'receive_token' => $issue->registration_token,
                    'first_received_at' => $issue->created_at, 'received_by' => $actor->id, 'state' => 'received', 'revision' => 1]);
                $legacy = ExportFulfillmentBox::where('box_number', $issue->box_number)->whereNull('export_stock_box_id')->first();
                if ($legacy) {
                    if ((int) $legacy->part_setting_id !== $part->id || $legacy->pcs_quantity !== $box->pcs_quantity) $this->fail('part_number', 'Komitmen lama tidak sesuai data registrasi. Minta admin meninjau kembali.');
                    $legacy->update(['export_stock_box_id' => $box->id]);
                }
                AuditService::record('export_stock_receipt', 'received', 'ExportStockBox', $box->id, $actor->id, [],
                    ['issue_id' => $issue->id, 'box_number' => $box->box_number, 'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity],
                    'Box ekspor baru didaftarkan saat penempatan koreksi yang telah disetujui admin dikonfirmasi.');
            }
            if ((int) $box->part_setting_id !== (int) $issue->part_setting_id || $box->pcs_quantity !== $issue->pcs_quantity) $this->fail('part_number', 'Data box berbeda dari approval. Minta admin meninjau kembali.');
            if ($issue->issue_type === 'wrong_box' && (int) $box->current_pallet_id !== (int) $target?->id) $this->fail('destination', 'Posisi box berubah dari asalnya. Minta admin meninjau ulang tujuan pengembalian.');
            if (!$target || (int) $box->current_pallet_id !== $target->id) {
                $target ??= ExportStockPallet::create(['master_location_id' => $location->id, 'initial_location_id' => $location->id,
                    'location_code' => $location->code, 'created_by' => $actor->id]);
                $source = $box->currentPallet;
                ExportStockMovement::create(['export_stock_box_id' => $box->id, 'request_token' => $data['request_token'],
                    'request_hash' => hash('sha256', 'export-picking-return:'.$issue->id.':'.$issue->revision),
                    'from_pallet_id' => $source?->id, 'to_pallet_id' => $target->id, 'from_location_code' => $source?->location_code,
                    'to_location_code' => $location->code, 'placed_by' => $actor->id, 'placed_at' => now()]);
                $box->update(['state' => 'stored', 'current_pallet_id' => $target->id, 'revision' => $box->revision + 1, 'stored_at' => now(), 'stored_by' => $actor->id]);
                $location->update(['is_occupied' => true]);
                $commitment = $box->fulfillment;
                if ($commitment?->placed_at && ((int) $target->export_planned_pallet_id !== (int) $commitment->plannedItem->export_planned_pallet_id
                    || (int) $commitment->placed_location_id !== $location->id)) {
                    $commitment->update(['placed_at' => null, 'placed_by' => null, 'placed_location_id' => null,
                        'picking_verified_at' => null, 'picking_verified_revision' => null, 'picking_verified_by' => null]);
                }
                if ($source && !$source->boxes()->exists() && !$source->retired_at) {
                    $sourceLocation = $source->location;
                    $source->update(['master_location_id' => null, 'export_planned_pallet_id' => null, 'retired_at' => now()]);
                    $sourceLocation?->update(['is_occupied' => false]);
                }
            }
            $issue->update(['status' => 'returned', 'export_stock_box_id' => $box->id, 'confirmation_token' => $data['request_token'],
                'confirmed_by' => $actor->id, 'confirmed_at' => now(), 'revision' => $issue->revision + 1]);
            app(ExportPickingService::class)->requireForSource($target->id);
            app(ExportStockAllocationService::class)->allocate($actor, [(int) $box->part_setting_id]);
            AuditService::record('export_picking_issue', 'returned', 'ExportPickingIssue', $issue->id, $actor->id,
                ['status' => 'awaiting_return'], ['status' => 'returned', 'box_number' => $box->box_number, 'pallet_id' => $target->id, 'location' => $location->code],
                'Operator mengonfirmasi box sudah ditempatkan sesuai approval; blokir issue ini dilepas.');
            return $issue->fresh();
        });
    }
}
