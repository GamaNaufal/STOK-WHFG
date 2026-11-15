<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportMasterLocation;
use App\Models\PartSetting;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportDeliveryPlanDeletionService;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportDeliveryPlanDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private PartSetting $part;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'admin_warehouse']);
        RoleFeatureAccess::create(['role' => 'admin_warehouse', 'permissions' => [
            'export_delivery_plans', 'export_pallet_plans', 'export_fulfillment', 'export_stock_input', 'export_locations',
        ]]);
        $this->actingAs($this->operator);
        $this->part = PartSetting::create(['part_number' => 'DELETE-PART', 'qty_box' => 100]);
    }

    private function delivery(): ExportDeliveryPlan
    {
        return app(ExportDeliveryPlanService::class)->save([
            'plan_code' => 'DELETE-'.Str::uuid(), 'customer_name' => 'Customer', 'delivery_deadline' => '2026-12-01',
            'source_type' => 'manual', 'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 200]],
        ], $this->operator);
    }

    private function planning(ExportDeliveryPlan $delivery, string $action = 'finalize')
    {
        $location = ExportMasterLocation::create(['code' => 'DELETE-LOCATION']);

        return app(ExportPalletPlanningService::class)->save($delivery->id, ['revision' => 0, 'delivery_revision' => 1,
            'action' => $action, 'pallets' => [['master_location_id' => $location->id,
                'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 2]]]],
        ], $this->operator);
    }

    private function reviewedData(ExportDeliveryPlan $delivery): array
    {
        $response = $this->getJson(route('export.delivery-plans.deletion-preview', $delivery))->assertOk()->assertJsonPath('can_delete', true);
        preg_match('/name="review_token" value="([^"]+)"/', $response->json('html'), $match);
        $this->assertNotEmpty($match[1] ?? null);

        return ['revision' => $delivery->fresh()->revision, 'pallet_revision' => $delivery->palletPlan()->value('revision'),
            'review_token' => html_entity_decode($match[1]), 'reason' => 'Customer membatalkan permintaan'];
    }

    public function test_empty_finalized_plan_can_be_deleted_and_location_is_released_with_history_intact(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        $this->get(route('export.delivery-plans.index'))->assertOk()->assertSee('#delete-delivery-plan', false);
        $this->get(route('export.delivery-plans.show', $delivery))->assertOk()->assertSee('data-delete-delivery-preview', false);
        $this->get(route('export.pallet-plans.show', $delivery))->assertOk()->assertSee('Hapus rencana delivery');
        $this->delete(route('export.delivery-plans.destroy', $delivery), ['revision' => 1, 'pallet_revision' => $planning->revision])
            ->assertRedirect(route('export.delivery-plans.index'))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('export_delivery_plans', ['id' => $delivery->id]);
        $this->assertSame('cancelled', $planning->fresh()->status);
        $this->assertDatabaseCount('export_pallet_location_reservations', 0);
        $this->assertDatabaseCount('export_delivery_plan_items', 1);
        $this->assertDatabaseCount('export_planned_pallet_items', 1);
        $this->assertDatabaseHas('export_delivery_plan_codes', ['code' => $delivery->plan_code]);
        $this->assertSame(1, AuditLog::where('type', 'export_delivery_plan')->where('action', 'deleted')->count());
        $this->get(route('export.delivery-plans.index'))->assertDontSee($delivery->plan_code);
        $this->assertDatabaseCount('export_stock_boxes', 0);
    }

    public function test_empty_draft_planning_is_cancelled_in_the_same_delete_transaction(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery, 'draft');
        $this->delete(route('export.delivery-plans.destroy', $delivery), ['revision' => 1, 'pallet_revision' => $planning->revision])
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted('export_delivery_plans', ['id' => $delivery->id]);
        $this->assertSame('cancelled', $planning->fresh()->status);
        $this->assertDatabaseCount('export_pallet_location_reservations', 0);
    }

    public function test_missing_or_stale_pallet_revision_and_stale_delivery_cannot_delete_or_release_locations(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        foreach ([[], ['pallet_revision' => $planning->revision + 1]] as $extra) {
            $this->deleteJson(route('export.delivery-plans.destroy', $delivery), ['revision' => 1] + $extra)
                ->assertUnprocessable()->assertJsonValidationErrors('pallet_plan');
        }
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), ['revision' => 2, 'pallet_revision' => $planning->revision])->assertConflict();
        $this->assertFalse($delivery->fresh()->trashed());
        $this->assertSame('final', $planning->fresh()->status);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
    }

    public function test_new_box_after_delete_preview_blocks_deletion_and_preserves_commitment_and_location(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        $this->get(route('export.delivery-plans.show', $delivery))->assertSee('data-delete-delivery-preview', false);
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => '98130001',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $before = $box->fresh()->getRawOriginal();
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), ['revision' => 1, 'pallet_revision' => $planning->revision])
            ->assertUnprocessable()->assertJsonValidationErrors('pallet_plan');
        $this->assertSame($before, $box->fresh()->getRawOriginal());
        $this->assertDatabaseCount('export_fulfillment_boxes', 1);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
        $this->assertSame('final', $planning->fresh()->status);
        $this->getJson(route('export.delivery-plans.deletion-preview', $delivery))->assertOk()->assertJsonPath('can_delete', true);
        $this->assertFalse($delivery->fresh()->trashed());
    }

    public function test_delivery_permission_cannot_cancel_a_pallet_plan_without_pallet_access(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        RoleFeatureAccess::updateOrCreate(['role' => $this->operator->role], ['permissions' => ['export_delivery_plans']]);
        $this->get(route('export.delivery-plans.show', $delivery))->assertOk()->assertDontSee('data-delete-delivery-form', false);
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), ['revision' => 1, 'pallet_revision' => $planning->revision])
            ->assertUnprocessable()->assertJsonValidationErrors('pallet_plan');
        $this->assertSame('final', $planning->fresh()->status);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
        $draft = $this->delivery();
        $this->delete(route('export.delivery-plans.destroy', $draft), ['revision' => 1])->assertSessionHasNoErrors();
        $this->assertSoftDeleted('export_delivery_plans', ['id' => $draft->id]);
    }

    public function test_completed_shipping_is_protected_even_if_delivery_status_is_inconsistent(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        $planning->update(['completed_at' => now(), 'completed_by' => $this->operator->id]);
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), ['revision' => 1, 'pallet_revision' => $planning->revision])
            ->assertUnprocessable()->assertJsonValidationErrors('pallet_plan');
        $this->assertFalse($delivery->fresh()->trashed());
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
    }

    public function test_audit_failure_rolls_back_deletion_planning_cancellation_and_location_release(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        $audits = AuditLog::count();
        AuditLog::creating(function ($log) {
            if ($log->type === 'export_delivery_plan' && $log->action === 'deleted') {
                throw new \RuntimeException('Audit unavailable');
            }
        });
        try {
            app(ExportDeliveryPlanDeletionService::class)->delete($delivery->id,
                ['revision' => 1, 'pallet_revision' => $planning->revision], $this->operator);
            $this->fail('Deletion must persist its audit.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit unavailable', $error->getMessage());
        }
        $this->assertFalse($delivery->fresh()->trashed());
        $this->assertSame('final', $planning->fresh()->status);
        $this->assertSame(1, $planning->fresh()->revision);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_preview_lists_boxes_positions_and_locations_without_releasing_anything(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => '98130003',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $audits = AuditLog::count();
        $before = $box->fresh()->getRawOriginal();
        $response = $this->getJson(route('export.delivery-plans.deletion-preview', $delivery))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('can_delete', true);
        $this->assertStringContainsString('98130003', $response->json('html'));
        $this->assertStringContainsString('DELETE-LOCATION', $response->json('html'));
        $this->assertStringContainsString('Reservasi akan dilepas, barang tetap di posisi tercatat', $response->json('html'));
        $this->assertSame($audits, AuditLog::count());
        $this->assertSame($before, $box->fresh()->getRawOriginal());
        $this->assertDatabaseCount('export_fulfillment_boxes', 1);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
    }

    public function test_reviewed_reservations_can_be_released_without_moving_stock_or_changing_fifo(): void
    {
        $delivery = $this->delivery();
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => '98130004',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $source = ExportMasterLocation::create(['code' => 'SOURCE-KEEP']);
        $box = app(ExportStockReceivingService::class)->place($box->id, ['revision' => $box->revision,
            'location_id' => $source->id, 'request_token' => (string) Str::uuid()], $this->operator);
        $planning = $this->planning($delivery);
        $physicalFields = ['box_number', 'part_setting_id', 'part_number', 'pcs_quantity', 'state', 'current_pallet_id', 'first_received_at'];
        $before = \Illuminate\Support\Arr::only($box->fresh()->getRawOriginal(), $physicalFields);
        $this->delete(route('export.delivery-plans.destroy', $delivery), $this->reviewedData($delivery))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSoftDeleted('export_delivery_plans', ['id' => $delivery->id]);
        $this->assertSame($before, \Illuminate\Support\Arr::only($box->fresh()->getRawOriginal(), $physicalFields));
        $this->assertDatabaseCount('export_fulfillment_boxes', 0);
        $this->assertDatabaseCount('export_stock_movements', 1);
        $this->assertDatabaseCount('export_pallet_location_reservations', 0);
        $this->assertSame('SOURCE-KEEP', $box->fresh()->currentPallet->location_code);
        $this->assertTrue($source->fresh()->is_occupied);
        $this->assertSame('cancelled', $planning->fresh()->status);
        $audit = AuditLog::where('type', 'export_delivery_plan')->where('action', 'deleted')->sole();
        $this->assertSame('98130004', $audit->getNewValuesArray()['released_boxes'][0]['box_number']);
    }

    public function test_new_reservation_after_popup_requires_new_review_even_when_pallet_revision_is_unchanged(): void
    {
        $delivery = $this->delivery();
        $this->planning($delivery);
        $data = $this->reviewedData($delivery);
        app(ExportStockReceivingService::class)->receive(['box_number' => '98130005',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), $data)->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->assertFalse($delivery->fresh()->trashed());
        $this->assertDatabaseCount('export_fulfillment_boxes', 1);
    }

    public function test_picked_box_is_listed_as_requiring_physical_return_and_has_no_delete_token(): void
    {
        $delivery = $this->delivery();
        $this->planning($delivery);
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => '98130006',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $box->fulfillment->update(['picking_started_at' => now()]);
        $response = $this->getJson(route('export.delivery-plans.deletion-preview', $delivery))->assertOk()->assertJsonPath('can_delete', false);
        $this->assertStringContainsString('98130006', $response->json('html'));
        $this->assertStringContainsString('Wajib pengembalian fisik', $response->json('html'));
        $this->assertStringNotContainsString('name="review_token"', $response->json('html'));
        $this->assertDatabaseCount('export_fulfillment_boxes', 1);
    }

    public function test_review_token_expires_and_is_bound_to_actor_and_delivery(): void
    {
        $delivery = $this->delivery();
        $data = $this->reviewedData($delivery);
        $other = User::factory()->create(['role' => 'admin_warehouse']);
        $this->actingAs($other)->deleteJson(route('export.delivery-plans.destroy', $delivery), $data)->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->actingAs($this->operator);
        $this->travel(16)->minutes();
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), $data)->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->travelBack();
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), array_replace($data, ['review_token' => 'tampered']))->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->assertFalse($delivery->fresh()->trashed());
    }

    public function test_box_release_requires_fulfillment_permission_and_reason(): void
    {
        $delivery = $this->delivery();
        $this->planning($delivery);
        app(ExportStockReceivingService::class)->receive(['box_number' => '98130007',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $data = $this->reviewedData($delivery);
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), array_replace($data, ['reason' => '']))->assertUnprocessable()->assertJsonValidationErrors('reason');
        RoleFeatureAccess::updateOrCreate(['role' => $this->operator->role], ['permissions' => ['export_delivery_plans', 'export_pallet_plans']]);
        $this->getJson(route('export.delivery-plans.deletion-preview', $delivery))->assertOk()->assertJsonPath('can_delete', false);
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), $data)->assertUnprocessable()->assertJsonValidationErrors('review_token');
        $this->assertDatabaseCount('export_fulfillment_boxes', 1);
    }

    public function test_release_and_cancel_roll_back_if_delete_audit_cannot_be_saved(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        $box = app(ExportStockReceivingService::class)->receive(['box_number' => '98130008',
            'part_number' => $this->part->part_number, 'receive_token' => (string) Str::uuid()], $this->operator);
        $data = $this->reviewedData($delivery);
        $before = $box->fresh()->getRawOriginal();
        AuditLog::creating(function ($log) {
            if ($log->type === 'export_delivery_plan' && $log->action === 'deleted') {
                throw new \RuntimeException('Delete audit unavailable');
            }
        });
        try {
            app(ExportDeliveryPlanDeletionService::class)->delete($delivery->id, $data, $this->operator);
            $this->fail('Audit failure must roll back reservation release.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Delete audit unavailable', $error->getMessage());
        }
        $this->assertFalse($delivery->fresh()->trashed());
        $this->assertSame($before, $box->fresh()->getRawOriginal());
        $this->assertSame('final', $planning->fresh()->status);
        $this->assertDatabaseCount('export_fulfillment_boxes', 1);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
        $this->assertDatabaseCount('export_fulfillment_actions', 0);
    }

    public function test_only_three_management_roles_can_preview_and_delete_with_existing_feature_permissions(): void
    {
        foreach (['admin', 'admin_warehouse', 'supervisi'] as $role) {
            RoleFeatureAccess::updateOrCreate(['role' => $role], ['permissions' => ['export_delivery_plans', 'export_pallet_plans', 'export_fulfillment']]);
            $delivery = $this->delivery();
            $manager = User::factory()->create(['role' => $role]);
            $this->actingAs($manager)->get(route('export.delivery-plans.show', $delivery))->assertOk()->assertSee('data-delete-delivery-preview', false);
            $this->delete(route('export.delivery-plans.destroy', $delivery), $this->reviewedData($delivery))->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSoftDeleted('export_delivery_plans', ['id' => $delivery->id]);
        }
    }

    public function test_other_roles_cannot_see_delete_or_bypass_it_via_routes_even_when_export_features_are_granted(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        foreach (['export_operator', 'warehouse_operator', 'sales', 'ppc'] as $role) {
            RoleFeatureAccess::updateOrCreate(['role' => $role], ['permissions' => ['export_delivery_plans', 'export_pallet_plans', 'export_fulfillment']]);
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            foreach (['export.delivery-plans.index', 'export.delivery-plans.show', 'export.pallet-plans.show'] as $route) {
                $this->get(route($route, $route === 'export.delivery-plans.index' ? [] : $delivery))->assertOk()->assertDontSee('data-delete-delivery-preview', false);
            }
            $this->getJson(route('export.delivery-plans.deletion-preview', $delivery))->assertForbidden();
            $this->deleteJson(route('export.delivery-plans.destroy', $delivery), [])->assertForbidden();
            $this->get(route('export.fulfillment.cancel-form', $delivery))->assertForbidden();
            $this->postJson(route('export.fulfillment.cancel', $delivery), [])->assertForbidden();
            $this->assertFalse($user->canAccessRoute('export.delivery-plans.destroy'));
        }
        $this->assertFalse($delivery->fresh()->trashed());
        $this->assertSame('final', $planning->fresh()->status);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
    }

    public function test_role_change_invalidates_existing_review_and_direct_service_access(): void
    {
        $delivery = $this->delivery();
        $data = $this->reviewedData($delivery);
        $this->operator->update(['role' => 'export_operator']);
        $this->actingAs($this->operator->fresh());
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), $data)->assertForbidden();
        $service = app(ExportDeliveryPlanDeletionService::class);
        foreach ([fn () => $service->preview($delivery->id, $this->operator), fn () => $service->delete($delivery->id, $data, $this->operator),
            fn () => app(\App\Services\ExportFulfillmentCompletionService::class)->cancel($delivery->id, [], $this->operator)] as $action) {
            try {
                $action();
                $this->fail('A non-management actor must be rejected before mutation.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
        }
        $this->assertFalse($delivery->fresh()->trashed());
    }

    public function test_operator_cannot_delete_the_delivery_by_reducing_the_last_pallet_to_zero(): void
    {
        $delivery = $this->delivery();
        $planning = $this->planning($delivery);
        $pallet = $planning->pallets()->sole();
        $operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($operator);
        $service = app(\App\Services\ExportFulfillmentCompletionService::class);
        $quantities = $pallet->items()->pluck('box_quantity', 'id')->map(fn () => 0)->all();
        $preview = $service->reduction($pallet, $quantities);
        $data = ['revision' => $planning->revision, 'fulfillment_revision' => $pallet->fulfillment_revision,
            'quantities' => $quantities, 'fingerprint' => $preview['fingerprint'], 'request_token' => (string) Str::uuid()];
        $this->postJson(route('export.fulfillment.reduce', [$delivery, $pallet]), $data)->assertForbidden();
        $this->assertFalse($delivery->fresh()->trashed());
        $this->assertDatabaseCount('export_planned_pallets', 1);
        $this->assertDatabaseCount('export_pallet_location_reservations', 1);
        $this->assertDatabaseCount('export_fulfillment_actions', 0);
    }

    public function test_management_role_does_not_override_revoked_feature_access_or_inactive_account(): void
    {
        $delivery = $this->delivery();
        RoleFeatureAccess::updateOrCreate(['role' => 'admin_warehouse'], ['permissions' => ['export_pallet_plans', 'export_fulfillment']]);
        $this->getJson(route('export.delivery-plans.deletion-preview', $delivery))->assertForbidden();
        $this->deleteJson(route('export.delivery-plans.destroy', $delivery), ['revision' => 1])->assertForbidden();
        $this->operator->update(['is_active' => false]);
        $this->assertFalse($this->operator->canDeleteExportDeliveryPlan());
        $this->assertFalse($delivery->fresh()->trashed());
    }
}
