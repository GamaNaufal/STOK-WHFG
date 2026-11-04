<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Box, ExpiredBoxHandling, ExportStockBox, PartSetting, StockOpnameSession, User};
use App\Services\StockOpnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function local(string $number, array $extra = []): Box
    {
        return Box::create($extra + ['box_number' => $number, 'part_number' => 'OP-A', 'pcs_quantity' => 80,
            'qr_code' => $number, 'user_id' => $this->admin->id]);
    }

    private function export(string $number): ExportStockBox
    {
        $part = PartSetting::firstOrCreate(['part_number' => 'OP-A'], ['qty_box' => 80]);
        return ExportStockBox::create(['box_number' => $number, 'part_setting_id' => $part->id,
            'part_number' => 'OP-A', 'pcs_quantity' => 80, 'state' => 'received',
            'receive_token' => (string) Str::uuid(), 'first_received_at' => now(), 'received_by' => $this->admin->id]);
    }

    private function start(string $scope = 'domestic'): StockOpnameSession
    {
        $base = $scope === 'export' ? 'export.stock-opname' : 'stock-opname';
        $this->post(route($base.'.start'), ['request_token' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        return StockOpnameSession::where('active_scope', $scope)->sole();
    }

    private function scan(StockOpnameSession $session, string $number, ?string $token = null)
    {
        $base = $session->scope === 'export' ? 'export.stock-opname' : 'stock-opname';
        return $this->postJson(route($base.'.scan', $session), ['box_number' => $number, 'request_token' => $token ?? (string) Str::uuid()]);
    }

    public function test_scopes_have_separate_single_active_sessions_and_start_is_idempotent(): void
    {
        $this->local('00000001');
        $this->export('00000002');
        $local = $this->start();
        $this->assertSame($local->id, $this->start()->id);
        $export = $this->start('export');
        $this->assertSame(2, StockOpnameSession::where('status', 'active')->count());
        $this->assertSame(['00000001'], $local->items()->pluck('box_number')->all());
        $this->assertSame(['00000002'], $export->items()->pluck('box_number')->all());
        $this->get(route('stock-opname.show', $local))->assertOk()->assertSee('data-opname-scan', false)->assertSee('audio/OK.mp3', false)->assertSee('audio/NG.mp3', false);
        $this->get(route('export.stock-opname.show', $local))->assertNotFound();
        $this->postJson(route('export.stock-opname.scan', $local), ['box_number' => '00000001', 'request_token' => (string) Str::uuid()])->assertNotFound();
        $this->scan($local, '00000002')->assertOk()->assertJsonPath('outcome', 'ng')->assertJsonPath('message', 'ID Box terdaftar pada lingkup Ekspor.');
        $this->scan($export, '00000001')->assertOk()->assertJsonPath('outcome', 'ng');
        $this->assertDatabaseCount('stock_opname_items', 2);
    }

    public function test_ok_duplicate_ng_invalid_and_retry_attempts_preserve_unique_counts(): void
    {
        $box = $this->local('00123456');
        $session = $this->start();
        $token = (string) Str::uuid();
        $this->scan($session, $box->box_number, $token)->assertOk()->assertJsonPath('outcome', 'ok')->assertJsonPath('summary.found', 1);
        $this->scan($session, $box->box_number, $token)->assertOk()->assertJsonPath('outcome', 'ok')->assertJsonPath('summary.scans', 1);
        $this->scan($session, $box->box_number)->assertOk()->assertJsonPath('outcome', 'duplicate')->assertJsonPath('summary.duplicates', 1);
        $this->scan($session, '123')->assertOk()->assertJsonPath('outcome', 'ng');
        $this->scan($session, '99999999')->assertOk()->assertJsonPath('outcome', 'ng')->assertJsonPath('summary.ng', 2)->assertJsonPath('summary.found', 1);
        $this->scan($session, '99999999', $token)->assertUnprocessable();
        $this->assertDatabaseCount('stock_opname_scans', 4);
        $this->assertDatabaseCount('boxes', 1);
        $this->assertSame(80, $box->fresh()->pcs_quantity);
        $this->getJson(route('stock-opname.show', ['opname' => $session, 'tab' => 'ng']))->assertOk()
            ->assertJsonPath('summary.ng', 2)->assertJsonStructure(['html']);
        $box->forceDelete();
        $this->scan($session, '00123456')->assertOk()->assertJsonPath('outcome', 'ng')
            ->assertJsonPath('summary.departed', 1)->assertJsonPath('summary.missing', 0);
    }

    public function test_live_movements_arrivals_and_archives_are_reconciled_and_history_is_immutable(): void
    {
        $first = $this->local('00000001');
        $out = $this->local('00000002');
        $missing = $this->local('00000003', ['expired_status' => 'expired']);
        $session = $this->start();
        $this->scan($session, $first->box_number)->assertOk();
        $out->update(['is_withdrawn' => true, 'withdrawn_at' => now()]);
        $this->scan($session, $out->box_number)->assertOk()->assertJsonPath('outcome', 'ng');
        $first->update(['pcs_quantity' => 20]);
        $this->local('00000004');
        $incoming = $this->local('00000005');
        $this->scan($session, $incoming->box_number)->assertOk()->assertJsonPath('outcome', 'ok');
        $this->local('00000006')->delete();
        $this->post(route('stock-opname.finish', $session), ['confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $session->refresh();
        foreach (['baseline' => 3, 'present' => 4, 'found' => 2, 'missing' => 2, 'arrivals' => 3, 'departed' => 2, 'ng' => 1] as $key => $value) {
            $this->assertSame($value, $session->summary[$key], $key);
        }
        $this->assertSame('completed', $session->status);
        $this->assertNull($session->active_scope);
        $this->assertSame(80, $session->items()->where('box_number', $first->box_number)->sole()->initial_snapshot['pcs_quantity']);
        $this->assertSame(20, $session->items()->where('box_number', $first->box_number)->sole()->current_snapshot['pcs_quantity']);
        $summary = $session->summary;
        $first->forceDelete();
        $missing->update(['is_withdrawn' => true]);
        $this->local('00000007');
        $this->get(route('stock-opname.show', $session))->assertOk()->assertViewHas('summary', $summary)
            ->assertSee('00000003')->assertSee('00000004')->assertDontSee('00000007');
        $this->scan($session, '00000007')->assertUnprocessable();
        $this->post(route('stock-opname.finish', $session), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame($summary, $session->fresh()->summary);
        $this->assertSame(1, AuditLog::where('type', 'stock_opname')->where('action', 'completed')->count());
        $this->get(route('stock-opname.index'))->assertOk()->assertSee($session->code);
        $this->assertNotSame($session->id, $this->start()->id);
    }

    public function test_expired_and_active_qa_boxes_are_included_but_retired_handled_sources_are_not(): void
    {
        $expired = $this->local('00000001', ['expired_status' => 'expired']);
        $qa = $this->local('00000002', ['expired_status' => 'handled']);
        $old = $this->local('00000003', ['expired_status' => 'handled']);
        ExpiredBoxHandling::create(['source_box_id' => $qa->id, 'source_box_number' => $qa->box_number,
            'part_number' => $qa->part_number, 'pcs_quantity' => 80, 'original_stored_at' => now()->subYear(),
            'status' => 'at_qa', 'source_snapshot' => $qa->toArray()]);
        $session = $this->start();
        $this->assertSame(2, $session->items()->count());
        $this->scan($session, $expired->box_number)->assertOk()->assertJsonPath('outcome', 'ok');
        $this->scan($session, $qa->box_number)->assertOk()->assertJsonPath('outcome', 'ok');
        $this->scan($session, $old->box_number)->assertOk()->assertJsonPath('outcome', 'ng');
    }

    public function test_export_received_and_stored_boxes_are_checked_and_shipping_preserves_the_scan_history(): void
    {
        $received = $this->export('00000001');
        $stored = $this->export('00000002');
        $location = \App\Models\ExportMasterLocation::create(['code' => 'OP-EX-A1', 'is_occupied' => true]);
        $pallet = \App\Models\ExportStockPallet::create(['master_location_id' => $location->id,
            'initial_location_id' => $location->id, 'location_code' => $location->code, 'created_by' => $this->admin->id]);
        $stored->update(['state' => 'stored', 'current_pallet_id' => $pallet->id, 'stored_at' => now(), 'stored_by' => $this->admin->id]);
        $session = $this->start('export');
        $this->scan($session, $stored->box_number)->assertOk()->assertJsonPath('outcome', 'ok')
            ->assertJsonPath('snapshot.location', 'OP-EX-A1');
        $this->scan($session, $received->box_number)->assertOk()->assertJsonPath('outcome', 'ok');
        $stored->delete();
        $this->post(route('export.stock-opname.finish', $session), ['confirmed' => 1])->assertSessionHasNoErrors();
        $summary = $session->fresh()->summary;
        $this->assertSame(2, $summary['baseline']);
        $this->assertSame(1, $summary['found']);
        $this->assertSame(1, $summary['departed']);
        $this->assertSame(0, $summary['missing']);
        $this->get(route('export.stock-opname.show', ['opname' => $session, 'tab' => 'scans']))->assertOk()
            ->assertSee('00000002')->assertSee('OP-EX-A1');
        $this->get(route('stock-opname.index'))->assertOk()->assertDontSee($session->code);
    }

    public function test_permissions_allow_operators_to_scan_only_their_scope_and_supervisors_to_read_only(): void
    {
        $local = $this->start();
        $export = $this->start('export');
        foreach (['warehouse_operator' => ['stock-opname', $local, 'export.stock-opname'],
            'export_operator' => ['export.stock-opname', $export, 'stock-opname']] as $role => [$base, $session, $other]) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route($base.'.index'))->assertOk();
            $this->get(route($other.'.index'))->assertForbidden();
            $this->scan($session, '123')->assertOk()->assertJsonPath('outcome', 'ng');
        }
        $this->actingAs(User::factory()->create(['role' => 'supervisi']));
        foreach (['stock-opname' => $local, 'export.stock-opname' => $export] as $base => $session) {
            $this->get(route($base.'.show', $session))->assertOk()->assertDontSee('data-opname-scan', false);
            $this->post(route($base.'.start'), ['request_token' => (string) Str::uuid()])->assertForbidden();
            $this->post(route($base.'.finish', $session), ['confirmed' => 1])->assertForbidden();
            $this->scan($session, '123')->assertForbidden();
        }
    }

    public function test_shared_session_tracks_each_operator_and_rejects_invalid_filters_before_recording(): void
    {
        $this->local('00000001');
        $session = $this->start();
        $this->scan($session, '00000001')->assertOk();
        $actor = User::factory()->create(['role' => 'warehouse_operator']);
        $this->actingAs($actor);
        $this->assertSame($session->id, $this->start()->id);
        $this->scan($session, '00000001')->assertOk()->assertJsonPath('outcome', 'duplicate');
        $this->postJson(route('stock-opname.scan', $session), ['box_number' => '00000001',
            'request_token' => (string) Str::uuid(), 'tab' => 'invalid'])->assertUnprocessable();
        $this->assertSame(2, $session->scans()->count());
        $this->assertSame($actor->id, $session->scans()->latest('id')->first()->scanned_by);
        $this->assertSame($this->admin->id, $session->items()->sole()->first_scanned_by);
        $this->post(route('stock-opname.finish', $session))->assertSessionHasErrors('confirmed');
    }

    public function test_failed_completion_audit_rolls_back_results_and_keeps_the_session_active(): void
    {
        $this->local('00000001');
        $session = $this->start();
        $this->local('00000002');
        AuditLog::creating(function ($log) {
            if ($log->type === 'stock_opname' && $log->action === 'completed') throw new \RuntimeException('Audit unavailable');
        });
        try {
            app(StockOpnameService::class)->finish($session->id, $this->admin);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Audit unavailable', $error->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->assertSame('active', $session->fresh()->status);
        $this->assertSame('domestic', $session->fresh()->active_scope);
        $this->assertSame(1, $session->items()->count());
    }
}
