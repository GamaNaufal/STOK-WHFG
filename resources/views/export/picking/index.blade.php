@extends('shared.layouts.app')
@section('title', 'Picking Verification Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/picking-verification-list.css') }}">
@endsection
@section('content')
<div id="pickingVerificationListPage" class="picking-verification-list-page">
    <header class="verification-list-header">
        <div class="verification-list-heading"><span class="verification-list-eyebrow">Ekspor / Picking</span><h1><i class="bi bi-upc-scan" aria-hidden="true"></i> Picking Verification</h1><p>Pilih pallet untuk mencari box rekomendasi. Scan pencarian tidak memblokir proses.</p></div>
        @if(auth()->user()->canAccess('export_stock_input'))<a class="btn verification-list-back" href="{{ route('export.receiving.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke penerimaan</a>@endif
    </header>
    <section class="card verification-list-panel" aria-labelledby="export-picking-tasks-title">
        <div class="verification-list-toolbar"><div><span class="verification-list-eyebrow">Pengambilan stok ekspor</span><h2 id="export-picking-tasks-title">Pallet untuk diperiksa</h2><p>Rekomendasi dari pallet campuran, urut tenggat pengiriman.</p></div><div class="verification-list-summary"><span><strong>{{ $tasks->total() }}</strong> tujuan</span><span class="verification-list-summary-ready"><strong>{{ $tasks->sum('required_scan_count') }}</strong> box di halaman ini</span></div></div>
        <div class="verification-list-scroll" role="region" tabindex="0" aria-label="Tugas pencarian box ekspor"><table class="table verification-list-table align-middle mb-0"><thead><tr><th scope="col">Rencana</th><th scope="col">Tenggat</th><th scope="col">Customer</th><th scope="col">Total box</th><th scope="col">Pallet tujuan</th><th scope="col">Aksi</th></tr></thead><tbody>
            @forelse($tasks as $task)<tr><td class="verification-list-order">{{ $task->palletPlan->deliveryPlan->plan_code }}</td><td>{{ $task->palletPlan->deliveryPlan->delivery_deadline->format('d M Y') }}</td><td class="verification-list-customer">{{ $task->palletPlan->deliveryPlan->customer_name }}</td><td class="verification-list-boxes">{{ $task->required_scan_count }} <small>box</small></td><td><strong>Pallet {{ $task->position }}</strong><small class="d-block">{{ $task->location_code }}</small></td><td class="verification-list-action"><a class="btn btn-verify" href="{{ route('export.picking.show', ['pallet'=>$task->id]) }}"><i class="bi bi-upc-scan" aria-hidden="true"></i> Cari box</a></td></tr>@empty<tr><td colspan="6" class="verification-list-empty"><i class="bi bi-inbox" aria-hidden="true"></i><strong>Belum ada tugas pallet campuran</strong><p>Rekomendasi dari pallet satu jenis part tidak memerlukan scan satuan.</p></td></tr>@endforelse
        </tbody></table></div>
        @if($tasks->hasPages())<div class="p-3">{{ $tasks->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
@endsection
