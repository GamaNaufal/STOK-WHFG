<?php

declare(strict_types=1);

/** Exercise the imported rows with native services, then roll every action back. */
function uatRuntimeChecks(string $root, string $database): array
{
    foreach (['APP_ENV'=>'testing','DB_CONNECTION'=>'mysql','DB_DATABASE'=>$database,
        'SESSION_DRIVER'=>'array','CACHE_STORE'=>'array','MAIL_MAILER'=>'array'] as $k=>$v) {
        putenv("$k=$v"); $_ENV[$k]=$v; $_SERVER[$k]=$v;
    }
    $app=require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (!app()->environment('testing') || Illuminate\Support\Facades\DB::connection()->getDatabaseName()!==$database
        || !str_starts_with($database,'whfg_uat_verify_')) throw new RuntimeException('Unsafe runtime test database.');
    $db=Illuminate\Support\Facades\DB::connection();
    $check=static function (bool $ok,string $name): void { if (!$ok) throw new RuntimeException('Imported runtime check: '.$name); };
    $operator=App\Models\User::where('email','uat26.export_operator@whfg.test')->firstOrFail();
    $warehouse=App\Models\User::where('email','uat26.admin_warehouse@whfg.test')->firstOrFail();
    $supervisor=App\Models\User::where('email','uat26.supervisi@whfg.test')->firstOrFail();
    Illuminate\Support\Facades\Auth::login($operator);
    $results=[]; $db->beginTransaction();
    try {
        $svc=app(App\Services\ExportNotFullService::class);
        $d=App\Models\ExportDeliveryPlan::where('plan_code','UAT26-EX-NF-SPLIT')->firstOrFail();
        $r=App\Models\ExportNotFullRequest::where('export_delivery_plan_id',$d->id)->firstOrFail();
        $check($svc->source($r)?->box_number==='98002262','FIFO split source262');
        $svc->prepare($r->id,['revision'=>$r->revision,'mode'=>'split','box_number'=>'98011002','notes'=>'UAT runtime split.'],$warehouse);
        $svc->approve($r->id,['revision'=>$r->fresh()->revision,'physical_confirmed'=>1],$supervisor);
        $check(App\Models\ExportStockBox::where('box_number','98002262')->value('pcs_quantity')===60,'source remainder60');
        $check((int)App\Models\ExportStockBox::where('part_number','UAT26-E-NF-SPLIT')->sum('pcs_quantity')===240,'split PCS conservation');
        $results[]='Not Full split FIFO + PCS conservation';

        $mix=App\Models\ExportDeliveryPlan::where('plan_code','UAT26-EX-MIX')->firstOrFail();
        $loc=App\Models\ExportMasterLocation::where('code','UAT26-E19')->firstOrFail();
        $rows=App\Services\ExportBoxRequirements::catalog($mix)->map(fn ($r)=>['part_setting_id'=>$r['part_setting_id'],
            'pcs_per_box'=>$r['pcs_per_box'],'box_quantity'=>$r['required']])->values()->all();
        $plan=app(App\Services\ExportPalletPlanningService::class)->save($mix->id,['revision'=>0,'delivery_revision'=>$mix->revision,
            'action'=>'finalize','pallets'=>[['master_location_id'=>$loc->id,'items'=>$rows]]],$operator);
        $notice=App\Models\ExportStockAllocationNotice::where('export_pallet_plan_id',$plan->id)->latest('id')->firstOrFail();
        app(App\Services\ExportStockAllocationNoticeService::class)->acknowledge($mix->id,$notice->id,$plan->revision,$operator);
        $p=$plan->pallets->sole(); $picking=app(App\Services\ExportPickingService::class);
        $check($picking->rows($p->id)->where('requires_scan',true)->count()===3,'mixed source scan mandatory');
        $wrong=$picking->scan($p->id,['box_number'=>'98002104','request_token'=>(string)Illuminate\Support\Str::uuid()],$operator);
        $check($wrong['blocked']===true,'wrong scan blocks');
        $issue=App\Models\ExportPickingIssue::where('box_number','98002104')->firstOrFail();
        $issueSvc=app(App\Services\ExportPickingIssueService::class);
        $issueSvc->approve($issue->id,['revision'=>$issue->revision,'notes'=>'Kembalikan ke asal.'],$warehouse);
        $check($picking->block($operator,$p->id)!==null,'approval alone remains blocked');
        $issueSvc->confirmReturn($issue->id,['revision'=>$issue->fresh()->revision,'request_token'=>(string)Illuminate\Support\Str::uuid()],$operator);
        $check($picking->block($operator,$p->id)===null,'physical return unblocks');
        foreach (['98002101','98002102','98002103'] as $number) {
            $check($picking->scan($p->id,['box_number'=>$number,'request_token'=>(string)Illuminate\Support\Str::uuid()],$operator)['success'],'correct mixed scan');
            $b=App\Models\ExportStockBox::where('box_number',$number)->firstOrFail();
            app(App\Services\ExportStockReceivingService::class)->place($b->id,['revision'=>$b->revision,
                'request_token'=>(string)Illuminate\Support\Str::uuid()],$operator);
        }
        $completion=app(App\Services\ExportFulfillmentCompletionService::class);
        $completion->ready($mix->id,$p->id,['revision'=>$plan->fresh()->revision,'fulfillment_revision'=>$p->fresh()->fulfillment_revision,
            'location_id'=>$loc->id,'request_token'=>(string)Illuminate\Support\Str::uuid()],$operator);
        $completion->complete($mix->id,['revision'=>$plan->fresh()->revision,'request_token'=>(string)Illuminate\Support\Str::uuid()],$operator);
        $check(App\Models\ExportStockBox::onlyTrashed()->whereIn('box_number',['98002101','98002102','98002103'])->count()===3,'shipment archive');
        $results[]='Mixed FIFO finalization, issue approval/return, scan, placement, ready, shipment';

        Illuminate\Support\Facades\Auth::login(App\Models\User::where('email','uat26.warehouse_operator@whfg.test')->firstOrFail());
        $order=App\Models\DeliveryOrder::where('customer_name','UAT26 DOM FIFO')->firstOrFail();
        $controller=app(App\Http\Controllers\DeliveryPickController::class);
        $started=$controller->startPick($order->id)->getData(true);
        $check(isset($started['session_id']),'domestic picking start');
        foreach (['98000101','98000102'] as $number) {
            $result=$controller->scanBox(Illuminate\Http\Request::create('/uat26','POST',['box_number'=>$number]),$started['session_id'])->getData(true);
            $check($result['success'] ?? false,'domestic FIFO scan');
        }
        $result=$controller->complete(Illuminate\Http\Request::create('/uat26','POST'),$started['session_id'])->getData(true);
        $check($result['success'] ?? false,'domestic shipment complete');
        $results[]='Domestic FIFO picking/scans/shipment';

        Illuminate\Support\Facades\Auth::login($warehouse);
        $history=App\Models\DeliveryOrder::where('customer_name','UAT26 DOM HISTORY-REDO')->firstOrFail();
        $session=$history->pickSessions->sole();
        $options=$controller->redoOptions(Illuminate\Http\Request::create('/uat26','GET'),$session->id)->getData(true);
        $check(($options['success'] ?? false) && count($options['targets'])===1,'imported history redo target');
        $controller->redo(Illuminate\Http\Request::create('/uat26','POST'),$session->id);
        $check(App\Models\Box::whereIn('box_number',['98001101','98001102'])->where('is_withdrawn',false)->count()===2,'imported history redo restores boxes');
        $results[]='Domestic historical shipment redo';

        $handling=App\Models\ExpiredBoxHandling::where('source_box_number','98000606')->firstOrFail();
        $check((int)$handling->source_snapshot['id']===$handling->source_box_id,'expired source snapshot remapped');
        $location=App\Models\MasterLocation::where('code','UAT26-D26')->firstOrFail();
        app(App\Services\ExpiredBoxBulkService::class)->execute(['action'=>'receive','confirmed'=>1,'location_id'=>$location->id,
            'rows'=>[['id'=>$handling->id,'box_number'=>'98010606','part_number'=>'UAT26-D-EXPIRED','pcs_quantity'=>10,
                'label_issued_at'=>now()->subDays(35)->toDateString()]]],$supervisor->id);
        $check(App\Models\Box::where('box_number','98010606')->value('expired_status')==='expired','late packing receipt stays expired');
        $results[]='Expired source snapshot/late packing receipt';
    } finally { $db->rollBack(); }
    return $results;
}
