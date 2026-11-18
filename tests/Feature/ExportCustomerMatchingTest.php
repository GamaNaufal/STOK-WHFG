<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ExportCustomer;
use App\Models\ExportDeliveryPlan;
use App\Models\PartSetting;
use App\Models\RoleFeatureAccess;
use App\Models\User;
use App\Services\ExportCustomerService;
use App\Services\ExportDeliveryPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExportCustomerMatchingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ExportCustomer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
        PartSetting::create(['part_number' => 'MATCH-PART', 'qty_box' => 10]);
        $this->customer = app(ExportCustomerService::class)->save(['code' => 'MATCH-A', 'name' => 'PT Customer Alpha',
            'aliases' => "ALPHA EXCEL\nALPHA DOCUMENT", 'is_active' => true], $this->admin);
    }

    private function payload(string $name): array
    {
        return ['request_token' => (string) Str::uuid(), 'plan_code' => 'MATCH-'.Str::uuid(),
            'customer_name' => $name, 'customer_id' => null, 'customer_reference_version' => 1,
            'customer_source_name' => '', 'delivery_deadline' => '2026-11-25', 'source_type' => 'manual',
            'items' => [['part_number' => 'MATCH-PART', 'pcs_quantity' => 10]]];
    }

    public function test_manual_name_code_and_excel_alias_resolve_when_hidden_customer_id_is_empty(): void
    {
        foreach (['PT Customer Alpha', 'ALPHA EXCEL', 'alpha   document', 'MATCH-A'] as $name) {
            $data = $this->payload($name);
            $this->postJson(route('export.delivery-plans.store'), $data)->assertRedirect();
            $plan = ExportDeliveryPlan::latest('id')->firstOrFail();
            $this->assertSame('PT Customer Alpha', $plan->customer_name);
            $this->assertSame($this->customer->id, $plan->customerLink->export_customer_id);
            $this->assertSame($name, $plan->customerLink->source_name);
        }
    }

    public function test_imported_alias_round_trip_saves_without_relying_on_client_id_assignment(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Customer', 'ALPHA EXCEL'], ['Tanggal', '2026-11-25'],
            ['Kode Rencana', 'MATCH-XLSX'], ['Nomor Part', 'Jumlah PCS'], ['MATCH-PART', 10]]);
        $path = tempnam(sys_get_temp_dir(), 'customer-match-');
        try {
            (new Xlsx($book))->save($path);
            $parsed = $this->postJson(route('export.delivery-plans.import-excel'), [
                'file' => new UploadedFile($path, 'customer-match.xlsx', null, null, true),
            ])->assertOk()->assertJsonPath('customer_match.status', 'matched')
                ->assertJsonPath('customer_match.customer_id', $this->customer->id)->json();
            $data = array_replace($this->payload($parsed['customer_name']), [
                'plan_code' => $parsed['plan_code'], 'source_type' => 'excel', 'reviewed' => 1,
                'items' => $parsed['items'], 'customer_source_name' => $parsed['customer_name'],
            ]);
            $this->postJson(route('export.delivery-plans.store'), $data)->assertRedirect();
            $plan = ExportDeliveryPlan::sole();
            $this->assertSame($this->customer->id, $plan->customerLink->export_customer_id);
            $this->assertSame('ALPHA EXCEL', $plan->customerLink->source_name);
        } finally {
            @unlink($path);
            $book->disconnectWorksheets();
        }
    }

    public function test_form_with_an_omitted_customer_id_still_resolves_the_exact_alias(): void
    {
        $data = $this->payload('ALPHA EXCEL');
        unset($data['customer_id']);
        $this->postJson(route('export.delivery-plans.store'), $data)->assertRedirect();
        $this->assertSame($this->customer->id, ExportDeliveryPlan::sole()->customerLink->export_customer_id);
    }

    public function test_stale_hidden_id_cannot_assign_a_different_known_customer_than_the_name(): void
    {
        $other = app(ExportCustomerService::class)->save(['code' => 'MATCH-B', 'name' => 'PT Customer Beta',
            'aliases' => 'BETA EXCEL', 'is_active' => true], $this->admin);
        $data = $this->payload('BETA EXCEL');
        $data['customer_id'] = $this->customer->id;
        $this->postJson(route('export.delivery-plans.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertDatabaseCount('export_delivery_plans', 0);
        $data['customer_id'] = $other->id;
        $this->postJson(route('export.delivery-plans.store'), $data)->assertRedirect();
        $this->assertSame($other->id, ExportDeliveryPlan::sole()->customerLink->export_customer_id);
    }

    public function test_delivery_operator_can_resolve_a_new_master_without_customer_management_permission(): void
    {
        RoleFeatureAccess::create(['role' => 'export_operator', 'permissions' => ['export_delivery_plans']]);
        $operator = User::factory()->create(['role' => 'export_operator']);
        $this->actingAs($operator)->getJson(route('export.customers.options', ['q' => 'ALPHA EXCEL']))
            ->assertOk()->assertJsonPath('status', 'matched')->assertJsonPath('customer_id', $this->customer->id);
        $this->get(route('export.customers.create'))->assertForbidden();
    }

    public function test_retry_keeps_original_result_after_the_master_alias_changes(): void
    {
        $data = $this->payload('ALPHA EXCEL');
        $service = app(ExportDeliveryPlanService::class);
        $plan = $service->save($data, $this->admin);
        app(ExportCustomerService::class)->save(['code' => 'MATCH-A', 'name' => 'Alpha renamed',
            'aliases' => 'NEW ALIAS', 'revision' => 1, 'is_active' => true], $this->admin, $this->customer->id);
        $audits = AuditLog::count();
        $this->assertSame($plan->id, $service->save($data, $this->admin)->id);
        $this->assertDatabaseCount('export_delivery_plans', 1);
        $this->assertSame($audits, AuditLog::count());
    }

    public function test_unknown_or_inactive_customer_cannot_be_silently_saved_as_general_stock(): void
    {
        $this->postJson(route('export.delivery-plans.store'), $this->payload('Unknown customer'))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->customer->update(['is_active' => false]);
        $this->postJson(route('export.delivery-plans.store'), $this->payload('ALPHA EXCEL'))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertDatabaseCount('export_delivery_plans', 0);
    }

    public function test_legacy_integration_does_not_gain_a_customer_identity_from_its_name(): void
    {
        $data = $this->payload('ALPHA EXCEL');
        unset($data['customer_reference_version'], $data['customer_id'], $data['customer_source_name']);
        $this->postJson(route('export.delivery-plans.store'), $data)->assertRedirect();
        $this->assertNull(ExportDeliveryPlan::sole()->customerLink);
    }

    public function test_edit_preserves_existing_customer_snapshot_if_master_is_renamed(): void
    {
        $data = $this->payload('PT Customer Alpha');
        $data['customer_id'] = $this->customer->id;
        $service = app(ExportDeliveryPlanService::class);
        $plan = $service->save($data, $this->admin);
        app(ExportCustomerService::class)->save(['code' => 'MATCH-A', 'name' => 'Alpha renamed',
            'aliases' => 'NEW ALIAS', 'revision' => 1, 'is_active' => false], $this->admin, $this->customer->id);
        $update = array_replace($data, ['request_token' => (string) Str::uuid(), 'customer_id' => null, 'revision' => 1]);
        $this->putJson(route('export.delivery-plans.update', $plan), $update)->assertRedirect();
        $this->assertSame('PT Customer Alpha', $plan->fresh()->customer_name);
        $this->assertSame($this->customer->id, $plan->fresh()->customerLink->export_customer_id);
    }
}
