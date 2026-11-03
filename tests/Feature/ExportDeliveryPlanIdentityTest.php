<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportDeliveryPlan;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportDeliveryPlanIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'export_operator']));
        PartSetting::create(['part_number' => 'SHARED-A', 'qty_box' => 10]);
    }

    private function data(array $changes = []): array
    {
        return array_replace(['request_token' => (string) Str::uuid(), 'plan_code' => 'RD-01', 'customer_name' => 'Customer Export',
            'delivery_deadline' => '2026-11-20', 'source_type' => 'manual',
            'items' => [['part_number' => 'SHARED-A', 'pcs_quantity' => 100]]], $changes);
    }

    public function test_code_is_required_normalized_and_cannot_be_overridden_by_the_obsolete_duplicate_confirmation(): void
    {
        foreach ([null, '', '   '] as $code) {
            $this->postJson(route('export.delivery-plans.store'), $this->data(['plan_code' => $code]))
                ->assertUnprocessable()->assertJsonValidationErrors('plan_code');
        }
        $this->post(route('export.delivery-plans.store'), $this->data(['plan_code' => '  rd-01  ']))->assertSessionHasNoErrors();
        $this->assertSame('RD-01', ExportDeliveryPlan::sole()->plan_code);
        $this->postJson(route('export.delivery-plans.store'), $this->data(['plan_code' => 'rd-01', 'customer_name' => 'Different',
            'duplicate_confirmed' => '1', 'duplicate_reason' => 'Previously allowed override', 'duplicate_review_token' => str_repeat('a', 64)]))
            ->assertUnprocessable()->assertJsonValidationErrors('plan_code');
        $this->assertDatabaseCount('export_delivery_plans', 1);
        $this->assertDatabaseCount('export_delivery_plan_requests', 1);
    }

    public function test_code_remains_reserved_for_every_status_and_after_archival(): void
    {
        foreach (['draft', 'planned', 'completed', 'cancelled'] as $index => $status) {
            $code = 'HISTORY-'.$index;
            $this->post(route('export.delivery-plans.store'), $this->data(['plan_code' => $code]))->assertSessionHasNoErrors();
            $plan = ExportDeliveryPlan::where('plan_code', $code)->sole();
            $plan->update(['status' => $status]);
            if (in_array($status, ['draft', 'completed', 'cancelled'])) {
                $plan->delete();
            }
            $this->postJson(route('export.delivery-plans.store'), $this->data(['plan_code' => strtolower($code)]))
                ->assertUnprocessable()->assertJsonValidationErrors('plan_code');
        }
        $this->assertSame(4, ExportDeliveryPlan::withTrashed()->count());
    }

    public function test_edit_can_keep_own_code_but_cannot_claim_another_code_or_release_a_previous_code(): void
    {
        $this->post(route('export.delivery-plans.store'), $this->data())->assertSessionHasNoErrors();
        $first = ExportDeliveryPlan::sole();
        $this->post(route('export.delivery-plans.store'), $this->data(['plan_code' => 'RD-02']))->assertSessionHasNoErrors();
        $url = route('export.delivery-plans.update', $first);
        $this->put($url, $this->data(['plan_code' => ' rd-01 ', 'revision' => 1]))->assertSessionHasNoErrors();
        $this->putJson($url, $this->data(['plan_code' => 'RD-02', 'revision' => 2]))->assertUnprocessable()->assertJsonValidationErrors('plan_code');
        $this->put($url, $this->data(['plan_code' => 'RD-03', 'revision' => 2]))->assertSessionHasNoErrors();
        $this->postJson(route('export.delivery-plans.store'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('plan_code');
        $this->assertDatabaseHas('export_delivery_plan_codes', ['code' => 'RD-01', 'export_delivery_plan_id' => $first->id]);
        $this->assertSame(3, $first->fresh()->revision);
    }

    public function test_identical_contents_with_a_new_code_are_allowed_and_replay_is_idempotent(): void
    {
        $data = $this->data();
        $this->post(route('export.delivery-plans.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('export.delivery-plans.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('export.delivery-plans.store'), $this->data(['plan_code' => 'RD-02']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('export_delivery_plans', 2);
        $this->assertDatabaseCount('export_delivery_plan_requests', 2);
        $this->assertSame(2, AuditLog::where('type', 'export_delivery_plan')->count());
    }

    public function test_model_registration_and_database_index_protect_direct_writes(): void
    {
        $this->post(route('export.delivery-plans.store'), $this->data())->assertSessionHasNoErrors();
        $first = ExportDeliveryPlan::sole();
        $first->update(['plan_code' => 'RENAMED']);
        try {
            ExportDeliveryPlan::create(array_replace($first->getAttributes(), ['id' => null, 'plan_code' => ' rd-01 ']));
            $this->fail('Historical code must remain reserved.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('plan_code', $error->errors());
        }
        $this->assertDatabaseCount('export_delivery_plans', 1);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('export_delivery_plans')->insert(['plan_code' => ' renamed ', 'customer_name' => 'SQL',
            'delivery_deadline' => '2026-11-20', 'status' => 'draft', 'revision' => 1, 'source_type' => 'manual', 'total_pcs' => 0, 'total_boxes' => 0, 'created_by' => $first->created_by, 'updated_by' => $first->updated_by]);
    }
}
