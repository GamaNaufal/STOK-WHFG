@extends('shared.layouts.app')
@section('title', 'Picking Verification Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page fulfillment-page">
    <header class="plan-header"><div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Picking Verification Ekspor</h1><p>Bantu cari box rekomendasi dari pallet campuran. Scan di halaman pencarian tidak memblokir proses.</p></div></header>
    <section class="plan-panel"><div class="plan-panel-heading"><h2>Rekomendasi pengambilan pallet campuran</h2><span>{{ $tasks->total() }} tujuan</span></div>
        <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Tugas pencarian box ekspor"><table class="table plan-table"><thead><tr><th>Delivery / customer</th><th>Pallet tujuan</th><th>Box dari pallet campuran</th><th>Aksi</th></tr></thead><tbody>
            @forelse($tasks as $task)<tr><th scope="row">{{ $task->palletPlan->deliveryPlan->plan_code }}<small class="fulfillment-customer">{{ $task->palletPlan->deliveryPlan->customer_name }}</small></th><td>Pallet {{ $task->position }} · {{ $task->location_code }}</td><td>{{ $task->required_scan_count }} box</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('export.picking.show', ['pallet'=>$task->id]) }}">Cari box</a></td></tr>@empty<tr><td colspan="4" class="plan-empty"><strong>Belum ada tugas pallet campuran</strong><span>Rekomendasi dari pallet satu jenis part tidak memerlukan scan satuan.</span></td></tr>@endforelse
        </tbody></table></div>
        @if($tasks->hasPages())<div class="plan-panel-body">{{ $tasks->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
@endsection
