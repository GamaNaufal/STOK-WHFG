<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportDeliveryPlan, ExportMasterLocation, ExportPartSetting, RoleFeatureAccess, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportPlanPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;
    private ExportPartSetting $a;
    private ExportPartSetting $b;

    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite();
        $this->operator = User::factory()->create(['role' => 'export_operator']); $this->actingAs($this->operator);
        $this->a = ExportPartSetting::create(['part_number' => 'PRINT-A','qty_box' => 20]);
        $this->b = ExportPartSetting::create(['part_number' => 'PRINT-B','qty_box' => 50]);
    }

    private function delivery()
    {
        return app(ExportDeliveryPlanService::class)->save(['plan_code' => 'RD-PRINT-001'.(ExportDeliveryPlan::withTrashed()->count() ? '-'.(ExportDeliveryPlan::withTrashed()->count()+1) : ''),'customer_name' => 'Customer Print & Export',
            'delivery_deadline' => '2026-11-20','source_type' => 'manual','items' => [
                ['part_number' => $this->a->part_number,'pcs_quantity' => 200], ['part_number' => $this->b->part_number,'pcs_quantity' => 200],
            ]],$this->operator);
    }

    private function planning($delivery, string $action = 'finalize')
    {
        $rows = [];
        foreach ([[6,4],[4,0]] as $i => [$a,$b]) {
            $items = [['part_setting_id' => $this->a->id,'box_quantity' => $a]];
            if ($b) $items[] = ['part_setting_id' => $this->b->id,'box_quantity' => $b];
            $rows[] = ['master_location_id' => ExportMasterLocation::create(['code' => 'PRINT-'.$delivery->id.'-'.$i])->id,'items' => $items];
        }
        return app(ExportPalletPlanningService::class)->save($delivery->id,['revision' => 0,'delivery_revision' => $delivery->revision,
            'action' => $action,'pallets' => $rows],$this->operator);
    }

    public function test_final_delivery_and_pallet_prints_preserve_snapshot_and_never_mutate_inventory(): void
    {
        $delivery = $this->delivery(); $planning = $this->planning($delivery); $audits = AuditLog::count();
        $this->a->update(['qty_box' => 999]);
        $this->get(route('export.delivery-plans.show',$delivery))->assertOk()->assertSee(route('export.delivery-plans.print',$delivery));
        $this->get(route('export.pallet-plans.show',$delivery))->assertOk()->assertSee('Cetak semua pallet')->assertSee('Cetak pallet');
        $this->get(route('export.delivery-plans.print',$delivery))->assertOk()->assertSee('PERENCANAAN FINAL')
            ->assertSee('RD-PRINT-001')->assertSee('Customer Print &amp; Export',false)->assertDontSee('999')->assertSee('Total box:')
            ->assertViewHas('delivery',fn ($d) => $d->total_boxes === 14 && $d->items[0]->pcs_per_box === 20);
        $response = $this->get(route('export.pallet-plans.print',$delivery))->assertOk()
            ->assertSeeInOrder(['Pallet 1','Pallet 2'])->assertSee('Nomor part')->assertSee('Jumlah box')
            ->assertDontSee('<form',false)->assertViewHas('pallets',fn ($p) => $p->count() === 2 && $p[0]->items->count() === 2);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private',$response->headers->get('Cache-Control'));
        $this->assertSame($audits,AuditLog::count());
        $this->assertDatabaseCount('export_stock_boxes',0); $this->assertDatabaseCount('export_stock_movements',0);
        $this->assertSame(2,$planning->reservations()->count());
    }

    public function test_single_pallet_print_is_scoped_to_this_delivery(): void
    {
        $delivery = $this->delivery(); $planning = $this->planning($delivery); $pallet = $planning->pallets()->firstOrFail();
        $this->get(route('export.pallet-plans.print-pallet',['deliveryPlan' => $delivery->id,'pallet' => $pallet->id]))->assertOk()
            ->assertSee('Pallet 1')->assertDontSee('Pallet 2')->assertViewHas('pallets',fn ($p) => $p->count() === 1 && $p[0]->id === $pallet->id);
        $other = $this->delivery(); $otherPlan = $this->planning($other);
        $this->get(route('export.pallet-plans.print-pallet',['deliveryPlan' => $other->id,'pallet' => $pallet->id]))->assertNotFound();
        $this->get(route('export.pallet-plans.print-pallet',['deliveryPlan' => $delivery->id,'pallet' => 'bad']))->assertNotFound();
    }

    public function test_print_requires_current_final_planning_and_links_disappear_after_reopening(): void
    {
        $delivery = $this->delivery();
        $urls = [route('export.delivery-plans.print',$delivery),route('export.pallet-plans.print',$delivery)];
        foreach ($urls as $url) $this->get($url)->assertStatus(409);
        $this->get(route('export.delivery-plans.show',$delivery))->assertDontSee('href="'.$urls[0].'"',false);
        $planning = $this->planning($delivery,'draft');
        foreach ($urls as $url) $this->get($url)->assertStatus(409);
        $planning->update(['status' => 'final','delivery_revision' => 999]);
        foreach ($urls as $url) $this->get($url)->assertStatus(409);
        $planning->update(['delivery_revision' => $delivery->revision]);
        foreach ($urls as $url) $this->get($url)->assertOk();
        app(ExportPalletPlanningService::class)->transition($delivery->id,$planning->revision,$this->operator);
        foreach ($urls as $url) $this->get($url)->assertStatus(409);
        $this->get(route('export.pallet-plans.show',$delivery))->assertDontSee('href="'.$urls[1].'"',false);
        $planning->refresh()->update(['status' => 'cancelled']);
        foreach ($urls as $url) $this->get($url)->assertStatus(409);
        $delivery->delete();
        foreach ($urls as $url) $this->get($url)->assertNotFound();
    }

    public function test_print_routes_keep_delivery_and_pallet_permissions_independent(): void
    {
        $delivery = $this->delivery(); $planning = $this->planning($delivery); $pallet = $planning->pallets()->firstOrFail();
        $palletUrl = route('export.pallet-plans.print-pallet',['deliveryPlan' => $delivery->id,'pallet' => $pallet->id]);
        $access = RoleFeatureAccess::create(['role' => 'export_operator','permissions' => ['export_pallet_plans']]);
        $this->get(route('export.pallet-plans.print',$delivery))->assertOk(); $this->get($palletUrl)->assertOk();
        $this->get(route('export.delivery-plans.print',$delivery))->assertForbidden();
        $this->get(route('export.pallet-plans.show',$delivery))->assertDontSee('href="'.route('export.delivery-plans.print',$delivery).'"',false);
        $access->update(['permissions' => ['export_delivery_plans']]);
        $this->get(route('export.delivery-plans.print',$delivery))->assertOk();
        $this->get(route('export.pallet-plans.print',$delivery))->assertForbidden(); $this->get($palletUrl)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'warehouse_operator']));
        $this->get(route('export.delivery-plans.print',$delivery))->assertForbidden(); $this->get($palletUrl)->assertForbidden();
    }
}
