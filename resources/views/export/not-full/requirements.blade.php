@php($nfRequests=\App\Models\ExportNotFullRequest::with('item','delivery.palletPlan')->where('export_delivery_plan_id',$notFullDelivery->id)->where('status','!=','cancelled')->get())
@if($nfRequests->isNotEmpty())
<section class="plan-panel"><div class="plan-panel-body"><h2>Kebutuhan wajib Box Not Full</h2><div class="alert alert-warning" role="alert">Request kebutuhan Not Full dicatat otomatis setelah rencana disimpan. Jika box belum tersedia, hubungi Admin Warehouse/Admin Utama untuk penyiapan setelah perencanaan pallet final. Approval oleh Admin Utama/Supervisi langsung mencatat stok di pallet rencana.</div>
    <ul class="mb-0">@foreach($nfRequests as $nf)
        @php($nfService=app(\App\Services\ExportNotFullService::class))
        <li><strong>{{ $nf->part_number }} · {{ $nf->required_boxes }} Not Full {{ $nf->pcs_quantity }} PCS</strong> — {{ $nfService->state($nf,$nfService->target($nf)) }}
            @if(in_array(auth()->user()->role,['admin','admin_warehouse','supervisi']) && (auth()->user()->canAccess('not_full_request') || auth()->user()->canAccess('not_full_approval')))<a href="{{ route('box-not-full.export.show',$nf) }}">Lihat request</a>@endif
        </li>
    @endforeach</ul>
</div></section>
@endif
