<?php

namespace Tests\Feature;

use App\Models\Box;
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

        $this->assertDatabaseMissing('pallets', ['id' => $pallet1->id]);
        $this->assertDatabaseMissing('pallets', ['id' => $pallet2->id]);

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
}
