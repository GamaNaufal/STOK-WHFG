<?php

declare(strict_types=1);

use App\Models\{Box, DeliveryOrder, ExportDeliveryPlan, ExportMasterLocation, ExportNotFullRequest, PartSetting, ExportStockAllocationNotice, ExportStockBox, MasterLocation, Pallet, StockInput, User};
use App\Services\{AuditService, ExpiredBoxService, ExportBoxRequirements, ExportDeliveryPlanService,
    ExportFulfillmentCompletionService, ExportNotFullService, ExportPalletPlanningService,
    ExportStockAllocationNoticeService, ExportStockReceivingService, LocationAssignmentService};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\Str;

final class UatFixtureBuilder
{
    private array $users = [];
    private array $locations = [];
    private array $parts = [];
    private array $manifest = ['namespace'=>'UAT26', 'password'=>'UatWHFG!2026',
        'base_time'=>'tanggal impor, Asia/Jakarta', 'accounts'=>[], 'domestic'=>[], 'export'=>[]];
    private int $palletSequence = 0;

    public function build(): array
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00', 'Asia/Jakarta'));
        foreach (['admin','supervisi','admin_warehouse','warehouse_operator','export_operator','sales','ppc','warehouse_operator2','inactive'] as $name) {
            $role = match ($name) {'warehouse_operator2'=>'warehouse_operator', 'inactive'=>'sales', default=>$name};
            $u = User::create(['name'=>'UAT26 '.$name, 'email'=>'uat26.'.$name.'@whfg.test',
                'role'=>$role, 'password'=>$this->manifest['password'], 'is_active'=>$name!=='inactive']);
            $this->users[$name]=$u;
            $this->manifest['accounts'][]=['email'=>$u->email, 'role'=>$role, 'active'=>$u->is_active];
        }
        Auth::login($this->users['admin']);
        for ($i=1; $i<=50; $i++) {
            foreach (['D'=>MasterLocation::class, 'E'=>ExportMasterLocation::class] as $scope=>$class) {
                $key=$scope.str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $this->locations[$key]=$class::create(['code'=>'UAT26-'.$key]);
            }
        }
        $this->domestic();
        $this->exports();
        Carbon::setTestNow();
        return $this->manifest;
    }

    private function part(string $name, int $qty): void
    {
        $this->parts[$name]=PartSetting::create(['part_number'=>'UAT26-'.$name, 'qty_box'=>$qty]);
    }

    private function domesticStock(string $part, array $numbers, string $location, int $days=3): array
    {
        return DB::transaction(function () use ($part,$numbers,$location,$days) {
            // End in a letter so the production PLT allocator does not jump to 26xxxx.
            $p=Pallet::create(['pallet_number'=>'UAT26-DP-'.++$this->palletSequence.'X']);
            $qty=(int)$this->parts[$part]->qty_box;
            $at=now()->subDays($days);
            $item=$p->items()->create(['part_number'=>'UAT26-'.$part, 'box_quantity'=>count($numbers), 'pcs_quantity'=>$qty*count($numbers)]);
            $input=StockInput::create(['pallet_id'=>$p->id, 'pallet_item_id'=>$item->id, 'user_id'=>$this->users['warehouse_operator']->id,
                'warehouse_location'=>$this->locations[$location]->code, 'pcs_quantity'=>$qty*count($numbers),
                'box_quantity'=>count($numbers), 'part_numbers'=>['UAT26-'.$part], 'stored_at'=>$at]);
            $boxes=[];
            foreach ($numbers as $i=>$number) {
                $box=Box::create(['box_number'=>(string)$number, 'part_number'=>'UAT26-'.$part, 'part_name'=>'UAT26 '.$part,
                    'pcs_quantity'=>$qty, 'qty_box'=>$qty, 'qr_code'=>(string)$number, 'user_id'=>$this->users['warehouse_operator']->id,
                    'created_at'=>$at->copy()->addMinutes($i), 'updated_at'=>$at]);
                $p->boxes()->attach($box->id, ['created_at'=>$at,'updated_at'=>$at]);
                $input->boxes()->attach($box->id, ['created_at'=>$at,'updated_at'=>$at]);
                $boxes[]=$box;
            }
            app(LocationAssignmentService::class)->assign($this->locations[$location]->id, $p, $at);
            $input->timestamps=false; $input->created_at=$at; $input->updated_at=$at; $input->save();
            AuditService::record('stock_input','created','StockInput',$input->id,$this->users['warehouse_operator']->id,[],
                ['pallet_id'=>$p->id,'part_number'=>'UAT26-'.$part,'pcs_quantity'=>$qty*count($numbers)], 'UAT26 baseline penerimaan lokal.');
            $this->manifest['domestic']['stock'][]=['part'=>'UAT26-'.$part,'pcs_per_box'=>$qty,'boxes'=>array_map('strval',$numbers),
                'pallet'=>$p->pallet_number,'location'=>$this->locations[$location]->code,'age_days'=>$days];
            return $boxes;
        });
    }

    private function order(string $code, string $part, int $pcs, string $status='approved', int $days=3): DeliveryOrder
    {
        $o=DeliveryOrder::create(['sales_user_id'=>$this->users['sales']->id,'customer_name'=>'UAT26 DOM '.$code,
            'delivery_date'=>now()->addDays($days)->toDateString(),'status'=>$status,'notes'=>'UAT26 '.$code.'; persetujuan PPC untuk fixture bila approved.']);
        $o->items()->create(['part_number'=>'UAT26-'.$part,'quantity'=>$pcs,'fulfilled_quantity'=>0]);
        $this->manifest['domestic']['orders'][]=['customer'=>$o->customer_name,'part'=>'UAT26-'.$part,'pcs'=>$pcs,'status'=>$status,'deadline_offset_days'=>$days];
        return $o;
    }

    private function domestic(): void
    {
        foreach (['D-FIFO'=>80,'D-DIRECT'=>30,'D-ISSUE'=>40,'D-LOCK'=>20,'D-SPLIT'=>20,'D-NF'=>80,
            'D-NF-ADD'=>80,'D-MOVE'=>25,'D-MERGE'=>10,'D-EDIT'=>30,'D-EXPIRED'=>10,'D-WARNING'=>10,
            'D-REDO'=>50,'D-CORRECTION'=>10,'D-PRIORITY'=>10,'D-FREE'=>10,'SHARED'=>100] as $p=>$q) $this->part($p,$q);
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00','Asia/Jakarta')->subDays(60));
        $old=$this->domesticStock('D-EXPIRED',[98000606],'D15',400)[0];
        $bulk=app(App\Services\ExpiredBoxBulkService::class);
        $ids=$bulk->execute(['action'=>'start','rows'=>[['id'=>$old->id]],'confirmed'=>1],$this->users['supervisi']->id);
        $bulk->execute(['action'=>'qa','rows'=>[['id'=>$ids[0],'decision'=>'passed','notes'=>'UAT26 QA lolos.']],'confirmed'=>1],$this->users['supervisi']->id);
        $bulk->execute(['action'=>'packing','rows'=>[['id'=>$ids[0]]],'confirmed'=>1],$this->users['supervisi']->id);
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00','Asia/Jakarta'));
        $this->domesticStock('D-FIFO',[98000101,98000102,98000103],'D01',5);
        $this->domesticStock('D-ISSUE',[98000201,98000202,98000203],'D02',4);
        $this->domesticStock('D-LOCK',[98000301,98000302],'D03');
        $this->domesticStock('D-SPLIT',[98000401,98000402,98000403,98000404],'D04');
        $this->domesticStock('D-NF',[98000501],'D05');
        $this->domesticStock('D-NF-ADD',[98000502],'D06');
        $this->domesticStock('D-MOVE',[98000701,98000702],'D07');
        $this->domesticStock('D-MERGE',[98000801,98000802],'D08');
        $this->domesticStock('D-MERGE',[98000803,98000804],'D09');
        $this->domesticStock('D-EDIT',[98000901,98000902],'D10');
        $this->domesticStock('D-PRIORITY',[98001001,98001002,98001003,98001004,98001005],'D11');
        $this->domesticStock('SHARED',[98009999],'D12');
        $this->domesticStock('D-EXPIRED',[98000601,98000602,98000603,98000604],'D13',400);
        $this->domesticStock('D-WARNING',[98000611,98000612],'D14',305);
        app(ExpiredBoxService::class)->syncStatuses();
        $this->order('FIFO','D-FIFO',160);
        $this->order('DIRECT','D-DIRECT',60);
        $this->order('ISSUE','D-ISSUE',80);
        $this->order('LOCK','D-LOCK',40);
        $this->order('SPLIT','D-SPLIT',80);
        $nf=$this->order('NF-SUPPLEMENT','D-NF',100);
        $add=$this->order('NF-ADDITIONAL','D-NF-ADD',80);
        $this->order('CORRECTION','D-CORRECTION',20,'pending');
        $this->order('REJECT','D-CORRECTION',10,'pending');
        $this->order('PRIORITY-FIRST','D-PRIORITY',40,'approved',1);
        $this->order('PRIORITY-LATER','D-PRIORITY',40,'pending',2);
        foreach ([[$nf,'D-NF','supplement','98010071','D21'],[$add,'D-NF-ADD','additional','98010073','D22']] as [$o,$part,$type,$number,$loc]) {
            Auth::login($this->users['warehouse_operator']);
            $request=Request::create('/uat26','POST',['box_number'=>$number,'part_number'=>'UAT26-'.$part,
                'pcs_quantity'=>20,'delivery_order_id'=>$o->id,'reason'=>'UAT26 kebutuhan pengujian '.$type,
                'request_type'=>$type,'target_type'=>'location','target_location_id'=>$this->locations[$loc]->id]);
            $request->setUserResolver(fn ()=>$this->users['warehouse_operator']);
            app(App\Http\Controllers\NotFullBoxRequestController::class)->store($request);
            if (!DB::table('not_full_box_requests')->where('box_number',$number)->exists()) throw new RuntimeException('Gagal membuat NF lokal.');
        }
        Auth::login($this->users['admin']);
        // A real completed domestic shipment, immediately available for reports/redo.
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00','Asia/Jakarta')->subDay());
        $this->domesticStock('D-REDO',[98001101,98001102],'D16',4);
        $history=$this->order('HISTORY-REDO','D-REDO',100,'approved',0);
        Auth::login($this->users['warehouse_operator']);
        $pick=app(App\Http\Controllers\DeliveryPickController::class);
        $response=$pick->startPick($history->id)->getData(true);
        if (!isset($response['session_id'])) throw new RuntimeException('Domestic history start: '.json_encode($response));
        foreach (['98001101','98001102'] as $number) {
            $result=$pick->scanBox(Request::create('/uat26','POST',['box_number'=>$number]),$response['session_id'])->getData(true);
            if (!($result['success'] ?? false)) throw new RuntimeException('Domestic history scan: '.json_encode($result));
        }
        $result=$pick->complete(Request::create('/uat26','POST'),$response['session_id'])->getData(true);
        if (!($result['success'] ?? false)) throw new RuntimeException('Domestic history complete: '.json_encode($result));
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00','Asia/Jakarta'));
        Auth::login($this->users['admin']);
    }

    private function stock(string $part, array $numbers, string $location, int $days=3): array
    {
        $s=app(ExportStockReceivingService::class); $pallet=null; $boxes=[];
        foreach ($numbers as $i=>$number) {
            $box=$s->receive(['box_number'=>(string)$number,'part_number'=>'UAT26-'.$part,'receive_token'=>(string)Str::uuid()],
                $this->users['export_operator'],false,now()->subDays($days)->addMinutes($i));
            $box=$s->place($box->id,['revision'=>$box->revision,'request_token'=>(string)Str::uuid()]
                +($pallet ? ['pallet_id'=>$pallet] : ['location_id'=>$this->locations[$location]->id]),$this->users['export_operator']);
            $pallet=$box->current_pallet_id; $boxes[]=$box;
        }
        $this->manifest['export']['stock'][]=['part'=>'UAT26-'.$part,'pcs_per_box'=>$this->parts[$part]->qty_box,
            'boxes'=>array_map('strval',$numbers),'location'=>$this->locations[$location]->code,'age_days'=>$days];
        return $boxes;
    }

    private function delivery(string $code, array $items, ?string $location=null, int $days=5): ExportDeliveryPlan
    {
        $d=app(ExportDeliveryPlanService::class)->save(['plan_code'=>'UAT26-EX-'.$code,'customer_name'=>'UAT26 EXP '.$code,
            'delivery_deadline'=>now()->addDays($days)->toDateString(),'source_type'=>'manual',
            'items'=>array_map(fn ($part,$pcs)=>['part_number'=>'UAT26-'.$part,'pcs_quantity'=>$pcs],array_keys($items),array_values($items))],$this->users['export_operator']);
        if ($location) {
            $rows=ExportBoxRequirements::catalog($d)->map(fn ($r)=>['part_setting_id'=>$r['part_setting_id'],'pcs_per_box'=>$r['pcs_per_box'],'box_quantity'=>$r['required']])->values()->all();
            app(ExportPalletPlanningService::class)->save($d->id,['revision'=>0,'delivery_revision'=>$d->revision,
                'action'=>'finalize','pallets'=>[['master_location_id'=>$this->locations[$location]->id,'items'=>$rows]]],$this->users['export_operator']);
        }
        $this->manifest['export']['plans'][]=['code'=>$d->plan_code,'customer'=>$d->customer_name,'items'=>$items,
            'final'=>$location!==null,'location'=>$location ? $this->locations[$location]->code : null,'deadline_offset_days'=>$days];
        return $d->fresh();
    }

    private function acknowledge(ExportDeliveryPlan $d): void
    {
        $plan=$d->fresh()->palletPlan;
        $n=ExportStockAllocationNotice::where('export_pallet_plan_id',$plan->id)->latest('id')->firstOrFail();
        app(ExportStockAllocationNoticeService::class)->acknowledge($d->id,$n->id,$plan->revision,$this->users['export_operator']);
    }

    private function exports(): void
    {
        foreach (['E-MIX-A'=>80,'E-MIX-B'=>40,'E-MONO'=>20,'E-LOAD36'=>40,'E-RECEIVE'=>80,'E-DEADLINE'=>80,
            'E-SPARE'=>40,'E-NF-EXT'=>80,'E-NF-DRAFT'=>80,'E-NF-SPLIT'=>80,'E-NF-SHORT'=>80,
            'E-NF-EXIST'=>80,'E-REV'=>50,'E-CANCEL'=>80,'E-WARN'=>10,'E-DUP'=>80,'E-HIST'=>20] as $p=>$q) $this->part($p,$q);
        // Both workflows use the same part master; box IDs remain globally unique.
        $this->parts['E-SHARED']=PartSetting::where('part_number','UAT26-SHARED')->firstOrFail();
        $mix=$this->stock('E-MIX-A',[98002101,98002102],'E01',5);
        $s=app(ExportStockReceivingService::class);
        $b=$s->receive(['box_number'=>'98002103','part_number'=>'UAT26-E-MIX-B','receive_token'=>(string)Str::uuid()],$this->users['export_operator'],false,now()->subDays(4));
        $s->place($b->id,['revision'=>$b->revision,'request_token'=>(string)Str::uuid(),'pallet_id'=>$mix[0]->current_pallet_id],$this->users['export_operator']);
        $this->manifest['export']['stock'][]=['part'=>'UAT26-E-MIX-B','pcs_per_box'=>40,'boxes'=>['98002103'],'location'=>'UAT26-E01','age_days'=>4];
        $this->stock('E-MIX-A',[98002104],'E02',2);
        $this->stock('E-MONO',range(98002001,98002071),'E03',4);
        $this->stock('E-LOAD36',range(98002201,98002235),'E04');
        $this->stock('E-SPARE',[98002251,98002252],'E05');
        $this->stock('E-NF-SPLIT',[98002261,98002262,98002263],'E06',6);
        $this->stock('E-NF-SHORT',[98002271],'E07');
        $this->stock('E-REV',[98002301,98002302,98002303],'E09',6);
        $this->stock('E-CANCEL',[98002311,98002312],'E10');
        $shared=$s->receive(['box_number'=>'98009998','part_number'=>'UAT26-SHARED','receive_token'=>(string)Str::uuid()],$this->users['export_operator'],false);
        $s->place($shared->id,['revision'=>$shared->revision,'request_token'=>(string)Str::uuid(),'location_id'=>$this->locations['E11']->id],$this->users['export_operator']);
        $this->delivery('MIX',['E-MIX-A'=>160,'E-MIX-B'=>40]);
        $this->delivery('MONO',['E-MONO'=>720]);
        $this->delivery('RECEIVE',['E-RECEIVE'=>240]);
        $this->delivery('EARLY',['E-DEADLINE'=>80],'E13',2);
        $this->delivery('LATER',['E-DEADLINE'=>80],'E14',3);
        $this->delivery('NF-EXT',['E-NF-EXT'=>100],'E31');
        $this->delivery('NF-DRAFT',['E-NF-DRAFT'=>100]);
        $this->delivery('NF-SPLIT',['E-NF-SPLIT'=>100],'E32');
        $this->delivery('NF-SHORT',['E-NF-SHORT'=>100],'E33');
        $this->delivery('NF-EXIST',['E-NF-EXIST'=>100]);
        $this->delivery('REV',['E-REV'=>200],'E35');
        $this->delivery('CANCEL',['E-CANCEL'=>160],'E37');
        $this->delivery('WARN',['E-WARN'=>720]);
        $this->delivery('DUP',['E-DUP'=>80]);
        $arch=$this->delivery('ARCHIVE',['E-DUP'=>80]); $arch->delete();
        // Spare NF 20, created and returned via the actual approval/revision workflow.
        foreach ([['98002401',3]] as [$number,$days]) {
            Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00','Asia/Jakarta')->subDays($days));
            $d=$this->delivery('NF-STOCK-'.$days,['E-NF-EXIST'=>20],'E08'); $this->acknowledge($d);
            $r=ExportNotFullRequest::where('export_delivery_plan_id',$d->id)->firstOrFail();
            app(ExportNotFullService::class)->prepare($r->id,['revision'=>$r->revision,'mode'=>'external','box_number'=>$number,'notes'=>'UAT26 stok Not Full existing.'],$this->users['admin_warehouse']);
            app(ExportNotFullService::class)->approve($r->id,['revision'=>$r->fresh()->revision,'physical_confirmed'=>1],$this->users['supervisi']);
            $p=$d->fresh()->palletPlan->pallets->sole();
            $svc=app(ExportFulfillmentCompletionService::class); $preview=$svc->reduction($p,[$p->items->sole()->id=>0]);
            $destination=$days===3 ? 'location:'.$this->locations['E12']->id : 'pallet:'.ExportStockBox::where('box_number','98002401')->firstOrFail()->current_pallet_id;
            $svc->reduce($d->id,$p->id,['revision'=>$d->fresh()->palletPlan->revision,'fulfillment_revision'=>$p->fulfillment_revision,
                'request_token'=>(string)Str::uuid(),'quantities'=>$preview['quantities'],'fingerprint'=>$preview['fingerprint'],
                'destination'=>$destination,'return_confirmed'=>1],$this->users['export_operator']);
        }
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00','Asia/Jakarta')->subDay());
        $this->stock('E-HIST',[98002901,98002902,98002903,98002904],'E15',5);
        $d=$this->delivery('HISTORY',['E-HIST'=>80],'E16',0); $this->acknowledge($d);
        foreach (ExportStockBox::where('part_number','UAT26-E-HIST')->get() as $box) {
            $s->place($box->id,['revision'=>$box->revision,'request_token'=>(string)Str::uuid()],$this->users['export_operator']);
        }
        $p=$d->fresh()->palletPlan->pallets->sole(); $svc=app(ExportFulfillmentCompletionService::class);
        $svc->ready($d->id,$p->id,['revision'=>$d->fresh()->palletPlan->revision,'fulfillment_revision'=>$p->fulfillment_revision,
            'request_token'=>(string)Str::uuid(),'location_id'=>$p->master_location_id],$this->users['export_operator']);
        $svc->complete($d->id,['revision'=>$d->fresh()->palletPlan->revision,'request_token'=>(string)Str::uuid()],$this->users['export_operator']);
        Carbon::setTestNow(Carbon::parse('2026-10-31 10:00:00','Asia/Jakarta'));
    }
}
