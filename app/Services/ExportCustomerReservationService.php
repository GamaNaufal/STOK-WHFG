<?php

namespace App\Services;

use App\Models\{ExportBoxCustomerReservation, ExportCustomer, ExportNotFullRequest, ExportPickingIssue, ExportStockBox, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExportCustomerReservationService
{
    public function assertWrites(): void
    {
        if (! app(ExportCustomerStockPolicy::class)->ready() || ! config('export_customer_stock.writes_enabled')) throw ValidationException::withMessages(['customer' => 'Perubahan reservasi customer belum tersedia. Reservasi yang sudah ada tetap dilindungi.']);
    }

    public function request(string $token, string $action, array $payload, User $actor, callable $work): array
    {
        $this->assertWrites();
        return app(ExportInventoryService::class)->transaction(function () use ($token, $action, $payload, $actor, $work) {
            $hash = hash('sha256', json_encode([$actor->id, $action, $payload], JSON_THROW_ON_ERROR));
            $previous = DB::table('export_customer_stock_requests')->where('token', $token)->first();
            if ($previous) {
                if (! hash_equals($previous->request_hash, $hash)) throw ValidationException::withMessages(['request_token' => 'Token sudah digunakan untuk tindakan atau data lain.']);
                return json_decode($previous->result, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = $work();
            DB::table('export_customer_stock_requests')->insert(['token' => $token, 'request_hash' => $hash, 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now()]);
            return $result;
        });
    }

    public function assertEditable(ExportStockBox $box): void
    {
        $box->assertEligible();
        if ($box->state !== 'stored' || ! $box->currentPallet || $box->currentPallet->retired_at || ! $box->currentPallet->master_location_id || $box->currentPallet->export_planned_pallet_id
            || $box->fulfillment()->exists() || ExportPickingIssue::open()->where('box_number', $box->box_number)->exists()
            || ExportNotFullRequest::where('status', 'prepared')->where('preparation->source_box_id', $box->id)->exists()) {
            throw ValidationException::withMessages(['boxes' => 'Box '.$box->box_number.' terikat delivery, issue atau penyiapan Not Full. Selesaikan proses terlebih dahulu.']);
        }
        if (\App\Models\ExportFulfillmentBox::whereNotNull('picking_started_at')->whereNull('placed_at')->whereHas('stockBox', fn ($q) => $q->where('current_pallet_id', $box->current_pallet_id))->exists()) {
            throw ValidationException::withMessages(['boxes' => 'Pallet sumber sedang dipakai picking. Selesaikan proses terlebih dahulu.']);
        }
        app(ExportFulfillmentCompletionService::class)->assertPalletUnlocked($box->currentPallet);
    }

    public function event(ExportStockBox $box, string $action, int $actorId, array $before, array $after, string $reason): void
    {
        $snapshot = ['box_number' => $box->box_number, 'identity_cycle' => $box->identity_cycle, 'part_number' => $box->part_number, 'pcs_quantity' => $box->pcs_quantity, 'first_received_at' => $box->first_received_at?->toDateTimeString()];
        DB::table('export_customer_stock_events')->insert(['stock_box_id' => $box->id, 'box_number' => $box->box_number, 'identity_cycle' => $box->identity_cycle,
            'action' => $action, 'actor_id' => $actorId, 'before' => json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after + $snapshot, JSON_THROW_ON_ERROR), 'reason' => $reason, 'created_at' => now()]);
        AuditService::record('export_customer_stock', $action, 'ExportStockBox', $box->id, $actorId, $before, $after + $snapshot, $reason);
    }

    /** Caller holds inventory mutex; also used before receipt allocation and for source lineage. */
    public function attach(ExportStockBox $box, int $customerId, User $actor, string $reason, bool $allowInactive = false): void
    {
        $customer = $allowInactive ? ExportCustomer::findOrFail($customerId) : app(ExportCustomerService::class)->active($customerId);
        ExportBoxCustomerReservation::create(['export_stock_box_id' => $box->id, 'export_customer_id' => $customerId, 'changed_by' => $actor->id, 'reason' => $reason]);
        $this->event($box, 'reserved', $actor->id, [], ['customer_id' => $customerId, 'customer_name' => $customer->name], $reason);
    }

    public function inherit(ExportStockBox $source, ExportStockBox $replacement, User $actor): void
    {
        if ($owner = app(ExportCustomerStockPolicy::class)->ownerId($source)) $this->attach($replacement, $owner, $actor, 'Customer diwarisi dari box '.$source->box_number.', penggunaan '.$source->identity_cycle.'.', true);
    }

    public function change(array $data, User $actor): array
    {
        abort_unless($actor->canAccess('export_stock_customer_manage'), 403);
        foreach (array_keys($data['boxes']) as $key) if (! preg_match('/^[1-9][0-9]*$/D', (string) $key)) throw ValidationException::withMessages(['boxes' => 'ID stok pada pilihan tidak valid.']);
        $ids = array_map('intval', array_keys($data['boxes'])); sort($ids);
        return $this->request($data['request_token'], 'reservation', $data, $actor, function () use ($data, $actor, $ids) {
            $customerId = ! empty($data['customer_id']) ? (int) $data['customer_id'] : null;
            if ($customerId) app(ExportCustomerService::class)->active($customerId);
            $reason = trim($data['reason'] ?? '');
            if ($reason === '') throw ValidationException::withMessages(['reason' => 'Alasan perubahan reservasi wajib diisi.']);
            $boxes = ExportStockBox::with('currentPallet')->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($boxes->count() !== count($ids)) throw ValidationException::withMessages(['boxes' => 'Daftar box tidak lengkap. Muat ulang stok.']);
            foreach ($boxes as $box) {
                $this->assertEditable($box);
                if ($box->revision !== (int) $data['boxes'][$box->id]) throw ValidationException::withMessages(['boxes' => 'Box '.$box->box_number.' berubah, pilih ulang stok.']);
                $reservation = ExportBoxCustomerReservation::with('customer')->where('export_stock_box_id', $box->id)->first();
                if ($customerId && $reservation) throw ValidationException::withMessages(['boxes' => 'Tetapkan customer hanya untuk stok umum. Gunakan pengecualian delivery untuk stok customer lain.']);
                if ($customerId) $this->attach($box, $customerId, $actor, $reason);
                elseif ($reservation) {
                    $before = ['customer_id' => $reservation->export_customer_id, 'customer_name' => $reservation->customer->name];
                    $reservation->delete();
                    $this->event($box, 'released', $actor->id, $before, ['customer_id' => null], $reason);
                }
                $box->increment('revision');
            }
            return ['count' => $boxes->count()];
        });
    }
}
