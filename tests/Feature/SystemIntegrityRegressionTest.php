<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\StockWithdrawal;
use App\Models\User;
use App\Services\ExpiredBoxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_cannot_execute_stock_withdrawal(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create(['part_number' => 'P-AUTH-01', 'qty_box' => 100]);

        $response = $this->actingAs($sales)->postJson(route('stock-withdrawal.confirm'), [
            'part_number' => 'P-AUTH-01',
            'pcs_quantity' => 100,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('stock_withdrawals', 0);
    }

    public function test_withdrawal_rolls_back_when_exact_quantity_cannot_be_fulfilled(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create(['part_number' => 'P-EXACT-01', 'qty_box' => 100]);

        [$pallet, $box] = $this->createStoredBox($operator, 'P-EXACT-01', 60, '92000001', 'LOC-EXACT-1');

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Exact Quantity Customer',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        $item = DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-EXACT-01',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $response = $this->actingAs($operator)->postJson(route('stock-withdrawal.confirm'), [
            'part_number' => 'P-EXACT-01',
            'pcs_quantity' => 100,
            'delivery_order_id' => $order->id,
            'delivery_order_item_id' => $item->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('stock_withdrawals', 0);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'is_withdrawn' => 0]);
        $this->assertDatabaseHas('delivery_order_items', ['id' => $item->id, 'fulfilled_quantity' => 0]);
        $this->assertDatabaseHas('pallets', ['id' => $pallet->id, 'deleted_at' => null]);
    }

    public function test_withdrawal_updates_fulfillment_using_actual_quantity(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        PartSetting::create(['part_number' => 'P-ACTUAL-01', 'qty_box' => 60]);

        [, $box] = $this->createStoredBox($operator, 'P-ACTUAL-01', 60, '92000002', 'LOC-ACTUAL-1');

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Actual Quantity Customer',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        $item = DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-ACTUAL-01',
            'quantity' => 60,
            'fulfilled_quantity' => 0,
        ]);

        $response = $this->actingAs($operator)->postJson(route('stock-withdrawal.confirm'), [
            'part_number' => 'P-ACTUAL-01',
            'pcs_quantity' => 60,
            'delivery_order_id' => $order->id,
            'delivery_order_item_id' => $item->id,
        ]);

        $response->assertOk()->assertJsonPath('actual_withdrawn_qty', 60);
        $this->assertDatabaseHas('stock_withdrawals', [
            'box_id' => $box->id,
            'pcs_quantity' => 60,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('delivery_order_items', [
            'id' => $item->id,
            'fulfilled_quantity' => 60,
        ]);
        $this->assertDatabaseHas('delivery_orders', [
            'id' => $order->id,
            'status' => 'completed',
        ]);
    }

    public function test_split_restore_returns_all_child_parts_to_parent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Multi Part Split',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-SPLIT-A',
            'quantity' => 100,
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-SPLIT-B',
            'quantity' => 80,
        ]);

        $this->actingAs($admin)->post(route('delivery.split', $order->id), [
            'items' => [
                ['part_number' => 'P-SPLIT-A', 'quantity' => 40],
                ['part_number' => 'P-SPLIT-B', 'quantity' => 30],
            ],
        ])->assertSessionHas('success');

        $child = DeliveryOrder::where('parent_delivery_order_id', $order->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('delivery.restore-split', $child->id))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $order->id,
            'part_number' => 'P-SPLIT-A',
            'quantity' => 100,
        ]);
        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $order->id,
            'part_number' => 'P-SPLIT-B',
            'quantity' => 80,
        ]);
    }

    public function test_split_rejects_duplicate_part_total_above_parent_quantity(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sales = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Duplicate Split',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-DUPLICATE',
            'quantity' => 100,
        ]);

        $response = $this->actingAs($admin)->post(route('delivery.split', $order->id), [
            'items' => [
                ['part_number' => 'P-DUPLICATE', 'quantity' => 60],
                ['part_number' => 'P-DUPLICATE', 'quantity' => 60],
            ],
        ]);

        $response->assertSessionHasErrors([
            'items.0.part_number',
            'items.1.part_number',
        ]);
        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $order->id,
            'part_number' => 'P-DUPLICATE',
            'quantity' => 100,
        ]);
        $this->assertDatabaseMissing('delivery_orders', [
            'parent_delivery_order_id' => $order->id,
        ]);
    }

    public function test_expired_status_uses_box_stock_input_mapping_and_deduplicates_shared_box(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-EXP-OLD']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'EXP-OLD',
            'stored_at' => now()->subMonths(13),
        ]);

        $oldBox = $this->createBox($operator, 'P-EXP', 100, '92000003', now()->subMonths(13));
        $newBox = $this->createBox($operator, 'P-EXP', 100, '92000004', now());
        $pallet->boxes()->attach([$oldBox->id, $newBox->id]);

        $oldInput = StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'EXP-OLD',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'stored_at' => now()->subMonths(13),
            'part_numbers' => ['P-EXP'],
        ]);
        $newInput = StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'EXP-OLD',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'stored_at' => now(),
            'part_numbers' => ['P-EXP'],
        ]);

        DB::table('stock_input_boxes')->insert([
            [
                'stock_input_id' => $oldInput->id,
                'box_id' => $oldBox->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'stock_input_id' => $newInput->id,
                'box_id' => $newBox->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $secondPallet = Pallet::create(['pallet_number' => 'PLT-EXP-SHARED']);
        StockLocation::create([
            'pallet_id' => $secondPallet->id,
            'warehouse_location' => 'EXP-SHARED',
            'stored_at' => now(),
        ]);
        $secondPallet->boxes()->attach($newBox->id);

        $service = app(ExpiredBoxService::class);
        $service->syncStatuses();

        $this->assertDatabaseHas('boxes', ['id' => $oldBox->id, 'expired_status' => 'expired']);
        $this->assertDatabaseHas('boxes', ['id' => $newBox->id, 'expired_status' => 'active']);
        $this->assertSame(
            1,
            $service->getExpirableBoxesQuery()->where('boxes.id', $newBox->id)->count()
        );
    }

    public function test_deleting_pallet_archives_it_without_deleting_stock_input_history(): void
    {
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-HISTORY']);
        $stockInput = StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'HISTORY-1',
            'pcs_quantity' => 0,
            'box_quantity' => 0,
            'stored_at' => now(),
            'part_numbers' => [],
        ]);

        $this->actingAs($supervisi)
            ->deleteJson(route('stock-view.pallet-delete', $pallet->id))
            ->assertOk();

        $this->assertSoftDeleted('pallets', ['id' => $pallet->id]);
        $this->assertDatabaseHas('stock_inputs', ['id' => $stockInput->id, 'pallet_id' => $pallet->id]);
    }

    public function test_deleting_user_deactivates_account_and_preserves_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create([
            'role' => 'warehouse_operator',
            'email' => 'inactive@example.test',
        ]);
        $pallet = Pallet::create(['pallet_number' => 'PLT-USER-HISTORY']);
        $stockInput = StockInput::create([
            'pallet_id' => $pallet->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'USER-HISTORY',
            'pcs_quantity' => 0,
            'box_quantity' => 0,
            'stored_at' => now(),
            'part_numbers' => [],
        ]);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $operator))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $operator->id, 'is_active' => 0]);
        $this->assertDatabaseHas('stock_inputs', ['id' => $stockInput->id, 'user_id' => $operator->id]);

        $this->actingAs($operator->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        auth()->logout();
        $this->flushSession();

        $this->post(route('login'), [
            'email' => 'inactive@example.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_unused_location_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('locations.show'));
        $this->assertFalse(Route::has('locations.create'));
    }

    public function test_partial_withdrawal_session_column_is_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('delivery_pick_sessions', 'allow_partial'));
    }

    private function createStoredBox(
        User $operator,
        string $partNumber,
        int $pcs,
        string $boxNumber,
        string $location
    ): array {
        $pallet = Pallet::create(['pallet_number' => 'PLT-' . $boxNumber]);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => $location,
            'stored_at' => now(),
        ]);
        $box = $this->createBox($operator, $partNumber, $pcs, $boxNumber, now());
        $pallet->boxes()->attach($box->id);
        PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => $partNumber,
            'box_quantity' => 1,
            'pcs_quantity' => $pcs,
        ]);

        return [$pallet, $box];
    }

    private function createBox(
        User $operator,
        string $partNumber,
        int $pcs,
        string $boxNumber,
        $createdAt
    ): Box {
        return Box::create([
            'box_number' => $boxNumber,
            'part_number' => $partNumber,
            'pcs_quantity' => $pcs,
            'qty_box' => $pcs,
            'qr_code' => "{$boxNumber}|{$partNumber}|{$pcs}",
            'user_id' => $operator->id,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
