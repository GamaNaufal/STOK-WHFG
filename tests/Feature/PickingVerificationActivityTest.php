<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\User;
use App\Services\PickingVerificationActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickingVerificationActivityTest extends TestCase
{
    use RefreshDatabase;

    private function picking(): array
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create(['role' => 'warehouse_operator']);
        $order = DeliveryOrder::create([
            'sales_user_id' => $user->id, 'customer_name' => 'Idle verification test',
            'delivery_date' => now()->addDay()->toDateString(), 'status' => 'approved',
        ]);
        DeliveryOrderItem::create([
            'delivery_order_id' => $order->id, 'part_number' => 'PART-IDLE',
            'quantity' => 10, 'fulfilled_quantity' => 0,
        ]);
        $box = Box::create([
            'box_number' => '12345678', 'part_number' => 'PART-IDLE', 'part_name' => 'Test',
            'pcs_quantity' => 10, 'qty_box' => 1, 'qr_code' => '12345678', 'user_id' => $user->id,
        ]);
        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id, 'created_by' => $user->id,
            'status' => 'scanning', 'started_at' => now(),
            'verification_last_activity_at' => now(), 'verification_box_ids' => [$box->id],
        ]);
        DeliveryPickItem::create([
            'pick_session_id' => $session->id, 'box_id' => $box->id,
            'part_number' => $box->part_number, 'pcs_quantity' => 10, 'status' => 'pending',
        ]);

        return [$user, $order, $session, $box];
    }

    public function test_manual_cancel_from_verification_releases_locks_and_rejects_late_scans(): void
    {
        [$user, $order, $session, $box] = $this->picking();
        $this->actingAs($user)->get(route('delivery.pick.verify', [$order->id, $session->id]))
            ->assertOk()->assertSee('Batalkan Verifikasi');
        $this->postJson(route('delivery.pick.cancel', $session->id))->assertOk()->assertJson(['success' => true]);
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertNull($session->fresh()->verification_box_ids);
        $this->assertNull($session->fresh()->verification_last_activity_at);
        $this->assertDatabaseMissing('delivery_pick_items', ['pick_session_id' => $session->id]);
        foreach (['delivery.pick.verify.scan', 'delivery.pick.scan.submit'] as $route) {
            $this->postJson(route($route, $session->id), ['box_number' => $box->box_number])->assertStatus(409);
        }
        $this->postJson(route('delivery.pick.complete', $session->id))->assertStatus(409);
        $this->assertFalse((bool) $box->fresh()->is_withdrawn);
    }

    public function test_scheduler_expires_at_ten_minutes_without_browser_and_preserves_stock(): void
    {
        [, , $session, $box] = $this->picking();
        $this->travel(599)->seconds();
        $this->artisan('delivery:expire-verifications')->assertSuccessful();
        $this->assertSame('scanning', $session->fresh()->status);
        $this->travel(1)->seconds();
        $this->artisan('delivery:expire-verifications')->assertSuccessful();
        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertStringContainsString('10 menit', $session->fresh()->approval_notes);
        $this->assertDatabaseMissing('delivery_pick_items', ['pick_session_id' => $session->id]);
        $this->assertFalse((bool) $box->fresh()->is_withdrawn);
        $this->assertDatabaseCount('stock_withdrawals', 0);
    }

    public function test_only_user_activity_extends_deadline_and_polling_does_not(): void
    {
        [$user, , $session] = $this->picking();
        $original = $session->verification_last_activity_at->toDateTimeString();
        $this->travel(9)->minutes();
        $this->actingAs($user)->postJson(route('delivery.pick.verify.activity', $session->id), ['active' => false])
            ->assertOk()->assertJson(['expires_in' => 60]);
        $this->assertSame($original, $session->fresh()->verification_last_activity_at->toDateTimeString());
        $this->postJson(route('delivery.pick.verify.activity', $session->id), ['active' => true])
            ->assertOk()->assertJson(['expires_in' => 600]);
        $this->travel(9)->minutes();
        $this->assertSame(0, app(PickingVerificationActivityService::class)->expireDue());
        $this->travel(1)->minutes();
        $this->postJson(route('delivery.pick.verify.activity', $session->id), ['active' => true])
            ->assertStatus(409)->assertJson(['success' => false]);
        $this->assertSame('cancelled', $session->fresh()->status);
    }

    public function test_other_operator_cannot_keep_alive_or_cancel_session(): void
    {
        [, , $session] = $this->picking();
        $other = User::factory()->create(['role' => 'warehouse_operator']);
        $this->actingAs($other)->postJson(route('delivery.pick.verify.activity', $session->id), ['active' => true])->assertForbidden();
        $this->postJson(route('delivery.pick.cancel', $session->id))->assertForbidden();
        $this->assertSame('scanning', $session->fresh()->status);
    }

    public function test_expired_scan_cannot_revive_session_even_before_scheduler_runs(): void
    {
        [$user, , $session, $box] = $this->picking();
        $this->travel(10)->minutes();
        $this->actingAs($user)->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $box->box_number])
            ->assertStatus(409)->assertJson(['message' => PickingVerificationActivityService::EXPIRED_MESSAGE]);
        $this->assertSame('cancelled', $session->fresh()->status);
    }

    public function test_duplicate_verification_scan_counts_as_activity(): void
    {
        [$user, , $session, $box] = $this->picking();
        $this->travel(9)->minutes();
        $this->actingAs($user)->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $box->box_number])->assertOk();
        $this->assertTrue($session->fresh()->verification_last_activity_at->equalTo(now()));
    }

    public function test_wrong_verification_box_blocks_scan_until_admin_approves_issue(): void
    {
        [$user, , $session, $expectedBox] = $this->picking();
        $session->update(['verification_box_ids' => []]);
        $wrongBox = Box::create([
            'box_number' => '87654321', 'part_number' => 'PART-IDLE', 'part_name' => 'Other',
            'pcs_quantity' => 10, 'qty_box' => 1, 'qr_code' => '87654321', 'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $wrongBox->box_number])
            ->assertStatus(422)
            ->assertJson(['success' => false, 'session_status' => 'blocked']);
        $this->assertSame('blocked', $session->fresh()->status);
        $this->assertSame([], $session->fresh()->verification_box_ids);
        $this->assertDatabaseHas('delivery_issues', [
            'pick_session_id' => $session->id, 'box_id' => $wrongBox->id,
            'issue_type' => 'scan_mismatch', 'status' => 'pending',
        ]);
        $this->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $expectedBox->box_number])
            ->assertStatus(423)
            ->assertJson(['session_status' => 'blocked']);
        foreach (['delivery.pick.verify', 'delivery.pick.scan'] as $route) {
            $response = $this->get(route($route, [$session->delivery_order_id, $session->id]))->assertOk();
            $response->assertSee('Sesi diblokir');
            $this->assertMatchesRegularExpression('/id="scanInput"[^>]*disabled/', $response->getContent());
        }

        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);
        $issue = DeliveryIssue::where('pick_session_id', $session->id)->where('status', 'pending')->firstOrFail();
        $this->actingAs($adminWarehouse)
            ->post(route('delivery.pick.issue.approve', $issue->id), ['notes' => 'Lanjutkan verifikasi'])
            ->assertRedirect();
        $this->assertSame('scanning', $session->fresh()->status);
        $this->actingAs($user)
            ->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $expectedBox->box_number])
            ->assertOk()
            ->assertJson(['success' => true, 'remaining' => 0]);

        $this->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => '99999999'])
            ->assertStatus(422)
            ->assertJson(['session_status' => 'blocked']);
        $this->assertDatabaseHas('delivery_issues', [
            'pick_session_id' => $session->id, 'box_id' => null,
            'scanned_code' => '99999999', 'issue_type' => 'scan_mismatch', 'status' => 'pending',
        ]);
    }

    public function test_every_wrong_scan_submission_blocks_both_verification_and_final_sessions(): void
    {
        [$operator, , $session, $expectedBox] = $this->picking();
        $admin = User::factory()->create(['role' => 'admin_warehouse']);
        $attempts = [
            [['box_number' => '1'], '1'],
            [['box_number' => 'abc'], 'abc'],
            [['box_number' => ''], '(kosong)'],
            [[], '(kosong)'],
            [['box_number' => str_repeat('9', 300)], str_repeat('9', 255)],
        ];

        foreach (['delivery.pick.verify.scan', 'delivery.pick.scan.submit'] as $scanRoute) {
            foreach ($attempts as [$payload, $storedCode]) {
                $this->actingAs($operator)
                    ->postJson(route($scanRoute, $session->id), $payload)
                    ->assertStatus(422)
                    ->assertJson(['success' => false, 'session_status' => 'blocked']);

                $this->assertSame('blocked', $session->fresh()->status);
                $issue = DeliveryIssue::where('pick_session_id', $session->id)
                    ->where('status', 'pending')->sole();
                $this->assertSame('scan_mismatch', $issue->issue_type);
                $this->assertSame($storedCode, $issue->scanned_code);
                $this->assertNull($issue->box_id);

                $this->postJson(route($scanRoute, $session->id), ['box_number' => $expectedBox->box_number])
                    ->assertStatus(423)->assertJson(['session_status' => 'blocked']);

                $this->actingAs($admin)
                    ->post(route('delivery.pick.issue.approve', $issue->id), ['notes' => 'Lanjutkan scan'])
                    ->assertRedirect();
                $this->assertSame('scanning', $session->fresh()->status);
            }
        }
    }

    public function test_withdrawn_verification_box_creates_issue_and_blocks_session(): void
    {
        [$user, , $session, $box] = $this->picking();
        $box->update(['is_withdrawn' => true]);

        $this->actingAs($user)
            ->postJson(route('delivery.pick.verify.scan', $session->id), ['box_number' => $box->box_number])
            ->assertStatus(422)
            ->assertJson(['session_status' => 'blocked']);
        $this->assertSame('blocked', $session->fresh()->status);
        $this->assertDatabaseHas('delivery_issues', [
            'pick_session_id' => $session->id, 'box_id' => $box->id,
            'issue_type' => 'box_withdrawn', 'status' => 'pending',
        ]);
    }

    public function test_final_scan_disables_timer_and_old_tab_cannot_reenable_it(): void
    {
        [$user, $order, $session] = $this->picking();
        $this->actingAs($user)->get(route('delivery.pick.scan', [$order->id, $session->id]))->assertOk();
        $this->assertNull($session->fresh()->verification_last_activity_at);
        $this->postJson(route('delivery.pick.verify.activity', $session->id), ['active' => true])
            ->assertOk()->assertJson(['expires_in' => null]);
        $this->travel(11)->minutes();
        $this->assertSame(0, app(PickingVerificationActivityService::class)->expireDue());
        $this->assertSame('scanning', $session->fresh()->status);
    }

    public function test_blocked_session_requires_admin_and_does_not_expire(): void
    {
        [$user, , $session, $box] = $this->picking();
        $session->update(['status' => 'blocked']);
        DeliveryIssue::create([
            'pick_session_id' => $session->id, 'box_id' => $box->id,
            'issue_type' => 'scan_mismatch', 'status' => 'pending', 'scanned_code' => $box->box_number,
        ]);
        $this->travel(11)->minutes();
        $this->assertSame(0, app(PickingVerificationActivityService::class)->expireDue());
        $this->actingAs($user)->postJson(route('delivery.pick.cancel', $session->id))->assertStatus(423);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson(route('delivery.pick.cancel', $session->id))->assertOk();
        $this->assertSame('cancelled', $session->fresh()->status);
    }

    public function test_start_verification_enables_timeout_on_reused_session(): void
    {
        [$user, $order, $session] = $this->picking();
        $session->update(['verification_last_activity_at' => null]);
        $this->actingAs($user)->postJson(route('delivery.pick.start-verification', $order->id))->assertOk();
        $this->assertNotNull($session->fresh()->verification_last_activity_at);
    }
}
