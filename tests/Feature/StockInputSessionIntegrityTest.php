<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInputSessionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_select_existing_pallet_does_not_move_items_between_existing_pallets(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $palletA = Pallet::create(['pallet_number' => 'PLT-SI-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-SI-B']);

        StockLocation::create([
            'pallet_id' => $palletA->id,
            'warehouse_location' => 'S1',
            'stored_at' => now(),
        ]);
        StockLocation::create([
            'pallet_id' => $palletB->id,
            'warehouse_location' => 'S2',
            'stored_at' => now(),
        ]);

        $boxA = Box::create([
            'box_number' => '970101',
            'part_number' => 'P-SI-A',
            'pcs_quantity' => 10,
            'qty_box' => 1,
            'qr_code' => '970101|P-SI-A|10',
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $boxB = Box::create([
            'box_number' => '970102',
            'part_number' => 'P-SI-B',
            'pcs_quantity' => 20,
            'qty_box' => 1,
            'qr_code' => '970102|P-SI-B|20',
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $palletA->boxes()->attach($boxA->id);
        $palletB->boxes()->attach($boxB->id);

        PalletItem::create([
            'pallet_id' => $palletA->id,
            'part_number' => 'P-SI-A',
            'box_quantity' => 1,
            'pcs_quantity' => 10,
        ]);
        PalletItem::create([
            'pallet_id' => $palletB->id,
            'part_number' => 'P-SI-B',
            'box_quantity' => 1,
            'pcs_quantity' => 20,
        ]);

        $response = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $palletA->id,
                'current_pallet_source' => 'existing',
                'scanned_boxes' => [],
            ])
            ->postJson(route('stock-input.select-existing-pallet'), [
                'pallet_id' => $palletB->id,
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $palletA->id,
            'part_number' => 'P-SI-A',
            'box_quantity' => 1,
            'pcs_quantity' => 10,
        ]);
        $this->assertDatabaseHas('pallet_items', [
            'pallet_id' => $palletB->id,
            'part_number' => 'P-SI-B',
            'box_quantity' => 1,
            'pcs_quantity' => 20,
        ]);
        $this->assertDatabaseMissing('pallet_items', [
            'pallet_id' => $palletB->id,
            'part_number' => 'P-SI-A',
        ]);
    }

    public function test_scan_part_preview_does_not_persist_box_or_pallet_items_before_store(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-SI-PREVIEW']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'S3',
            'stored_at' => now(),
        ]);

        PartSetting::create([
            'part_number' => 'P-SI-PREVIEW',
            'qty_box' => 12,
        ]);

        $scanBox = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $pallet->id,
                'current_pallet_source' => 'existing',
            ])
            ->postJson(route('stock-input.scan-barcode'), [
                'barcode' => '970201',
            ]);

        $scanBox->assertOk()->assertJson(['success' => true]);

        $scanPart = $this->actingAs($operator)->postJson(route('stock-input.scan-part'), [
            'part_number' => 'P-SI-PREVIEW',
        ]);

        $scanPart->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('boxes', [
            'box_number' => '970201',
        ]);
        $this->assertDatabaseMissing('pallet_items', [
            'pallet_id' => $pallet->id,
            'part_number' => 'P-SI-PREVIEW',
        ]);

        $this->actingAs($operator)->postJson(route('stock-input.clear-session'))->assertOk();

        $this->assertDatabaseMissing('boxes', [
            'box_number' => '970201',
        ]);
        $this->assertDatabaseMissing('pallet_items', [
            'pallet_id' => $pallet->id,
            'part_number' => 'P-SI-PREVIEW',
        ]);
    }

    public function test_scan_qr_preview_does_not_persist_pallet_item_or_attachment_before_store(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-SI-QR']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'S4',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => '970301',
            'part_number' => 'P-SI-QR',
            'pcs_quantity' => 30,
            'qty_box' => 1,
            'qr_code' => '970301|P-SI-QR|30',
            'user_id' => $operator->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $response = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $pallet->id,
                'current_pallet_source' => 'existing',
            ])
            ->postJson(route('stock-input.scan-box'), [
                'qr_data' => '970301|P-SI-QR|30',
            ]);

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('pallet_items', [
            'pallet_id' => $pallet->id,
            'part_number' => 'P-SI-QR',
        ]);
        $this->assertDatabaseMissing('pallet_boxes', [
            'pallet_id' => $pallet->id,
            'box_id' => $box->id,
        ]);
    }

    public function test_store_rejects_when_request_pallet_does_not_match_active_session_pallet(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $activePallet = Pallet::create(['pallet_number' => 'PLT-SI-ACTIVE']);
        $otherPallet = Pallet::create(['pallet_number' => 'PLT-SI-OTHER']);

        StockLocation::create([
            'pallet_id' => $activePallet->id,
            'warehouse_location' => 'S5',
            'stored_at' => now(),
        ]);
        StockLocation::create([
            'pallet_id' => $otherPallet->id,
            'warehouse_location' => 'S6',
            'stored_at' => now(),
        ]);

        $response = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $activePallet->id,
                'current_pallet_source' => 'existing',
                'scanned_boxes' => [
                    [
                        'box_number' => '970401',
                        'part_number' => 'P-SI-MISMATCH',
                        'pcs_quantity' => 40,
                        'qty_box' => 40,
                        'is_not_full' => false,
                        'delivery_order_id' => null,
                    ],
                ],
            ])
            ->postJson(route('stock-input.store'), [
                'pallet_id' => $otherPallet->id,
            ]);

        $response->assertStatus(422)->assertJson([
            'success' => false,
        ]);

        $this->assertDatabaseMissing('boxes', [
            'box_number' => '970401',
        ]);
        $this->assertDatabaseCount('stock_inputs', 0);
    }

    public function test_store_rejects_location_override_for_existing_pallet(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $existingPallet = Pallet::create(['pallet_number' => 'PLT-SI-LOC-EXIST']);
        StockLocation::create([
            'pallet_id' => $existingPallet->id,
            'warehouse_location' => 'S7',
            'stored_at' => now(),
        ]);

        $targetLocation = \App\Models\MasterLocation::create([
            'code' => 'S8',
            'is_occupied' => false,
        ]);

        $response = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $existingPallet->id,
                'current_pallet_source' => 'existing',
                'scanned_boxes' => [
                    [
                        'box_number' => '970501',
                        'part_number' => 'P-SI-OVERRIDE',
                        'pcs_quantity' => 50,
                        'qty_box' => 50,
                        'is_not_full' => false,
                        'delivery_order_id' => null,
                    ],
                ],
            ])
            ->postJson(route('stock-input.store'), [
                'pallet_id' => $existingPallet->id,
                'location_id' => $targetLocation->id,
                'warehouse_location' => 'S8',
            ]);

        $response->assertStatus(422)->assertJson([
            'success' => false,
        ]);

        $this->assertDatabaseMissing('boxes', [
            'box_number' => '970501',
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $targetLocation->id,
            'is_occupied' => 0,
            'current_pallet_id' => null,
        ]);
    }
}
