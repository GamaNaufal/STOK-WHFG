<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FeatureAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    private function savePermissions(string $role, array $permissions): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('access.update'), compact('role', 'permissions'))
            ->assertSessionHasNoErrors()->assertRedirect(route('access.index', ['role' => $role]));
    }

    public function test_only_main_admin_can_view_or_change_access(): void
    {
        $this->get(route('access.index'))->assertRedirect(route('login'));
        foreach (array_keys(config('feature_access.roles')) as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('access.index'))->assertForbidden();
            $this->put(route('access.update'), ['role' => $role, 'permissions' => ['users']])->assertForbidden();
        }
        $this->assertDatabaseCount('role_feature_access', 0);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('access.index'))->assertOk()->assertSee('Simpan Hak Akses');
    }

    public function test_grant_and_revoke_apply_to_all_accounts_on_next_request(): void
    {
        $first = User::factory()->create(['role' => 'sales']);
        $second = User::factory()->create(['role' => 'sales']);
        $this->actingAs($first)->get(route('stock-input.index'))->assertForbidden();
        $this->savePermissions('sales', ['stock_input']);
        foreach ([$first, $second] as $user) {
            $this->actingAs($user)->get(route('stock-input.index'))->assertOk();
            $this->get(route('delivery.create'))->assertForbidden();
        }
        $this->savePermissions('sales', []);
        foreach ([$first, $second] as $user) {
            $this->actingAs($user)->get(route('stock-input.index'))->assertForbidden();
            $this->postJson(route('stock-input.scan-box'), ['box_id' => '12345678'])->assertForbidden();
            $this->getJson(route('api.locations.search'))->assertForbidden();
            $this->get(route('profile.edit'))->assertOk();
        }
        $this->assertDatabaseCount('audit_logs', 2);
        $audit = AuditLog::latest('id')->first();
        $this->assertSame(['stock_input'], $audit->getOldValuesArray()['permissions']);
        $this->assertSame([], $audit->getNewValuesArray()['permissions']);
    }

    public function test_defaults_remain_unchanged_for_other_roles(): void
    {
        $this->savePermissions('sales', []);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $this->actingAs($operator)->get(route('stock-input.index'))->assertOk();
        $this->get(route('locations.index'))->assertForbidden();
        $this->get(route('reports.stock-input'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'supervisi']))
            ->get(route('box-not-full.approvals'))->assertOk();
    }

    public function test_navigation_and_search_follow_permissions(): void
    {
        $this->savePermissions('sales', ['move_pallet']);
        $response = $this->actingAs(User::factory()->create(['role' => 'sales']))->get(route('profile.edit'));
        $response->assertOk()->assertSee(route('move-pallet.index'), false)
            ->assertDontSee(route('delivery.create'), false)
            ->assertDontSee(route('access.index'), false)
            ->assertDontSee(route('users.index'), false);
        $this->savePermissions('sales', []);
        $this->actingAs(User::factory()->create(['role' => 'sales']))->get(route('profile.edit'))
            ->assertOk()->assertDontSee(route('move-pallet.index'), false);
    }

    public function test_granted_features_pass_former_controller_role_checks(): void
    {
        $this->savePermissions('supervisi', ['delivery_schedule', 'delivery_order', 'delivery_approval', 'delivery_pick', 'delivery_issues', 'audit']);
        $this->actingAs(User::factory()->create(['role' => 'supervisi']));
        foreach (['delivery.index', 'delivery.create', 'delivery.approvals', 'delivery.pick.verification', 'delivery.pick.issues', 'audit.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->postJson(route('stock-withdrawal.confirm'), [])->assertUnprocessable();
    }

    public function test_granted_order_access_preserves_ownership_checks(): void
    {
        $owner = User::factory()->create(['role' => 'sales']);
        $other = User::factory()->create(['role' => 'supervisi']);
        $order = DeliveryOrder::create(['sales_user_id' => $owner->id, 'customer_name' => 'Customer', 'delivery_date' => now()->addDay(), 'status' => 'correction']);
        $this->savePermissions('supervisi', ['delivery_order']);
        $this->actingAs($other)->get(route('delivery.edit', $order->id))
            ->assertRedirect()->assertSessionHas('error');
        $this->put(route('delivery.update', $order->id), ['customer_name' => 'Changed'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('Customer', $order->fresh()->customer_name);
    }

    public function test_new_picking_role_cannot_read_another_users_picklist(): void
    {
        $owner = User::factory()->create(['role' => 'warehouse_operator']);
        $other = User::factory()->create(['role' => 'sales']);
        $order = DeliveryOrder::create(['sales_user_id' => $other->id, 'customer_name' => 'Customer', 'delivery_date' => now()->addDay(), 'status' => 'processing']);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $owner->id, 'status' => 'scanning', 'started_at' => now()]);
        $this->savePermissions('sales', ['delivery_schedule', 'delivery_pick']);
        $this->actingAs($other)->get(route('delivery.pick.pdf', [$order->id, $session->id]))->assertForbidden();
        $this->get(route('delivery.pick.print-preview', [$order->id, $session->id]))->assertForbidden();
    }

    public function test_invalid_or_privileged_permissions_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['users', 'access_management', 'delivery_locks', 'made_up_feature'] as $permission) {
            $this->putJson(route('access.update'), ['role' => 'sales', 'permissions' => [$permission]])
                ->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
        }
        foreach (['admin', 'invalid_role'] as $role) {
            $this->putJson(route('access.update'), ['role' => $role, 'permissions' => []])
                ->assertUnprocessable()->assertJsonValidationErrors('role');
        }
        $this->putJson(route('access.update'), ['role' => 'sales'])->assertUnprocessable();
        $this->putJson(route('access.update'), ['role' => 'sales', 'permissions' => ['stock_edit']])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions');
        $this->assertDatabaseCount('role_feature_access', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_empty_html_form_selection_removes_all_permissions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('access.update'), ['role' => 'warehouse_operator', 'permissions' => ''])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([], RoleFeatureAccess::first()->permissions);
    }

    public function test_admin_always_has_access_and_corrupt_rows_cannot_elevate_other_roles(): void
    {
        RoleFeatureAccess::create(['role' => 'admin', 'permissions' => []]);
        RoleFeatureAccess::create(['role' => 'sales', 'permissions' => ['users', 'access_management', 'delivery_locks', 'stock_edit']]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('access.index'))->assertOk();
        $this->get(route('users.index'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'sales']));
        foreach (['users.index', 'access.index', 'delivery.pick.locks'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->postJson(route('stock-view.box-update', 1), [])->assertForbidden();
    }

    public function test_shared_lookups_are_allowed_only_for_consuming_features(): void
    {
        $this->savePermissions('sales', ['move_pallet']);
        $this->actingAs(User::factory()->create(['role' => 'sales']))->getJson(route('api.locations.search'))->assertOk();
        $this->getJson(route('api.stock.by-part'))->assertForbidden();
        $this->getJson(route('api.stock.part-detail', 'TEST'))->assertForbidden();
        $this->get(route('locations.index'))->assertForbidden();
    }

    public function test_every_protected_route_has_an_explicit_access_rule(): void
    {
        $access = app(FeatureAccessService::class);
        foreach (Route::getRoutes() as $route) {
            if (! in_array('features', $route->gatherMiddleware(), true)) {
                continue;
            }
            if (in_array($route->getName(), ['dashboard', 'profile.edit', 'profile.update'], true)) {
                continue;
            }
            $this->assertNotEmpty($access->routePermissions($route->getName()), 'Missing access rule for '.$route->uri());
        }
    }
}
