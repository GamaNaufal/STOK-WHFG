<?php

namespace Tests\Feature;

use App\Models\ExportMasterLocation;
use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\PartSetting;
use App\Models\User;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportPalletPlanningService;
use App\Services\ExportStockAdminService;
use App\Services\ExportStockReceivingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportStockAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private PartSetting $part;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']);
        $this->part = PartSetting::create(['part_number' => 'STOK-ADMIN-A', 'qty_box' => 10]);
    }

    private function box(string $number, array $destination = []): ExportStockBox
    {
        $service = app(ExportStockReceivingService::class);
        $box = $service->receive(['box_number' => $number, 'part_number' => $this->part->part_number,
            'receive_token' => (string) Str::uuid()], $this->operator, false, now()->subDays(2));

        return $destination ? $service->place($box->id, $destination + [
            'revision' => $box->revision, 'request_token' => (string) Str::uuid(),
        ], $this->operator) : $box;
    }

    private function fingerprint(ExportStockPallet $pallet): string
    {
        return app(ExportStockAdminService::class)->inspectPallet($pallet->fresh())['fingerprint'];
    }

    public function test_stock_management_is_available_only_to_the_three_designated_roles(): void
    {
        $location = ExportMasterLocation::create(['code' => 'ADM-A1']);
        $box = $this->box('00490001', ['location_id' => $location->id]);
        $pallet = $box->currentPallet;

        foreach (['admin', 'supervisi', 'admin_warehouse'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('export.stock.index'))->assertOk()->assertSee('Riwayat box');
            $this->assertStringContainsString('Kelola box', $this->getJson(route('export.stock.show', $box))->json('html'));
            $this->assertStringContainsString('Kelola pallet', $this->getJson(route('export.stock.pallet-boxes', $pallet))->json('html'));
            $this->get(route('export.stock.box-manage', $box))->assertOk()->assertSee('Edit data box');
            $this->get(route('export.stock.pallet-manage', $pallet))->assertOk()->assertSee('Edit batas maksimal box');
            $this->get(route('export.stock.box-history', ['box_number' => $box->box_number]))->assertOk()->assertSee('Penempatan awal');
            if ($role !== 'admin') {
                $this->get(route('export.receiving.index'))->assertForbidden();
            }
        }

        $this->actingAs($this->operator)->get(route('export.stock.index'))->assertOk()->assertDontSee('Riwayat box');
        $this->get(route('export.stock.box-manage', $box))->assertForbidden();
        $this->get(route('export.stock.box-history'))->assertForbidden();
        $this->patchJson(route('export.stock.pallet-limit', $pallet), [])->assertForbidden();
        $this->deleteJson(route('export.stock.box-delete', $box), [])->assertForbidden();
    }

    public function test_box_correction_and_deletion_require_confirmation_and_preserve_the_full_history(): void
    {
        $location = ExportMasterLocation::create(['code' => 'ADM-B1']);
        $box = $this->box('00490002', ['location_id' => $location->id]);
        $actor = User::factory()->create(['role' => 'supervisi']);
        $this->actingAs($actor);
        $otherPart = PartSetting::create(['part_number' => 'STOK-ADMIN-B', 'qty_box' => 20]);
        $date = now()->subDays(5)->format('Y-m-d\TH:i');
        $edit = ['revision' => $box->revision, 'part_number' => $otherPart->part_number,
            'pcs_quantity' => 20, 'first_received_at' => $date, 'reason' => 'Koreksi label penerimaan'];
        $url = route('export.stock.box-update', $box);

        $this->patchJson($url, $edit)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->patchJson($url, ['pcs_quantity' => 19, 'confirmed' => 1] + $edit)->assertUnprocessable();
        $this->patchJson($url, ['box_number' => '00490099', 'confirmed' => 1] + $edit)
            ->assertUnprocessable()->assertJsonValidationErrors('box_number');
        $this->patch($url, $edit + ['confirmed' => 1])->assertRedirect(route('export.stock.box-manage', $box));
        $updated = $box->fresh();
        $this->assertSame($otherPart->id, $updated->part_setting_id);
        $this->assertSame(20, $updated->pcs_quantity);
        $this->assertSame(10, $updated->receipt_pcs_quantity);
        $this->assertSame($date, $updated->first_received_at->format('Y-m-d\TH:i'));
        $this->assertSame($box->box_number, $updated->box_number);
        $this->patchJson($url, $edit + ['confirmed' => 1])->assertUnprocessable()->assertJsonValidationErrors('stock');

        $url = route('export.stock.box-delete', $box);
        $delete = ['revision' => $updated->revision, 'reason' => 'Pencatatan stok keliru'];
        $this->deleteJson($url, $delete)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->assertNotSoftDeleted($box);
        $this->delete($url, $delete + ['confirmed' => 1])
            ->assertRedirect(route('export.stock.box-history', ['box_number' => $box->box_number]));
        $this->assertSoftDeleted($box);
        $this->assertNotNull($updated->currentPallet->fresh()->retired_at);
        $this->assertFalse($location->fresh()->is_occupied);
        $this->assertDatabaseHas('box_identities', ['box_number' => $box->box_number, 'origin_scope' => 'export']);
        $this->get(route('export.stock.box-history', ['box_number' => $box->box_number]))->assertOk()
            ->assertSee('Diarsipkan')->assertSee('Penempatan awal')->assertSee('ADM-B1')
            ->assertSee('STOK-ADMIN-A')->assertSee('Koreksi label penerimaan')->assertSee('Pencatatan stok keliru')->assertSee($actor->name);
    }

    public function test_pallet_limit_and_atomic_delete_check_current_contents_before_using_confirmation(): void
    {
        $location = ExportMasterLocation::create(['code' => 'ADM-C1']);
        $first = $this->box('00490003', ['location_id' => $location->id]);
        $pallet = $first->currentPallet;
        $second = $this->box('00490004', ['pallet_id' => $pallet->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin_warehouse']));
        $fingerprint = $this->fingerprint($pallet);
        $data = ['fingerprint' => $fingerprint, 'reason' => 'Penyesuaian pallet'];
        $limit = route('export.stock.pallet-limit', $pallet);

        $this->patchJson($limit, ['box_limit' => 2] + $data)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->patchJson($limit, ['box_limit' => 1, 'confirmed' => 1] + $data)->assertUnprocessable();
        $this->patch($limit, ['box_limit' => 2, 'confirmed' => 1] + $data)->assertRedirect();
        $this->assertSame(2, $pallet->fresh()->box_limit);
        $this->patchJson($limit, ['box_limit' => 0, 'confirmed' => 1] + $data)->assertUnprocessable();
        $this->patch($limit, ['box_limit' => 0, 'confirmed' => 1, 'fingerprint' => $this->fingerprint($pallet)] + $data)->assertRedirect();
        $this->assertNull($pallet->fresh()->box_limit);

        $data['fingerprint'] = $this->fingerprint($pallet);
        $third = $this->box('00490005', ['pallet_id' => $pallet->id]);
        $delete = route('export.stock.pallet-delete', $pallet);
        $this->deleteJson($delete, ['confirmed' => 1] + $data)->assertUnprocessable();
        $this->assertSame(3, $pallet->boxes()->count());
        $data['fingerprint'] = $this->fingerprint($pallet);
        $this->deleteJson($delete, $data)->assertUnprocessable()->assertJsonValidationErrors('confirmed');
        $this->delete($delete, ['confirmed' => 1] + $data)->assertRedirect(route('export.stock.index', ['mode' => 'pallet']));
        foreach ([$first, $second, $third] as $box) {
            $this->assertSoftDeleted($box);
            $this->assertDatabaseHas('box_identities', ['box_number' => $box->box_number]);
            $this->assertDatabaseHas('audit_logs', ['type' => 'export_stock_admin', 'action' => 'deleted', 'model_id' => $box->id, 'model' => 'ExportStockBox']);
        }
        $this->assertNotNull($pallet->fresh()->retired_at);
        $this->assertFalse($location->fresh()->is_occupied);
    }

    public function test_reservation_blocks_correction_and_pallet_deletion_without_removing_other_boxes(): void
    {
        $location = ExportMasterLocation::create(['code' => 'ADM-D1']);
        $stock = $this->box('00490006', ['location_id' => $location->id]);
        $pallet = $stock->currentPallet;
        $other = $this->box('00490007', ['pallet_id' => $pallet->id]);
        $target = ExportMasterLocation::create(['code' => 'ADM-D2']);
        $delivery = app(ExportDeliveryPlanService::class)->save([
            'plan_code' => 'STOK-ADMIN-RES', 'customer_name' => 'Customer Test', 'delivery_deadline' => now()->addDay()->toDateString(),
            'source_type' => 'manual', 'items' => [['part_number' => $this->part->part_number, 'pcs_quantity' => 10]],
        ], $this->operator);
        app(ExportPalletPlanningService::class)->save($delivery->id, [
            'revision' => 0, 'delivery_revision' => 1, 'action' => 'finalize',
            'pallets' => [['master_location_id' => $target->id, 'items' => [['part_setting_id' => $this->part->id, 'box_quantity' => 1]]]],
        ], $this->operator);
        $this->assertNotNull($stock->fresh()->fulfillment);
        $this->assertNull($other->fresh()->fulfillment);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('export.stock.pallet-manage', $pallet))->assertOk()->assertSee('sudah direservasi');
        $this->deleteJson(route('export.stock.pallet-delete', $pallet), [
            'fingerprint' => $this->fingerprint($pallet), 'reason' => 'Verifikasi proteksi reservasi', 'confirmed' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('stock');
        $this->patchJson(route('export.stock.box-update', $stock), [
            'revision' => $stock->fresh()->revision, 'part_number' => $this->part->part_number, 'pcs_quantity' => 10,
            'first_received_at' => now()->subDays(3)->format('Y-m-d\TH:i'), 'reason' => 'Verifikasi proteksi reservasi', 'confirmed' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('stock');
        $this->assertNotSoftDeleted($stock);
        $this->assertNotSoftDeleted($other);
        $this->assertTrue($location->fresh()->is_occupied);
    }
}
