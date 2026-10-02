<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInputPostMergeLocationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOperator(): User
    {
        return User::factory()->create(['role' => 'warehouse_operator']);
    }

    public function test_stock_input_succeeds_even_when_orphan_stock_location_exists_at_target(): void
    {
        $operator = $this->makeOperator();

        // 1. Create a location (marked as free in master_locations)
        $masterLoc = MasterLocation::create([
            'code' => 'A28',
            'is_occupied' => false,
            'current_pallet_id' => null,
        ]);

        // 2. Simulate dirty data: an orphan stock_location row remains pointing to a deleted pallet
        $oldPallet = Pallet::create(['pallet_number' => 'PLT-OLD']);
        $orphanLocation = StockLocation::create([
            'pallet_id' => $oldPallet->id,
            'master_location_id' => $masterLoc->id,
            'warehouse_location' => 'A28',
            'stored_at' => now()->subDays(2),
        ]);
        $oldPallet->delete(); // Old pallet is deleted (like after merge)

        // 3. Create a box ready for stock input
        $box = Box::create([
            'box_number' => '12345678',
            'part_number' => 'PART-A',
            'pcs_quantity' => 50,
            'qr_code' => '12345678|PART-A|50',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);

        // 4. Create active pallet for input stock
        $newPallet = Pallet::create(['pallet_number' => 'PLT-1206']);

        // 5. Execute stock input
        $response = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $newPallet->id,
                'current_pallet_source' => 'new',
                'scanned_boxes' => [
                    [
                        'box_number' => '12345678',
                        'part_number' => 'PART-A',
                        'pcs_quantity' => 50,
                    ],
                ],
            ])
            ->postJson(route('stock-input.store'), [
                'pallet_id' => $newPallet->id,
                'location_id' => $masterLoc->id,
                'warehouse_location' => 'A28',
            ]);

        // Must succeed with 200, not fail with 500 Duplicate entry
        $response->assertStatus(200);

        // Verify stock_locations has exactly 1 row for A28, pointing to newPallet
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $newPallet->id,
            'master_location_id' => $masterLoc->id,
            'warehouse_location' => 'A28',
        ]);
        $this->assertDatabaseMissing('stock_locations', [
            'id' => $orphanLocation->id,
        ]);

        // Verify master location is occupied by newPallet
        $masterLoc->refresh();
        $this->assertTrue($masterLoc->is_occupied);
        $this->assertEquals($newPallet->id, $masterLoc->current_pallet_id);
    }

    public function test_stock_input_rejects_with_friendly_message_if_location_still_has_active_pallet(): void
    {
        $operator = $this->makeOperator();

        // 1. Create an active pallet with a box
        $activePallet = Pallet::create(['pallet_number' => 'PLT-ACTIVE']);
        $box = Box::create([
            'box_number' => '88888888',
            'part_number' => 'PART-ACTIVE',
            'pcs_quantity' => 20,
            'qr_code' => '88888888|PART-ACTIVE|20',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);
        $activePallet->boxes()->attach($box->id);

        // Master location accidentally has is_occupied = false (desync)
        $masterLoc = MasterLocation::create([
            'code' => 'A28',
            'is_occupied' => false,
            'current_pallet_id' => null,
        ]);

        StockLocation::create([
            'pallet_id' => $activePallet->id,
            'master_location_id' => $masterLoc->id,
            'warehouse_location' => 'A28',
            'stored_at' => now(),
        ]);

        // 2. Try to store another pallet into A28
        $newPallet = Pallet::create(['pallet_number' => 'PLT-NEW']);
        $newBox = Box::create([
            'box_number' => '99999999',
            'part_number' => 'PART-NEW',
            'pcs_quantity' => 10,
            'qr_code' => '99999999|PART-NEW|10',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);

        $response = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $newPallet->id,
                'current_pallet_source' => 'new',
                'scanned_boxes' => [
                    [
                        'box_number' => '99999999',
                        'part_number' => 'PART-NEW',
                        'pcs_quantity' => 10,
                    ],
                ],
            ])
            ->postJson(route('stock-input.store'), [
                'pallet_id' => $newPallet->id,
                'location_id' => $masterLoc->id,
                'warehouse_location' => 'A28',
            ]);

        // Must reject with 422, not 500 SQL crash
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'success' => false,
        ]);
        $this->assertStringContainsString('PLT-ACTIVE', $response->json('message'));

        // Master location is now properly synced
        $masterLoc->refresh();
        $this->assertTrue($masterLoc->is_occupied);
        $this->assertEquals($activePallet->id, $masterLoc->current_pallet_id);
    }
}
