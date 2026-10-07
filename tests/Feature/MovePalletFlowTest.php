<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\AuditLog;
use App\Models\DeliveryOrder;
use App\Models\Pallet;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovePalletFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createPallet(User $user, string $palletNumber, string $locationCode): array
    {
        $pallet = Pallet::create(['pallet_number' => $palletNumber]);
        $location = MasterLocation::create([
            'code' => $locationCode,
            'is_occupied' => true,
            'current_pallet_id' => $pallet->id,
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'master_location_id' => $location->id,
            'warehouse_location' => $locationCode,
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => "BOX-{$palletNumber}",
            'part_number' => 'PART-01',
            'pcs_quantity' => 100,
            'qr_code' => "{$palletNumber}|PART-01|100",
            'qty_box' => 1,
            'user_id' => $user->id,
        ]);
        $pallet->boxes()->attach($box->id);

        return [$pallet, $location];
    }

    public function test_admin_warehouse_can_move_pallet_and_update_occupancy(): void
    {
        $user = User::factory()->create(['role' => 'admin_warehouse']);
        [$pallet, $source] = $this->createPallet($user, 'PLT-MOVE-01', 'A-01');
        $target = MasterLocation::create(['code' => 'B-01', 'is_occupied' => false]);

        $response = $this->actingAs($user)->postJson(route('move-pallet.store'), [
            'pallet_id' => $pallet->id,
            'location_id' => $target->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $pallet->id,
            'master_location_id' => $target->id,
            'warehouse_location' => 'B-01',
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $source->id,
            'is_occupied' => 0,
            'current_pallet_id' => null,
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $target->id,
            'is_occupied' => 1,
            'current_pallet_id' => $pallet->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'type' => 'pallet_location_moved',
            'model_id' => $pallet->id,
        ]);
    }

    public function test_move_rejects_occupied_target_and_keeps_source(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        [$pallet, $source] = $this->createPallet($user, 'PLT-MOVE-02', 'A-02');
        [$otherPallet, $target] = $this->createPallet($user, 'PLT-MOVE-03', 'B-02');

        $response = $this->actingAs($user)->postJson(route('move-pallet.store'), [
            'pallet_id' => $pallet->id,
            'location_id' => $target->id,
        ]);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('stock_locations', [
            'pallet_id' => $pallet->id,
            'master_location_id' => $source->id,
        ]);
        $this->assertDatabaseHas('master_locations', [
            'id' => $target->id,
            'current_pallet_id' => $otherPallet->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'type' => 'pallet_location_moved',
            'model_id' => $pallet->id,
        ]);
    }

    public function test_sales_cannot_access_move_pallet(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $this->actingAs($sales)
            ->get(route('move-pallet.index'))
            ->assertForbidden();
    }

    public function test_move_page_lists_only_pallets_that_are_eligible(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        [$eligible] = $this->createPallet($user, 'PLT-MOVE-LIST-01', 'A-LIST-01');
        [$assigned] = $this->createPallet($user, 'PLT-MOVE-LIST-02', 'A-LIST-02');
        MasterLocation::create(['code' => 'B-LIST-01', 'is_occupied' => false]);
        $deliveryOrder = DeliveryOrder::create([
            'sales_user_id' => $user->id,
            'customer_name' => 'Assigned customer',
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);
        $assigned->boxes()->first()->update(['assigned_delivery_order_id' => $deliveryOrder->id]);

        $response = $this->actingAs($user)->get(route('move-pallet.index'));

        $response->assertOk()
            ->assertSee($eligible->pallet_number)
            ->assertDontSee($assigned->pallet_number)
            ->assertSee('B-LIST-01');
    }
}
