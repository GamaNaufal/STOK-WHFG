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
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MergePalletFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_merge_search_rejects_pallet_with_only_expired_boxes(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-EXP-ONLY-01']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A9',
            'stored_at' => now(),
        ]);

        $expiredBox = Box::create([
            'box_number' => 'BOX-EXP-ONLY-01',
            'part_number' => 'P-EXP-ONLY',
            'pcs_quantity' => 10,
            'qr_code' => 'BOX-EXP-ONLY-01|P-EXP-ONLY|10',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'expired',
        ]);
        $pallet->boxes()->attach($expiredBox->id);

        $response = $this->actingAs($operator)->getJson(route('merge-pallet.search', [
            'code' => $pallet->pallet_number,
        ]));

        $response->assertStatus(404);
    }

    public function test_merge_pallet_fails_when_all_source_boxes_are_inactive(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet1 = Pallet::create(['pallet_number' => 'PLT-010']);
        $pallet2 = Pallet::create(['pallet_number' => 'PLT-011']);

        StockLocation::create([
            'pallet_id' => $pallet1->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        StockLocation::create([
            'pallet_id' => $pallet2->id,
            'warehouse_location' => 'A2',
            'stored_at' => now(),
        ]);

        $inactiveBox1 = Box::create([
            'box_number' => 'BOX-INACTIVE-1',
            'part_number' => 'P-IN-1',
            'pcs_quantity' => 10,
            'qr_code' => 'BOX-INACTIVE-1|P-IN-1|10',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => true,
        ]);

        $inactiveBox2 = Box::create([
            'box_number' => 'BOX-INACTIVE-2',
            'part_number' => 'P-IN-2',
            'pcs_quantity' => 20,
            'qr_code' => 'BOX-INACTIVE-2|P-IN-2|20',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'expired_status' => 'expired',
        ]);

        $pallet1->boxes()->attach($inactiveBox1->id);
        $pallet2->boxes()->attach($inactiveBox2->id);

        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet1->id, $pallet2->id],
            'warehouse_location' => 'B1',
        ]);

        $response->assertStatus(422)->assertJson([
            'success' => false,
        ]);

        $this->assertDatabaseHas('pallets', ['id' => $pallet1->id]);
        $this->assertDatabaseHas('pallets', ['id' => $pallet2->id]);
    }

    public function test_merge_pallet_fails_when_target_location_is_occupied_and_rolls_back(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet1 = Pallet::create(['pallet_number' => 'PLT-020']);
        $pallet2 = Pallet::create(['pallet_number' => 'PLT-021']);

        StockLocation::create([
            'pallet_id' => $pallet1->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        StockLocation::create([
            'pallet_id' => $pallet2->id,
            'warehouse_location' => 'A2',
            'stored_at' => now(),
        ]);

        $occupiedTarget = MasterLocation::create([
            'code' => 'B2',
            'is_occupied' => true,
        ]);

        $box1 = Box::create([
            'box_number' => 'BOX-ROLLBACK-1',
            'part_number' => 'P-RB-1',
            'pcs_quantity' => 10,
            'qr_code' => 'BOX-ROLLBACK-1|P-RB-1|10',
            'qty_box' => 1,
            'user_id' => $operator->id,
        ]);

        $box2 = Box::create([
            'box_number' => 'BOX-ROLLBACK-2',
            'part_number' => 'P-RB-1',
            'pcs_quantity' => 20,
            'qr_code' => 'BOX-ROLLBACK-2|P-RB-1|20',
            'qty_box' => 1,
            'user_id' => $operator->id,
        ]);

        $pallet1->boxes()->attach($box1->id);
        $pallet2->boxes()->attach($box2->id);

        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet1->id, $pallet2->id],
            'warehouse_location' => 'B2',
            'location_id' => $occupiedTarget->id,
        ]);

        $response->assertStatus(500)->assertJson([
            'success' => false,
        ]);

        $this->assertDatabaseHas('pallets', ['id' => $pallet1->id]);
        $this->assertDatabaseHas('pallets', ['id' => $pallet2->id]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $occupiedTarget->id,
            'is_occupied' => 1,
        ]);
    }

    public function test_merge_pallet_combines_boxes_cleans_sources_and_sets_new_location(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet1 = Pallet::create(['pallet_number' => 'PLT-001']);
        $pallet2 = Pallet::create(['pallet_number' => 'PLT-002']);

        $location1 = MasterLocation::create([
            'code' => 'A1',
            'is_occupied' => true,
            'current_pallet_id' => $pallet1->id,
        ]);

        $location2 = MasterLocation::create([
            'code' => 'A2',
            'is_occupied' => true,
            'current_pallet_id' => $pallet2->id,
        ]);

        $newLocation = MasterLocation::create([
            'code' => 'B1',
            'is_occupied' => false,
        ]);

        StockLocation::create([
            'pallet_id' => $pallet1->id,
            'warehouse_location' => $location1->code,
            'stored_at' => now(),
            'master_location_id' => $location1->id,
        ]);

        StockLocation::create([
            'pallet_id' => $pallet2->id,
            'warehouse_location' => $location2->code,
            'stored_at' => now(),
            'master_location_id' => $location2->id,
        ]);

        PalletItem::create([
            'pallet_id' => $pallet1->id,
            'part_number' => 'P-M1',
            'box_quantity' => 1,
            'pcs_quantity' => 10,
        ]);

        PalletItem::create([
            'pallet_id' => $pallet2->id,
            'part_number' => 'P-M1',
            'box_quantity' => 1,
            'pcs_quantity' => 20,
        ]);

        StockInput::create([
            'pallet_id' => $pallet1->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'A1',
            'pcs_quantity' => 10,
            'box_quantity' => 1,
            'stored_at' => now(),
            'part_numbers' => ['P-M1'],
        ]);

        StockInput::create([
            'pallet_id' => $pallet2->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'A2',
            'pcs_quantity' => 20,
            'box_quantity' => 1,
            'stored_at' => now(),
            'part_numbers' => ['P-M1'],
        ]);

        $box1 = Box::create([
            'box_number' => 'BOX-M-1',
            'part_number' => 'P-M1',
            'pcs_quantity' => 10,
            'qr_code' => 'BOX-M-1|P-M1|10',
            'qty_box' => 1,
            'user_id' => $operator->id,
        ]);

        $box2 = Box::create([
            'box_number' => 'BOX-M-2',
            'part_number' => 'P-M1',
            'pcs_quantity' => 20,
            'qr_code' => 'BOX-M-2|P-M1|20',
            'qty_box' => 1,
            'user_id' => $operator->id,
        ]);

        $pallet1->boxes()->attach($box1->id);
        $pallet2->boxes()->attach($box2->id);

        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet1->id, $pallet2->id],
            'warehouse_location' => 'B1',
            'location_id' => $newLocation->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $newPalletNumber = (string) $response->json('new_pallet_number');
        $newPallet = Pallet::query()->where('pallet_number', $newPalletNumber)->first();

        $this->assertNotNull($newPallet);

        $this->assertSoftDeleted('pallets', ['id' => $pallet1->id]);
        $this->assertSoftDeleted('pallets', ['id' => $pallet2->id]);

        $this->assertDatabaseHas('master_locations', [
            'id' => $location1->id,
            'is_occupied' => 0,
            'current_pallet_id' => null,
        ]);

        $this->assertDatabaseHas('master_locations', [
            'id' => $location2->id,
            'is_occupied' => 0,
            'current_pallet_id' => null,
        ]);

        $this->assertDatabaseHas('master_locations', [
            'id' => $newLocation->id,
            'is_occupied' => 1,
            'current_pallet_id' => $newPallet->id,
        ]);

        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $newPallet->id,
            'warehouse_location' => 'B1',
        ]);

        $this->assertDatabaseCount('pallet_boxes', 2);

        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $newPallet->id,
            'part_number' => 'P-M1',
            'box_quantity' => 2,
            'pcs_quantity' => 30,
        ]);

        $this->assertEquals(
            2,
            StockInput::where('pallet_id', $newPallet->id)->count()
        );
    }

    public function test_merge_rejects_pallet_containing_assigned_box(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Merge Assigned Guard',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-MERGE-GUARD',
            'quantity' => 20,
            'fulfilled_quantity' => 0,
        ]);
        $target = MasterLocation::create([
            'code' => 'MERGE-GUARD-TARGET',
            'is_occupied' => false,
        ]);

        $pallet1 = Pallet::create(['pallet_number' => 'PLT-MERGE-GUARD-1']);
        $pallet2 = Pallet::create(['pallet_number' => 'PLT-MERGE-GUARD-2']);
        StockLocation::create(['pallet_id' => $pallet1->id, 'warehouse_location' => 'MG-1', 'stored_at' => now()]);
        StockLocation::create(['pallet_id' => $pallet2->id, 'warehouse_location' => 'MG-2', 'stored_at' => now()]);

        $assignedBox = Box::create([
            'box_number' => '92000001',
            'part_number' => 'P-MERGE-GUARD',
            'pcs_quantity' => 20,
            'qty_box' => 20,
            'qr_code' => '92000001|P-MERGE-GUARD|20',
            'user_id' => $operator->id,
            'assigned_delivery_order_id' => $order->id,
        ]);
        $freeBox = Box::create([
            'box_number' => '92000002',
            'part_number' => 'P-MERGE-GUARD',
            'pcs_quantity' => 20,
            'qty_box' => 20,
            'qr_code' => '92000002|P-MERGE-GUARD|20',
            'user_id' => $operator->id,
        ]);
        $pallet1->boxes()->attach($assignedBox->id);
        $pallet2->boxes()->attach($freeBox->id);

        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet1->id, $pallet2->id],
            'location_id' => $target->id,
            'warehouse_location' => $target->code,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('pallets', ['id' => $pallet1->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('pallet_boxes', ['pallet_id' => $pallet1->id, 'box_id' => $assignedBox->id]);
    }

    public function test_merge_rejects_pallet_containing_pick_locked_box(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Merge Pick Guard',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-MERGE-PICK',
            'quantity' => 20,
            'fulfilled_quantity' => 0,
        ]);
        $target = MasterLocation::create([
            'code' => 'MERGE-PICK-TARGET',
            'is_occupied' => false,
        ]);
        $pallet1 = Pallet::create(['pallet_number' => 'PLT-MERGE-PICK-1']);
        $pallet2 = Pallet::create(['pallet_number' => 'PLT-MERGE-PICK-2']);
        StockLocation::create(['pallet_id' => $pallet1->id, 'warehouse_location' => 'MP-1', 'stored_at' => now()]);
        StockLocation::create(['pallet_id' => $pallet2->id, 'warehouse_location' => 'MP-2', 'stored_at' => now()]);
        $lockedBox = Box::create([
            'box_number' => '92000003',
            'part_number' => 'P-MERGE-PICK',
            'pcs_quantity' => 20,
            'qty_box' => 20,
            'qr_code' => '92000003|P-MERGE-PICK|20',
            'user_id' => $operator->id,
        ]);
        $freeBox = Box::create([
            'box_number' => '92000004',
            'part_number' => 'P-MERGE-PICK',
            'pcs_quantity' => 20,
            'qty_box' => 20,
            'qr_code' => '92000004|P-MERGE-PICK|20',
            'user_id' => $operator->id,
        ]);
        $pallet1->boxes()->attach($lockedBox->id);
        $pallet2->boxes()->attach($freeBox->id);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'scanning',
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $lockedBox->id,
            'part_number' => $lockedBox->part_number,
            'pcs_quantity' => 20,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet1->id, $pallet2->id],
            'location_id' => $target->id,
            'warehouse_location' => $target->code,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('pallet_boxes', ['pallet_id' => $pallet1->id, 'box_id' => $lockedBox->id]);
    }

    public function test_merge_rejects_free_text_target_location_without_master_id(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $pallet1 = Pallet::create(['pallet_number' => 'PLT-MERGE-FREE-1']);
        $pallet2 = Pallet::create(['pallet_number' => 'PLT-MERGE-FREE-2']);
        StockLocation::create(['pallet_id' => $pallet1->id, 'warehouse_location' => 'MF-1', 'stored_at' => now()]);
        StockLocation::create(['pallet_id' => $pallet2->id, 'warehouse_location' => 'MF-2', 'stored_at' => now()]);
        $box1 = Box::create([
            'box_number' => '92000005',
            'part_number' => 'P-MERGE-FREE',
            'pcs_quantity' => 20,
            'qty_box' => 20,
            'qr_code' => '92000005|P-MERGE-FREE|20',
            'user_id' => $operator->id,
        ]);
        $box2 = Box::create([
            'box_number' => '92000006',
            'part_number' => 'P-MERGE-FREE',
            'pcs_quantity' => 20,
            'qty_box' => 20,
            'qr_code' => '92000006|P-MERGE-FREE|20',
            'user_id' => $operator->id,
        ]);
        $pallet1->boxes()->attach($box1->id);
        $pallet2->boxes()->attach($box2->id);

        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet1->id, $pallet2->id],
            'warehouse_location' => 'LOKASI-FIKTIF',
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseMissing('stock_locations', [
            'warehouse_location' => 'LOKASI-FIKTIF',
        ]);
    }
}
