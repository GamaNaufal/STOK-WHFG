<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportPalletPlan;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportFulfillmentCompletionService;
use App\Services\ExportPalletPlanningService;
use App\Services\FeatureAccessService;
use App\Services\GranularFeaturePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GranularFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    private function grant(array $permissions, string $role = 'export_operator', int $version = 2): User
    {
        RoleFeatureAccess::updateOrCreate(['role' => $role], ['permissions' => $permissions, 'permissions_version' => $version]);
        request()->attributes->remove('feature_access.'.$role);
        return User::factory()->create(['role' => $role]);
    }

    private function delivery(User $user): ExportDeliveryPlan
    {
        return ExportDeliveryPlan::create(['plan_code' => 'ACCESS-1', 'customer_name' => 'Access test',
            'delivery_deadline' => now()->addDay(), 'total_pcs' => 0, 'total_boxes' => 0,
            'created_by' => $user->id, 'updated_by' => $user->id]);
    }

    public function test_legacy_menu_grants_keep_actions_but_new_read_only_grants_do_not(): void
    {
        $user = $this->grant(['export_pallet_plans', 'export_fulfillment'], version: 1);
        $this->assertTrue($user->canAccess('export_pallet_finalize'));
        $this->assertTrue($user->canAccess('export_fulfillment_revise'));
        $this->assertFalse($user->canAccess('export_delivery_delete'));
        $user = $this->grant(['export_pallet_plans', 'export_fulfillment']);
        $delivery = $this->delivery($user);
        $plan = ExportPalletPlan::create(['export_delivery_plan_id' => $delivery->id, 'delivery_revision' => 1,
            'created_by' => $user->id, 'updated_by' => $user->id]);
        $pallet = $plan->pallets()->create(['position' => 1]);
        $this->assertFalse($user->canAccess('export_pallet_finalize'));
        $this->assertFalse($user->canAccess('export_fulfillment_revise'));
        $this->actingAs($user)->get(route('export.pallet-plans.index'))->assertOk();
        $this->get(route('export.fulfillment.index'))->assertOk();
        $this->putJson(route('export.pallet-plans.update', $delivery), ['action' => 'finalize'])->assertForbidden();
        $this->get(route('export.fulfillment.revise', [$delivery, $pallet]))->assertForbidden();
    }

    public function test_shared_routes_check_selected_action_before_validation_or_mutation(): void
    {
        $user = $this->grant(['export_pallet_plans', 'export_pallet_edit']);
        $delivery = $this->delivery($user);
        $this->actingAs($user)->putJson(route('export.pallet-plans.update', $delivery), ['action' => 'finalize'])->assertForbidden();
        $this->postJson(route('export.pallet-plans.transition', $delivery), ['action' => 'cancel'])->assertForbidden();
        $this->putJson(route('export.pallet-plans.update', $delivery), ['action' => 'draft'])->assertUnprocessable();

        $user = $this->grant(['delivery_approval', 'delivery_approve'], 'ppc');
        $this->actingAs($user)->postJson(route('delivery.status', 1), ['status' => 'rejected'])->assertForbidden();
        $this->postJson(route('delivery.status', 1), ['status' => 'correction'])->assertForbidden();

        $user = $this->grant(['expired_box', 'expired_box_qa'], 'admin_warehouse');
        $this->actingAs($user)->postJson(route('expired-box.bulk.store'), ['action' => 'packing'])->assertForbidden();
        $this->postJson(route('expired-box.bulk.store'), ['action' => 'receive'])->assertForbidden();
        $this->postJson(route('expired-box.bulk.store'), ['action' => 'qa'])->assertUnprocessable();
        $this->assertDatabaseCount('export_pallet_plans', 0);
        $this->assertDatabaseCount('expired_box_handlings', 0);
    }

    public function test_new_box_assignment_requires_its_own_permission(): void
    {
        $user = $this->grant(['delivery_assign', 'delivery_assign_execute'], 'warehouse_operator');
        $this->actingAs($user)->postJson(route('delivery-assign.assign'), ['new_boxes' => [['box_number' => '12345678']]])->assertForbidden();
        $this->postJson(route('delivery-assign.assign'), [])->assertUnprocessable();
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_saving_explicit_actions_never_reexpands_revoked_children(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $permissions = ['export_pallet_plans', 'export_fulfillment', 'export_fulfillment_revise'];
        $this->actingAs($admin)->put(route('access.update'), ['role' => 'sales', 'permissions' => $permissions, 'permissions_version' => 2])
            ->assertSessionHasNoErrors()->assertRedirect();
        $record = RoleFeatureAccess::where('role', 'sales')->firstOrFail();
        $this->assertSame(2, $record->permissions_version);
        $this->assertSame($permissions, $record->permissions);
        $user = User::factory()->create(['role' => 'sales']);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->assertTrue($user->canAccess('export_fulfillment_revise'));
        $this->assertFalse($user->canAccess('export_pallet_finalize'));
        $this->assertFalse($user->canAccess('export_fulfillment_complete'));
        $this->assertFalse($user->canDeleteExportDeliveryPlan());
        $this->actingAs($admin)->put(route('access.update'), ['role' => 'sales', 'permissions' => ['export_fulfillment'], 'permissions_version' => 2])->assertRedirect();
        $this->actingAs($user)->get(route('export.fulfillment.index'))->assertOk();
        $this->assertFalse($user->canAccess('export_fulfillment_revise'));
        $this->assertSame(2, AuditLog::where('type', 'role_feature_access')->count());
    }

    public function test_dependencies_and_fixed_role_limits_cannot_be_bypassed_by_payload(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson(route('access.update'), ['role' => 'sales', 'permissions_version' => 2, 'permissions' => ['export_fulfillment_revise']])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions');
        foreach (['sales', 'ppc', 'warehouse_operator', 'export_operator'] as $role) {
            $this->putJson(route('access.update'), ['role' => $role, 'permissions_version' => 2,
                'permissions' => ['export_delivery_plans', 'export_delivery_delete']])->assertUnprocessable();
            $user = $this->grant(['export_delivery_plans', 'export_delivery_delete'], $role);
            $this->assertFalse($user->canDeleteExportDeliveryPlan());
        }
        foreach (['admin_warehouse', 'supervisi'] as $role) {
            $user = $this->grant(['export_delivery_plans', 'export_delivery_delete'], $role);
            $this->assertTrue($user->canDeleteExportDeliveryPlan());
            $this->assertFalse($user->canAccess('export_fulfillment_cancel'));
        }
    }

    public function test_legacy_export_stock_access_is_converted_without_granting_management(): void
    {
        $user = $this->grant(['export_stock_input'], version: 1);
        $this->assertTrue($user->canAccess('export_stock_view'));
        $this->assertTrue($user->canAccess('export_stock_export'));
        $this->assertFalse($user->canAccess('export_stock_edit'));
        $user = $this->grant(['export_stock_manage'], 'admin_warehouse', 1);
        $this->assertTrue($user->canAccess('export_stock_view'));
        $this->assertTrue($user->canAccess('export_stock_edit'));
        $this->assertFalse($user->canAccess('export_receiving_scan'));
    }

    public function test_read_only_module_permissions_block_all_write_routes(): void
    {
        $access = app(FeatureAccessService::class);
        $features = config('feature_access.features');
        $readonly = array_keys(array_filter($features, fn ($f) => !isset($f['parent']) && isset($f['route']) && !($f['admin_only'] ?? false)));
        $user = $this->grant($readonly, 'admin_warehouse');
        $queryOnly = ['stock-withdrawal.search', 'stock-withdrawal.preview'];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('features', $route->gatherMiddleware(), true) || !array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods())) continue;
            if (in_array($route->getName(), ['profile.update', ...$queryOnly], true)) continue;
            $this->assertFalse($access->allowsRoute($user, $route->getName()), 'Read-only permission allowed '.$route->getName());
        }
    }

    public function test_every_action_rule_references_real_routes_and_valid_dependencies(): void
    {
        $features = config('feature_access.features');
        $routes = collect(Route::getRoutes())->map->getName()->filter()->all();
        foreach ($features as $key => $feature) {
            foreach ($feature['requires'] ?? [] as $required) $this->assertArrayHasKey($required, $features, $key);
            foreach ($feature['routes'] as $pattern) $this->assertTrue((bool) array_filter($routes, fn ($name) => \Illuminate\Support\Str::is($pattern, $name)), $key.': '.$pattern);
        }
        foreach (config('feature_access.action_dispatch') as $route => $dispatch) {
            $this->assertContains($route, $routes);
            foreach ($dispatch['choices'] as $key) $this->assertArrayHasKey($key, $features);
        }
    }

    public function test_migration_expands_legacy_rows_once_and_preserves_explicit_revocations(): void
    {
        $legacy = $this->grant(['export_pallet_plans'], version: 1);
        $modern = $this->grant(['stock_input'], 'warehouse_operator');
        $migration = require database_path('migrations/2026_11_12_000002_version_granular_feature_permissions.php');
        $migration->up();
        $row = RoleFeatureAccess::where('role', $legacy->role)->firstOrFail();
        $this->assertSame(2, $row->permissions_version);
        $this->assertContains('export_pallet_finalize', $row->permissions);
        $this->assertSame(['stock_input'], RoleFeatureAccess::where('role', $modern->role)->first()->permissions);
        $migration->up();
        $this->assertSame(1, AuditLog::where('action', 'migrated')->count());
        $this->assertDatabaseCount('boxes', 0);
        $this->assertDatabaseCount('export_stock_boxes', 0);
        $this->assertDatabaseCount('export_delivery_plans', 0);
    }

    public function test_direct_service_calls_respect_revoked_actions(): void
    {
        $user = $this->grant(['export_pallet_plans', 'export_fulfillment']);
        $calls = [fn () => app(ExportPalletPlanningService::class)->save(1, ['action' => 'finalize'], $user),
            fn () => app(ExportFulfillmentCompletionService::class)->ready(1, 1, [], $user),
            fn () => app(ExportFulfillmentCompletionService::class)->reopen(1, 1, [], $user),
            fn () => app(ExportFulfillmentCompletionService::class)->reduce(1, 1, [], $user),
            fn () => app(ExportFulfillmentCompletionService::class)->complete(1, [], $user),
            fn () => app(ExportFulfillmentCompletionService::class)->cancel(1, [], $user)];
        foreach ($calls as $call) {
            try { $call(); $this->fail('Revoked service action succeeded'); }
            catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
    }

    public function test_access_page_explains_actions_and_hides_role_restricted_options(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('access.index', ['role' => 'export_operator']))
            ->assertOk()->assertSee('Revisi part dan jumlah pallet')->assertSee('Finalisasi perencanaan pallet')
            ->assertSee('Cari menu atau tindakan')->assertDontSee('id="feature-export_delivery_delete"', false);
        $this->get(route('access.index', ['role' => 'supervisi']))->assertOk()->assertSee('id="feature-export_delivery_delete"', false);
    }

    public function test_dashboard_detail_revocation_also_blocks_query_string_fallback(): void
    {
        $user = $this->grant([], 'supervisi');
        $this->actingAs($user)->get(route('dashboard.details'))->assertForbidden();
        $this->get(route('dashboard', ['show_details' => 1, 'audit_type' => 'role_feature_access']))
            ->assertOk()->assertViewMissing('orders')->assertViewMissing('auditLogs');
        $user = $this->grant(['dashboard_details'], 'supervisi');
        $this->actingAs($user)->getJson(route('dashboard.details'))->assertOk();
    }

    public function test_audit_failure_rolls_back_permission_changes(): void
    {
        $this->grant(['export_fulfillment'], 'sales');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        AuditLog::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->withoutExceptionHandling()->putJson(route('access.update'), ['role' => 'sales', 'permissions_version' => 2,
                'permissions' => ['export_fulfillment', 'export_fulfillment_revise']]);
            $this->fail('Missing audit must fail the update');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit unavailable', $error->getMessage());
        } finally {
            AuditLog::getEventDispatcher()->forget('eloquent.creating: '.AuditLog::class);
        }
        $this->assertSame(['export_fulfillment'], RoleFeatureAccess::where('role', 'sales')->first()->permissions);
    }

    public function test_reading_old_schema_keeps_legacy_actions_before_migration(): void
    {
        \Illuminate\Support\Facades\Schema::table('role_feature_access', fn ($table) => $table->dropColumn('permissions_version'));
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_pallet_plans']]);
        $user = User::factory()->create(['role' => 'export_operator']);
        $this->assertTrue($user->canAccess('export_pallet_edit'));
        $this->assertTrue($user->canAccess('export_pallet_finalize'));
        $this->assertFalse($user->canAccess('export_fulfillment_revise'));
    }

    public function test_stock_history_revocation_also_redacts_embedded_history_in_detail_api(): void
    {
        $pallet = \App\Models\Pallet::create(['pallet_number' => 'ACCESS-PALLET']);
        AuditLog::create(['type' => 'pallet', 'action' => 'created', 'model' => 'Pallet', 'model_id' => $pallet->id,
            'description' => 'History that requires separate access']);
        $user = $this->grant(['stock_view'], 'warehouse_operator');
        $this->actingAs($user)->getJson(route('api.stock.pallet-detail', $pallet))->assertOk()->assertJsonPath('history', []);
        $this->getJson(route('stock-view.pallet-history', $pallet))->assertForbidden();
        $user = $this->grant(['stock_view', 'stock_history'], 'warehouse_operator');
        $this->actingAs($user)->getJson(route('api.stock.pallet-detail', $pallet))
            ->assertOk()->assertJsonCount(1, 'history')->assertJsonFragment(['description' => 'History that requires separate access']);
    }
}
