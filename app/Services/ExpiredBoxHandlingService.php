<?php

namespace App\Services;

use App\Models\Box;
use App\Models\ExpiredBoxHandling;
use App\Models\ExpiredBoxTransaction;
use App\Models\Pallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpiredBoxHandlingService
{
    public function start(int $boxId, int $userId, bool $syncStatuses = true, ?int $transactionId = null): ExpiredBoxHandling
    {
        if ($syncStatuses) {
            app(ExpiredBoxService::class)->syncStatuses();
        }

        return DB::transaction(function () use ($boxId, $userId, $transactionId) {
            $box = Box::whereKey($boxId)->lockForUpdate()->first();
            $existing = ExpiredBoxHandling::where('source_box_id', $boxId)->first();
            if ($existing) {
                return $existing;
            }
            if (! $box || $box->is_withdrawn || $box->expired_status !== 'expired') {
                throw new \RuntimeException('Hanya box expired yang dapat diserahkan ke QA.');
            }
            $this->assertUnlocked($box);
            $row = app(ExpiredBoxService::class)->getExpirableBoxesQuery()->where('boxes.id', $boxId)->first();
            if (! $row?->stored_at) {
                throw new \RuntimeException('Tanggal penyimpanan asli tidak tersedia.');
            }
            $snapshot = $box->toArray();
            $snapshot['pallets'] = $box->pallets()->with('stockLocation')->get()->toArray();
            $snapshot['pallet_boxes'] = DB::table('pallet_boxes')->where('box_id', $boxId)->get()->toArray();
            $snapshot['last_expired_report'] = DB::table('expired_box_reports')->where('box_id', $boxId)->latest('id')->first();
            $handling = ExpiredBoxHandling::create([
                'transaction_id' => $transactionId ?? ExpiredBoxTransaction::create(['created_by' => $userId])->id,
                'source_box_id' => $boxId, 'source_box_number' => $box->box_number,
                'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity,
                'original_stored_at' => $row->stored_at, 'source_snapshot' => $snapshot,
                'status' => 'at_qa', 'sent_qa_at' => now(), 'sent_qa_by' => $userId,
            ]);
            // Existing stock/FIFO/scan filters already exclude handled permanently.
            $box->update(['expired_status' => 'handled', 'handled_at' => now(), 'handled_by' => $userId]);
            app(ExpiredBoxService::class)->syncLinkedPalletsAfterBoxDeactivation($box);
            // Physical custody has left the pallet. Keep origin in the immutable snapshot,
            // rather than allowing later pallet operations to mutate a box held by QA.
            $box->pallets()->detach();
            $this->audit($handling, 'sent_qa', $userId);

            return $handling;
        });
    }

    public function decideQa(int $id, string $decision, ?string $notes, int $userId): void
    {
        if (! in_array($decision, ['passed', 'rejected'], true)) {
            throw new \RuntimeException('Keputusan QA tidak valid.');
        }
        DB::transaction(function () use ($id, $decision, $notes, $userId) {
            $handling = ExpiredBoxHandling::whereKey($id)->lockForUpdate()->firstOrFail();
            $next = $decision === 'passed' ? 'awaiting_packing' : 'rejected';
            if ($handling->status === $next) {
                return;
            }
            $this->assertStatus($handling, 'at_qa');
            $box = $this->source($handling);
            $this->assertUnlocked($box);
            $handling->fill(['status' => $next, 'qa_notes' => $notes, 'qa_decided_at' => now(), 'qa_decided_by' => $userId]);
            if ($next === 'rejected') {
                // Preserve cascaded mappings and transaction snapshots before the physical delete.
                $history = [];
                foreach (['stock_input_boxes', 'delivery_pick_items', 'stock_withdrawals', 'delivery_issues', 'not_full_box_requests', 'expired_box_reports', 'pallet_boxes'] as $table) {
                    $history[$table] = DB::table($table)->where('box_id', $box->id)->get()->toArray();
                }
                $history['stock_inputs'] = DB::table('stock_inputs')->whereIn('id', collect($history['stock_input_boxes'])->pluck('stock_input_id'))->get()->toArray();
                $history['delivery_pick_sessions'] = DB::table('delivery_pick_sessions')->whereIn('id', collect($history['delivery_pick_items'])->pluck('pick_session_id'))->get()->toArray();
                $history['audit_logs'] = DB::table('audit_logs')->where('model', 'Box')->where('model_id', $box->id)->get()->toArray();
                $handling->history_snapshot = $history;
                $handling->save();
                app(ExpiredBoxService::class)->syncLinkedPalletsAfterBoxDeactivation($box);
                $box->forceDelete();
            } else {
                $handling->save();
            }
            $this->audit($handling, $next === 'rejected' ? 'qa_rejected' : 'qa_passed', $userId);
        });
    }

    public function sendToPacking(int $id, int $userId): void
    {
        DB::transaction(function () use ($id, $userId) {
            $handling = ExpiredBoxHandling::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($handling->status === 'at_packing') {
                return;
            }
            $this->assertStatus($handling, 'awaiting_packing');
            $this->assertUnlocked($this->source($handling));
            $handling->update(['status' => 'at_packing', 'sent_packing_at' => now(), 'sent_packing_by' => $userId]);
            $this->audit($handling, 'sent_packing', $userId);
        });
    }

    public function receive(int $id, array $data, int $userId, bool $syncPallet = true): void
    {
        DB::transaction(function () use ($id, $data, $userId, $syncPallet) {
            $handling = ExpiredBoxHandling::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($handling->status === 'completed' && $handling->replacement_box_number === $data['box_number']) {
                return;
            }
            $this->assertStatus($handling, 'at_packing');
            $source = $this->source($handling);
            $this->assertUnlocked($source);
            if ($data['part_number'] !== $handling->part_number || (int) $data['pcs_quantity'] !== (int) $handling->pcs_quantity) {
                throw new \RuntimeException('Part dan PCS hasil packing harus sama dengan box asal.');
            }
            if (DB::table('box_identities')->where('box_number', $data['box_number'])->exists()
                || DB::table('not_full_box_requests')->where('box_number', $data['box_number'])->exists()) {
                throw ValidationException::withMessages(['box_number' => 'ID box baru sudah pernah digunakan atau diajukan.']);
            }
            $issuedAt = Carbon::parse($data['label_issued_at']);
            if ($issuedAt->gt(now()) || $issuedAt->lt($handling->sent_packing_at)) {
                throw ValidationException::withMessages(['label_issued_at' => 'Waktu penerbitan ID harus sejak penyerahan ke packing sampai sekarang.']);
            }
            $until = $issuedAt->copy()->addMonthNoOverflow();
            if (! empty($data['pallet_id'])) {
                $pallet = Pallet::whereKey($data['pallet_id'])->lockForUpdate()->firstOrFail();
                $blocking = app(PalletDeliveryLockService::class)->blockingBoxForPallets([$pallet->id], true);
                if ($blocking) {
                    throw new \RuntimeException('Pallet tujuan sedang digunakan dalam delivery aktif.');
                }
                $storage = $pallet->stockLocation()->lockForUpdate()->first();
                if (! $storage) {
                    throw new \RuntimeException('Pallet tujuan tidak memiliki lokasi aktif. Pilih pallet baru.');
                }
                $locationId = $storage->master_location_id;
                if (! $locationId) {
                    $locationId = \App\Models\MasterLocation::where('code', $storage->warehouse_location)->value('id');
                }
                if (! $locationId) {
                    throw new \RuntimeException('Lokasi pallet tujuan tidak valid.');
                }
            } else {
                $pallet = app(PalletNumberService::class)->create();
                $locationId = (int) ($data['location_id'] ?? 0);
                if (! $locationId) {
                    throw new \RuntimeException('Pilih lokasi kosong untuk pallet baru.');
                }
            }
            $storage = app(LocationAssignmentService::class)->assign((int) $locationId, $pallet, $storage->stored_at ?? now());
            $attributes = $source->only(['part_name', 'qty_box', 'type_box', 'wk_transfer', 'lot01', 'lot02', 'lot03', 'is_not_full', 'not_full_reason']);
            $replacement = Box::create(array_merge($attributes, [
                'box_number' => $data['box_number'], 'part_number' => $handling->part_number,
                'pcs_quantity' => $handling->pcs_quantity, 'user_id' => $userId,
                'qr_code' => $data['box_number'].'|'.$handling->part_number.'|'.$handling->pcs_quantity,
                'created_at' => $handling->original_stored_at, 'reactivated_until' => $until,
                'expired_status' => $until->gt(now()) ? 'active' : 'expired',
            ]));
            $pallet->boxes()->attach($replacement->id);
            $handling->update([
                'status' => 'completed', 'replacement_box_id' => $replacement->id,
                'replacement_box_number' => $replacement->box_number, 'label_issued_at' => $issuedAt,
                'usable_until' => $until, 'received_at' => now(), 'received_by' => $userId,
                'destination_pallet' => $pallet->pallet_number, 'destination_location' => $storage->warehouse_location,
            ]);
            // A separate handling receipt avoids counting the same goods as production inbound.
            if ($until->lte(now())) {
                \App\Models\ExpiredBoxReport::create([
                    'box_id' => $replacement->id, 'box_number' => $replacement->box_number,
                    'part_number' => $replacement->part_number, 'pallet_id' => $pallet->id,
                    'pallet_number' => $pallet->pallet_number, 'warehouse_location' => $storage->warehouse_location,
                    'stored_at' => $handling->original_stored_at, 'age_months' => (int) $handling->original_stored_at->diffInMonths(now()),
                    'status' => 'expired',
                ]);
            }
            if ($syncPallet) {
                app(ExpiredBoxService::class)->syncLinkedPalletsAfterBoxDeactivation($replacement);
            }
            $this->audit($handling, 'packing_received', $userId);
            AuditService::record('expired_box', 'replacement_created', 'Box', $replacement->id, $userId, [],
                ['handling_id' => $handling->id, 'source_box_number' => $handling->source_box_number, 'usable_until' => $until->toDateTimeString()],
                "Box {$replacement->box_number} diterima dari packing; asal {$handling->source_box_number} ({$handling->reference}).");
        });
    }

    private function source(ExpiredBoxHandling $handling): Box
    {
        $box = Box::whereKey($handling->source_box_id)->lockForUpdate()->first();
        if (! $box || $box->expired_status !== 'handled' || $box->is_withdrawn) {
            throw new \RuntimeException('Box sumber tidak lagi valid. Periksa riwayat penanganan.');
        }

        return $box;
    }

    private function assertStatus(ExpiredBoxHandling $handling, string $expected): void
    {
        if ($handling->status !== $expected) {
            throw new \RuntimeException('Status penanganan sudah berubah. Muat ulang halaman dan ikuti urutan QA lalu packing.');
        }
    }

    private function assertUnlocked(Box $box): void
    {
        if ($box->assigned_delivery_order_id !== null || DB::table('delivery_pick_items')
            ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
            ->where('delivery_pick_items.box_id', $box->id)->whereIn('delivery_pick_sessions.status', ['pending', 'scanning', 'blocked', 'approved'])->exists()) {
            throw new \RuntimeException('Box masih di-assign atau digunakan dalam sesi picking.');
        }
        $palletIds = $box->pallets()->lockForUpdate()->pluck('pallets.id')->all();
        if (app(PalletDeliveryLockService::class)->blockingBoxForPallets($palletIds, true)) {
            throw new \RuntimeException('Pallet masih digunakan dalam proses delivery aktif.');
        }
    }

    private function audit(ExpiredBoxHandling $handling, string $action, int $userId): void
    {
        AuditService::record('expired_box', $action, 'Box', $handling->source_box_id, $userId, [],
            \Illuminate\Support\Arr::except($handling->toArray(), ['source_snapshot', 'history_snapshot']),
            "{$handling->reference}: box {$handling->source_box_number} — {$handling->status_label}.");
    }
}
