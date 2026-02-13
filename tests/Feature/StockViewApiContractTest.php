<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockViewApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_stock_by_part_returns_aggregated_contract(): void
    {
        $user = User::factory()->create(['role' => 'sales']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-API-001']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $box1 = Box::create([
            'box_number' => 'BOX-API-1',
            'part_number' => 'P-API-01',
            'pcs_quantity' => 10,
            'qty_box' => 1,
            'qr_code' => 'BOX-API-1|P-API-01|10',
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $box2 = Box::create([
            'box_number' => 'BOX-API-2',
            'part_number' => 'P-API-01',
            'pcs_quantity' => 15,
            'qty_box' => 1,
            'qr_code' => 'BOX-API-2|P-API-01|15',
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $pallet->boxes()->attach([$box1->id, $box2->id]);

        $response = $this->actingAs($user)->getJson('/api/stock/by-part');

        $response->assertOk();
        $response->assertJsonFragment([
            'part_number' => 'P-API-01',
            'total_box' => 2,
            'total_pcs' => 25,
        ]);
    }

    public function test_api_part_detail_returns_contract_and_404_when_missing(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-API-002']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A2',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => 'BOX-API-3',
            'part_number' => 'P-API-02',
            'pcs_quantity' => 20,
            'qty_box' => 1,
            'qr_code' => 'BOX-API-3|P-API-02|20',
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $pallet->boxes()->attach($box->id);

        $ok = $this->actingAs($user)->getJson('/api/stock/part-detail/P-API-02');
        $ok->assertOk();
        $ok->assertJsonStructure([
            'part_number',
            'total_box',
            'total_pcs',
            'pallet_count',
            'pallets' => [
                ['pallet_number', 'box_quantity', 'pcs_quantity', 'location', 'created_at', 'is_not_full', 'not_full_reason'],
            ],
        ]);

        $notFound = $this->actingAs($user)->getJson('/api/stock/part-detail/P-NOT-FOUND');
        $notFound->assertStatus(404)->assertJson(['error' => 'Part not found']);
    }

    public function test_api_pallet_detail_returns_active_boxes_contract(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-API-003']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A3',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => 'BOX-API-4',
            'part_number' => 'P-API-03',
            'pcs_quantity' => 30,
            'qty_box' => 1,
            'qr_code' => 'BOX-API-4|P-API-03|30',
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $pallet->boxes()->attach($box->id);

        $response = $this->actingAs($user)->getJson('/api/stock/pallet-detail/' . $pallet->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'pallet_number',
            'location',
            'items' => [
                ['part_number', 'box_number', 'box_quantity', 'pcs_quantity', 'created_at', 'origin_pallet', 'is_not_full', 'not_full_reason'],
            ],
        ]);

        $response->assertJsonFragment([
            'pallet_number' => 'PLT-API-003',
            'location' => 'A3',
        ]);
    }

    public function test_api_pallet_detail_fallbacks_to_legacy_pallet_items_when_no_boxes(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-API-004']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A4',
            'stored_at' => now(),
        ]);

        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-LEGACY-01',
            'box_quantity' => 2,
            'pcs_quantity' => 40,
        ]);

        $response = $this->actingAs($user)->getJson('/api/stock/pallet-detail/' . $pallet->id);

        $response->assertOk();
        $response->assertJsonFragment([
            'pallet_number' => 'PLT-API-004',
            'location' => 'A4',
        ]);
        $response->assertJsonFragment([
            'part_number' => 'P-LEGACY-01',
            'box_number' => '-',
            'box_quantity' => 2,
            'pcs_quantity' => 40,
        ]);
    }
}
