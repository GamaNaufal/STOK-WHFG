<?php

namespace App\Services;

use App\Models\{AuditLog, Box};
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseReportService
{
    public const KINDS = ['receipts' => 'Penerimaan', 'shipments' => 'Pengiriman / pengambilan',
        'stock' => 'Stok aktif / cadangan', 'fulfillment' => 'Pemenuhan permintaan', 'returns' => 'Revisi / pengembalian'];

    public function filters(Request $request): array
    {
        $data = $request->validate(['scope' => ['nullable', Rule::in(['domestic','export'])], 'kind' => ['nullable', Rule::in(array_keys(self::KINDS))],
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => ['nullable','date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'q' => 'nullable|string|max:100', 'part_number' => 'nullable|string|max:100', 'warehouse_location' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string|max:150', 'user_id' => 'nullable|integer|min:1|exists:users,id', 'status' => 'nullable|string|max:30'],
            ['end_date.after_or_equal'=>'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
                'start_date.date_format'=>'Tanggal awal tidak valid.', 'end_date.date_format'=>'Tanggal akhir tidak valid.']);
        return ['scope'=>$data['scope']??'domestic','kind'=>$data['kind']??'receipts', 'start_date'=>$data['start_date']??'',
            'end_date'=>$data['end_date']??'', 'q'=>trim($data['q']??''), 'part_number'=>trim($data['part_number']??''),
            'warehouse_location'=>trim($data['warehouse_location']??''), 'customer_name'=>trim($data['customer_name']??''),
            'user_id'=>$data['user_id']??'', 'status'=>$data['status']??''];
    }

    private function scalar(string $column, string $path): string
    {
        return DB::getDriverName()==='sqlite' ? "json_extract($column, '$.$path')" : "JSON_UNQUOTE(JSON_EXTRACT($column, '$.$path'))";
    }

    private function snapshotPart(Builder $query, string $part): void
    {
        if (DB::getDriverName()==='sqlite') $query->whereRaw("EXISTS (SELECT 1 FROM json_each(audit_logs.new_values, '$.boxes') b WHERE json_extract(b.value, '$.part_number') LIKE ?)", ['%'.$part.'%']);
        else $query->whereRaw("JSON_SEARCH(audit_logs.new_values, 'one', ?, NULL, '$.boxes[*].part_number') IS NOT NULL", ['%'.$part.'%']);
    }

    private function exportReceipts(): Builder
    {
        $first = DB::table('export_stock_movements')->select('export_stock_box_id')->selectRaw('MIN(id) AS first_id')->groupBy('export_stock_box_id');
        $reference = DB::getDriverName()==='sqlite'
            ? "CASE WHEN batch_item.export_receiving_batch_id IS NOT NULL THEN printf('TRX-%06d',batch_item.export_receiving_batch_id) ELSE printf('BOX-%06d',box.id) END"
            : "CASE WHEN batch_item.export_receiving_batch_id IS NOT NULL THEN CONCAT('TRX-',LPAD(batch_item.export_receiving_batch_id,6,'0')) ELSE CONCAT('BOX-',LPAD(box.id,6,'0')) END";
        return DB::table('export_stock_boxes as box')->leftJoin('users as actor','actor.id','=','box.received_by')
            ->leftJoin('export_receiving_batch_items as batch_item','batch_item.export_stock_box_id','=','box.id')
            ->leftJoinSub($first,'first_move',fn($j)=>$j->on('first_move.export_stock_box_id','=','box.id'))
            ->leftJoin('export_stock_movements as movement','movement.id','=','first_move.first_id')
            ->selectRaw("box.id AS record_id, box.first_received_at AS occurred_at, batch_item.export_receiving_batch_id AS batch_id,
                box.box_number, box.part_number, box.pcs_quantity, 1 AS box_quantity, $reference AS reference, '' AS customer_name, '' AS pallet_number,
                movement.to_location_code AS location, box.received_by AS actor_id, actor.name AS operator_name,
                CASE WHEN box.state='shipped' THEN 'shipped' ELSE 'received' END AS status");
    }

    private function exportShipments(): Builder
    {
        return DB::table('export_fulfillment_boxes as box')->join('export_planned_pallet_items as item','item.id','=','box.export_planned_pallet_item_id')
            ->join('export_planned_pallets as pallet','pallet.id','=','item.export_planned_pallet_id')
            ->join('export_pallet_plans as plan','plan.id','=','pallet.export_pallet_plan_id')
            ->join('export_delivery_plans as delivery','delivery.id','=','plan.export_delivery_plan_id')
            ->leftJoin('users as actor','actor.id','=','plan.completed_by')->whereNotNull('plan.completed_at')
            ->selectRaw("box.id AS record_id, plan.completed_at AS occurred_at, delivery.plan_code AS reference, delivery.customer_name,
                box.box_number, item.part_number, box.pcs_quantity, 1 AS box_quantity, pallet.position AS pallet_number,
                pallet.location_code AS location, plan.completed_by AS actor_id, actor.name AS operator_name, 'completed' AS status");
    }

    private function exportStock(): Builder
    {
        return DB::table('export_stock_boxes as box')->leftJoin('export_stock_pallets as physical','physical.id','=','box.current_pallet_id')
            ->leftJoin('export_planned_pallets as pallet','pallet.id','=','physical.export_planned_pallet_id')
            ->leftJoin('export_fulfillment_boxes as commitment','commitment.export_stock_box_id','=','box.id')
            ->leftJoin('export_planned_pallet_items as item','item.id','=','commitment.export_planned_pallet_item_id')
            ->leftJoin('export_planned_pallets as target','target.id','=','item.export_planned_pallet_id')
            ->leftJoin('export_pallet_plans as plan','plan.id','=','target.export_pallet_plan_id')
            ->leftJoin('export_delivery_plans as delivery','delivery.id','=','plan.export_delivery_plan_id')
            ->leftJoin('users as actor','actor.id','=','box.received_by')->whereNull('box.deleted_at')->whereIn('box.state',['received','stored'])
            ->selectRaw("box.id AS record_id, box.first_received_at AS occurred_at, delivery.plan_code AS reference, delivery.customer_name,
                box.box_number, box.part_number, box.pcs_quantity, 1 AS box_quantity, pallet.position AS pallet_number,
                physical.location_code AS location, box.received_by AS actor_id, actor.name AS operator_name,
                CASE WHEN pallet.ready_at IS NOT NULL THEN 'ready' WHEN commitment.placed_at IS NOT NULL THEN 'fulfilled'
                WHEN commitment.id IS NOT NULL THEN 'reserved' WHEN box.state='stored' THEN 'spare' ELSE 'pending' END AS status");
    }

    private function exportFulfillment(): Builder
    {
        $ready=DB::table('export_planned_pallets')->select('export_pallet_plan_id')->selectRaw('SUM(CASE WHEN ready_at IS NOT NULL THEN 1 ELSE 0 END) AS ready_pallets')->groupBy('export_pallet_plan_id');
        return DB::query()->fromSub(app(ExportFulfillmentService::class)->overview(true),'plan')
            ->leftJoinSub($ready,'ready',fn($j)=>$j->on('ready.export_pallet_plan_id','=','plan.plan_id'))
            ->leftJoin('users as actor','actor.id','=',DB::raw('COALESCE(plan.completed_by, plan.created_by)'))
            ->selectRaw("plan.plan_id AS record_id, plan.delivery_deadline AS occurred_at, plan.plan_code AS reference, plan.customer_name,
                '' AS box_number, '' AS part_number, 0 AS pcs_quantity, plan.required_boxes AS box_quantity, '' AS pallet_number,
                '' AS location, COALESCE(plan.completed_by,plan.created_by) AS actor_id, actor.name AS operator_name,
                plan.required_boxes, plan.fulfilled_boxes, plan.reserved_boxes, plan.remaining_boxes, plan.pallet_count, ready.ready_pallets,
                plan.completed_at, CASE WHEN plan.completed_at IS NOT NULL THEN 'shipped' WHEN plan.archived_at IS NOT NULL THEN 'archived' ELSE plan.fulfillment_status END AS status");
    }

    private function exportReturns(): Builder
    {
        $new=fn($key)=>$this->scalar('audit_logs.new_values',$key);$old=fn($key)=>$this->scalar('audit_logs.old_values',$key);
        return DB::table('audit_logs')->leftJoin('users as actor','actor.id','=','audit_logs.user_id')
            ->where('audit_logs.type','export_fulfillment')->where('audit_logs.action','reduced')
            ->selectRaw("audit_logs.id AS record_id, audit_logs.created_at AS occurred_at, {$new('plan_code')} AS reference,
                {$new('customer_name')} AS customer_name, '' AS box_number, '' AS part_number,
                ({$old('total_pcs')} - {$new('total_pcs')}) AS pcs_quantity, ({$old('total_boxes')} - {$new('total_boxes')}) AS box_quantity,
                {$new('pallet_number')} AS pallet_number, {$new('boxes[0].warehouse_location')} AS location,
                audit_logs.user_id AS actor_id, actor.name AS operator_name, 'returned' AS status");
    }

    private function domesticStock(): Builder
    {
        return Box::query()->where('boxes.is_withdrawn',false)->where(fn($q)=>Box::applyUsableExpiryFilter($q,'boxes'))
            ->leftJoin('pallet_boxes as link','link.box_id','=','boxes.id')->leftJoin('pallets as pallet',fn($j)=>$j->on('pallet.id','=','link.pallet_id')->whereNull('pallet.deleted_at'))
            ->leftJoin('stock_locations as location','location.pallet_id','=','pallet.id')->leftJoin('users as actor','actor.id','=','boxes.user_id')
            ->selectRaw("boxes.id AS record_id, boxes.created_at AS occurred_at, '' AS reference, '' AS customer_name,
                boxes.box_number, boxes.part_number, boxes.pcs_quantity, 1 AS box_quantity, pallet.pallet_number,
                location.warehouse_location AS location, boxes.user_id AS actor_id, actor.name AS operator_name,
                CASE WHEN boxes.assigned_delivery_order_id IS NOT NULL THEN 'reserved' ELSE 'spare' END AS status")->distinct()->toBase();
    }

    private function domesticFulfillment(): Builder
    {
        $items=DB::table('delivery_order_items')->select('delivery_order_id')->selectRaw('SUM(quantity) AS required_pcs, SUM(fulfilled_quantity) AS fulfilled_pcs')->groupBy('delivery_order_id');
        return DB::table('delivery_orders as delivery')->leftJoinSub($items,'items',fn($j)=>$j->on('items.delivery_order_id','=','delivery.id'))
            ->leftJoin('users as actor','actor.id','=','delivery.sales_user_id')->whereNull('delivery.deleted_at')
            ->selectRaw("delivery.id AS record_id, delivery.delivery_date AS occurred_at, delivery.id AS reference, delivery.customer_name,
                '' AS box_number, '' AS part_number, COALESCE(items.required_pcs,0) AS pcs_quantity, 0 AS box_quantity,
                '' AS pallet_number, '' AS location, delivery.sales_user_id AS actor_id, actor.name AS operator_name,
                COALESCE(items.required_pcs,0) AS required_pcs, COALESCE(items.fulfilled_pcs,0) AS fulfilled_pcs,
                delivery.status");
    }

    private function domesticReturns(): Builder
    {
        return DB::table('stock_withdrawals as withdrawal')->leftJoin('boxes as box','box.id','=','withdrawal.box_id')
            ->leftJoin('users as actor','actor.id','=','withdrawal.user_id')->whereNull('withdrawal.deleted_at')->where('withdrawal.status','reversed')
            ->selectRaw("withdrawal.id AS record_id, withdrawal.updated_at AS occurred_at, withdrawal.withdrawal_batch_id AS reference,
                '' AS customer_name, box.box_number, withdrawal.part_number, withdrawal.pcs_quantity, withdrawal.box_quantity,
                '' AS pallet_number, withdrawal.warehouse_location AS location, withdrawal.user_id AS actor_id,
                actor.name AS operator_name, 'reversed' AS status");
    }

    public function query(array $filters): Builder
    {
        $export=$filters['scope']==='export';$kind=$filters['kind'];
        $base=$export ? match($kind){'receipts'=>$this->exportReceipts(),'shipments'=>$this->exportShipments(),'stock'=>$this->exportStock(),
            'fulfillment'=>$this->exportFulfillment(),'returns'=>$this->exportReturns()} : match($kind){
            'stock'=>$this->domesticStock(),'fulfillment'=>$this->domesticFulfillment(),'returns'=>$this->domesticReturns(), default=>throw new \LogicException('Domestic receipt/shipment reports retain their existing controller.')};
        $query=DB::query()->fromSub($base,'records');
        foreach(['start_date'=>'>=','end_date'=>'<='] as $key=>$operator)if($filters[$key]!=='')$query->where('occurred_at',$operator,$filters[$key].($key==='start_date'?' 00:00:00':' 23:59:59'));
        // Delivery deadlines are date-only and use the selected calendar dates inclusively.
        if($kind==='fulfillment'){
            $query=DB::query()->fromSub($base,'records');
            if($filters['start_date']!=='')$query->whereDate('occurred_at','>=',$filters['start_date']);
            if($filters['end_date']!=='')$query->whereDate('occurred_at','<=',$filters['end_date']);
        }
        foreach(['warehouse_location'=>'location','customer_name'=>'customer_name']as$key=>$column)if($filters[$key]!=='')$query->where($column,'like','%'.$filters[$key].'%');
        if($filters['user_id']!=='')$query->where('actor_id',$filters['user_id']);
        if($filters['status']!=='')$query->where('status',$filters['status']);
        if($filters['q']!=='')$query->where(function($q)use($filters){
            foreach(['reference','customer_name','box_number','part_number','location']as$column)$q->orWhere($column,'like','%'.$filters['q'].'%');
            if(preg_match('/^pallet\s*(\d+)$/i',$filters['q'],$match))$q->orWhere('pallet_number',(int)$match[1]);
            elseif(strcasecmp($filters['q'],'pallet cadangan')===0)$q->orWhere(fn($p)=>$p->whereNull('pallet_number')->whereNotNull('location'));
        });
        if($filters['part_number']!==''){
            if($kind==='fulfillment'){
                $partQuery=$export ? DB::table('export_planned_pallet_items as i')->join('export_planned_pallets as p','p.id','=','i.export_planned_pallet_id')->select('p.export_pallet_plan_id')
                    : DB::table('delivery_order_items as i')->select('i.delivery_order_id');
                $query->whereIn('record_id',$partQuery->where('i.part_number','like','%'.$filters['part_number'].'%'));
            }elseif($kind==='returns'&&$export){$audit=DB::table('audit_logs')->select('id');$this->snapshotPart($audit,$filters['part_number']);$query->whereIn('record_id',$audit);}
            else $query->where('part_number','like','%'.$filters['part_number'].'%');
        }
        return $query->orderByDesc('occurred_at')->orderByDesc('record_id');
    }

    public function columns(array $filters): array
    {
        $kind=$filters['kind'];$export=$filters['scope']==='export';
        $columns=['reference'=>'Referensi','occurred_at'=>$kind==='fulfillment'?'Tenggat pengiriman':'Tanggal & waktu'];
        if(in_array($kind,['shipments','fulfillment','returns']))$columns['customer_name']='Customer';
        if($kind==='fulfillment'){
            $columns += $export ? ['pallet_count'=>'Pallet','ready_pallets'=>'Pallet siap','required_boxes'=>'Butuh box','fulfilled_boxes'=>'Ditempatkan','reserved_boxes'=>'Reservasi','remaining_boxes'=>'Sisa']
                : ['required_pcs'=>'Butuh PCS','fulfilled_pcs'=>'Terpenuhi PCS'];
            if($export)$columns['completed_at']='Selesai dikirim';
        }else{
            if(!($kind==='returns'&&$export))$columns+=['box_number'=>'Nomor box','part_number'=>'Nomor part'];
            $columns+=['box_quantity'=>'Jumlah box','pcs_quantity'=>'PCS'];
            if($kind!=='receipts')$columns['pallet_number']=$kind==='returns'?'Pallet asal':'Pallet fisik';
            $columns['location']=$kind==='receipts'?'Lokasi penempatan awal':($kind==='returns'?'Tujuan pengembalian':'Lokasi');
        }
        return $columns+['status'=>'Status','operator_name'=>$kind==='fulfillment'?'Pembuat / penyelesai':'Operator'];
    }

    public function value(object $row,string $key,array $filters): mixed
    {
        $value=$row->$key??null;
        if($key==='reference'){
            if($filters['scope']==='export'&&$filters['kind']==='receipts')return ExportReceivingTransactionService::code(empty($row->batch_id)?'legacy':'batch',(int)($row->batch_id??$row->record_id));
            return filled($value)?(string)$value:($filters['kind']==='returns'?'AUDIT-':'RD-').$row->record_id;
        }
        if($key==='status')return self::statusLabel((string)$value);
        if($key==='pallet_number'&&$filters['scope']==='export')return filled($value)?'Pallet '.$value:($row->location?'Pallet cadangan':'Belum ditempatkan');
        return $value;
    }

    public static function statusLabel(string $status): string
    {
        return ['received'=>'Diterima','shipped'=>'Sudah dikirim','completed'=>'Selesai','returned'=>'Kembali stok cadangan','reversed'=>'Dikembalikan / Redo',
            'spare'=>'Stok cadangan','pending'=>'Menunggu','reserved'=>'Direservasi','fulfilled'=>'Terpenuhi','ready'=>'Pallet siap',
            'waiting'=>'Belum dimulai','partial'=>'Dalam pemenuhan','complete'=>'Terpenuhi','paused'=>'Perlu revisi','attention'=>'Perlu diperiksa',
            'archived'=>'Diarsipkan','approved'=>'Disetujui','cancelled'=>'Dibatalkan','rejected'=>'Ditolak'][$status]??$status;
    }
}
