<?php

use App\Models\User;

function makeUserWithRole(string $role): User
{
    return User::factory()->create([
        'role' => $role,
        'password' => bcrypt('password'),
    ]);
}

it('allows admin to access user management [MUST PASS]', function () {
    $admin = makeUserWithRole('admin');

    $this->actingAs($admin)
        ->get('/users')
        ->assertStatus(200);
});

it('blocks non-admin from user management [MUST PASS]', function () {
    $sales = makeUserWithRole('sales');

    $this->actingAs($sales)
        ->get('/users')
        ->assertStatus(403);
});

it('allows admin_warehouse to access locations and part settings [MUST PASS]', function () {
    $adminWh = makeUserWithRole('admin_warehouse');

    $this->actingAs($adminWh)
        ->get('/locations')
        ->assertStatus(200);

    $this->actingAs($adminWh)
        ->get('/part-settings')
        ->assertStatus(200);
});

it('blocks sales from stock view [MUST PASS]', function () {
    $sales = makeUserWithRole('sales');

    $this->actingAs($sales)
        ->get('/stock-view')
        ->assertStatus(403);
});

it('allows stock view roles to access stock view [MUST PASS]', function () {
    $operator = makeUserWithRole('warehouse_operator');

    $this->actingAs($operator)
        ->get('/stock-view')
        ->assertStatus(200);
});

it('blocks non-allowed roles from stock API [MUST PASS]', function () {
    $sales = makeUserWithRole('sales');

    $this->actingAs($sales)
        ->get('/api/stock/by-part')
        ->assertStatus(403);
});

it('allows allowed roles to access stock API [MUST PASS]', function () {
    $operator = makeUserWithRole('warehouse_operator');

    $this->actingAs($operator)
        ->get('/api/stock/by-part')
        ->assertStatus(200);
});
