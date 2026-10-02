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

/**
 * Tests for the merge pallet location-conflict fix.
 *
 * Covers three positive scenarios:
 *   1. Target = location of pallet A (source)
 *   2. Target = location of pallet B (source)
 *   3. Target = a completely free location
 *
 * Plus one negative scenario:
 *   4. Target = location held by an unrelated pallet → must fail and rollback
 */
class MergePalletLocationConflictTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function makeOperator(): User
    {
        return User::factory()->create(['role' => 'warehouse_operator']);
    }

    /**
     * Create a pallet with one active box, a stock_location, and linked master_location.
     */
    private function createPalletWithBox(
        string $palletNumber,
        string $locationCode,
        string $boxNumber,
        string $partNumber,
        int $pcs,
        User $operator,
    ): array {
        $pallet = Pallet::create(['pallet_number' => $palletNumber]);

        $masterLocation = MasterLocation::create([
            'code' => $locationCode,
            'is_occupied' => true,
            'current_pallet_id' => $pallet->id,
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'master_location_id' => $masterLocation->id,
            'warehouse_location' => $locationCode,
            'stored_at' => now(),
        ]);

        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => $partNumber,
            'box_quantity' => 1,
            'pcs_quantity' => $pcs,
        ]);

        StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => $locationCode,
            'pcs_quantity' => $pcs,
            'box_quantity' => 1,
            'stored_at' => now(),
            'part_numbers' => [$partNumber],
        ]);

        $box = Box::create([
            'box_number' => $boxNumber,
            'part_number' => $partNumber,
            'pcs_quantity' => $pcs,
            'qr_code' => "{$boxNumber}|{$partNumber}|{$pcs}",
            'qty_box' => 1,
            'user_id' => $operator->id,
        ]);

        $pallet->boxes()->attach($box->id);

        return [$pallet, $masterLocation, $box];
    }

    /**
     * Assert that a successful merge cleaned up the expected data.
     */
    private function assertMergeSucceeded(
        $response,
        Pallet $sourceA,
        Pallet $sourceB,
        MasterLocation $targetLocation,
    ): Pallet {
        $response->assertOk()->assertJson(['success' => true]);

        $newPalletNumber = (string) $response->json('new_pallet_number');
        $newPallet = Pallet::where('pallet_number', $newPalletNumber)->first();
        $this->assertNotNull($newPallet, 'New pallet should exist');

        // Source pallets soft-deleted
        $this->assertSoftDeleted('pallets', ['id' => $sourceA->id]);
        $this->assertSoftDeleted('pallets', ['id' => $sourceB->id]);

        // Source stock_locations removed
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $sourceA->id]);
        $this->assertDatabaseMissing('stock_locations', ['pallet_id' => $sourceB->id]);

        // New pallet has the target location
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $newPallet->id,
            'master_location_id' => $targetLocation->id,
            'warehouse_location' => $targetLocation->code,
        ]);

        // Target master_location occupied by new pallet
        $this->assertDatabaseHas('master_locations', [
            'id' => $targetLocation->id,
            'is_occupied' => 1,
            'current_pallet_id' => $newPallet->id,
        ]);

        // New pallet has 2 boxes attached
        $this->assertEquals(2, $newPallet->boxes()->count());

        // Pallet items rebuilt
        $this->assertTrue($newPallet->items()->exists());

        return $newPallet;
    }

    // ---------------------------------------------------------------
    // POSITIVE: target = location of pallet A
    // ---------------------------------------------------------------

    public function test_merge_target_is_location_of_pallet_a(): void
    {
        $op = $this->makeOperator();

        [$palletA, $locA, $boxA] = $this->createPalletWithBox(
            'PLT-LOC-A1', 'LC-A1', 'BOX-LA-01', 'PART-LA', 10, $op
        );
        [$palletB, $locB, $boxB] = $this->createPalletWithBox(
            'PLT-LOC-A2', 'LC-A2', 'BOX-LA-02', 'PART-LA', 20, $op
        );

        $response = $this->actingAs($op)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$palletA->id, $palletB->id],
            'location_id' => $locA->id,
            'warehouse_location' => $locA->code,
        ]);

        $newPallet = $this->assertMergeSucceeded($response, $palletA, $palletB, $locA);

        // Location B should be freed
        $this->assertDatabaseHas('master_locations', [
            'id' => $locB->id,
            'is_occupied' => 0,
            'current_pallet_id' => null,
        ]);

        // Only 1 stock_location row for the new pallet (unique constraints intact)
        $this->assertEquals(1, StockLocation::where('pallet_id', $newPallet->id)->count());
    }

    // ---------------------------------------------------------------
    // POSITIVE: target = location of pallet B
    // ---------------------------------------------------------------

    public function test_merge_target_is_location_of_pallet_b(): void
    {
        $op = $this->makeOperator();

        [$palletA, $locA, $boxA] = $this->createPalletWithBox(
            'PLT-LOC-B1', 'LC-B1', 'BOX-LB-01', 'PART-LB', 15, $op
        );
        [$palletB, $locB, $boxB] = $this->createPalletWithBox(
            'PLT-LOC-B2', 'LC-B2', 'BOX-LB-02', 'PART-LB', 25, $op
        );

        $response = $this->actingAs($op)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$palletA->id, $palletB->id],
            'location_id' => $locB->id,
            'warehouse_location' => $locB->code,
        ]);

        $newPallet = $this->assertMergeSucceeded($response, $palletA, $palletB, $locB);

        // Location A should be freed
        $this->assertDatabaseHas('master_locations', [
            'id' => $locA->id,
            'is_occupied' => 0,
            'current_pallet_id' => null,
        ]);

        $this->assertEquals(1, StockLocation::where('pallet_id', $newPallet->id)->count());
    }

    // ---------------------------------------------------------------
    // POSITIVE: target = an empty third location
    // ---------------------------------------------------------------

    public function test_merge_target_is_empty_third_location(): void
    {
        $op = $this->makeOperator();

        [$palletA, $locA, $boxA] = $this->createPalletWithBox(
            'PLT-LOC-C1', 'LC-C1', 'BOX-LC-01', 'PART-LC', 12, $op
        );
        [$palletB, $locB, $boxB] = $this->createPalletWithBox(
            'PLT-LOC-C2', 'LC-C2', 'BOX-LC-02', 'PART-LC', 18, $op
        );

        $freeLocation = MasterLocation::create([
            'code' => 'LC-FREE',
            'is_occupied' => false,
        ]);

        $response = $this->actingAs($op)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$palletA->id, $palletB->id],
            'location_id' => $freeLocation->id,
            'warehouse_location' => $freeLocation->code,
        ]);

        $newPallet = $this->assertMergeSucceeded($response, $palletA, $palletB, $freeLocation);

        // Both source locations should be freed
        $this->assertDatabaseHas('master_locations', [
            'id' => $locA->id, 'is_occupied' => 0, 'current_pallet_id' => null,
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $locB->id, 'is_occupied' => 0, 'current_pallet_id' => null,
        ]);

        $this->assertEquals(1, StockLocation::where('pallet_id', $newPallet->id)->count());
    }

    // ---------------------------------------------------------------
    // NEGATIVE: target location held by an unrelated pallet → fail + rollback
    // ---------------------------------------------------------------

    public function test_merge_fails_when_target_held_by_unrelated_pallet(): void
    {
        $op = $this->makeOperator();

        [$palletA, $locA, $boxA] = $this->createPalletWithBox(
            'PLT-LOC-D1', 'LC-D1', 'BOX-LD-01', 'PART-LD', 10, $op
        );
        [$palletB, $locB, $boxB] = $this->createPalletWithBox(
            'PLT-LOC-D2', 'LC-D2', 'BOX-LD-02', 'PART-LD', 20, $op
        );

        // Create a third pallet occupying a location
        $otherPallet = Pallet::create(['pallet_number' => 'PLT-OTHER-OCCUPANT']);
        $occupiedLocation = MasterLocation::create([
            'code' => 'LC-OCCUPIED',
            'is_occupied' => true,
            'current_pallet_id' => $otherPallet->id,
        ]);
        StockLocation::create([
            'pallet_id' => $otherPallet->id,
            'master_location_id' => $occupiedLocation->id,
            'warehouse_location' => 'LC-OCCUPIED',
            'stored_at' => now(),
        ]);
        $otherBox = Box::create([
            'box_number' => 'BOX-OTHER-OCC',
            'part_number' => 'PART-OTHER',
            'pcs_quantity' => 5,
            'qr_code' => 'BOX-OTHER-OCC|PART-OTHER|5',
            'qty_box' => 1,
            'user_id' => $op->id,
        ]);
        $otherPallet->boxes()->attach($otherBox->id);

        $response = $this->actingAs($op)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$palletA->id, $palletB->id],
            'location_id' => $occupiedLocation->id,
            'warehouse_location' => $occupiedLocation->code,
        ]);

        // Should fail with user-friendly message (not raw SQL)
        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringNotContainsString('SQLSTATE', $response->json('message'));
        $this->assertStringContainsString('sudah digunakan', $response->json('message'));

        // ROLLBACK assertions: nothing should have changed
        $this->assertDatabaseHas('pallets', ['id' => $palletA->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('pallets', ['id' => $palletB->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('stock_locations', ['pallet_id' => $palletA->id]);
        $this->assertDatabaseHas('stock_locations', ['pallet_id' => $palletB->id]);

        // Occupied location unchanged
        $this->assertDatabaseHas('master_locations', [
            'id' => $occupiedLocation->id,
            'is_occupied' => 1,
            'current_pallet_id' => $otherPallet->id,
        ]);

        // Other pallet's stock_location intact
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $otherPallet->id,
            'master_location_id' => $occupiedLocation->id,
        ]);
    }
}
