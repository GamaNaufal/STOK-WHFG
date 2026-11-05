<?php

namespace App\Services;

use App\Models\ExpiredBoxHandling;
use App\Models\ExportExpiredBoxHandling;
use App\Models\ExportExpiredBoxTransaction;
use App\Models\ExportFulfillmentBox;
use App\Models\ExportMasterLocation;
use App\Models\ExportPickingIssue;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\User;
use Illuminate\Support\Str;

class ExportExpiredBoxHandlingService
{
    public function start(int $boxId, int $userId, bool $syncStatuses = true, ?int $transactionId = null): ExpiredBoxHandling
    {
        return app(ExportInventoryService::class)->transaction(function () use ($boxId, $userId, $transactionId) {
            $box = ExportStockBox::whereKey($boxId)->lockForUpdate()->firstOrFail();
            $existing = ExportExpiredBoxHandling::where('source_box_id', $boxId)->first();
            if ($existing) {
                return $existing;
            }
            if (! in_array($box->state, ['received', 'stored'], true) || $box->expiry_label !== 'Expired') {
                throw new \RuntimeException('Hanya box ekspor expired yang dapat diserahkan ke QA.');
            }
            $this->assertUnlocked($box);
            $pallet = $box->current_pallet_id ? ExportStockPallet::with('plannedPallet')->findOrFail($box->current_pallet_id) : null;
            $handling = ExportExpiredBoxHandling::create([
                'transaction_id' => $transactionId ?? ExportExpiredBoxTransaction::create(['created_by' => $userId])->id,
                'source_box_id' => $box->id, 'source_box_number' => $box->box_number,
                'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity,
                'original_stored_at' => $box->first_received_at,
                'source_snapshot' => $box->toArray() + ['physical_pallet' => $pallet?->toArray()],
                'status' => 'at_qa', 'sent_qa_at' => now(), 'sent_qa_by' => $userId,
            ]);
            $box->update(['state' => 'handling', 'expired_status' => 'handled', 'current_pallet_id' => null, 'revision' => $box->revision + 1]);
            if ($pallet && ! $pallet->boxes()->exists()) {
                $location = ExportMasterLocation::whereKey($pallet->master_location_id)->lockForUpdate()->first();
                $pallet->update(['master_location_id' => null, 'export_planned_pallet_id' => null, 'retired_at' => now()]);
                $location?->update(['is_occupied' => false]);
            }
            $this->audit($handling, 'sent_qa', $userId);

            return $handling;
        });
    }

    private function source(ExpiredBoxHandling $handling): ExportStockBox
    {
        $box = ExportStockBox::whereKey($handling->source_box_id)->lockForUpdate()->first();
        if (! $box || $box->state !== 'handling' || $box->expired_status !== 'handled') {
            throw new \RuntimeException('Box sumber ekspor tidak lagi valid. Periksa jejak penanganan.');
        }

        return $box;
    }

