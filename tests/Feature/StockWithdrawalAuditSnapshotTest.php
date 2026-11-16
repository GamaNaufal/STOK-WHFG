<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Box, DeliveryOrder, DeliveryOrderItem, DeliveryPickItem, DeliveryPickSession, MasterLocation, Pallet, PalletItem, StockLocation, StockWithdrawal, User};
use App\Support\AuditLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class StockWithdrawalAuditSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $reserve = true): array
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $order = DeliveryOrder::create(['sales_user_id' => $operator->id, 'customer_name' => 'Audit diagnosis', 'delivery_date' => now()->addDay()->toDateString(), 'status' => 'processing']);
        DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'part_number' => '949322-3750', 'quantity' => 1000, 'fulfilled_quantity' => 0]);
        $pallet = Pallet::create(['pallet_number' => 'DIAG-01']);
        StockLocation::create(['pallet_id' => $pallet->id, 'warehouse_location' => 'A23', 'stored_at' => now()]);
        PalletItem::create(['pallet_id' => $pallet->id, 'part_number' => '949322-3750', 'box_quantity' => $reserve ? 6 : 5, 'pcs_quantity' => $reserve ? 1200 : 1000]);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $operator->id, 'status' => 'scanning', 'started_at' => now()]);
        $boxes = collect();
        for ($i = 1; $i <= ($reserve ? 6 : 5); $i++) {
            $box = Box::create(['box_number' => sprintf('%08d', $i), 'part_number' => '949322-3750', 'pcs_quantity' => 200, 'qty_box' => 1, 'qr_code' => 'DIAG-'.$i, 'user_id' => $operator->id, 'assigned_delivery_order_id' => $i <= 5 ? $order->id : null]);
            $pallet->boxes()->attach($box->id);
            if ($i <= 5) {
                $boxes->push($box);
                DeliveryPickItem::create(['pick_session_id' => $session->id, 'box_id' => $box->id, 'part_number' => $box->part_number, 'pcs_quantity' => 200, 'status' => 'scanned', 'scanned_at' => now(), 'scanned_by' => $operator->id]);
            }
        }
        return compact('operator', 'order', 'pallet', 'session', 'boxes');
    }

    private function complete(array $f): AuditLog
    {
        $this->actingAs($f['operator'])->postJson(route('delivery.pick.complete', $f['session']->id))->assertOk()->assertJson(['success' => true]);
        return AuditLog::where('type', 'stock_withdrawal')->where('action', 'completed')->sole();
    }

    public function test_completion_records_every_label_with_leading_zero_and_identity_cycle(): void
    {
        $f = $this->fixture();
        $log = $this->complete($f);
        $snapshot = $log->getNewValuesArray();
        $this->assertSame(1000, $snapshot['total_pcs_quantity']);
        $this->assertCount(5, $snapshot['boxes']);
        foreach ($snapshot['boxes'] as $index => $box) {
            $this->assertSame($f['boxes'][$index]->id, $box['box_id']);
            $this->assertSame(sprintf('%08d', $index + 1), $box['box_number']);
            $this->assertSame(1, $box['identity_cycle']);
            $this->assertSame('A23', $box['warehouse_location']);
        }
        $rows = AuditLogPresenter::present($log)['tables'][0]['rows'];
        $this->assertSame($f['boxes']->pluck('box_number')->all(), array_column($rows, 0));
        $supervisor = User::factory()->create(['role' => 'supervisi']);
        $this->actingAs($supervisor)->get(route('audit.index'))->assertOk()->assertSee('00000001')->assertSee('00000005');
        $this->assertSame($f['operator']->id, $log->user_id);
        $this->assertSame(5, StockWithdrawal::where('status', 'completed')->count());
    }

    public function test_empty_pallet_keeps_origin_in_audit_and_withdrawals(): void
    {
        $log = $this->complete($this->fixture(false));
        $this->assertSame(['A23'], array_values(array_unique(array_column($log->getNewValuesArray()['boxes'], 'warehouse_location'))));
        $this->assertSame(['A23'], StockWithdrawal::pluck('warehouse_location')->unique()->values()->all());
        $this->assertDatabaseCount('stock_locations', 0);
    }

    public static function redoDestinations(): array
    {
        return ['origin' => ['A23'], 'relocated' => ['B02'], 'legacy without master location' => [null]];
    }

    #[DataProvider('redoDestinations')]
    public function test_redo_records_all_labels_and_the_actual_destination(?string $destination): void
    {
        $f = $this->fixture(false);
        $completed = $this->complete($f)->new_values;
        if ($destination !== null) {
            MasterLocation::create(['code' => $destination]);
        }
        $admin = User::factory()->create(['role' => 'admin_warehouse']);
        $this->actingAs($admin)->post(route('delivery.pick.redo', $f['session']->id), $destination === 'B02' ? ['relocation_location' => $destination] : [])->assertRedirect()->assertSessionHas('success');
        $log = AuditLog::where('type', 'stock_withdrawal')->where('action', 'reversed')->sole();
        $this->assertCount(5, $log->getNewValuesArray()['boxes']);
        $this->assertSame($f['boxes']->pluck('box_number')->all(), array_column(AuditLogPresenter::present($log)['tables'][0]['rows'], 0));
        $this->assertSame([$destination], array_values(array_unique(array_column($log->getNewValuesArray()['boxes'], 'warehouse_location'))));
        $this->assertSame($destination, $f['boxes']->first()->fresh()->pallets()->sole()->stockLocation?->warehouse_location);
        $this->assertSame($completed, AuditLog::where('type', 'stock_withdrawal')->where('action', 'completed')->sole()->new_values);
    }

    public function test_completed_snapshot_survives_soft_delete_label_reuse_and_hard_delete(): void
    {
        $f = $this->fixture();
        $log = $this->complete($f);
        $original = $log->new_values;
        $oldBox = $f['boxes']->first();
        $oldBox->delete();
        $newBox = Box::create(['box_number' => $oldBox->box_number, 'part_number' => 'NEW-PART', 'pcs_quantity' => 77, 'qty_box' => 1, 'qr_code' => 'NEW-USE', 'user_id' => $f['operator']->id]);
        $this->assertSame(2, (int) $newBox->identity_cycle);
        $snapshot = $log->fresh()->getNewValuesArray();
        $this->assertSame(5, $snapshot['total_box_quantity']);
        $this->assertCount(5, $snapshot['boxes']);
        $this->assertSame(1000, $snapshot['total_pcs_quantity']);
        $this->assertSame(1, $snapshot['boxes'][0]['identity_cycle']);
        $this->assertSame($oldBox->id, $snapshot['boxes'][0]['box_id']);
        $oldBox->forceDelete();
        $this->assertSame($original, $log->fresh()->new_values);
        $this->assertSame(['00000001', '949322-3750', '200', 'A23'], AuditLogPresenter::present($log->fresh())['tables'][0]['rows'][0]);
    }

    public function test_shared_box_uses_the_same_latest_valid_location_as_completion(): void
    {
        $f = $this->fixture();
        $latest = Pallet::create(['pallet_number' => 'DIAG-NEW']);
        StockLocation::create(['pallet_id' => $latest->id, 'warehouse_location' => 'B02', 'stored_at' => now()]);
        $latest->boxes()->attach($f['boxes']->first()->id);
        $canonical = Pallet::query()->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'pallets.id')->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')->where('pallet_boxes.box_id', $f['boxes']->first()->id)->orderByDesc('pallet_boxes.id')->value('stock_locations.warehouse_location');
        $this->assertSame('B02', $canonical);
        $log = $this->complete($f);
        $snapshot = $log->getNewValuesArray();
        $this->assertSame('B02', $snapshot['boxes'][0]['warehouse_location']);
        $this->assertSame('B02', StockWithdrawal::where('box_id', $f['boxes']->first()->id)->sole()->warehouse_location);
        $this->assertSame(['A23'], array_values(array_unique(array_column(array_slice($snapshot['boxes'], 1), 'warehouse_location'))));
    }

    public function test_failed_batch_audit_write_rolls_back_completion(): void
    {
        $f = $this->fixture();
        AuditLog::creating(function (AuditLog $log) {
            if ($log->type === 'stock_withdrawal') {
                throw new RuntimeException('Synthetic audit failure');
            }
        });
        try {
            $this->actingAs($f['operator'])->postJson(route('delivery.pick.complete', $f['session']->id))->assertStatus(500)->assertJson(['success' => false]);
            $this->assertSame(0, AuditLog::where('type', 'stock_withdrawal')->count());
            $this->assertDatabaseCount('stock_withdrawals', 0);
            $this->assertSame('processing', $f['order']->fresh()->status);
            $this->assertSame(0, $f['order']->items()->sole()->fulfilled_quantity);
            $this->assertSame('scanning', $f['session']->fresh()->status);
            $this->assertSame(0, Box::where('is_withdrawn', true)->count());
            $this->assertSame(1200, $f['pallet']->items()->sole()->pcs_quantity);
            $this->assertSame('A23', $f['pallet']->fresh()->stockLocation->warehouse_location);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public static function failingRedoAudits(): array
    {
        return [
            'batch reversal' => ['stock_withdrawal', 'reversed', false],
            'redo event' => ['delivery_redo', 'redo', false],
            'not full restoration' => ['delivery_redo', 'not_full_restored', true],
        ];
    }

    #[DataProvider('failingRedoAudits')]
    public function test_failed_audit_rolls_back_redo_and_location_assignment(string $type, string $action, bool $notFull): void
    {
        $f = $this->fixture(false);
        if ($notFull) {
            $f['boxes']->first()->update(['is_not_full' => true]);
        }
        $this->complete($f);
        MasterLocation::create(['code' => 'B02']);
        $palletCount = Pallet::count();
        $admin = User::factory()->create(['role' => 'admin_warehouse']);
        AuditLog::creating(function (AuditLog $log) use ($type, $action) {
            if ($log->type === $type && $log->action === $action) {
                throw new RuntimeException('Synthetic Redo audit failure');
            }
        });
        try {
            $this->actingAs($admin)->post(route('delivery.pick.redo', $f['session']->id), ['relocation_location' => 'B02'])->assertRedirect()->assertSessionHas('error');
            $this->assertSame('completed', $f['order']->fresh()->status);
            $this->assertSame('completed', $f['session']->fresh()->completion_status);
            $this->assertSame(5, StockWithdrawal::where('status', 'completed')->count());
            $this->assertSame(5, Box::where('is_withdrawn', true)->count());
            $this->assertSame($palletCount, Pallet::count());
            $this->assertDatabaseMissing('stock_locations', ['warehouse_location' => 'B02']);
            $this->assertFalse(MasterLocation::where('code', 'B02')->sole()->is_occupied);
            $this->assertSame(0, AuditLog::where('type', 'stock_withdrawal')->where('action', 'reversed')->count());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_redo_rejects_missing_boxes_instead_of_saving_a_partial_audit(): void
    {
        $f = $this->fixture(false);
        $this->complete($f);
        $f['boxes']->first()->delete();
        $admin = User::factory()->create(['role' => 'admin_warehouse']);
        $this->actingAs($admin)->post(route('delivery.pick.redo', $f['session']->id))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(5, StockWithdrawal::where('status', 'completed')->count());
        $this->assertSame('completed', $f['session']->fresh()->completion_status);
        $this->assertSame(0, AuditLog::where('type', 'stock_withdrawal')->where('action', 'reversed')->count());
    }

    public function test_new_completion_records_second_use_without_changing_the_first_snapshot(): void
    {
        $f = $this->fixture();
        $firstLog = $this->complete($f);
        $firstSnapshot = $firstLog->new_values;
        $order = DeliveryOrder::create(['sales_user_id' => $f['operator']->id, 'customer_name' => 'Second use', 'delivery_date' => now()->addDay()->toDateString(), 'status' => 'processing']);
        DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'part_number' => '949322-3750', 'quantity' => 200, 'fulfilled_quantity' => 0]);
        $newBox = Box::create(['box_number' => '00000001', 'part_number' => '949322-3750', 'pcs_quantity' => 200, 'qty_box' => 1, 'qr_code' => 'SECOND-USE', 'user_id' => $f['operator']->id, 'assigned_delivery_order_id' => $order->id]);
        $f['pallet']->boxes()->attach($newBox->id);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $f['operator']->id, 'status' => 'scanning', 'started_at' => now()]);
        DeliveryPickItem::create(['pick_session_id' => $session->id, 'box_id' => $newBox->id, 'part_number' => $newBox->part_number, 'pcs_quantity' => 200, 'status' => 'scanned', 'scanned_at' => now(), 'scanned_by' => $f['operator']->id]);
        $this->actingAs($f['operator'])->postJson(route('delivery.pick.complete', $session->id))->assertOk()->assertJson(['success' => true]);
        $newSnapshot = AuditLog::where('type', 'stock_withdrawal')->where('model_id', $session->id)->sole()->getNewValuesArray();
        $this->assertSame(2, $newSnapshot['boxes'][0]['identity_cycle']);
        $this->assertSame('00000001', $newSnapshot['boxes'][0]['box_number']);
        $this->assertSame($newBox->id, $newSnapshot['boxes'][0]['box_id']);
        $this->assertSame($firstSnapshot, $firstLog->fresh()->new_values);
    }
}
