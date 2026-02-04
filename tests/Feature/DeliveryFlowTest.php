<?php

use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickSession;
use App\Models\User;
use Database\Factories\BoxFactory;
use Database\Factories\DeliveryIssueFactory;
use Database\Factories\DeliveryOrderFactory;
use Database\Factories\DeliveryOrderItemFactory;
use Database\Factories\PalletFactory;
use Database\Factories\StockLocationFactory;

function userWithRole(string $role): User
{
    return User::factory()->create([
        'role' => $role,
        'password' => bcrypt('password'),
    ]);
}

it('sales can create delivery order with items [MUST PASS]', function () {
    $sales = userWithRole('sales');

    $this->actingAs($sales)
        ->post('/delivery-stock/store', [
            'customer_name' => 'Customer A',
            'delivery_date' => now()->addDay()->toDateString(),
            'items' => [
                ['part_number' => 'PN-2001', 'quantity' => 10],
            ],
        ])
        ->assertRedirect();

    $order = DeliveryOrder::first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe('pending');
    expect($order->items()->count())->toBe(1);
});

it('ppc can approve/reject/correction order [MUST PASS]', function () {
    $ppc = userWithRole('ppc');

    $order = DeliveryOrderFactory::new()->create([
        'status' => 'pending',
    ]);

    $this->actingAs($ppc)
        ->post("/delivery-stock/{$order->id}/status", [
            'status' => 'approved',
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('approved');

    $this->actingAs($ppc)
        ->post("/delivery-stock/{$order->id}/status", [
            'status' => 'rejected',
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('rejected');

    $this->actingAs($ppc)
        ->post("/delivery-stock/{$order->id}/status", [
            'status' => 'correction',
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('correction');
});

it('sales can resubmit correction to pending [MUST PASS]', function () {
    $sales = userWithRole('sales');

    $order = DeliveryOrderFactory::new()->create([
        'sales_user_id' => $sales->id,
        'status' => 'correction',
    ]);

    DeliveryOrderItemFactory::new()->create([
        'delivery_order_id' => $order->id,
        'part_number' => 'PN-2002',
        'quantity' => 10,
    ]);

    $this->actingAs($sales)
        ->put("/delivery-stock/{$order->id}", [
            'customer_name' => 'Customer B',
            'delivery_date' => now()->addDays(2)->toDateString(),
            'items' => [
                ['part_number' => 'PN-2003', 'quantity' => 5],
            ],
            'notes' => 'Updated',
        ])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('pending');
    expect($order->items()->count())->toBe(1);
});

it('warehouse start pick creates session [MUST PASS]', function () {
    $warehouse = userWithRole('warehouse_operator');

    $order = DeliveryOrderFactory::new()->create([
        'status' => 'approved',
    ]);

    DeliveryOrderItemFactory::new()->create([
        'delivery_order_id' => $order->id,
        'part_number' => 'PN-3001',
        'quantity' => 10,
    ]);

    $pallet = PalletFactory::new()->create();
    StockLocationFactory::new()->create([
        'pallet_id' => $pallet->id,
        'warehouse_location' => 'A-1-1',
    ]);

    $box = BoxFactory::new()->create([
        'part_number' => 'PN-3001',
        'pcs_quantity' => 10,
        'user_id' => $warehouse->id,
    ]);
    $pallet->boxes()->attach($box->id);

    $this->actingAs($warehouse)
        ->post("/delivery-stock/{$order->id}/start-pick")
        ->assertOk()
        ->assertJsonStructure(['session_id', 'pdf_url', 'scan_url']);

    expect(DeliveryPickSession::count())->toBe(1);
});

it('scan mismatch blocks session and creates issue [MUST PASS]', function () {
    $warehouse = userWithRole('warehouse_operator');

    $order = DeliveryOrderFactory::new()->create([
        'status' => 'approved',
    ]);

    DeliveryOrderItemFactory::new()->create([
        'delivery_order_id' => $order->id,
        'part_number' => 'PN-4001',
        'quantity' => 10,
    ]);

    $pallet = PalletFactory::new()->create();
    StockLocationFactory::new()->create([
        'pallet_id' => $pallet->id,
        'warehouse_location' => 'A-1-2',
    ]);

    $box = BoxFactory::new()->create([
        'part_number' => 'PN-4001',
        'pcs_quantity' => 10,
        'user_id' => $warehouse->id,
    ]);
    $pallet->boxes()->attach($box->id);

    $response = $this->actingAs($warehouse)
        ->post("/delivery-stock/{$order->id}/start-pick")
        ->assertOk();

    $sessionId = $response->json('session_id');

    $this->actingAs($warehouse)
        ->postJson("/delivery-pick/{$sessionId}/scan", [
            'box_number' => 'BOX-WRONG-1',
        ])
        ->assertStatus(422);

    $session = DeliveryPickSession::find($sessionId);
    expect($session->status)->toBe('blocked');
    expect(DeliveryIssue::count())->toBe(1);
});

it('admin can approve issue and unblock session [MUST PASS]', function () {
    $admin = userWithRole('admin');

    $issue = DeliveryIssueFactory::new()->create([
        'status' => 'pending',
    ]);

    $session = $issue->session;
    $session->status = 'blocked';
    $session->save();

    $this->actingAs($admin)
        ->post("/delivery-scan-issues/{$issue->id}/approve", [
            'notes' => 'Approved',
        ])
        ->assertRedirect();

    expect($issue->fresh()->status)->toBe('approved');
    expect($session->fresh()->status)->toBe('scanning');
});

it('complete pick marks boxes withdrawn and updates order [MUST PASS]', function () {
    $warehouse = userWithRole('warehouse_operator');

    $order = DeliveryOrderFactory::new()->create([
        'status' => 'approved',
    ]);

    $item = DeliveryOrderItemFactory::new()->create([
        'delivery_order_id' => $order->id,
        'part_number' => 'PN-5001',
        'quantity' => 10,
    ]);

    $pallet = PalletFactory::new()->create();
    StockLocationFactory::new()->create([
        'pallet_id' => $pallet->id,
        'warehouse_location' => 'A-1-3',
    ]);

    $box = BoxFactory::new()->create([
        'part_number' => 'PN-5001',
        'pcs_quantity' => 10,
        'user_id' => $warehouse->id,
    ]);
    $pallet->boxes()->attach($box->id);

    $response = $this->actingAs($warehouse)
        ->post("/delivery-stock/{$order->id}/start-pick")
        ->assertOk();

    $sessionId = $response->json('session_id');

    $this->actingAs($warehouse)
        ->postJson("/delivery-pick/{$sessionId}/scan", [
            'box_number' => $box->box_number,
        ])
        ->assertOk();

    $this->actingAs($warehouse)
        ->postJson("/delivery-pick/{$sessionId}/complete")
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($box->fresh()->is_withdrawn)->toBeTrue();
    expect($order->fresh()->status)->toBe('completed');
    expect($item->fresh()->fulfilled_quantity)->toBe(10);
});
