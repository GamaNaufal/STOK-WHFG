<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiReviewPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_every_role_and_preserves_supervisor_detail_links(): void
    {
        foreach (['warehouse_operator', 'sales', 'ppc', 'admin_warehouse', 'admin', 'supervisi'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk();
            if (in_array($role, ['supervisi', 'admin'], true)) {
                $response->assertViewIs('operator.reports.operational');
                foreach (['#fulfillment-report', '#current-handling', '#active-pallets', '#scan-issues'] as $target) {
                    $response->assertSee('href="'.$target.'"', false);
                }
                $response->assertSee('Fulfillment Rate (Order Full)');
                $this->get(route('dashboard', ['fulfillment_filter' => 'partial']))
                    ->assertOk()->assertSee('Fulfillment Rate (Order Partial)');
            } else {
                $response->assertViewIs('shared.dashboard')->assertSee('Akses cepat');
            }
        }
    }

    public function test_module_pages_render(): void
    {
        $user = User::factory()->create(['name' => 'Admin Preview', 'email' => 'preview@example.test', 'role' => 'admin']);
        $this->actingAs($user);
        foreach (['pending', 'approved'] as $status) {
            $order = DeliveryOrder::create(['sales_user_id' => $user->id, 'customer_name' => 'Customer Contoh untuk Pemeriksaan Tampilan', 'delivery_date' => now()->addDay()->toDateString(), 'status' => $status]);
            DeliveryOrderItem::create(['delivery_order_id' => $order->id, 'part_number' => 'PART-CONTOH-001', 'quantity' => 1200]);
        }
        $routes = ['dashboard', 'users.index', 'users.create', 'locations.index', 'part-settings.index', 'part-settings.create', 'stock-input.index', 'stock-view.index', 'expired-box.index', 'merge-pallet.index', 'move-pallet.index', 'box-not-full.create', 'box-not-full.approvals', 'delivery.index', 'delivery.create', 'delivery.approvals', 'delivery-assign.index', 'delivery.pick.verification', 'delivery.pick.issues', 'delivery.pick.locks', 'reports.stock-input', 'reports.withdrawal', 'audit.index', 'access.index', 'profile.edit'];
        foreach ($routes as $route) {
            $response = $this->get(route($route));
            $response->assertOk();
        }
        $this->post(route('logout'));
        $response = $this->get(route('login'));
        $response->assertOk();
    }
}
