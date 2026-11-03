@extends('shared.layouts.app')
@section('title','Box Not Full Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
@unless($approval)
    <link rel="stylesheet" href="{{ asset('css/pages/warehouse-scope-switch.css') }}?v={{ filemtime(public_path('css/pages/warehouse-scope-switch.css')) }}">
@endunless
@endsection
@section('content')
<div @class(['delivery-plan-page', 'container-fluid' => ! $approval])>
    @include('shared.not-full-feedback')
    @if($approval)
        @include('shared.not-full-scope', ['approval' => true])
        <header class="plan-header"><div><h1>Box Not Full · Ekspor</h1><p>Request wajib dari sisa PCS rencana delivery. Penyiapan oleh Admin Warehouse/Admin Utama, approval oleh Admin Utama/Supervisi.</p></div></header>
    @else
        <header class="warehouse-scope-header mb-4">
            <div class="warehouse-scope-heading">
                <span class="warehouse-scope-eyebrow">Warehouse / Ekspor</span>
                <h1>Box Not Full · Ekspor</h1>
            </div>
            @include('shared.not-full-scope', ['approval' => false])
            <p class="warehouse-scope-description">Request wajib dari sisa PCS rencana delivery. Penyiapan oleh Admin Warehouse/Admin Utama, approval oleh Admin Utama/Supervisi.</p>
        </header>
    @endif
    <div class="alert alert-info">Box baru dapat disiapkan setelah perencanaan pallet final. Stok Not Full yang sesuai diprioritaskan melalui FIFO; pemecahan hanya memakai box penuh yang bebas setelah reservasi.</div>
    <section class="plan-panel"><div class="plan-table-scroll" tabindex="0" role="region" aria-label="Request Not Full ekspor">
        <table class="table plan-table"><thead><tr><th>Rencana / customer</th><th>Nomor part</th><th>Kebutuhan</th><th>Tujuan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse($requests as $r)
            @php($target=$service->target($r))
            <tr><td>{{ $r->delivery->plan_code ?: 'RD-'.$r->export_delivery_plan_id }}<small class="d-block">{{ $r->delivery->customer_name }}</small></td><td>{{ $r->part_number }}</td><td>{{ $r->required_boxes }} Not Full · <strong>{{ $r->pcs_quantity }} PCS</strong><small class="d-block">Standar {{ $r->fixed_qty }} PCS/box</small></td><td>{{ $target ? 'Pallet '.$target->pallet->position.' · '.$target->pallet->location_code : ($r->preparation ? 'Pallet '.$r->preparation['pallet_number'].' · '.$r->preparation['location_code'] : ($r->status==='cancelled'?'Riwayat dibatalkan':'Menunggu perencanaan final')) }}</td><td>{{ $service->state($r,$target) }}</td><td><a class="btn btn-outline-primary btn-sm" href="{{ route('box-not-full.export.show',$r) }}">Lihat detail</a></td></tr>
        @empty<tr><td colspan="6">Belum ada request Not Full ekspor.</td></tr>@endforelse
        </tbody></table>
    </div><div class="p-3">{{ $requests->links('pagination::bootstrap-5') }}</div></section>
</div>
@endsection
