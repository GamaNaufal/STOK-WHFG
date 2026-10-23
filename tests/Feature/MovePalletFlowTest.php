<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\AuditLog;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\Pallet;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovePalletFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createPallet(User $user, string $palletNumber, string $locationCode): array
    {
        $pallet = Pallet::create(['pallet_number' => $palletNumber]);
        $location = MasterLocation::create([
            'code' => $locationCode,
            'is_occupied' => true,
            'current_pallet_id' => $pallet->id,
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'master_location_id' => $location->id,
            'warehouse_location' => $locationCode,
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => "BOX-{$palletNumber}",
            'part_number' => 'PART-01',
            'pcs_quantity' => 100,
            'qr_code' => "{$palletNumber}|PART-01|100",
            'qty_box' => 1,
            'user_id' => $user->id,
        ]);
        $pallet->boxes()->attach($box->id);

        return [$pallet, $location];
    }

    public function test_admin_warehouse_can_move_pallet_and_update_occupancy(): void
    {
        $user = User::factory()->create(['role' => 'admin_warehouse']);
        [$pallet, $source] = $this->createPallet($user, 'PLT-MOVE-01', 'A-01');
        $target = MasterLocation::create(['code' => 'B-01', 'is_occupied' => false]);

        $response = $this->actingAs($user)->postJson(route('move-pallet.store'), [
            'pallet_id' => $pallet->id,
            'location_id' => $target->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $pallet->id,
            'master_location_id' => $target->id,
            'warehouse_location' => 'B-01',
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $source->id,
            'is_occupied' => 0,
            'current_pallet_id' => null,
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $target->id,
            'is_occupied' => 1,
            'current_pallet_id' => $pallet->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'type' => 'pallet_location_moved',
            'model_id' => $pallet->id,
        ]);

        $this->actingAs($user)->get(route('move-pallet.index'))
            ->assertOk()
            ->assertSee('Riwayat pemindahan pallet')
            ->assertSee('1 catatan')
            ->assertSeeInOrder(['Asal', 'A-01', 'Tujuan', 'B-01']);
    }

    public function test_move_history_shows_an_empty_state_before_first_move(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);

        $this->actingAs($user)->get(route('move-pallet.index'))
            ->assertOk()
            ->assertSee('0 catatan')
            ->assertSee('Belum ada pemindahan');
    }

    public function test_move_rejects_occupied_target_and_keeps_source(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        [$pallet, $source] = $this->createPallet($user, 'PLT-MOVE-02', 'A-02');
        [$otherPallet, $target] = $this->createPallet($user, 'PLT-MOVE-03', 'B-02');

        $response = $this->actingAs($user)->postJson(route('move-pallet.store'), [
            'pallet_id' => $pallet->id,
            'location_id' => $target->id,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $pallet->id,
            'master_location_id' => $source->id,
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $target->id,
            'current_pallet_id' => $otherPallet->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'type' => 'pallet_location_moved',
            'model_id' => $pallet->id,
        ]);
    }

    public function test_sales_cannot_access_move_pallet(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)
            ->get(route('move-pallet.index'))
            ->assertForbidden();
    }

    public function test_assigned_pallet_with_pending_pick_can_move_without_changing_delivery_assignment(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        [$pallet, $source] = $this->createPallet($user, 'PLT-ASSIGNED', 'A-ASSIGNED');
        $target = MasterLocation::create(['code' => 'B-ASSIGNED', 'is_occupied' => false]);
        $deliveryOrder = DeliveryOrder::create([
            'sales_user_id' => $user->id,
            'customer_name' => 'Assigned customer',
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);
        $box = $pallet->boxes()->first();
        $box->update(['assigned_delivery_order_id' => $deliveryOrder->id]);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $deliveryOrder->id,
            'created_by' => $user->id,
            'status' => 'pending',
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $box->id,
            'part_number' => $box->part_number,
            'pcs_quantity' => $box->pcs_quantity,
            'status' => 'pending',
        ]);

        $this->actingAs($user)->get(route('move-pallet.index'))
            ->assertOk()->assertSee($pallet->pallet_number);
        $this->getJson(route('move-pallet.search', ['code' => $pallet->pallet_number]))
            ->assertOk()->assertJsonPath('pallet.id', $pallet->id);
        $this->postJson(route('move-pallet.store'), [
            'pallet_id' => $pallet->id,
            'location_id' => $target->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'assigned_delivery_order_id' => $deliveryOrder->id,
        ]);
        $this->assertDatabaseHas('delivery_pick_items', [
            'pick_session_id' => $session->id,
            'box_id' => $box->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $pallet->id,
            'master_location_id' => $target->id,
            'warehouse_location' => $target->code,
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $source->id,
            'is_occupied' => false,
            'current_pallet_id' => null,
        ]);
    }

    public function test_not_full_pallet_can_move_without_changing_box_quantity(): void
    {
        $user = User::factory()->create(['role' => 'admin_warehouse']);
        [$pallet] = $this->createPallet($user, 'PLT-NOT-FULL', 'A-NOT-FULL');
        $target = MasterLocation::create(['code' => 'B-NOT-FULL', 'is_occupied' => false]);
        $box = $pallet->boxes()->first();
        $box->update(['is_not_full' => true, 'pcs_quantity' => 60, 'qty_box' => 100]);

        $this->actingAs($user)->get(route('move-pallet.index'))
            ->assertOk()->assertSee($pallet->pallet_number);
        $this->getJson(route('move-pallet.search', ['code' => $pallet->pallet_number]))
            ->assertOk()->assertJsonPath('pallet.total_pcs', 60);
        $this->postJson(route('move-pallet.store'), [
            'pallet_id' => $pallet->id,
            'location_id' => $target->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'is_not_full' => true,
            'pcs_quantity' => 60,
            'qty_box' => 100,
        ]);
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $pallet->id,
            'master_location_id' => $target->id,
        ]);
    }

    public function test_started_picking_still_blocks_move_and_preserves_location(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $target = MasterLocation::create(['code' => 'B-PICKING', 'is_occupied' => false]);

        foreach (['scanning', 'blocked', 'approved'] as $status) {
            [$pallet, $source] = $this->createPallet($user, "PLT-{$status}", "A-{$status}");
            $box = $pallet->boxes()->first();
            $order = DeliveryOrder::create([
                'sales_user_id' => $user->id,
                'customer_name' => "Picking {$status}",
                'delivery_date' => now()->toDateString(),
                'status' => 'approved',
            ]);
            $box->update(['assigned_delivery_order_id' => $order->id]);
            $session = DeliveryPickSession::create([
                'delivery_order_id' => $order->id,
                'created_by' => $user->id,
                'status' => $status,
            ]);
            DeliveryPickItem::create([
                'pick_session_id' => $session->id,
                'box_id' => $box->id,
                'part_number' => $box->part_number,
                'pcs_quantity' => $box->pcs_quantity,
                'status' => 'pending',
            ]);

            $this->actingAs($user)->getJson(route('move-pallet.search', ['code' => $pallet->pallet_number]))
                ->assertStatus(422)->assertJson(['success' => false]);
            $this->postJson(route('move-pallet.store'), [
                'pallet_id' => $pallet->id,
                'location_id' => $target->id,
            ])->assertStatus(422)->assertJson(['success' => false]);
            $this->assertDatabaseHas('stock_locations', [
                'pallet_id' => $pallet->id,
                'master_location_id' => $source->id,
            ]);
        }

        $page = $this->actingAs($user)->get(route('move-pallet.index'));
        foreach (['scanning', 'blocked', 'approved'] as $status) {
            $page->assertDontSee("PLT-{$status}");
        }
    }

    public function test_final_delivery_box_blocks_move_even_when_expired_box_is_not_in_active_stock(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        [$pallet, $source] = $this->createPallet($user, 'PLT-FINAL-LOCK', 'A-FINAL-LOCK');
        $target = MasterLocation::create(['code' => 'B-FINAL-LOCK', 'is_occupied' => false]);
        $order = DeliveryOrder::create([
            'sales_user_id' => $user->id,
            'customer_name' => 'Final delivery',
            'delivery_date' => now()->toDateString(),
            'status' => 'processing',
        ]);
        $lockedBox = Box::create([
            'box_number' => 'BOX-FINAL-LOCK',
            'part_number' => 'PART-01',
            'pcs_quantity' => 50,
            'qr_code' => 'BOX-FINAL-LOCK|PART-01|50',
            'qty_box' => 50,
            'user_id' => $user->id,
            'expired_status' => 'expired',
            'assigned_delivery_order_id' => $order->id,
        ]);
        $pallet->boxes()->attach($lockedBox->id);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $user->id,
            'status' => 'approved',
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $lockedBox->id,
            'part_number' => $lockedBox->part_number,
            'pcs_quantity' => 50,
            'status' => 'scanned',
        ]);

        $this->actingAs($user)->get(route('move-pallet.index'))
            ->assertOk()->assertDontSee($pallet->pallet_number);
        $this->getJson(route('move-pallet.search', ['code' => $pallet->pallet_number]))
            ->assertStatus(422)->assertJson(['success' => false]);
        $this->postJson(route('move-pallet.store'), [
            'pallet_id' => $pallet->id,
            'location_id' => $target->id,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $pallet->id,
            'master_location_id' => $source->id,
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $target->id,
            'is_occupied' => false,
        ]);
    }
}
