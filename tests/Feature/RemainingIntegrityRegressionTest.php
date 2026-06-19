<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use App\Services\ExpiredBoxService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemainingIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_box_deactivation_clears_summary_and_vacates_location(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-EXP-SYNC']);
        $location = MasterLocation::create([
            'code' => 'EXP-SYNC',
            'is_occupied' => true,
            'current_pallet_id' => $pallet->id,
        ]);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'master_location_id' => $location->id,
            'warehouse_location' => $location->code,
            'stored_at' => now()->subMonths(13),
        ]);
        $box = $this->createBox($operator, '94000001', 'P-EXP-SYNC', 20, [
            'created_at' => now()->subMonths(13),
            'updated_at' => now()->subMonths(13),
        ]);
        $pallet->boxes()->attach($box->id);
        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => $box->part_number,
            'box_quantity' => 1,
            'pcs_quantity' => 20,
        ]);

        app(ExpiredBoxService::class)->syncStatuses();

        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'expired_status' => 'expired']);
        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $pallet->id,
            'part_number' => $box->part_number,
            'box_quantity' => 0,
            'pcs_quantity' => 0,
        ]);
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $pallet->id]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $location->id,
            'is_occupied' => false,
            'current_pallet_id' => null,
        ]);
    }

    public function test_start_pick_reuses_pending_assignment_session_without_orphan_lock(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-PENDING-REUSE', 30);
        [, $box] = $this->createStoredBox($operator, '94000002', 'P-PENDING-REUSE', 30, 'REUSE-1');
        $box->update(['assigned_delivery_order_id' => $order->id]);

        $pending = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'pending',
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $pending->id,
            'box_id' => $box->id,
            'part_number' => $box->part_number,
            'pcs_quantity' => 30,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($operator)->postJson(route('delivery.pick.start', $order->id));

        $response->assertOk()->assertJson(['session_id' => $pending->id]);
        $this->assertDatabaseHas('delivery_pick_sessions', [
            'id' => $pending->id,
            'status' => 'scanning',
        ]);
        $this->assertSame(0, DeliveryPickSession::where('delivery_order_id', $order->id)->where('status', 'pending')->count());
        $this->assertSame(1, DeliveryPickItem::where('pick_session_id', $pending->id)->count());
    }

    public function test_split_is_blocked_when_order_has_pending_assignment_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-SPLIT-LOCK', 10);
        DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $admin->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('delivery.split', $order->id), [
            'items' => [['part_number' => 'P-SPLIT-LOCK', 'quantity' => 5]],
        ]);

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, DeliveryOrder::where('parent_delivery_order_id', $order->id)->count());
        $this->assertSame(10, (int) $order->items()->firstOrFail()->quantity);
    }

    public function test_delivery_status_and_sales_edit_cannot_replay_after_state_has_advanced(): void
    {
        $ppc = User::factory()->create(['role' => 'ppc']);
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create(['part_number' => 'P-STATE-GUARD', 'qty_box' => 10]);
        $order = $this->createOrder($sales, 'P-STATE-GUARD', 10);

        $this->actingAs($ppc)->post(route('delivery.status', $order->id), [
            'status' => 'rejected',
            'notes' => 'replay',
        ])->assertSessionHas('error');

        $this->actingAs($sales)->put(route('delivery.update', $order->id), [
            'customer_name' => 'Changed Illegally',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'items' => [['part_number' => 'P-STATE-GUARD', 'quantity' => 10]],
        ])->assertSessionHas('error');

        $order->refresh();
        $this->assertSame('approved', $order->status);
        $this->assertNotSame('Changed Illegally', $order->customer_name);
    }

    public function test_completed_delivery_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-HISTORY-GUARD', 10, 'completed');
        DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $admin->id,
            'status' => 'completed',
            'completion_status' => 'completed',
            'completed_at' => now(),
            'redo_until' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->delete(route('delivery.destroy', $order->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('delivery_orders', ['id' => $order->id, 'deleted_at' => null]);
    }

    public function test_referenced_master_part_cannot_be_renamed_or_deleted(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $part = PartSetting::create(['part_number' => 'P-MASTER-GUARD', 'qty_box' => 10]);
        $box = $this->createBox($adminWarehouse, '94000003', $part->part_number, 10);
        $box->delete();

        $this->actingAs($adminWarehouse)->put(route('part-settings.update', $part), [
            'part_number' => 'P-MASTER-RENAMED',
            'qty_box' => 20,
        ])->assertSessionHas('error');

        $this->actingAs($adminWarehouse)
            ->delete(route('part-settings.destroy', $part))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('part_settings', [
            'id' => $part->id,
            'part_number' => 'P-MASTER-GUARD',
            'qty_box' => 10,
        ]);
    }

    public function test_picklist_pdf_is_forbidden_for_sales_and_other_operator(): void
    {
        $owner = User::factory()->create(['role' => 'warehouse_operator']);
        $otherOperator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-PDF-AUTH', 10);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $owner->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);

        $this->actingAs($sales)
            ->get(route('delivery.pick.pdf', [$order->id, $session->id]))
            ->assertForbidden();
        $this->actingAs($otherOperator)
            ->get(route('delivery.pick.pdf', [$order->id, $session->id]))
            ->assertForbidden();
    }

    public function test_redo_cannot_be_replayed_after_session_is_redone(): void
    {
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-REDO-GUARD', 10, 'processing');
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $adminWarehouse->id,
            'status' => 'completed',
            'completion_status' => 'redone',
            'completed_at' => now(),
            'redo_until' => now()->addDay(),
        ]);

        $this->actingAs($adminWarehouse)
            ->post(route('delivery.pick.redo', $session->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('delivery_pick_sessions', [
            'id' => $session->id,
            'completion_status' => 'redone',
        ]);
    }

    public function test_redo_normalizes_shared_box_to_withdrawal_pallet_and_cancels_pending_assignment(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = $this->createOrder($sales, 'P-REDO-SHARED', 10, 'processing');

        $palletA = Pallet::create(['pallet_number' => 'PLT-REDO-SHARED-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-REDO-SHARED-B']);
        $locationA = MasterLocation::create([
            'code' => 'REDO-SA',
            'is_occupied' => true,
            'current_pallet_id' => $palletA->id,
        ]);
        $locationB = MasterLocation::create([
            'code' => 'REDO-SB',
            'is_occupied' => true,
            'current_pallet_id' => $palletB->id,
        ]);
        StockLocation::create([
            'pallet_id' => $palletA->id,
            'master_location_id' => $locationA->id,
            'warehouse_location' => $locationA->code,
            'stored_at' => now(),
        ]);
        StockLocation::create([
            'pallet_id' => $palletB->id,
            'master_location_id' => $locationB->id,
            'warehouse_location' => $locationB->code,
            'stored_at' => now(),
        ]);

        $box = $this->createBox($operator, '94000005', 'P-REDO-SHARED', 10);
        $palletA->boxes()->attach($box->id);
        $palletB->boxes()->attach($box->id);
        foreach ([$palletA, $palletB] as $pallet) {
            PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $box->part_number,
                'box_quantity' => 1,
                'pcs_quantity' => 10,
            ]);
        }

        $completedSession = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $completedSession->id,
            'box_id' => $box->id,
            'part_number' => $box->part_number,
            'pcs_quantity' => 10,
            'status' => 'scanned',
            'scanned_at' => now(),
            'scanned_by' => $operator->id,
        ]);

        $this->actingAs($operator)
            ->postJson(route('delivery.pick.complete', $completedSession->id))
            ->assertOk();

        $pendingSession = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $adminWarehouse->id,
            'status' => 'pending',
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $pendingSession->id,
            'box_id' => $box->id,
            'part_number' => $box->part_number,
            'pcs_quantity' => 10,
            'status' => 'pending',
        ]);

        $this->actingAs($adminWarehouse)
            ->post(route('delivery.pick.redo', $completedSession->id))
            ->assertSessionHas('success');

        $this->assertSame([$palletB->id], $box->fresh()->pallets()->pluck('pallets.id')->map(fn ($id) => (int) $id)->all());
        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $palletA->id,
            'part_number' => $box->part_number,
            'box_quantity' => 0,
            'pcs_quantity' => 0,
        ]);
        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $palletB->id,
            'part_number' => $box->part_number,
            'box_quantity' => 1,
            'pcs_quantity' => 10,
        ]);
        $this->assertDatabaseHas('delivery_pick_sessions', [
            'id' => $pendingSession->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('delivery_pick_items', ['pick_session_id' => $pendingSession->id]);
    }

    public function test_soft_deleted_box_with_stale_summary_is_not_available_stock(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        PartSetting::create(['part_number' => 'P-DELETED-GHOST', 'qty_box' => 10]);
        [, $box] = $this->createStoredBox($operator, '94000004', 'P-DELETED-GHOST', 10, 'GHOST-1');
        $box->delete();

        $this->actingAs($operator)->postJson(route('stock-withdrawal.preview'), [
            'part_number' => 'P-DELETED-GHOST',
            'pcs_quantity' => 10,
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_stock_location_allows_only_one_row_per_pallet(): void
    {
        $pallet = Pallet::create(['pallet_number' => 'PLT-UNIQUE-LOC']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'UNIQUE-1',
            'stored_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'UNIQUE-2',
            'stored_at' => now(),
        ]);
    }

    private function createOrder(User $sales, string $partNumber, int $quantity, string $status = 'approved'): DeliveryOrder
    {
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Remaining Integrity Customer',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => $status,
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => $partNumber,
            'quantity' => $quantity,
            'fulfilled_quantity' => 0,
        ]);

        return $order;
    }

    private function createStoredBox(
        User $user,
        string $boxNumber,
        string $partNumber,
        int $pcs,
        string $location
    ): array {
        $pallet = Pallet::create(['pallet_number' => 'PLT-' . $boxNumber]);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => $location,
            'stored_at' => now(),
        ]);
        $box = $this->createBox($user, $boxNumber, $partNumber, $pcs);
        $pallet->boxes()->attach($box->id);
        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => $partNumber,
            'box_quantity' => 1,
            'pcs_quantity' => $pcs,
        ]);

        return [$pallet, $box];
    }

    private function createBox(
        User $user,
        string $boxNumber,
        string $partNumber,
        int $pcs,
        array $extra = []
    ): Box {
        return Box::create(array_merge([
            'box_number' => $boxNumber,
            'part_number' => $partNumber,
            'pcs_quantity' => $pcs,
            'qty_box' => $pcs,
            'qr_code' => "{$boxNumber}|{$partNumber}|{$pcs}",
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ], $extra));
    }
}
