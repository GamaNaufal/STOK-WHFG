<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExportOperatorRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_and_filter_export_operator_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()->assertSee('Operator Export');

        $this->post(route('users.store'), [
            'name' => 'Operator Ekspor Baru', 'email' => 'export@example.test',
            'role' => 'export_operator', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

        $operator = User::where('email', 'export@example.test')->firstOrFail();
        $this->assertSame('export_operator', $operator->role);
        $this->assertTrue(Hash::check('secret123', $operator->password));
        $this->get(route('users.edit', $operator))->assertOk()->assertSee('Operator Export');
        $this->put(route('users.update', $operator), [
            'name' => 'Operator Ekspor Diperbarui', 'email' => $operator->email,
            'role' => 'export_operator', 'is_active' => true,
        ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

        $this->get(route('users.index', ['role' => 'export_operator']))
            ->assertOk()->assertSee('Operator Ekspor Diperbarui')->assertSee('1 akun')
            ->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->id === $operator->id);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_export_operator_can_login_use_master_dashboard_manage_profile_and_logout(): void
    {
        $operator = User::factory()->create(['role' => 'export_operator']);
        $this->post('/login', ['email' => $operator->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($operator);

        $this->get(route('dashboard'))->assertOk()->assertViewIs('export.dashboard')
            ->assertSee('Operator Export')->assertSee('Master data ekspor')
            ->assertDontSee('Ringkasan operasional')->assertDontSee('Total PCS')
            ->assertDontSee(route('stock-input.index'), false)
            ->assertDontSee(route('delivery.index'), false)
            ->assertDontSee(route('users.index'), false);
        $this->get(route('profile.edit'))->assertOk();
        $this->put(route('profile.update'), ['name' => 'Operator Ekspor', 'email' => $operator->email])
            ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
        $this->assertSame('export_operator', $operator->fresh()->role);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_export_operator_has_no_operational_access_including_direct_requests(): void
    {
        $operator = User::factory()->create(['role' => 'export_operator']);
        $access = app(FeatureAccessService::class);
        $this->assertSame(['export_delivery_plans', 'export_parts', 'export_locations'], $access->defaults($operator->role));
        foreach (array_keys(config('feature_access.features')) as $feature) {
            $this->assertSame(in_array($feature, ['export_delivery_plans', 'export_parts', 'export_locations'], true), $access->allows($operator, $feature), $feature);
        }

        $this->actingAs($operator);
        foreach (['stock-input.index', 'stock-view.index', 'delivery.index', 'delivery-assign.index',
            'delivery.pick.verification', 'reports.stock-input', 'users.index', 'access.index'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        foreach (['api.stock.by-part', 'api.locations.search', 'part-settings.search'] as $route) {
            $this->getJson(route($route))->assertForbidden();
        }
        $this->postJson(route('stock-input.scan-barcode'), ['barcode' => '12345678'])->assertForbidden();
        $this->postJson(route('stock-input.store'), [])->assertForbidden();
        $this->postJson(route('stock-withdrawal.confirm'), [])->assertForbidden();
        $this->putJson(route('access.update'), ['role' => 'export_operator', 'permissions' => ['stock_input']])
            ->assertForbidden();
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_export_operator_is_available_in_access_management_with_master_defaults(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('access.index', ['role' => 'export_operator']))
            ->assertOk()->assertSee('Operator Export')->assertViewHas('selected', ['export_delivery_plans', 'export_parts', 'export_locations']);
        $this->put(route('access.update'), ['role' => 'export_operator', 'permissions' => []])
            ->assertSessionHasNoErrors()->assertRedirect(route('access.index', ['role' => 'export_operator']));
        $this->assertDatabaseHas('role_feature_access', ['role' => 'export_operator', 'permissions' => '[]']);
    }

    public function test_inactive_export_operator_cannot_login_or_use_dashboard(): void
    {
        $operator = User::factory()->create(['role' => 'export_operator', 'is_active' => false]);
        $this->post('/login', ['email' => $operator->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($operator)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
