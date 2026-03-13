<?php

namespace Tests\Feature;

use App\Http\Controllers\StockWithdrawalController;
use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockWithdrawalUndoConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_undo_restores_box_withdrawal_state_and_location_occupancy(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);

        $pallet = Pallet::create(['pallet_number' => 'PLT-UNDO-01']);

        $palletItem = PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-UNDO-01',
            'box_quantity' => 0,
            'pcs_quantity' => 0,
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'UNDO-A1',
            'stored_at' => now(),
        ]);

        MasterLocation::create([
            'code' => 'UNDO-A1',
            'is_occupied' => false,
            'current_pallet_id' => null,
        ]);

        $box = Box::create([
            'box_number' => 'UNDO-BOX-001',
            'part_number' => 'P-UNDO-01',
            'pcs_quantity' => 100,
            'qr_code' => 'UNDO-BOX-001|P-UNDO-01|100',
            'user_id' => $operator->id,
            'is_withdrawn' => true,
            'withdrawn_at' => now(),
        ]);

        $pallet->boxes()->attach($box->id);

        $withdrawal = StockWithdrawal::create([
            'withdrawal_batch_id' => (string) Str::uuid(),
            'user_id' => $operator->id,
            'pallet_item_id' => $palletItem->id,
            'box_id' => $box->id,
            'part_number' => 'P-UNDO-01',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'warehouse_location' => 'UNDO-A1',
            'status' => 'completed',
            'withdrawn_at' => now(),
        ]);

        $response = app(StockWithdrawalController::class)->undo($withdrawal->id);

        $this->assertSame(200, $response->status());
        $this->assertSame(true, (bool) $response->getData(true)['success']);

        $this->assertDatabaseHas('stock_withdrawals', [
            'id' => $withdrawal->id,
            'status' => 'reversed',
        ]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'is_withdrawn' => 0,
        ]);

        $this->assertNull($box->fresh()->withdrawn_at);

        $this->assertDatabaseHas('pallet_items', [
            'id' => $palletItem->id,
            'pcs_quantity' => 100,
            'box_quantity' => 1,
        ]);

        $this->assertDatabaseHas('master_locations', [
            'code' => 'UNDO-A1',
            'is_occupied' => 1,
            'current_pallet_id' => $pallet->id,
        ]);
    }
}
