<?php

namespace Tests\Feature;

use App\Exports\AuditTrailExport;
use App\Models\AuditLog;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\PartSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalAuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_location_and_part_mutations_have_attributed_audit_entries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post(route('locations.store'), ['code' => 'AUD-01'])->assertRedirect();
        $location = MasterLocation::where('code', 'AUD-01')->sole();
        $this->put(route('locations.update', $location), ['code' => 'AUD-02'])->assertRedirect();
        $this->delete(route('locations.destroy', $location))->assertRedirect();
        $this->assertSame(['created', 'updated', 'deleted'], AuditLog::where('type', 'master_location')
            ->where('model_id', $location->id)->orderBy('id')->pluck('action')->all());

        $this->post(route('part-settings.store'), ['part_number' => 'AUD-PART', 'qty_box' => 20])->assertRedirect();
        $part = PartSetting::where('part_number', 'AUD-PART')->sole();
        $this->put(route('part-settings.update', $part), ['part_number' => 'AUD-PART', 'qty_box' => 25])->assertRedirect();
        $this->delete(route('part-settings.destroy', $part))->assertRedirect();
        $this->assertSame(['created', 'updated', 'deleted'], AuditLog::where('type', 'master_part')
            ->where('model_id', $part->id)->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseHas('audit_logs', ['type' => 'master_part', 'action' => 'updated', 'user_id' => $admin->id]);
        $this->get(route('audit.index', ['type' => 'master_part']))->assertOk()->assertSee('AUD-PART');
        $exportRows = (new AuditTrailExport(AuditLog::where('type', 'master_part')->with('user')->orderBy('id')->get()))->collection();
        $this->assertSame('Master Part', $exportRows->first()['Tipe Aksi']);
        $this->assertSame('Dibuat', $exportRows->first()['Status']);
    }

    public function test_user_and_profile_audits_exclude_passwords(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post(route('users.store'), [
            'name' => 'Audit Operator', 'email' => 'audit-operator@example.test',
            'role' => 'warehouse_operator', 'password' => 'InitialSecret123',
            'password_confirmation' => 'InitialSecret123',
        ])->assertRedirect();
        $operator = User::where('email', 'audit-operator@example.test')->sole();
        $this->put(route('users.update', $operator), [
            'name' => 'Audit Operator Baru', 'email' => 'audit-operator@example.test',
            'role' => 'warehouse_operator', 'password' => 'ChangedSecret123',
            'password_confirmation' => 'ChangedSecret123',
        ])->assertRedirect();
        $this->delete(route('users.destroy', $operator))->assertRedirect();

        $logs = AuditLog::where('type', 'user_management')->where('model_id', $operator->id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deactivated'], $logs->pluck('action')->all());
        $this->assertTrue($logs->every(fn ($log) => (int) $log->user_id === $admin->id));
        $this->assertTrue($logs[1]->getNewValuesArray()['password_changed']);
        $snapshots = $logs->pluck('old_values')->join(' ').' '.$logs->pluck('new_values')->join(' ');
        $this->assertStringNotContainsString('InitialSecret123', $snapshots);
        $this->assertStringNotContainsString('ChangedSecret123', $snapshots);

        $this->put(route('profile.update'), ['name' => 'Admin Audit', 'email' => 'admin-audit@example.test'])
            ->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['type' => 'profile', 'action' => 'updated',
            'model_id' => $admin->id, 'user_id' => $admin->id]);
    }

    public function test_admin_lock_termination_records_reason_without_repeating(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = DeliveryOrder::create(['sales_user_id' => $admin->id, 'customer_name' => 'Audit Lock',
            'delivery_date' => now()->addDay(), 'status' => 'approved']);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id,
            'created_by' => $admin->id, 'status' => 'scanning', 'started_at' => now()]);

        $this->actingAs($admin)->post(route('delivery.pick.lock.terminate', $session->id),
            ['reason' => 'Perangkat scanner rusak'])->assertSessionHas('success');
        $log = AuditLog::where('type', 'delivery_pick')->where('action', 'terminated')->sole();
        $this->assertSame('Perangkat scanner rusak', $log->getNewValuesArray()['reason']);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->post(route('delivery.pick.lock.terminate', $session->id),
            ['reason' => 'Klik ulang'])->assertSessionHas('error');
        $this->assertSame(1, AuditLog::where('type', 'delivery_pick')->where('action', 'terminated')->count());
    }
}
