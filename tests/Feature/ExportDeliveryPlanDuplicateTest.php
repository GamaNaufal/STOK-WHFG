<?php

namespace Tests\Feature;

use App\Models\{AuditLog, ExportDeliveryPlan, ExportPartSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportDeliveryPlanDuplicateTest extends TestCase
{
    use RefreshDatabase;
    private User $operator;
    private ExportPartSetting $a;
    private ExportPartSetting $b;

    protected function setUp(): void
    {
        parent::setUp();$this->withoutVite();
        $this->operator=User::factory()->create(['role'=>'export_operator']);$this->actingAs($this->operator);
        $this->a=ExportPartSetting::create(['part_number'=>'DUP-A','qty_box'=>10]);
        $this->b=ExportPartSetting::create(['part_number'=>'DUP-B','qty_box'=>20]);
    }

    private function data(array $changes=[]): array
    {
        return array_replace(['request_token'=>(string)Str::uuid(),'plan_code'=>'RD-DUP-01','customer_name'=>'Customer Export',
            'delivery_deadline'=>'2026-11-20','source_type'=>'manual','items'=>[
                ['part_number'=>$this->a->part_number,'pcs_quantity'=>100],['part_number'=>$this->b->part_number,'pcs_quantity'=>200],
            ]],$changes);
    }

    /** Legacy rows intentionally have no token or precomputed fingerprint. */
    private function seedPlan(array $changes=[]): ExportDeliveryPlan
    {
        $plan=ExportDeliveryPlan::create(array_replace(['plan_code'=>'RD-DUP-01','customer_name'=>'Customer Export',
            'delivery_deadline'=>'2026-11-20','status'=>'draft','revision'=>1,'source_type'=>'manual',
            'total_pcs'=>300,'total_boxes'=>20,'created_by'=>$this->operator->id,'updated_by'=>$this->operator->id],$changes));
        foreach([[$this->a,100],[$this->b,200]] as $i=>[$part,$pcs])$plan->items()->create([
            'position'=>$i+1,'part_setting_id'=>$part->id,'part_number'=>$part->part_number,
            'pcs_per_box'=>$part->qty_box,'pcs_quantity'=>$pcs,'box_quantity'=>intdiv($pcs,$part->qty_box),
        ]);
        return $plan;
    }

    private function review(array $data, ?string $url=null): array
    {
        return $this->postJson($url??route('export.delivery-plans.store'),$data)->assertUnprocessable()
            ->assertJsonValidationErrors('duplicate')->json('duplicate_review');
    }

    private function approved(array $data,array $review): array
    {
        return $data+['duplicate_review_token'=>$review['token'],'duplicate_confirmed'=>'1','duplicate_reason'=>'Permintaan tambahan customer yang memang identik.'];
    }

    public function test_existing_legacy_plan_warns_without_creating_another_request_or_inventory(): void
    {
        $existing=$this->seedPlan();$data=$this->data();$review=$this->review($data);
        $this->assertSame([$existing->id],array_column($review['matches'],'id'));
        $this->assertDatabaseCount('export_delivery_plans',1);$this->assertDatabaseCount('export_delivery_plan_requests',0);
        $this->assertDatabaseCount('audit_logs',0);$this->assertDatabaseCount('export_stock_boxes',0);$this->assertDatabaseCount('export_pallet_plans',0);
    }

    public function test_comparison_ignores_case_whitespace_row_order_and_file_name(): void
    {
        $this->seedPlan();$data=$this->data(['plan_code'=>" rd- dup-01 ",'customer_name'=>"CUSTOMER\t  EXPORT ",
            'source_type'=>'excel','source_name'=>'a different filename.xlsx','reviewed'=>'1']);
        $data['items']=array_reverse($data['items']);$review=$this->review($data);
        $this->assertCount(1,$review['matches']);$this->assertSame('Aktif',$review['matches'][0]['status']);
    }

    public function test_html_warning_preserves_input_and_offers_required_reason_and_review(): void
    {
        $this->seedPlan();$create=route('export.delivery-plans.create');$data=$this->data();
        $this->from($create)->post(route('export.delivery-plans.store'),$data)->assertRedirect($create)
            ->assertSessionHasErrors('duplicate')->assertSessionHas('delivery_duplicate_review');
        $this->get($create)->assertOk()->assertSee('Rencana identik sudah tercatat')->assertSee('Alasan membuat rencana identik')
            ->assertSee('plan-duplicate-confirmed')->assertSee($data['request_token']);
        $this->assertDatabaseCount('export_delivery_plans',1);
    }

    public function test_confirmed_duplicate_requires_reason_is_audited_and_retry_does_not_make_a_third_plan(): void
    {
        $existing=$this->seedPlan();$data=$this->data();$review=$this->review($data);$accepted=$this->approved($data,$review);
        $this->post(route('export.delivery-plans.store'),$accepted)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('export.delivery-plans.store'),$accepted)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('export_delivery_plans',2);$this->assertDatabaseCount('export_delivery_plan_requests',1);
        $log=AuditLog::where('type','export_delivery_plan')->sole();$values=$log->getNewValuesArray();
        $this->assertSame($accepted['duplicate_reason'],$values['duplicate_reason']);$this->assertSame((string)$existing->id,$values['duplicate_plan_ids']);
        $presentation=\App\Support\AuditLogPresenter::present($log);
        $this->assertTrue(collect($presentation['facts'])->contains('value',$accepted['duplicate_reason']));
    }

    public function test_reason_or_checkbox_without_current_signed_review_cannot_bypass_warning(): void
    {
        $this->seedPlan();$data=$this->data();$review=$this->review($data);
        foreach([
            ['duplicate_reason'=>'Reason','duplicate_confirmed'=>'1'],
            ['duplicate_review_token'=>str_repeat('0',64),'duplicate_confirmed'=>'1','duplicate_reason'=>'Reason'],
            ['duplicate_review_token'=>$review['token'],'duplicate_confirmed'=>'1','duplicate_reason'=>'   '],
            ['duplicate_review_token'=>$review['token'],'duplicate_reason'=>'Reason'],
        ] as $confirmation)$this->review($data+$confirmation);
        $this->assertDatabaseCount('export_delivery_plans',1);$this->assertDatabaseCount('export_delivery_plan_requests',0);
    }

    public function test_active_and_completed_plans_are_compared_but_cancellations_and_archived_drafts_are_ignored(): void
    {
        $active=$this->seedPlan();$completed=$this->seedPlan(['status'=>'completed']);$completed->delete();
        $cancelled=$this->seedPlan(['status'=>'cancelled']);$cancelled->delete();$this->seedPlan(['status'=>'cancelled']);
        $archived=$this->seedPlan();$archived->delete();$review=$this->review($this->data());
        $this->assertSame([$active->id,$completed->id],array_column($review['matches'],'id'));
        $this->assertSame('Selesai dikirim',$review['matches'][1]['status']);$this->assertTrue($review['matches'][1]['archived']);
    }

    public function test_cancelled_and_archived_draft_alone_do_not_require_override(): void
    {
        $cancelled=$this->seedPlan(['status'=>'cancelled']);$cancelled->delete();$archived=$this->seedPlan();$archived->delete();
        $this->post(route('export.delivery-plans.store'),$this->data())->assertSessionHasNoErrors();
        $this->assertSame(1,ExportDeliveryPlan::count());
    }

    public function test_different_customer_code_deadline_parts_or_quantities_are_not_duplicates(): void
    {
        $this->seedPlan();$c=ExportPartSetting::create(['part_number'=>'DUP-C','qty_box'=>20]);
        foreach([
            ['customer_name'=>'Another customer'],['plan_code'=>'OTHER-CODE'],['delivery_deadline'=>'2026-11-21'],
            ['items'=>[['part_number'=>$this->a->part_number,'pcs_quantity'=>120],['part_number'=>$this->b->part_number,'pcs_quantity'=>180]]],
            ['items'=>[['part_number'=>$this->a->part_number,'pcs_quantity'=>100],['part_number'=>$c->part_number,'pcs_quantity'=>200]]],
        ] as $changes)$this->post(route('export.delivery-plans.store'),$this->data($changes))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('export_delivery_plans',6);
    }

    public function test_blank_codes_are_compared_and_not_treated_as_globally_unique(): void
    {
        $this->seedPlan(['plan_code'=>null]);$review=$this->review($this->data(['plan_code'=>'']));$this->assertCount(1,$review['matches']);
        $this->post(route('export.delivery-plans.store'),$this->data(['plan_code'=>'','customer_name'=>'Different customer']))->assertSessionHasNoErrors();
    }

    public function test_new_matching_plan_after_review_requires_reviewing_the_latest_matches_again(): void
    {
        $this->seedPlan();$data=$this->data();$review=$this->review($data);$this->seedPlan();
        $latest=$this->review($this->approved($data,$review));$this->assertCount(2,$latest['matches']);$this->assertNotSame($review['token'],$latest['token']);
        $this->post(route('export.delivery-plans.store'),$this->approved($data,$latest))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('export_delivery_plans',3);
    }

    public function test_duplicate_review_is_bound_to_actor_form_and_contents(): void
    {
        $this->seedPlan();$data=$this->data();$review=$this->review($data);$approved=$this->approved($data,$review);
        $fresh=array_replace($approved,['request_token'=>(string)Str::uuid()]);$newReview=$this->review($fresh);$this->assertNotSame($review['token'],$newReview['token']);
        $this->actingAs(User::factory()->create(['role'=>'export_operator']));$otherReview=$this->review($approved);$this->assertNotSame($review['token'],$otherReview['token']);
        $this->assertDatabaseCount('export_delivery_plans',1);
    }

    public function test_unchanged_update_excludes_itself_and_edit_into_another_plan_is_checked(): void
    {
        $original=$this->seedPlan();$this->put(route('export.delivery-plans.update',$original),$this->data(['revision'=>1]))->assertSessionHasNoErrors();
        $other=$this->seedPlan(['plan_code'=>'OTHER-CODE']);$data=$this->data(['revision'=>1]);$url=route('export.delivery-plans.update',$other);
        $review=$this->putJson($url,$data)->assertUnprocessable()->assertJsonValidationErrors('duplicate')->json('duplicate_review');
        $this->assertSame([$original->id],array_column($review['matches'],'id'));
        $this->put($url,$this->approved($data,$review))->assertSessionHasNoErrors();
        $this->assertSame(2,$other->fresh()->revision);$this->assertDatabaseCount('export_delivery_plans',2);
    }
}
