<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockInputExistingPalletActiveFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_existing_pallet_only_returns_pallet_with_active_boxes(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $emptyPallet = Pallet::create(['pallet_number' => 'PLT-EMPTY-01']);
        StockLocation::create([
            'pallet_id' => $emptyPallet->id,
            'warehouse_location' => 'R1',
            'stored_at' => now(),
        ]);

        $withdrawnBox = Box::create([
            'box_number' => '980001',
            'part_number' => 'P-OLD',
            'pcs_quantity' => 10,
            'qr_code' => '980001|P-OLD|10',
            'is_withdrawn' => true,
            'withdrawn_at' => now(),
            'user_id' => $operator->id,
        ]);
        $emptyPallet->boxes()->attach($withdrawnBox->id);

        $activePallet = Pallet::create(['pallet_number' => 'PLT-ACTIVE-01']);
        StockLocation::create([
            'pallet_id' => $activePallet->id,
            'warehouse_location' => 'R2',
            'stored_at' => now(),
        ]);

        $activeBox = Box::create([
            'box_number' => '980002',
            'part_number' => 'P-NEW',
            'pcs_quantity' => 12,
            'qr_code' => '980002|P-NEW|12',
            'is_withdrawn' => false,
            'user_id' => $operator->id,
        ]);
        $activePallet->boxes()->attach($activeBox->id);

        $response = $this->actingAs($operator)->getJson(route('stock-input.search-existing-pallet'));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->all();

        $this->assertContains($activePallet->id, $ids);
        $this->assertNotContains($emptyPallet->id, $ids);
    }

    public function test_select_existing_pallet_rejects_pallet_without_active_boxes(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $emptyPallet = Pallet::create(['pallet_number' => 'PLT-EMPTY-02']);
        StockLocation::create([
            'pallet_id' => $emptyPallet->id,
            'warehouse_location' => 'R3',
            'stored_at' => now(),
        ]);

        $withdrawnBox = Box::create([
            'box_number' => '980003',
            'part_number' => 'P-OLD-2',
            'pcs_quantity' => 8,
            'qr_code' => '980003|P-OLD-2|8',
            'is_withdrawn' => true,
            'withdrawn_at' => now(),
            'user_id' => $operator->id,
        ]);
        $emptyPallet->boxes()->attach($withdrawnBox->id);

        $response = $this->actingAs($operator)->postJson(route('stock-input.select-existing-pallet'), [
            'pallet_id' => $emptyPallet->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Pallet ini sudah kosong dan tidak bisa dipilih sebagai pallet existing.',
            ]);
    }

    public function test_get_current_pallet_data_excludes_old_withdrawn_boxes_from_print_payload(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-PRINT-01']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'R4',
            'stored_at' => now(),
        ]);

        $oldWithdrawn = Box::create([
            'box_number' => '980004',
            'part_number' => 'P-OLD-PRINT',
            'pcs_quantity' => 5,
            'qr_code' => '980004|P-OLD-PRINT|5',
            'is_withdrawn' => true,
            'withdrawn_at' => now(),
            'user_id' => $operator->id,
        ]);

        $activeBox = Box::create([
            'box_number' => '980005',
            'part_number' => 'P-ACTIVE-PRINT',
            'pcs_quantity' => 7,
            'qr_code' => '980005|P-ACTIVE-PRINT|7',
            'is_withdrawn' => false,
            'user_id' => $operator->id,
        ]);

        $pallet->boxes()->attach([$oldWithdrawn->id, $activeBox->id]);

        $response = $this->actingAs($operator)
            ->withSession([
                'current_pallet_id' => $pallet->id,
                'current_pallet_source' => 'existing',
                'scanned_boxes' => [
                    [
                        'box_number' => '980099',
                        'part_number' => 'P-PENDING',
                        'pcs_quantity' => 9,
                        'qty_box' => 10,
                        'is_not_full' => true,
                    ],
                ],
            ])
            ->getJson(route('stock-input.get-pallet-data'));

        $response->assertOk()->assertJson(['success' => true]);

        $existingBoxNumbers = collect($response->json('pallet.boxes_existing'))->pluck('box_number')->all();
        $printBoxNumbers = collect($response->json('pallet.boxes_for_print'))->pluck('box_number')->all();

        $this->assertContains('980005', $existingBoxNumbers);
        $this->assertNotContains('980004', $existingBoxNumbers);

        $this->assertContains('980005', $printBoxNumbers);
        $this->assertContains('980099', $printBoxNumbers);
        $this->assertNotContains('980004', $printBoxNumbers);
    }
}
