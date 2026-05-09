<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAssignBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_order_parts_endpoint_returns_remaining_quantity_only(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Boundary Parts',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-BOUND-01',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-BOUND-02',
            'quantity' => 30,
            'fulfilled_quantity' => 0,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-BOUND-01',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => '10001',
            'part_number' => 'P-BOUND-01',
            'part_name' => 'Boundary Part',
            'pcs_quantity' => 60,
            'qty_box' => 100,
            'qr_code' => '10001|P-BOUND-01|60',
            'user_id' => $user->id,
            'assigned_delivery_order_id' => $order->id,
        ]);
        $pallet->boxes()->attach($box->id);

        Box::create([
            'box_number' => '10002',
            'part_number' => 'P-BOUND-02',
            'part_name' => 'Boundary Part 2',
            'pcs_quantity' => 30,
            'qty_box' => 100,
            'qr_code' => '10002|P-BOUND-02|30',
            'user_id' => $user->id,
            'assigned_delivery_order_id' => $order->id,
        ]);

        $response = $this->actingAs($user)->getJson(route('delivery-assign.delivery-order-parts', [
            'deliveryOrderId' => $order->id,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'parts');
        $response->assertJsonPath('parts.0.part_number', 'P-BOUND-01');
        $response->assertJsonPath('parts.0.requested_quantity', 100);
        $response->assertJsonPath('parts.0.assigned_quantity', 60);
        $response->assertJsonPath('parts.0.remaining_quantity', 40);
    }

    public function test_assign_rejects_part_not_requested_by_delivery_order(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Boundary Reject Part',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-REQ-ONLY',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-REJECT-01',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => '20001',
            'part_number' => 'P-NOT-REQ',
            'part_name' => 'Not Requested Part',
            'pcs_quantity' => 20,
            'qty_box' => 100,
            'qr_code' => '20001|P-NOT-REQ|20',
            'user_id' => $user->id,
        ]);
        $pallet->boxes()->attach($box->id);

        $response = $this->actingAs($user)->postJson(route('delivery-assign.assign'), [
            'delivery_order_id' => $order->id,
            'box_ids' => [$box->id],
            'pallet_ids' => [],
            'new_boxes' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'part_coverage_errors']);
        $this->assertDatabaseMissing('boxes', [
            'id' => $box->id,
            'assigned_delivery_order_id' => $order->id,
        ]);
    }

    public function test_assign_rejects_quantity_overflow_for_same_part_across_existing_and_new_boxes(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Boundary Overflow',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-OVERFLOW',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        PartSetting::create([
            'part_number' => 'P-OVERFLOW',
            'qty_box' => 100,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-OVERFLOW-01',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => '30001',
            'part_number' => 'P-OVERFLOW',
            'part_name' => 'Overflow Part',
            'pcs_quantity' => 60,
            'qty_box' => 100,
            'qr_code' => '30001|P-OVERFLOW|60',
            'user_id' => $user->id,
        ]);
        $pallet->boxes()->attach($box->id);

        $response = $this->actingAs($user)->postJson(route('delivery-assign.assign'), [
            'delivery_order_id' => $order->id,
            'box_ids' => [$box->id],
            'pallet_ids' => [],
            'new_boxes' => [
                [
                    'box_number' => '30002',
                    'part_number' => 'P-OVERFLOW',
                    'pcs_quantity' => 50,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Part P-OVERFLOW melebihi sisa request delivery order (110/100).',
        ]);
        $this->assertDatabaseMissing('boxes', [
            'box_number' => '30002',
            'assigned_delivery_order_id' => $order->id,
        ]);
        $this->assertDatabaseMissing('boxes', [
            'id' => $box->id,
            'assigned_delivery_order_id' => $order->id,
        ]);
    }
}