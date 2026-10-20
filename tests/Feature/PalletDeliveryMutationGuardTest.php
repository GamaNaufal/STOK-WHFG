<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\NotFullBoxRequest;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PalletDeliveryMutationGuardTest extends TestCase
{
    use RefreshDatabase;

    private function palletWithFinalDeliveryBox(User $operator): Pallet
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Active final delivery',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);
        $pallet = Pallet::create(['pallet_number' => 'PLT-FINAL-GUARD']);
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'FINAL-A1',
            'stored_at' => now(),
        ]);
        $box = Box::create([
            'box_number' => '97009001',
            'part_number' => 'P-FINAL',
            'pcs_quantity' => 10,
            'qty_box' => 10,
            'qr_code' => '97009001|P-FINAL|10',
            'user_id' => $operator->id,
            'assigned_delivery_order_id' => $order->id,
        ]);
        $pallet->boxes()->attach($box->id);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'approved',
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $session->id,
            'box_id' => $box->id,
            'part_number' => $box->part_number,
            'pcs_quantity' => 10,
            'status' => 'scanned',
        ]);

        return $pallet;
    }

    public function test_stock_input_cannot_add_box_to_pallet_used_by_final_delivery(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $pallet = $this->palletWithFinalDeliveryBox($operator);

        $response = $this->actingAs($operator)->withSession([
            'current_pallet_id' => $pallet->id,
            'current_pallet_source' => 'existing',
            'scanned_boxes' => [[
                'box_number' => '97009002',
                'part_number' => 'P-NEW',
                'pcs_quantity' => 10,
                'qty_box' => 10,
                'is_not_full' => false,
            ]],
        ])->postJson(route('stock-input.store'), ['pallet_id' => $pallet->id]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertStringContainsString('delivery aktif', $response->json('message'));
        $this->assertDatabaseMissing('boxes', ['box_number' => '97009002']);
        $this->assertDatabaseCount('stock_inputs', 0);
    }

    public function test_not_full_approval_cannot_add_box_to_pallet_used_by_another_final_delivery(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $sales = User::factory()->create(['role' => 'sales']);
        $pallet = $this->palletWithFinalDeliveryBox($operator);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Other order',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NF-NEW',
            'quantity' => 50,
            'fulfilled_quantity' => 0,
        ]);
        PartSetting::create(['part_number' => 'P-NF-NEW', 'qty_box' => 100]);
        $request = NotFullBoxRequest::create([
            'box_number' => '97009003',
            'part_number' => 'P-NF-NEW',
            'pcs_quantity' => 50,
            'fixed_qty' => 100,
            'reason' => 'Supplement',
            'request_type' => 'supplement',
            'delivery_order_id' => $order->id,
            'requested_by' => $operator->id,
            'target_pallet_id' => $pallet->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($supervisi)->post(route('box-not-full.approve', $request->id));

        $response->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('not_full_box_requests', ['id' => $request->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('boxes', ['box_number' => '97009003']);
    }

    public function test_delivery_assign_cannot_add_new_box_to_pallet_used_by_another_final_delivery(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);
        $pallet = $this->palletWithFinalDeliveryBox($operator);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'New box order',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-NEW',
            'quantity' => 10,
            'fulfilled_quantity' => 0,
        ]);
        PartSetting::create(['part_number' => 'P-NEW', 'qty_box' => 10]);

        $response = $this->actingAs($operator)->postJson(route('delivery-assign.assign'), [
            'delivery_order_id' => $order->id,
            'box_ids' => [],
            'pallet_ids' => [],
            'new_boxes' => [[
                'box_number' => '97009004',
                'part_number' => 'P-NEW',
                'pcs_quantity' => 10,
            ]],
            'new_boxes_pallet_mode' => 'existing',
            'new_boxes_pallet_id' => $pallet->id,
        ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('delivery aktif', $response->json('message'));
        $this->assertDatabaseMissing('boxes', ['box_number' => '97009004']);
        $this->assertDatabaseMissing('delivery_pick_sessions', ['delivery_order_id' => $order->id]);
    }

    public function test_sibling_box_cannot_be_edited_deleted_or_assigned_while_pallet_has_final_delivery(): void
    {
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $admin = User::factory()->create(['role' => 'admin_warehouse']);
        $sales = User::factory()->create(['role' => 'sales']);
        $pallet = $this->palletWithFinalDeliveryBox($operator);
        PartSetting::create(['part_number' => 'P-SIBLING', 'qty_box' => 10]);
        $sibling = Box::create([
            'box_number' => '97009005',
            'part_number' => 'P-SIBLING',
            'pcs_quantity' => 10,
            'qty_box' => 10,
            'qr_code' => '97009005|P-SIBLING|10',
            'user_id' => $operator->id,
        ]);
        $pallet->boxes()->attach($sibling->id);

        $this->actingAs($admin)->postJson(route('stock-view.box-update', $sibling->id), [
            'part_number' => 'P-SIBLING',
            'pcs_quantity' => 9,
            'stored_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'reason' => 'Correction',
        ])->assertStatus(422);
        $this->actingAs($admin)->deleteJson(route('stock-view.box-delete', $sibling->id))
            ->assertStatus(422);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Sibling assignment',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-SIBLING',
            'quantity' => 10,
            'fulfilled_quantity' => 0,
        ]);
        $this->actingAs($operator)->postJson(route('delivery-assign.assign'), [
            'delivery_order_id' => $order->id,
            'box_ids' => [$sibling->id],
            'pallet_ids' => [],
            'new_boxes' => [],
        ])->assertStatus(409);

        $this->assertDatabaseHas('boxes', [
            'id' => $sibling->id,
            'pcs_quantity' => 10,
            'assigned_delivery_order_id' => null,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('pallet_boxes', ['pallet_id' => $pallet->id, 'box_id' => $sibling->id]);
    }
}
