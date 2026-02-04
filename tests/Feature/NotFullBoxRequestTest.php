<?php

use App\Models\Box;
use App\Models\NotFullBoxRequest;
use App\Models\StockInput;
use App\Models\User;
use Database\Factories\DeliveryOrderFactory;
use Database\Factories\MasterLocationFactory;
use Database\Factories\NotFullBoxRequestFactory;
use Database\Factories\PartSettingFactory;

function adminWarehouseUser(): User
{
    return User::factory()->create([
        'role' => 'admin_warehouse',
        'password' => bcrypt('password'),
    ]);
}

function supervisiUser(): User
{
    return User::factory()->create([
        'role' => 'supervisi',
        'password' => bcrypt('password'),
    ]);
}

it('validates required fields for not-full request creation [MUST PASS]', function () {
    $adminWh = adminWarehouseUser();

    $this->actingAs($adminWh)
        ->post('/box-not-full', [])
        ->assertSessionHasErrors(['box_number', 'part_number', 'pcs_quantity', 'delivery_order_id', 'reason', 'request_type', 'target_type']);
});

it('approving not-full request creates box and stock input [MUST PASS]', function () {
    $supervisi = supervisiUser();

    $part = PartSettingFactory::new()->create([
        'part_number' => 'PN-NF-01',
        'qty_box' => 10,
    ]);

    $order = DeliveryOrderFactory::new()->create([
        'status' => 'approved',
    ]);

    $location = MasterLocationFactory::new()->create([
        'is_occupied' => false,
    ]);

    $request = NotFullBoxRequestFactory::new()->create([
        'box_number' => 'BOX-NF-01',
        'part_number' => $part->part_number,
        'pcs_quantity' => 5,
        'fixed_qty' => 10,
        'delivery_order_id' => $order->id,
        'requested_by' => $supervisi->id,
        'target_location_id' => $location->id,
        'status' => 'pending',
    ]);

    $this->actingAs($supervisi)
        ->post("/box-not-full/{$request->id}/approve")
        ->assertRedirect();

    $request->refresh();

    expect($request->status)->toBe('approved');
    expect($request->box_id)->not->toBeNull();
    expect(Box::where('box_number', 'BOX-NF-01')->exists())->toBeTrue();
    expect(StockInput::count())->toBe(1);
});

it('rejecting not-full request updates status only [MUST PASS]', function () {
    $supervisi = supervisiUser();

    $request = NotFullBoxRequestFactory::new()->create([
        'status' => 'pending',
    ]);

    $this->actingAs($supervisi)
        ->post("/box-not-full/{$request->id}/reject")
        ->assertRedirect();

    expect($request->fresh()->status)->toBe('rejected');
});
