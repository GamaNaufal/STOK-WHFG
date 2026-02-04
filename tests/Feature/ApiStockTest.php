<?php

use App\Models\Box;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\User;
use Database\Factories\BoxFactory;
use Database\Factories\MasterLocationFactory;
use Database\Factories\PalletFactory;
use Database\Factories\StockLocationFactory;

function stockApiUser(): User
{
    return User::factory()->create([
        'role' => 'warehouse_operator',
        'password' => bcrypt('password'),
    ]);
}

it('returns stock by part [MUST PASS]', function () {
    $user = stockApiUser();

    $pallet = PalletFactory::new()->create();
    StockLocationFactory::new()->create([
        'pallet_id' => $pallet->id,
        'warehouse_location' => 'A-2-1',
    ]);

    $box = BoxFactory::new()->create([
        'part_number' => 'PN-API-01',
        'pcs_quantity' => 10,
        'user_id' => $user->id,
    ]);
    $pallet->boxes()->attach($box->id);

    $this->actingAs($user)
        ->get('/api/stock/by-part')
        ->assertOk()
        ->assertJsonFragment([
            'part_number' => 'PN-API-01',
        ]);
});

it('returns part detail and 404 for missing part [MUST PASS]', function () {
    $user = stockApiUser();

    $pallet = PalletFactory::new()->create();
    StockLocationFactory::new()->create([
        'pallet_id' => $pallet->id,
        'warehouse_location' => 'A-2-2',
    ]);

    $box = BoxFactory::new()->create([
        'part_number' => 'PN-API-02',
        'pcs_quantity' => 10,
        'user_id' => $user->id,
    ]);
    $pallet->boxes()->attach($box->id);

    $this->actingAs($user)
        ->get('/api/stock/part-detail/PN-API-02')
        ->assertOk()
        ->assertJsonFragment([
            'part_number' => 'PN-API-02',
        ]);

    $this->actingAs($user)
        ->get('/api/stock/part-detail/PN-NOT-FOUND')
        ->assertStatus(404);
});

it('returns pallet detail and 404 for missing pallet [MUST PASS]', function () {
    $user = stockApiUser();

    $pallet = PalletFactory::new()->create();
    StockLocationFactory::new()->create([
        'pallet_id' => $pallet->id,
        'warehouse_location' => 'A-2-3',
    ]);

    $box = BoxFactory::new()->create([
        'part_number' => 'PN-API-03',
        'pcs_quantity' => 10,
        'user_id' => $user->id,
    ]);
    $pallet->boxes()->attach($box->id);

    $this->actingAs($user)
        ->get("/api/stock/pallet-detail/{$pallet->id}")
        ->assertOk()
        ->assertJsonFragment([
            'pallet_number' => $pallet->pallet_number,
        ]);

    $this->actingAs($user)
        ->get('/api/stock/pallet-detail/999999')
        ->assertStatus(404);
});

it('returns available locations for search [MUST PASS]', function () {
    $user = stockApiUser();

    MasterLocationFactory::new()->create([
        'code' => 'A-9-9',
        'is_occupied' => false,
    ]);

    $this->actingAs($user)
        ->get('/api/locations/search?q=A-9')
        ->assertOk()
        ->assertJsonFragment([
            'code' => 'A-9-9',
        ]);
});
