<?php

use App\Models\Box;
use App\Models\PartSetting;
use App\Models\Pallet;
use App\Models\StockLocation;
use App\Models\User;
use Database\Factories\BoxFactory;
use Database\Factories\PartSettingFactory;
use Database\Factories\PalletFactory;
use Database\Factories\StockLocationFactory;

function operatorUser(): User
{
    return User::factory()->create([
        'role' => 'warehouse_operator',
        'password' => bcrypt('password'),
    ]);
}

it('scans barcode and creates pending box [MUST PASS]', function () {
    $user = operatorUser();

    $this->actingAs($user)
        ->postJson('/stock-input/scan-barcode', [
            'barcode' => 'BOX-1001',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(session()->has('pending_box'))->toBeTrue();
});

it('requires reason and delivery order for not-full box [MUST PASS]', function () {
    $user = operatorUser();
    PartSettingFactory::new()->create([
        'part_number' => 'PN-1001',
        'qty_box' => 10,
    ]);

    $this->actingAs($user)
        ->postJson('/stock-input/scan-barcode', [
            'barcode' => 'BOX-2001',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson('/stock-input/scan-part', [
            'part_number' => 'PN-1001',
            'pcs_quantity' => 5,
        ])
        ->assertStatus(422);

    $this->actingAs($user)
        ->postJson('/stock-input/scan-part', [
            'part_number' => 'PN-1001',
            'pcs_quantity' => 5,
            'not_full_reason' => 'Short qty',
        ])
        ->assertStatus(422);
});

it('rejects duplicate box scanned in the same session [MUST PASS]', function () {
    $user = operatorUser();
    PartSettingFactory::new()->create([
        'part_number' => 'PN-1002',
        'qty_box' => 10,
    ]);

    $this->actingAs($user)
        ->postJson('/stock-input/scan-barcode', [
            'barcode' => 'BOX-3001',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson('/stock-input/scan-part', [
            'part_number' => 'PN-1002',
            'pcs_quantity' => 10,
        ])
        ->assertOk();

    $this->actingAs($user)
        ->postJson('/stock-input/scan-barcode', [
            'barcode' => 'BOX-3001',
        ])
        ->assertStatus(400);
});

it('rejects scanning box already stored in a pallet [MUST PASS]', function () {
    $user = operatorUser();
    $part = PartSettingFactory::new()->create([
        'part_number' => 'PN-1003',
        'qty_box' => 10,
    ]);

    $pallet = PalletFactory::new()->create([
        'pallet_number' => 'PLT-EXIST-1',
    ]);

    StockLocationFactory::new()->create([
        'pallet_id' => $pallet->id,
        'warehouse_location' => 'A-1-1',
        'stored_at' => now(),
    ]);

    $box = BoxFactory::new()->create([
        'box_number' => 'BOX-EXIST-1',
        'part_number' => $part->part_number,
        'pcs_quantity' => 10,
        'qty_box' => 10,
        'user_id' => $user->id,
    ]);

    $pallet->boxes()->attach($box->id);

    $this->actingAs($user)
        ->postJson('/stock-input/scan-barcode', [
            'barcode' => 'BOX-EXIST-1',
        ])
        ->assertStatus(400);
});
