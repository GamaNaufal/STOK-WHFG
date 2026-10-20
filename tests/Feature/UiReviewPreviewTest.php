<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryIssue;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UiReviewPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_lock_management_shows_active_session_and_preserves_admin_only_terminate_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'warehouse_operator', 'name' => 'Operator Lock']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $admin->id,
            'customer_name' => 'Customer Lock',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'blocked',
            'started_at' => now(),
        ]);
        DeliveryIssue::create([
            'pick_session_id' => $session->id,
            'scanned_code' => 'wrong-box',
            'issue_type' => 'scan_mismatch',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('delivery.pick.locks'))
            ->assertOk()
            ->assertSee('Sesi #'.$session->id)
            ->assertSee('Customer Lock')
            ->assertSee('Operator Lock')
            ->assertSee('1</strong> issue pending', false)
            ->assertSee('name="reason"', false)
            ->assertSee(route('delivery.pick.lock.terminate', $session->id));

        $this->actingAs($operator)->get(route('delivery.pick.locks'))->assertForbidden();
    }

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
                $response->assertSee('class="dashboard-stock dashboard-issues" id="scan-issues"', false);
                $response->assertSee('class="dashboard-stock dashboard-processing" id="processing-time"', false);
                $this->assertSame(1, substr_count($response->getContent(), 'id="scan-issues"'));
                $response->assertSee('Fulfillment Rate (Order Full)');
                $this->get(route('dashboard', ['fulfillment_filter' => 'partial']))
                    ->assertOk()->assertSee('Fulfillment Rate (Order Partial)');
            } else {
                $response->assertViewIs('shared.dashboard')->assertSee('Akses cepat');
            }
        }
    }

    public function test_dashboard_processing_table_keeps_zero_duration_visible_and_marks_missing_scan_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operator = User::factory()->create(['role' => 'warehouse_operator', 'name' => 'Operator Picking A']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $admin->id,
            'customer_name' => 'Customer Processing',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);
        $startedAt = now()->startOfMinute();
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $operator->id,
            'status' => 'completed',
            'started_at' => $startedAt,
            'completed_at' => $startedAt,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sesi #'.$session->id)
            ->assertSee('Nama customer')
            ->assertSee('Customer Processing')
            ->assertSee('Operator sesi')
            ->assertSee('Operator Picking A')
            ->assertSee('dashboard-processing-duration">00:00', false)
            ->assertSee('Data belum tersedia');
    }

    public function test_dashboard_processing_table_formats_fractional_minutes_without_column_spill(): void
    {
        $html = view('shared.partials.dashboard-processing-table', [
            'processingReport' => collect([[
                'session_id' => 14,
                'order_id' => 2,
                'customer_name' => 'Customer Processing',
                'operator_name' => 'Operator Picking A',
                'started_at' => '20 Oct 2026 23:15',
                'completed_at' => '20 Oct 2026 23:16',
                'duration_min' => 17 / 60,
                'scan_duration_min' => 95 / 60,
            ]]),
        ])->render();

        $this->assertStringContainsString('00:17', $html);
        $this->assertStringContainsString('01:35', $html);
        $this->assertStringNotContainsString('0.283333', $html);
    }

    public function test_dashboard_throughput_table_compares_daily_flows_and_identifies_quiet_days(): void
    {
        $html = view('shared.partials.dashboard-throughput-table', [
            'throughputDays' => [
                ['date' => '19 Oct 2026', 'inbound_pcs' => 1500, 'outbound_pcs' => 500],
                ['date' => '20 Oct 2026', 'inbound_pcs' => 0, 'outbound_pcs' => 0],
            ],
        ])->render();

        $this->assertStringContainsString('2 hari ditampilkan · 1 beraktivitas', $html);
        $this->assertStringContainsString('1,500', $html);
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('width: 33.3%', $html);
        $this->assertStringContainsString('Tidak ada pergerakan', $html);

        $zeroHtml = view('shared.partials.dashboard-throughput-table', [
            'throughputDays' => [['date' => '20 Oct 2026', 'inbound_pcs' => 0, 'outbound_pcs' => 0]],
        ])->render();
        $this->assertStringContainsString('1 hari ditampilkan · 0 beraktivitas', $zeroHtml);
        $this->assertStringContainsString('width: 0%', $zeroHtml);
    }

    public function test_dashboard_scan_issues_table_shows_readable_reason_status_and_full_notes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $admin->id,
            'customer_name' => 'Customer Issue',
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'processing',
        ]);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $admin->id,
            'status' => 'blocked',
            'started_at' => now(),
        ]);
        $longCode = str_repeat('X', 80);
        DeliveryIssue::create([
            'pick_session_id' => $session->id,
            'scanned_code' => $longCode,
            'issue_type' => 'scan_mismatch',
            'status' => 'pending',
        ]);
        DeliveryIssue::create([
            'pick_session_id' => $session->id,
            'scanned_code' => 'BOX-WITHDRAWN',
            'issue_type' => 'box_withdrawn',
            'status' => 'approved',
            'notes' => 'Box dikeluarkan dari daftar picking dan operator diminta scan ulang.',
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($longCode)
            ->assertSee('Box tidak sesuai')
            ->assertSee('scan_mismatch')
            ->assertSee('Menunggu')
            ->assertSee('Belum ada catatan')
            ->assertSee('Box sudah withdrawn')
            ->assertSee('Disetujui')
            ->assertSee('Box dikeluarkan dari daftar picking dan operator diminta scan ulang.');
    }

    public function test_dashboard_audit_filters_show_applied_state_and_keep_report_context_on_reset(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Audit']);
        AuditLog::create([
            'type' => 'stock_input',
            'action' => 'created',
            'description' => 'Stok diterima',
            'user_id' => $admin->id,
        ]);

        $query = [
            'period' => 'month',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'group_by' => 'week',
            'fulfillment_filter' => 'partial',
            'audit_type' => 'stock_input',
            'audit_action' => 'created',
            'audit_user' => $admin->id,
            'page' => 2,
        ];
        $response = $this->actingAs($admin)->get(route('dashboard', $query));

        $response->assertOk()
            ->assertSee('3 filter aktif')
            ->assertSee('Jenis: Input Stok')
            ->assertSee('Aksi: created')
            ->assertSee('Petugas: Admin Audit');
        foreach (['period', 'start_date', 'end_date', 'group_by', 'fulfillment_filter'] as $key) {
            $response->assertSee('name="'.$key.'" value="'.$query[$key].'"', false);
        }

        $this->assertSame(1, preg_match('/href="([^"]+)"[^>]*id="audit-filter-reset"/', $response->getContent(), $match));
        $resetUrl = html_entity_decode($match[1]);
        parse_str(parse_url($resetUrl, PHP_URL_QUERY), $resetQuery);
        $this->assertSame(array_intersect_key($query, array_flip(['period', 'start_date', 'end_date', 'group_by', 'fulfillment_filter'])), $resetQuery);
    }

    public function test_master_location_pages_show_the_occupied_guard_and_keep_invalid_code_input(): void
    {
        $admin = User::factory()->create(['role' => 'admin_warehouse']);
        $pallet = Pallet::create(['pallet_number' => 'PLT-LOKASI-01']);
        $location = MasterLocation::create([
            'code' => 'A-1-1',
            'is_occupied' => true,
            'current_pallet_id' => $pallet->id,
        ]);

        $index = $this->actingAs($admin)->get(route('locations.index'));
        $index->assertOk()
            ->assertSee('Tambah lokasi')
            ->assertSee('PLT-LOKASI-01')
            ->assertSee('Pindahkan pallet sebelum mengubah atau menghapus.');

        $edit = $this->get(route('locations.edit', $location));
        $edit->assertOk()
            ->assertSee('Lokasi sedang terisi')
            ->assertSee('PLT-LOKASI-01');
        $this->assertMatchesRegularExpression('/<input[^>]*id="code"[^>]*readonly/s', $edit->getContent());
        $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*disabled/s', $edit->getContent());

        $available = MasterLocation::create(['code' => 'B-1-1', 'is_occupied' => false]);
        $availableEdit = $this->get(route('locations.edit', $available));
        $availableEdit->assertOk()->assertSee('Simpan perubahan');
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="code"[^>]*readonly/s', $availableEdit->getContent());

        $this->post(route('locations.store'), ['code' => 'A-1-1'])->assertSessionHasErrors('code');
        $this->get(route('locations.index'))
            ->assertOk()
            ->assertSee('name="code" value="A-1-1"', false);
    }

    public function test_master_part_pages_keep_capacity_units_pagination_and_invalid_input(): void
    {
        $admin = User::factory()->create(['role' => 'admin_warehouse']);
        foreach (range(1, 11) as $number) {
            PartSetting::create(['part_number' => sprintf('PART-%02d', $number), 'qty_box' => 100]);
        }

        $this->actingAs($admin)->get(route('part-settings.index'))
            ->assertOk()
            ->assertSee('Menampilkan 1–10 dari 11 nomor part')
            ->assertSee('PCS / box')
            ->assertDontSee('PART-11');
        $this->get(route('part-settings.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('PART-11');
        $this->get(route('part-settings.search', ['q' => 'PART-']))
            ->assertOk()
            ->assertJsonCount(11, 'data');
        $this->get(route('part-settings.edit', PartSetting::where('part_number', 'PART-01')->sole()))
            ->assertOk()
            ->assertSee('PCS standar per box')
            ->assertSee('value="100"', false);

        $this->post(route('part-settings.store'), ['part_number' => 'PART-01', 'qty_box' => 100])
            ->assertSessionHasErrors('part_number');
        $this->get(route('part-settings.create'))
            ->assertOk()
            ->assertSee('value="PART-01"', false);
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
