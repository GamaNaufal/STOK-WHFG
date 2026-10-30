<?php

namespace App\Http\Controllers;

use App\Exports\WarehouseRecordsExport;
use App\Models\{AuditLog, User};
use App\Services\WarehouseReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class WarehouseReportController extends Controller
{
    private function domesticRoute(array $filters, bool $export=false): ?string
    {
        if($filters['scope']!=='domestic')return null;
        return match($filters['kind']){'receipts'=>'reports.stock-input'.($export?'.export':''),
            'shipments'=>'reports.withdrawal'.($export?'.export':''),default=>null};
    }

    public function index(Request $request, WarehouseReportService $service)
    {
        $filters=$service->filters($request);
        if($route=$this->domesticRoute($filters))return redirect()->route($route, array_filter($filters,fn($v)=>$v!==''));
        $query=$service->query($filters);
        $totals=(clone $query)->reorder()->selectRaw('COUNT(*) AS records, COALESCE(SUM(box_quantity),0) AS boxes, COALESCE(SUM(pcs_quantity),0) AS pcs')->first();
        $rows=$query->paginate(25)->withQueryString();$columns=$service->columns($filters);
        $users=User::where(fn($q)=>$q->whereIn('id', $service->query(array_replace($filters,['user_id'=>'']))->reorder()->select('actor_id')->distinct())
            ->when($filters['user_id']!=='',fn($q)=>$q->orWhere('id',$filters['user_id'])))->orderBy('name')->get(['id','name']);
        $statusOptions=$service->query(array_replace($filters,['status'=>'']))->reorder()->select('status')->distinct()->pluck('status')->mapWithKeys(fn($status)=>[$status=>$service::statusLabel($status)])->all();
        if($filters['status']!=='')$statusOptions[$filters['status']]=$service::statusLabel($filters['status']);
        $hasDetail=$filters['kind']==='fulfillment'||($filters['kind']==='returns'&&$filters['scope']==='export');
        $periodNote=match($filters['kind']){
            'receipts'=>'Tanggal mengikuti pertama kali box diterima. Satu baris mewakili satu box; pemindahan tidak menambah penerimaan.',
            'shipments'=>'Tanggal mengikuti Selesaikan permintaan. Pallet siap yang belum dikirim tidak dihitung sebagai barang keluar.',
            'stock'=>'Tanggal mengikuti pertama kali diterima. Hanya stok aktif yang ditampilkan; penempatan fisik terpisah dari tujuan reservasi.',
            'fulfillment'=>'Tanggal mengikuti tenggat pengiriman. Terpenuhi berbeda dari sudah selesai dikirim.',
            'returns'=>$filters['scope']==='export'?'Tanggal mengikuti konfirmasi revisi/pengembalian. Pengembalian tidak dihitung sebagai penerimaan baru.':'Catatan pengambilan berstatus dikembalikan/redo; tanggal perubahan terakhir dan operator pada catatan asal.'};
        return \App\Support\ReportPageResponse::make($request, 'operator.reports.records', compact('filters','rows','columns','totals','users','service','statusOptions','hasDetail','periodNote')+['kinds'=>$service::KINDS]);
    }

    public function export(Request $request, WarehouseReportService $service)
    {
        $filters=$service->filters($request);
        if($route=$this->domesticRoute($filters,true))return redirect()->route($route,array_filter($filters,fn($v)=>$v!==''));
        return Excel::download(new WarehouseRecordsExport($service->query($filters),$filters),
            'laporan_'.$filters['scope'].'_'.$filters['kind'].'_'.now()->format('Ymd_His').'.xlsx')->setPrivate();
    }

    public function detail(Request $request, int $record, WarehouseReportService $service)
    {
        $filters=$service->filters($request);
        abort_unless(in_array($filters['kind'],['returns','fulfillment']),404);
        $row=$service->query($filters)->where('record_id',$record)->first();abort_unless($row,404);
        $export=$filters['scope']==='export';
        if($filters['kind']==='returns'&&$export){
            $snapshot=json_decode(AuditLog::findOrFail($record)->new_values,true,512,JSON_THROW_ON_ERROR);
            $all=collect($snapshot['boxes']??[])->map(fn($r)=>(object)($r+['pallet_number'=>$snapshot['pallet_number']??null]));$page=max(1,(int)$request->input('page',1));
            $lines=new LengthAwarePaginator($all->forPage($page,50)->values(),$all->count(),50,$page,['path'=>$request->url(),'query'=>$request->query()]);
            $columns=['box_number'=>'Nomor box','part_number'=>'Nomor part','pcs_quantity'=>'PCS','pallet_number'=>'Pallet asal',
                'from_location'=>'Lokasi asal','warehouse_location'=>'Lokasi pengembalian','first_received_at'=>'Pertama diterima'];
        }elseif($filters['kind']==='fulfillment'&&$export){
            $lines=DB::table('export_fulfillment_boxes as box')->join('export_planned_pallet_items as item','item.id','=','box.export_planned_pallet_item_id')
                ->join('export_planned_pallets as pallet','pallet.id','=','item.export_planned_pallet_id')->where('pallet.export_pallet_plan_id',$record)
                ->select('box.box_number','item.part_number','box.pcs_quantity','box.first_received_at','box.placed_at','pallet.position as pallet_number','pallet.location_code as warehouse_location')
                ->orderBy('pallet.position')->orderBy('box.first_received_at')->orderBy('box.id')->paginate(50)->withQueryString();
            $columns=['box_number'=>'Nomor box','part_number'=>'Nomor part','pcs_quantity'=>'PCS','pallet_number'=>'Pallet',
                'warehouse_location'=>'Lokasi tujuan','first_received_at'=>'Pertama diterima','placed_at'=>'Ditempatkan'];
        }elseif($filters['kind']==='fulfillment'){
            $lines=DB::table('delivery_order_items')->where('delivery_order_id',$record)->orderBy('id')->paginate(50)->withQueryString();
            $columns=['part_number'=>'Nomor part','quantity'=>'Kebutuhan PCS','fulfilled_quantity'=>'Terpenuhi PCS'];
        }else abort(404);
        return \App\Support\ReportPageResponse::make($request, 'operator.reports.record-detail',compact('row','filters','lines','columns','service'));
    }
}
