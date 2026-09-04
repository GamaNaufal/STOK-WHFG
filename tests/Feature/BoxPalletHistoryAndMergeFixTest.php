<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BoxPalletHistoryAndMergeFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_pallet_detail_and_history_endpoints_return_pallet_history(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $masterLocation = MasterLocation::create([
            'code' => 'A-01',
            'is_occupied' => true,
        ]);

        $pallet = Pallet::create(['pallet_number' => 'PLT-TEST-001']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'master_location_id' => $masterLocation->id,
            'warehouse_location' => 'A-01',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => '10000001',
            'part_number' => 'PART-A',
            'pcs_quantity' => 50,
            'qr_code' => '10000001|PART-A|50',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);
        $pallet->boxes()->attach($box->id);

        StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'A-01',
            'part_numbers' => json_encode(['PART-A']),
            'box_quantity' => 1,
            'pcs_quantity' => 50,
            'stored_at' => now(),
        ]);

        AuditLog::create([
            'type' => 'pallet_merged',
            'action' => 'merged',
            'model' => 'Pallet',
            'model_id' => $pallet->id,
            'description' => 'Merge dari 2 pallet: PLT-001, PLT-002',
            'user_id' => $operator->id,
        ]);

        // 1. Check apiGetPalletDetail includes history
        $response = $this->actingAs($operator)->getJson("/api/stock/pallet-detail/{$pallet->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'pallet_id',
            'pallet_number',
            'location',
            'items',
            'history',
        ]);
        $this->assertNotEmpty($response->json('history'));
        $this->assertTrue(collect($response->json('history'))->contains('action', 'stock_input'));
        $this->assertTrue(collect($response->json('history'))->contains('action', 'merged'));

        // 2. Check palletHistory endpoint
        $historyResponse = $this->actingAs($operator)->getJson(route('stock-view.pallet-history', ['palletId' => $pallet->id]));
        $historyResponse->assertStatus(200);
        $historyResponse->assertJsonStructure([
            'pallet_id',
            'pallet_number',
            'location',
            'history',
        ]);
        $this->assertNotEmpty($historyResponse->json('history'));
    }

    public function test_box_history_aggregates_stock_input_and_box_events(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $box = Box::create([
            'box_number' => '10000002',
            'part_number' => 'PART-B',
            'pcs_quantity' => 100,
            'qr_code' => '10000002|PART-B|100',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);

        $pallet = Pallet::create(['pallet_number' => 'PLT-TEST-002']);
        $stockInput = StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'B-01',
            'part_numbers' => json_encode(['PART-B']),
            'box_quantity' => 1,
            'pcs_quantity' => 100,
            'stored_at' => now(),
        ]);

        DB::table('stock_input_boxes')->insert([
            'stock_input_id' => $stockInput->id,
            'box_id' => $box->id,
        ]);

        AuditLog::create([
            'type' => 'box_updated',
            'action' => 'box_updated_by_admin_warehouse',
            'model' => 'Box',
            'model_id' => $box->id,
            'description' => 'Admin mengubah data box',
            'user_id' => $operator->id,
            'old_values' => json_encode(['pcs_quantity' => 90]),
            'new_values' => json_encode(['pcs_quantity' => 100, 'reason' => 'Koreksi hitungan']),
        ]);

        $response = $this->actingAs($operator)->getJson(route('stock-view.box-history', ['boxId' => $box->id]));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'box_id',
            'box_number',
            'history',
        ]);

        $history = collect($response->json('history'));
        $this->assertTrue($history->contains('action', 'stock_input'));
        $this->assertTrue($history->contains('action', 'box_updated_by_admin_warehouse'));
    }

    public function test_merge_pallet_succeeds_when_destination_location_is_from_source_pallet(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $locA = MasterLocation::create([
            'code' => 'LOC-A',
            'is_occupied' => true,
        ]);

        $locB = MasterLocation::create([
            'code' => 'LOC-B',
            'is_occupied' => true,
        ]);

        $pallet1 = Pallet::create(['pallet_number' => 'PLT-001']);
        $locA->update(['current_pallet_id' => $pallet1->id]);
        StockLocation::create([
            'pallet_id' => $pallet1->id,
            'master_location_id' => $locA->id,
            'warehouse_location' => 'LOC-A',
            'stored_at' => now(),
        ]);

        $box1 = Box::create([
            'box_number' => '10000010',
            'part_number' => 'PART-X',
            'pcs_quantity' => 20,
            'qr_code' => '10000010|PART-X|20',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);
        $pallet1->boxes()->attach($box1->id);

        $pallet2 = Pallet::create(['pallet_number' => 'PLT-002']);
        $locB->update(['current_pallet_id' => $pallet2->id]);
        StockLocation::create([
            'pallet_id' => $pallet2->id,
            'master_location_id' => $locB->id,
            'warehouse_location' => 'LOC-B',
            'stored_at' => now(),
        ]);

        $box2 = Box::create([
            'box_number' => '10000011',
            'part_number' => 'PART-Y',
            'pcs_quantity' => 30,
            'qr_code' => '10000011|PART-Y|30',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);
        $pallet2->boxes()->attach($box2->id);

        // Merge and choose LOC-A (which belonged to Pallet 1)
        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet1->id, $pallet2->id],
            'location_id' => $locA->id,
            'warehouse_location' => 'LOC-A',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Old pallets should be soft-deleted
        $this->assertSoftDeleted('pallets', ['id' => $pallet1->id]);
        $this->assertSoftDeleted('pallets', ['id' => $pallet2->id]);

        // Loc B should be unoccupied and freed
        $locB->refresh();
        $this->assertFalse((bool) $locB->is_occupied);
        $this->assertNull($locB->current_pallet_id);

        // Loc A should be occupied by the new pallet
        $locA->refresh();
        $this->assertTrue((bool) $locA->is_occupied);
        $this->assertNotNull($locA->current_pallet_id);
        $this->assertNotEquals($pallet1->id, $locA->current_pallet_id);
    }

    public function test_merge_pallet_succeeds_with_warehouse_location_fallback(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $locC = MasterLocation::create([
            'code' => 'LOC-C',
            'is_occupied' => true,
        ]);

        $locD = MasterLocation::create([
            'code' => 'LOC-D',
            'is_occupied' => true,
        ]);

        $pallet3 = Pallet::create(['pallet_number' => 'PLT-003']);
        $locC->update(['current_pallet_id' => $pallet3->id]);
        StockLocation::create([
            'pallet_id' => $pallet3->id,
            'master_location_id' => $locC->id,
            'warehouse_location' => 'LOC-C',
            'stored_at' => now(),
        ]);

        $box3 = Box::create([
            'box_number' => '10000012',
            'part_number' => 'PART-Z',
            'pcs_quantity' => 15,
            'qr_code' => '10000012|PART-Z|15',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);
        $pallet3->boxes()->attach($box3->id);

        $pallet4 = Pallet::create(['pallet_number' => 'PLT-004']);
        $locD->update(['current_pallet_id' => $pallet4->id]);
        StockLocation::create([
            'pallet_id' => $pallet4->id,
            'master_location_id' => $locD->id,
            'warehouse_location' => 'LOC-D',
            'stored_at' => now(),
        ]);

        $box4 = Box::create([
            'box_number' => '10000013',
            'part_number' => 'PART-W',
            'pcs_quantity' => 25,
            'qr_code' => '10000013|PART-W|25',
            'qty_box' => 1,
            'user_id' => $operator->id,
            'is_withdrawn' => false,
        ]);
        $pallet4->boxes()->attach($box4->id);

        // Merge sending null location_id and string warehouse_location 'LOC-D'
        $response = $this->actingAs($operator)->postJson(route('merge-pallet.store'), [
            'pallet_ids' => [$pallet3->id, $pallet4->id],
            'location_id' => null,
            'warehouse_location' => 'LOC-D',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $locD->refresh();
        $this->assertTrue((bool) $locD->is_occupied);
        $this->assertNotNull($locD->current_pallet_id);
    }
}
