<?php

namespace Tests\Feature;

use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AuthorizationAndReportExportSanityTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_authorization_for_core_pages(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $supervisi = User::factory()->create(['role' => 'supervisi']);

        $this->actingAs($sales)->get(route('stock-input.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('stock-input.index'))->assertOk();

        $this->actingAs($sales)->get(route('merge-pallet.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('merge-pallet.index'))->assertOk();

        $this->actingAs($operator)->get(route('box-not-full.approvals'))->assertForbidden();
        $this->actingAs($supervisi)->get(route('box-not-full.approvals'))->assertOk();

        $this->actingAs($operator)->get(route('reports.stock-input'))->assertForbidden();
        $this->actingAs($supervisi)->get(route('reports.stock-input'))->assertOk();
    }

    public function test_export_endpoints_sanity_for_authorized_roles(): void
    {
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $operator = User::factory()->create(['role' => 'warehouse_operator']);
        $sales = User::factory()->create(['role' => 'sales']);

        $pallet = Pallet::create([
            'pallet_number' => 'PLT-900',
        ]);

        $palletItem = PalletItem::create([
            'pallet_id' => $pallet->id,
            'part_number' => 'P-EXP-01',
            'box_quantity' => 1,
            'pcs_quantity' => 100,
        ]);

        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => 'Z1',
            'stored_at' => now(),
        ]);

        StockInput::create([
            'pallet_id' => $pallet->id,
            'pallet_item_id' => $palletItem->id,
            'user_id' => $operator->id,
            'warehouse_location' => 'Z1',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'stored_at' => now(),
            'part_numbers' => ['P-EXP-01'],
        ]);

        StockWithdrawal::create([
            'user_id' => $operator->id,
            'pallet_item_id' => $palletItem->id,
            'part_number' => 'P-EXP-01',
            'pcs_quantity' => 100,
            'box_quantity' => 1,
            'warehouse_location' => 'Z1',
            'status' => 'completed',
            'withdrawn_at' => now(),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-02-14 10:11:12'));

        $withdrawalExport = $this->actingAs($supervisi)->get(route('reports.withdrawal.export'));
        $withdrawalExport->assertOk();
        $this->assertStringContainsString(
            'stock_withdrawal_report_20260214_101112.xlsx',
            (string) $withdrawalExport->headers->get('content-disposition')
        );

        $stockInputExport = $this->actingAs($supervisi)->get(route('reports.stock-input.export'));
        $stockInputExport->assertOk();
        $this->assertStringContainsString(
            'stock_input_report_20260214_101112.xlsx',
            (string) $stockInputExport->headers->get('content-disposition')
        );

        // NOTE: Operational export uses DB-specific SQL functions (e.g. HOUR())
        // that are not available in sqlite test DB, so full execution is covered
        // in integration/manual environment. Here we keep route-level auth sanity.
        $this->actingAs($sales)->get(route('reports.operational.export'))->assertForbidden();

        $stockByPartExport = $this->actingAs($operator)->get(route('stock-view.export-part'));
        $stockByPartExport->assertOk();
        $this->assertStringContainsString(
            'stock_by_part_20260214_101112.xlsx',
            (string) $stockByPartExport->headers->get('content-disposition')
        );

        $stockByPalletExport = $this->actingAs($operator)->get(route('stock-view.export-pallet'));
        $stockByPalletExport->assertOk();
        $this->assertStringContainsString(
            'stock_by_pallet_20260214_101112.xlsx',
            (string) $stockByPalletExport->headers->get('content-disposition')
        );

        $this->actingAs($sales)->get(route('reports.stock-input.export'))->assertForbidden();
        $this->actingAs($sales)->get(route('stock-view.export-part'))->assertForbidden();

        Carbon::setTestNow();
    }

    public function test_route_role_matrix_for_issue_approval_and_redo_endpoints(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $admin = User::factory()->create(['role' => 'admin']);

        $order = DeliveryOrder::create([
            'sales_user_id' => $sales->id,
            'customer_name' => 'Role Matrix Cust',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $adminWarehouse->id,
            'status' => 'blocked',
            'started_at' => now(),
            'redo_until' => now()->addDays(2),
            'completion_status' => 'completed',
        ]);

        $issue = DeliveryIssue::create([
            'pick_session_id' => $session->id,
            'scanned_code' => 'BOX-MATRIX-1',
            'issue_type' => 'scan_mismatch',
            'status' => 'pending',
        ]);

        $this->actingAs($sales)
            ->post(route('delivery.pick.issue.approve', $issue->id), ['notes' => 'x'])
            ->assertForbidden();

        $this->actingAs($adminWarehouse)
            ->post(route('delivery.pick.issue.approve', $issue->id), ['notes' => 'ok'])
            ->assertRedirect();

        $this->actingAs($sales)
            ->post(route('delivery.pick.redo', $session->id))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('delivery.pick.redo', $session->id))
            ->assertRedirect();
    }
}
