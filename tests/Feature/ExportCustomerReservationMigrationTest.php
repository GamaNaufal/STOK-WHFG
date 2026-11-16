<?php

namespace Tests\Feature;

use App\Models\{ExportMasterLocation, ExportReceivingBatch, ExportReceivingBatchItem, PartSetting, User};
use App\Services\{ExportDeliveryPlanService, ExportPalletPlanningService, ExportStockReceivingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportCustomerReservationMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_additive_migration_preserves_existing_stock_commitments_drafts_and_history(): void
    {
        $actor=User::factory()->create(['role'=>'admin']); $this->actingAs($actor);
        $part=PartSetting::create(['part_number'=>'LEGACY-CUSTOMER','qty_box'=>10]);
        $delivery=app(ExportDeliveryPlanService::class)->save(['plan_code'=>'CUSTOMER-LEGACY','customer_name'=>'Legacy customer text','delivery_deadline'=>'2026-12-20','source_type'=>'manual',
            'items'=>[['part_number'=>$part->part_number,'pcs_quantity'=>10]]],$actor);
        $location=ExportMasterLocation::create(['code'=>'CUSTOMER-LEGACY']);
        app(ExportPalletPlanningService::class)->save($delivery->id,['revision'=>0,'delivery_revision'=>1,'action'=>'finalize',
            'pallets'=>[['master_location_id'=>$location->id,'items'=>[['part_setting_id'=>$part->id,'box_quantity'=>1]]]]],$actor);
        app(ExportStockReceivingService::class)->receive(['box_number'=>'99000001','part_number'=>$part->part_number,'receive_token'=>(string)Str::uuid()],$actor);
        $batch=ExportReceivingBatch::create(['token'=>(string)Str::uuid(),'created_by'=>$actor->id]);
        ExportReceivingBatchItem::create(['export_receiving_batch_id'=>$batch->id,'box_number'=>'99000002','part_setting_id'=>$part->id,'part_number'=>$part->part_number,'pcs_quantity'=>10,'position'=>1,'receive_token'=>(string)Str::uuid()]);
        $tables=['export_stock_boxes','export_fulfillment_boxes','export_delivery_plans','export_delivery_plan_items','export_receiving_batches','export_receiving_batch_items','audit_logs','box_identities'];
        $hashes=[]; foreach($tables as $table) $hashes[$table]=hash('sha256',json_encode(DB::table($table)->orderBy('id')->get()));
        $migration=require database_path('migrations/2026_11_16_000001_create_export_customer_stock_reservations.php');
        $migration->down(); $migration->up();
        foreach($tables as $table) $this->assertSame($hashes[$table],hash('sha256',json_encode(DB::table($table)->orderBy('id')->get())),$table);
        foreach(['export_customers','export_box_customer_reservations','export_delivery_customer_links','export_receiving_item_customers','export_customer_stock_events'] as $table) $this->assertDatabaseCount($table,0);
    }

    public function test_rollback_refuses_to_discard_customer_data(): void
    {
        \App\Models\ExportCustomer::create(['code'=>'PERSIST','name'=>'Persistent customer']);
        $migration=require database_path('migrations/2026_11_16_000001_create_export_customer_stock_reservations.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }
}
