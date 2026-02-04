<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

it('allows login with valid credentials [MUST PASS]', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'role' => 'warehouse_operator',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    expect(Auth::check())->toBeTrue();
});

it('rejects login with invalid credentials [MUST PASS]', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'role' => 'warehouse_operator',
    ]);

    $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertRedirect('/login')
      ->assertSessionHasErrors('email');
});

it('logs out and invalidates session [MUST PASS]', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'role' => 'warehouse_operator',
    ]);

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});

it('[FLAKY] expires session after configured lifetime [MUST PASS]', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'role' => 'warehouse_operator',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $sessionId = Session::getId();
    $expiredTimestamp = now()->subMinutes(config('session.lifetime') + 1)->timestamp;

    DB::table('sessions')->where('id', $sessionId)->update([
        'last_activity' => $expiredTimestamp,
    ]);

    $this->get('/')->assertRedirect(route('login'));
})->group('flaky');