    public function decideQa(int $id, string $decision, ?string $notes, int $userId): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($id, $decision, $notes, $userId) {
            if (! in_array($decision, ['passed', 'rejected'], true) || ($decision === 'rejected' && ! trim($notes ?? ''))) {
                throw new \RuntimeException('Hasil QA tidak valid atau alasan penolakan belum diisi.');
            }
            $handling = ExportExpiredBoxHandling::whereKey($id)->lockForUpdate()->firstOrFail();
            $next = $decision === 'passed' ? 'awaiting_packing' : 'rejected';
            if ($handling->status === $next) {
                return;
            }
            $this->assertStatus($handling, 'at_qa');
            $source = $this->source($handling);
            $this->assertUnlocked($source);
            $handling->fill(['status' => $next, 'qa_notes' => $notes, 'qa_decided_at' => now(), 'qa_decided_by' => $userId]);
            if ($next === 'rejected') {
                // Keep movement and request references intact; retire the inventory row while preserving its globally claimed ID.
                $handling->history_snapshot = ['stock_box' => $source->toArray(), 'movements' => $source->movements()->get()->toArray()];
                $source->delete();
            }
            $handling->save();
            $this->audit($handling, $next === 'rejected' ? 'qa_rejected' : 'qa_passed', $userId);
        });
    }

    public function sendToPacking(int $id, int $userId): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($id, $userId) {
            $handling = ExportExpiredBoxHandling::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($handling->status === 'at_packing') {
                return;
            }
            $this->assertStatus($handling, 'awaiting_packing');
            $this->source($handling);
            $handling->update(['status' => 'at_packing', 'sent_packing_at' => now(), 'sent_packing_by' => $userId]);
            $this->audit($handling, 'sent_packing', $userId);
        });
    }

    public function receive(int $id, array $data, int $userId, bool $syncPallet = true): void
    {
        app(ExportInventoryService::class)->transaction(function () use ($id, $data, $userId) {
            $handling = ExportExpiredBoxHandling::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($handling->status === 'completed' && $handling->replacement_box_number === $data['box_number']) {
                return;
            }
            $this->assertStatus($handling, 'at_packing');
            $source = $this->source($handling);
            app(ExpiredBoxPackingReceiptService::class)->assertNewNumber($data['box_number']);
            $receivedAt = \Illuminate\Support\Carbon::parse($data['received_at']);
            $actor = User::findOrFail($userId);
            $box = ExportStockBox::create([
                'box_number' => $data['box_number'], 'part_setting_id' => $source->part_setting_id,
                'part_number' => $handling->part_number, 'pcs_quantity' => $handling->pcs_quantity,
                'standard_pcs_quantity' => $source->standard_pcs_quantity, 'receipt_pcs_quantity' => 0,
                'origin' => 'packing', 'source_box_id' => $source->id, 'is_not_full' => $source->is_not_full,
                'state' => 'received', 'revision' => 1, 'receive_token' => (string) Str::uuid(),
                'first_received_at' => $handling->original_stored_at, 'received_by' => $userId,
                'expired_status' => 'active', 'reactivated_until' => $receivedAt->copy()->addMonthNoOverflow(),
            ]);
            $placement = ['revision' => 1, 'request_token' => (string) Str::uuid()];
            $placement += ! empty($data['pallet_id']) ? ['pallet_id' => $data['pallet_id']] : ['location_id' => $data['location_id']];
            $box = app(ExportStockReceivingService::class)->place($box->id, $placement, $actor);
            $pallet = ExportStockPallet::findOrFail($box->current_pallet_id);
            $handling->update(['status' => 'completed', 'replacement_box_id' => $box->id,
                'replacement_box_number' => $box->box_number, 'replacement_mapping' => 'automatic_fifo',
                'receipt_id' => $data['receipt_id'], 'received_at' => $receivedAt, 'received_by' => $userId,
                'usable_until' => $box->reactivated_until, 'destination_pallet' => $pallet->number,
                'destination_location' => $pallet->location_code]);
            $source->delete();
            $this->audit($handling, 'packing_received', $userId);
        });
    }

    private function assertStatus(ExpiredBoxHandling $handling, string $expected): void
    {
        if ($handling->status !== $expected) {
            throw new \RuntimeException('Status penanganan berubah. Muat ulang transaksi.');
        }
    }

    private function assertUnlocked(ExportStockBox $box): void
    {
        if (ExportFulfillmentBox::where('export_stock_box_id', $box->id)->exists()) {
            throw new \RuntimeException('Box terikat permintaan ekspor. Lepaskan melalui revisi sebelum QA.');
        }
        $pallet = $box->current_pallet_id ? ExportStockPallet::findOrFail($box->current_pallet_id) : null;
        app(ExportFulfillmentCompletionService::class)->assertPalletUnlocked($pallet);
        if ($pallet) {
            app(ExportPalletMoveService::class)->assertPickingIdle($pallet);
        }
        if (ExportPickingIssue::open()->where('box_number', $box->box_number)->exists()
            || ($pallet && ExportFulfillmentBox::whereNull('placed_at')->whereNotNull('picking_started_at')
                ->whereHas('stockBox', fn ($q) => $q->where('current_pallet_id', $pallet->id))->exists())) {
            throw new \RuntimeException('Box atau pallet masih digunakan dalam picking. Selesaikan prosesnya terlebih dahulu.');
        }
        if (\App\Models\ExportNotFullRequest::where('status', 'prepared')->where('preparation->source_box_id', $box->id)->exists()) {
            throw new \RuntimeException('Box sedang disiapkan untuk Not Full. Batalkan penyiapan sebelum QA.');
        }
    }

    private function audit(ExpiredBoxHandling $handling, string $action, int $userId): void
    {
        AuditService::record('export_expired_box', $action, 'ExportStockBox', $handling->source_box_id, $userId, [],
            \Illuminate\Support\Arr::except($handling->toArray(), ['source_snapshot', 'history_snapshot']),
            $handling->reference.': '.$handling->source_box_number.' · '.$handling->status_label);
    }
}
