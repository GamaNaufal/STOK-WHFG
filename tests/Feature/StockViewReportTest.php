<?php

use App\Models\User;

function supervisorUser(): User
{
    return User::factory()->create([
        'role' => 'supervisi',
        'password' => bcrypt('password'),
    ]);
}

function operatorUserForView(): User
{
    return User::factory()->create([
        'role' => 'warehouse_operator',
        'password' => bcrypt('password'),
    ]);
}

it('renders stock view modes for allowed roles [MUST PASS]', function () {
    $operator = operatorUserForView();

    $this->actingAs($operator)
        ->get('/stock-view?view_mode=part')
        ->assertStatus(200);

    $this->actingAs($operator)
        ->get('/stock-view?view_mode=pallet')
        ->assertStatus(200);

    $this->actingAs($operator)
        ->get('/stock-view?view_mode=not_full')
        ->assertStatus(200);
});

it('[FLAKY] exports stock view by part and pallet [MUST PASS]', function () {
    $operator = operatorUserForView();

    $this->actingAs($operator)
        ->get('/stock-view/export-part')
        ->assertStatus(200)
        ->assertHeader('content-disposition');

    $this->actingAs($operator)
        ->get('/stock-view/export-pallet')
        ->assertStatus(200)
        ->assertHeader('content-disposition');
})->group('flaky');

it('[FLAKY] renders reports and exports for supervisi/admin [MUST PASS]', function () {
    $supervisor = supervisorUser();

    $this->actingAs($supervisor)
        ->get('/reports/withdrawal')
        ->assertStatus(200);

    $this->actingAs($supervisor)
        ->get('/reports/stock-input')
        ->assertStatus(200);

    $this->actingAs($supervisor)
        ->get('/reports/operational/export')
        ->assertStatus(200)
        ->assertHeader('content-disposition');

    $this->actingAs($supervisor)
        ->get('/reports/withdrawal-export')
        ->assertStatus(200)
        ->assertHeader('content-disposition');

    $this->actingAs($supervisor)
        ->get('/reports/stock-input-export')
        ->assertStatus(200)
        ->assertHeader('content-disposition');
})->group('flaky');
