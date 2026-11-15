<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PartDocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function master(array $changes = []): array
    {
        return array_replace(['part_number' => 'P71005079', 'qty_box' => 1000,
            'same_document_number' => '0', 'document_numbers' => "7100-5079\nDOC-SECOND"], $changes);
    }

    private function plan(array $changes = []): array
    {
        return array_replace(['request_token' => (string) Str::uuid(), 'plan_code' => (string) Str::uuid(),
            'customer_name' => 'Customer', 'delivery_deadline' => '2026-12-01', 'source_type' => 'excel', 'reviewed' => '1',
            'items' => [['part_number' => '7100-5079', 'pcs_quantity' => 2000]]], $changes);
    }

    public function test_one_master_form_saves_scan_and_multiple_document_codes_and_searches_both(): void
    {
        $this->post(route('part-settings.store'), $this->master())->assertSessionHasNoErrors();
        $part = PartSetting::sole();
        $this->assertSame(['7100-5079', 'DOC-SECOND'], $part->documentNumbers()->orderBy('id')->pluck('document_number')->all());
        $this->getJson(route('part-settings.search', ['q' => '7100-5079']))->assertOk()
            ->assertJsonPath('data.0.part_number', 'P71005079')->assertJsonCount(2, 'data.0.document_numbers');
        $this->get(route('part-settings.edit', $part))->assertOk()->assertSee('Nomor part label / scan')->assertSee('7100-5079');
        $this->assertSame(['7100-5079', 'DOC-SECOND'], AuditLog::where('type', 'master_part')->sole()->getNewValuesArray()['document_numbers']);
        foreach (['boxes', 'export_stock_boxes', 'export_delivery_plans'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_same_code_and_legacy_capacity_edits_need_no_second_input(): void
    {
        $this->post(route('part-settings.store'), $this->master(['same_document_number' => '1']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('part_document_numbers', 0);
        $part = PartSetting::sole();
        $this->put(route('part-settings.update', $part), $this->master())->assertSessionHasNoErrors();
        $this->put(route('part-settings.update', $part), ['part_number' => 'P71005079', 'qty_box' => 500])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('part_document_numbers', 2);
        $this->assertSame(500, $part->fresh()->qty_box);
        $this->put(route('part-settings.update', $part), $this->master(['same_document_number' => '1']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('part_document_numbers', 0);
    }

    public function test_alias_conflicts_and_scan_collisions_roll_back_the_entire_master_save(): void
    {
        $this->post(route('part-settings.store'), $this->master())->assertSessionHasNoErrors();
        $this->postJson(route('part-settings.store'), $this->master(['part_number' => 'OTHER']))
            ->assertUnprocessable()->assertJsonValidationErrors('document_numbers');
        $this->postJson(route('part-settings.store'), $this->master(['part_number' => '7100-5079', 'same_document_number' => '1']))
            ->assertUnprocessable()->assertJsonValidationErrors('part_number');
        $other = PartSetting::create(['part_number' => 'OTHER', 'qty_box' => 20]);
        $part = PartSetting::where('part_number', 'P71005079')->sole();
        $this->putJson(route('part-settings.update', $part), $this->master(['qty_box' => 30, 'document_numbers' => 'OTHER']))
            ->assertUnprocessable()->assertJsonValidationErrors('document_numbers');
        $this->assertSame(1000, $part->fresh()->qty_box);
        $this->assertDatabaseCount('part_document_numbers', 2);
        $this->assertSame(1, AuditLog::where('type', 'master_part')->count());
    }

    public function test_slashes_are_literal_and_codes_keep_leading_zeroes_and_case(): void
    {
        $this->post(route('part-settings.store'), $this->master(['document_numbers' => "001-A/B\n001-a\n001-a"]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('part_document_numbers', 2);
        $this->postJson(route('export.delivery-plans.store'), $this->plan(['items' => [['part_number' => '001-A', 'pcs_quantity' => 2000]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.part_number');
        $this->post(route('export.delivery-plans.store'), $this->plan(['items' => [['part_number' => '001-A/B', 'pcs_quantity' => 2000]]]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('export_delivery_plan_items', ['document_part_number' => '001-A/B', 'part_number' => 'P71005079']);
    }

    public function test_document_delivery_resolves_existing_identity_and_snapshots_original_code_without_touching_stock(): void
    {
        $this->post(route('part-settings.store'), $this->master())->assertSessionHasNoErrors();
        $part = PartSetting::sole();
        $data = $this->plan(['items' => [['part_number' => '7100-5079', 'pcs_quantity' => 2500, 'expected_part_setting_id' => $part->id]]]);
        $this->post(route('export.delivery-plans.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('export.delivery-plans.store'), $data)->assertSessionHasNoErrors();
        $plan = ExportDeliveryPlan::sole();
        $item = $plan->items()->sole();
        $this->assertSame($part->id, $item->part_setting_id);
        $this->assertSame('P71005079', $item->part_number);
        $this->assertSame('7100-5079', $item->document_part_number);
        $this->assertSame(3, $item->box_quantity);
        $this->assertSame(500, $item->not_full_pcs);
        $this->get(route('export.delivery-plans.show', $plan))->assertOk()->assertSee('7100-5079')->assertSee('Label / scan: P71005079');
        $this->assertSame('7100-5079', AuditLog::where('type', 'export_delivery_plan')->sole()->getNewValuesArray()['items'][0]['document_part_number']);
        $this->assertDatabaseCount('export_stock_boxes', 0);
        $this->assertDatabaseCount('boxes', 0);
        $this->assertDatabaseCount('export_delivery_plan_requests', 1);
    }

    public function test_two_aliases_or_alias_and_label_of_same_product_cannot_duplicate_delivery(): void
    {
        $this->post(route('part-settings.store'), $this->master())->assertSessionHasNoErrors();
        foreach (['DOC-SECOND', 'P71005079'] as $code) {
            $this->postJson(route('export.delivery-plans.store'), $this->plan(['items' => [
                ['part_number' => '7100-5079', 'pcs_quantity' => 1000], ['part_number' => $code, 'pcs_quantity' => 1000],
            ]]))->assertUnprocessable()->assertJsonValidationErrors('items.1.part_number');
        }
        $this->assertDatabaseCount('export_delivery_plans', 0);
        $this->assertDatabaseCount('export_delivery_plan_requests', 0);
    }

    public function test_saved_delivery_stays_bound_after_mapping_changes_and_stale_new_preview_is_rejected(): void
    {
        $this->post(route('part-settings.store'), $this->master())->assertSessionHasNoErrors();
        $part = PartSetting::sole();
        $original = $this->plan();
        $this->post(route('export.delivery-plans.store'), $original)->assertSessionHasNoErrors();
        $plan = ExportDeliveryPlan::sole();
        $this->put(route('part-settings.update', $part), $this->master(['document_numbers' => 'DOC-NEW']))->assertSessionHasNoErrors();
        $this->post(route('part-settings.store'), $this->master(['part_number' => 'OTHER', 'document_numbers' => '7100-5079']))->assertSessionHasNoErrors();
        $this->get(route('export.delivery-plans.edit', $plan))->assertOk()->assertSee('7100-5079');
        $this->put(route('export.delivery-plans.update', $plan), $this->plan(['revision' => 1, 'plan_code' => $plan->plan_code,
            'items' => [['part_number' => '7100-5079', 'pcs_quantity' => 3000, 'expected_part_setting_id' => $part->id]]]))->assertSessionHasNoErrors();
        $item = $plan->items()->sole();
        $this->assertSame($part->id, $item->part_setting_id);
        $this->assertSame('7100-5079', $item->document_part_number);
        $this->postJson(route('export.delivery-plans.store'), $this->plan(['items' => [
            ['part_number' => '7100-5079', 'pcs_quantity' => 1000, 'expected_part_setting_id' => $part->id],
        ]]))->assertUnprocessable()->assertJsonValidationErrors('items.0.part_number');
        $this->post(route('export.delivery-plans.store'), $original)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('export_delivery_plans', 1);
    }

    public function test_master_mapping_uses_existing_permissions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales']))
            ->postJson(route('part-settings.store'), $this->master())->assertForbidden();
        $this->assertDatabaseCount('part_settings', 0);
    }

    public function test_different_document_option_requires_a_code_and_rejects_overlong_codes(): void
    {
        foreach (['', str_repeat('X', 101)] as $codes) {
            $this->postJson(route('part-settings.store'), $this->master(['document_numbers' => $codes]))
                ->assertUnprocessable()->assertJsonValidationErrors('document_numbers');
        }
        $this->assertDatabaseCount('part_settings', 0);
        $this->assertDatabaseCount('part_document_numbers', 0);
    }

    public function test_label_receipt_fulfills_document_delivery_with_same_identity_and_prints_original_document(): void
    {
        $this->post(route('part-settings.store'), $this->master())->assertSessionHasNoErrors();
        $part = PartSetting::sole();
        $box = app(\App\Services\ExportStockReceivingService::class)->receive([
            'box_number' => '98120001', 'part_number' => 'P71005079', 'receive_token' => (string) Str::uuid(),
        ], auth()->user(), false);
        $this->post(route('export.delivery-plans.store'), $this->plan(['items' => [['part_number' => '7100-5079', 'pcs_quantity' => 1000]]]))
            ->assertSessionHasNoErrors();
        $plan = ExportDeliveryPlan::sole();
        $location = \App\Models\ExportMasterLocation::create(['code' => 'MAP-TEST']);
        app(\App\Services\ExportPalletPlanningService::class)->save($plan->id, ['revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $location->id, 'items' => [['part_setting_id' => $part->id, 'box_quantity' => 1]]]],
        ], auth()->user());
        $this->assertDatabaseHas('export_fulfillment_boxes', ['export_stock_box_id' => $box->id, 'part_setting_id' => $part->id]);
        $this->assertSame($part->id, $box->fresh()->part_setting_id);
        $this->assertSame('P71005079', $box->fresh()->part_number);
        $this->assertSame(1000, $box->fresh()->pcs_quantity);
        $this->get(route('export.delivery-plans.print', $plan))->assertOk()->assertSee('7100-5079')->assertSee('Label / scan: P71005079');
    }

    public function test_additive_migration_preserves_populated_master_box_and_historical_delivery(): void
    {
        $part = PartSetting::create(['part_number' => 'LEGACY', 'qty_box' => 20]);
        $box = app(\App\Services\ExportStockReceivingService::class)->receive([
            'box_number' => '98120002', 'part_number' => 'LEGACY', 'receive_token' => (string) Str::uuid(),
        ], auth()->user(), false);
        $this->post(route('export.delivery-plans.store'), $this->plan(['items' => [['part_number' => 'LEGACY', 'pcs_quantity' => 20]]]))->assertSessionHasNoErrors();
        $plan = ExportDeliveryPlan::sole();
        $beforeBox = $box->getRawOriginal();
        $beforePart = $part->fresh()->getRawOriginal();
        $migration = require database_path('migrations/2026_11_12_000001_add_part_document_numbers.php');
        $migration->down();
        $beforeLine = \Illuminate\Support\Facades\DB::table('export_delivery_plan_items')->where('export_delivery_plan_id', $plan->id)->sole();
        $migration->up();
        $this->assertSame($beforeBox, $box->fresh()->getRawOriginal());
        $this->assertSame($beforePart, $part->fresh()->getRawOriginal());
        $afterLine = (array) \Illuminate\Support\Facades\DB::table('export_delivery_plan_items')->where('export_delivery_plan_id', $plan->id)->sole();
        $this->assertNull($afterLine['document_part_number']);
        unset($afterLine['document_part_number']);
        $this->assertEquals((array) $beforeLine, $afterLine);
        $this->assertDatabaseCount('part_document_numbers', 0);
        $this->get(route('export.delivery-plans.show', $plan))->assertOk()->assertSee('LEGACY');
    }
}
