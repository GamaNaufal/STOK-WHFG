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

    public function test_index_shows_only_approved_delivery_orders(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $approved = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Approved Order',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        $pending = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Pending Order',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('delivery-assign.index'));

        $response->assertOk();

        $deliveryOrders = $response->viewData('deliveryOrders');
        $this->assertNotNull($deliveryOrders);
        $this->assertTrue($deliveryOrders->contains('id', $approved->id));
        $this->assertFalse($deliveryOrders->contains('id', $pending->id));
    }

    public function test_index_hides_soft_deleted_delivery_orders_even_if_status_is_approved(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $deletedOrder = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Deleted Approved Order',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        $deletedOrder->delete();

        $response = $this->actingAs($user)->get(route('delivery-assign.index'));

        $response->assertOk();

        $deliveryOrders = $response->viewData('deliveryOrders');
        $this->assertNotNull($deliveryOrders);
        $this->assertFalse($deliveryOrders->contains('id', $deletedOrder->id));
    }

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

    public function test_delivery_order_parts_endpoint_rejects_deleted_orders(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Deleted Parts Order',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        $order->delete();

        $response = $this->actingAs($user)->getJson(route('delivery-assign.delivery-order-parts', [
            'deliveryOrderId' => $order->id,
        ]));

        $response->assertNotFound();
        $response->assertJsonFragment([
            'message' => 'Delivery order tidak ditemukan atau belum approved.',
        ]);
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

    public function test_assign_rejects_non_approved_delivery_order(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Pending For Assign',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-PENDING-01',
            'quantity' => 10,
            'fulfilled_quantity' => 0,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-PENDING-01',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => '22001',
            'part_number' => 'P-PENDING-01',
            'part_name' => 'Pending Part',
            'pcs_quantity' => 10,
            'qty_box' => 10,
            'qr_code' => '22001|P-PENDING-01|10',
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
        $response->assertJsonFragment([
            'message' => 'Hanya delivery order dengan status approved yang dapat di-assign.',
        ]);
    }

    public function test_assign_rejects_new_box_part_not_in_selected_delivery_order(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Boundary New Box Part',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-REQ-ONLY-NEWBOX',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        PartSetting::create([
            'part_number' => 'P-NOT-IN-ORDER',
            'qty_box' => 100,
        ]);

        $response = $this->actingAs($user)->postJson(route('delivery-assign.assign'), [
            'delivery_order_id' => $order->id,
            'box_ids' => [],
            'pallet_ids' => [],
            'new_boxes' => [
                [
                    'box_number' => '23001',
                    'part_number' => 'P-NOT-IN-ORDER',
                    'pcs_quantity' => 20,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('new_box_errors.0.reason', 'No Part tidak ada dalam delivery order yang dipilih.');
    }

    public function test_assign_allows_new_box_over_master_qty_box_when_delivery_qty_is_still_available(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Boundary Master Qty Box',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-MASTER-QTY',
            'quantity' => 200,
            'fulfilled_quantity' => 0,
        ]);

        PartSetting::create([
            'part_number' => 'P-MASTER-QTY',
            'qty_box' => 100,
        ]);

        $response = $this->actingAs($user)->postJson(route('delivery-assign.assign'), [
            'delivery_order_id' => $order->id,
            'box_ids' => [],
            'pallet_ids' => [],
            'new_boxes' => [
                [
                    'box_number' => '24001',
                    'part_number' => 'P-MASTER-QTY',
                    'pcs_quantity' => 150,
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('created_new_count', 1);
        $this->assertDatabaseHas('boxes', [
            'box_number' => '24001',
            'assigned_delivery_order_id' => $order->id,
            'pcs_quantity' => 150,
        ]);
    }

    public function test_assign_overflow_requires_confirmation_and_updates_delivery_item_quantity(): void
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

        $firstResponse = $this->actingAs($user)->postJson(route('delivery-assign.assign'), [
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

        $firstResponse->assertStatus(409);
        $firstResponse->assertJsonPath('requires_overage_confirmation', true);
        $this->assertDatabaseMissing('boxes', [
            'box_number' => '30002',
            'assigned_delivery_order_id' => $order->id,
        ]);
        $this->assertDatabaseMissing('boxes', [
            'id' => $box->id,
            'assigned_delivery_order_id' => $order->id,
        ]);

        $confirmedResponse = $this->actingAs($user)->postJson(route('delivery-assign.assign'), [
            'delivery_order_id' => $order->id,
            'box_ids' => [$box->id],
            'pallet_ids' => [],
            'confirm_overage' => true,
            'new_boxes' => [
                [
                    'box_number' => '30002',
                    'part_number' => 'P-OVERFLOW',
                    'pcs_quantity' => 50,
                ],
            ],
        ]);

        $confirmedResponse->assertOk();

        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $order->id,
            'part_number' => 'P-OVERFLOW',
            'quantity' => 110,
        ]);

        $this->assertDatabaseHas('boxes', [
            'id' => $box->id,
            'assigned_delivery_order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('boxes', [
            'box_number' => '30002',
            'assigned_delivery_order_id' => $order->id,
        ]);
    }

    public function test_assigned_boxes_appear_in_stock_withdrawal_preview(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Preview Test',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-PREVIEW',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        PartSetting::create([
            'part_number' => 'P-PREVIEW',
            'qty_box' => 100,
        ]);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-PREVIEW-01',
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'A1',
            'stored_at' => now(),
        ]);

        $box = Box::create([
            'box_number' => '40001',
            'part_number' => 'P-PREVIEW',
            'part_name' => 'Preview Part',
            'pcs_quantity' => 100,
            'qty_box' => 100,
            'qr_code' => '40001|P-PREVIEW|100',
            'user_id' => $user->id,
            'assigned_delivery_order_id' => $order->id, // Pre-assigned
        ]);
        $pallet->boxes()->attach($box->id);

        // Preview withdrawal should show the assigned box
        $response = $this->actingAs($user)->postJson(route('stock-withdrawal.preview'), [
            'part_number' => 'P-PREVIEW',
            'pcs_quantity' => 100,
            'delivery_order_id' => $order->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('planned_qty', 100);
        
        // Check that locations include our assigned box (as reserved)
        $locations = $response->json('locations');
        $this->assertCount(1, $locations, 'Should have 1 location');
        $this->assertTrue($locations[0]['is_reserved'], 'Assigned box should be marked as reserved');
        $this->assertEquals('40001', $locations[0]['box_number']);
    }
}