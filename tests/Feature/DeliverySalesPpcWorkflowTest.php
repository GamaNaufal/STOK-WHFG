<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliverySalesPpcWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ppc_approve_requires_notes(): void
    {
        $ppc = User::factory()->create(['role' => 'ppc']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Approve Note Required',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($ppc)->post(route('delivery.status', $order->id), [
            'status' => 'approved',
            'notes' => '',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['notes']);

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_ppc_approve_with_notes_is_saved_and_visible_in_delivery_schedule(): void
    {
        $ppc = User::factory()->create(['role' => 'ppc']);
        $sales = User::factory()->create(['role' => 'sales']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Approve With Note',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'notes' => 'Initial Sales Note',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => 'P-APP-01',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $approve = $this->actingAs($ppc)->post(route('delivery.status', $order->id), [
            'status' => 'approved',
            'notes' => 'Gate 2 ready',
        ]);

        $approve->assertRedirect();
        $approve->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $order->id,
            'status' => 'approved',
        ]);

        $updated = DeliveryOrder::findOrFail($order->id);
        $this->assertStringContainsString('[PPC]: Gate 2 ready', (string) $updated->notes);

        $schedule = $this->actingAs($ppc)->get(route('delivery.index'));
        $schedule->assertOk();
        $schedule->assertSee('Gate 2 ready');
    }

    public function test_sales_can_submit_delivery_order_with_multiple_items(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);

        $response = $this->actingAs($sales)->post(route('delivery.store'), [
            'customer_name' => 'PT Customer A',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'notes' => 'Need morning delivery',
            'items' => [
                ['part_number' => 'P-001', 'quantity' => 100],
                ['part_number' => 'P-002', 'quantity' => 50],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $order = DeliveryOrder::query()->latest('id')->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $order->id,
            'sales_user_id' => $sales->id,
            'customer_name' => 'PT Customer A',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $order->id,
            'part_number' => 'P-001',
            'quantity' => 100,
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $order->id,
            'part_number' => 'P-002',
            'quantity' => 50,
        ]);
    }

    public function test_sales_input_page_shows_only_own_orders(): void
    {
        $salesA = User::factory()->create(['role' => 'sales']);
        $salesB = User::factory()->create(['role' => 'sales']);

        $orderA = DeliveryOrder::create([
            'sales_user_id' => $salesA->id,
            'customer_name' => 'Own Customer',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        DeliveryOrder::create([
            'sales_user_id' => $salesB->id,
            'customer_name' => 'Other Customer',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($salesA)->get(route('delivery.create'));

        $response->assertOk();
        $response->assertViewHas('myOrders', function ($orders) use ($orderA) {
            return $orders->count() === 1
                && (int) $orders->first()->id === (int) $orderA->id;
        });
    }

    public function test_ppc_cannot_use_sales_input_page_and_gets_redirected_to_schedule(): void
    {
        $ppc = User::factory()->create(['role' => 'ppc']);

        $response = $this->actingAs($ppc)->get(route('delivery.create'));

        $response->assertRedirect(route('delivery.index'));
    }

    public function test_sales_cannot_access_ppc_approvals_or_update_status(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Customer X',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $respApprovals = $this->actingAs($sales)->get(route('delivery.approvals'));
        $respApprovals->assertRedirect(route('delivery.index'));

        $respStatus = $this->actingAs($sales)->post(route('delivery.status', $order->id), [
            'status' => 'approved',
            'notes' => 'try force approve',
        ]);

        $respStatus->assertRedirect();

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $order->id,
            'status' => 'pending',
        ]);
    }

    public function test_ppc_approval_updates_only_target_order_even_when_orders_overlap_same_date(): void
    {
        $ppc = User::factory()->create(['role' => 'ppc']);
        $sales = User::factory()->create(['role' => 'sales']);

        $sameDate = now()->addDays(3)->toDateString();

        $orderA = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Overlap A',
            'delivery_date' => $sameDate,
            'status' => 'pending',
            'notes' => 'Original note A',
        ]);

        $orderB = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Overlap B',
            'delivery_date' => $sameDate,
            'status' => 'pending',
            'notes' => 'Original note B',
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $orderA->id,
            'part_number' => 'P-SAME',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $orderB->id,
            'part_number' => 'P-SAME',
            'quantity' => 100,
            'fulfilled_quantity' => 0,
        ]);

        $response = $this->actingAs($ppc)->post(route('delivery.status', $orderA->id), [
            'status' => 'correction',
            'notes' => 'Need change qty',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $orderA->id,
            'status' => 'correction',
        ]);

        $updatedA = DeliveryOrder::findOrFail($orderA->id);
        $this->assertStringContainsString('[PPC]: Need change qty', (string) $updatedA->notes);

        // Ensure other pending order is untouched (no status/notes contamination)
        $this->assertDatabaseHas('delivery_orders', [
            'id' => $orderB->id,
            'status' => 'pending',
            'notes' => 'Original note B',
        ]);
    }
}
