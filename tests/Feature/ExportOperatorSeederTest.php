<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ExportOperatorSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExportOperatorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_seeder_includes_export_operator_with_empty_operational_access(): void
    {
        $this->seed(UserSeeder::class);

        $operator = User::where('email', 'export@warehouse.local')->firstOrFail();
        $this->assertSame('export_operator', $operator->role);
        $this->assertTrue($operator->is_active);
        $this->assertTrue(Hash::check('password', $operator->password));

        $this->post('/login', ['email' => $operator->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk()->assertViewIs('export.dashboard');
        $this->get(route('stock-input.index'))->assertForbidden();
        $this->get(route('delivery.index'))->assertForbidden();
        $this->assertDatabaseCount('role_feature_access', 0);
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_repeated_seeding_preserves_existing_account_and_other_users(): void
    {
        $this->seed(ExportOperatorSeeder::class);
        $operator = User::where('email', 'export@warehouse.local')->firstOrFail();
        $operator->update([
            'name' => 'Nama Diperbarui',
            'password' => Hash::make('changed-password'),
            'role' => 'sales',
            'is_active' => false,
        ]);
        $other = User::factory()->create(['role' => 'warehouse_operator']);
        $operatorBefore = $operator->fresh()->getRawOriginal();
        $otherBefore = $other->fresh()->getRawOriginal();

        $this->seed(ExportOperatorSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertSame($operatorBefore, $operator->fresh()->getRawOriginal());
        $this->assertSame($otherBefore, $other->fresh()->getRawOriginal());
        $this->assertDatabaseCount('role_feature_access', 0);
        $this->assertDatabaseCount('boxes', 0);
    }
}
