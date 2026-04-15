<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStockSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_ignores_stale_legacy_items_when_pallet_has_only_non_active_box_history(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-DB-001']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $withdrawnBox = Box::create([
            'box_number' => 'BOX-DB-001',
            'part_number' => 'P-DB-001',
            'pcs_quantity' => 50,
            'qty_box' => 1,
            'qr_code' => 'BOX-DB-001|P-DB-001|50',
            'user_id' => $user->id,
            'is_withdrawn' => true,
            'withdrawn_at' => now(),
            'expired_status' => 'active',
        ]);
        $pallet->boxes()->attach($withdrawnBox->id);

        // Simulate stale legacy rows that should not be counted for pallets with box history.
        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-DB-001',
            'box_quantity' => 3,
            'pcs_quantity' => 150,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(0, (int) ($stats['pallets_with_location'] ?? -1));
        $this->assertSame(0, (int) ($stats['total_box'] ?? -1));
        $this->assertSame(0, (int) ($stats['total_pcs'] ?? -1));
        $this->assertSame(0, (int) ($stats['total_items'] ?? -1));
    }

    public function test_admin_dashboard_uses_legacy_pallet_items_only_for_pallet_without_box_history(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $legacyPallet = Pallet::create(['pallet_number' => 'PLT-DB-LEGACY']);
        StockLocation::create([
            'pallet_id' => $legacyPallet->id,
            'warehouse_location' => 'A2',
            'stored_at' => now(),
        ]);

        PalletItem::create([
            'pallet_id' => $legacyPallet->id,
            'part_number' => 'P-LEGACY-DB',
            'box_quantity' => 2,
            'pcs_quantity' => 40,
        ]);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(1, (int) ($stats['pallets_with_location'] ?? 0));
        $this->assertSame(2, (int) ($stats['total_box'] ?? 0));
        $this->assertSame(40, (int) ($stats['total_pcs'] ?? 0));
        $this->assertSame(1, (int) ($stats['total_items'] ?? 0));
    }

    public function test_admin_dashboard_counts_shared_active_box_once(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $palletA = Pallet::create(['pallet_number' => 'PLT-DB-SHARE-A']);
        $palletB = Pallet::create(['pallet_number' => 'PLT-DB-SHARE-B']);

        StockLocation::create([
            'pallet_id' => $palletA->id,
            'warehouse_location' => 'A6',
            'stored_at' => now(),
        ]);
        StockLocation::create([
            'pallet_id' => $palletB->id,
            'warehouse_location' => 'A7',
            'stored_at' => now(),
        ]);

        $sharedBox = Box::create([
            'box_number' => 'BOX-DB-SHARED-01',
            'part_number' => 'P-DB-SHARED',
            'pcs_quantity' => 25,
            'qty_box' => 1,
            'qr_code' => 'BOX-DB-SHARED-01|P-DB-SHARED|25',
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        $palletA->boxes()->attach($sharedBox->id);
        $palletB->boxes()->attach($sharedBox->id);

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(1, (int) ($stats['pallets_with_location'] ?? 0));
        $this->assertSame(1, (int) ($stats['total_box'] ?? 0));
        $this->assertSame(25, (int) ($stats['total_pcs'] ?? 0));
        $this->assertSame(1, (int) ($stats['total_items'] ?? 0));
    }
}
