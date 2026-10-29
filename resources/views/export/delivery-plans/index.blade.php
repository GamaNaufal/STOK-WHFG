@extends('shared.layouts.app')
@section('title', 'Rencana Delivery Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
<div class="delivery-plan-page">
    <header class="plan-header">
        <div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Rencana delivery</h1><p>Daftar kebutuhan pengiriman untuk persiapan perencanaan pallet.</p></div>
        <a class="btn btn-primary" href="{{ route('export.delivery-plans.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Buat rencana</a>
    </header>
    <form method="GET" class="plan-search" action="{{ route('export.delivery-plans.index') }}">
        <label class="form-label" for="plan-search">Cari kode rencana atau customer</label>
        <div class="plan-inline-actions"><input class="form-control" type="search" id="plan-search" name="q" value="{{ $search }}" maxlength="100" placeholder="Kode rencana atau nama customer"><button class="btn btn-outline-primary">Cari</button></div>
    </form>
    <section class="plan-panel">
        <div class="plan-panel-heading"><h2>Daftar rencana</h2><span>{{ $plans->total() }} rencana</span></div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Daftar rencana delivery">
            <table class="table plan-table"><thead><tr><th scope="col">Kode rencana</th><th scope="col">Customer</th><th scope="col">Batas pengiriman</th><th scope="col">Total PCS</th><th scope="col">Total box</th><th scope="col">Dibuat oleh</th><th scope="col">Aksi</th></tr></thead>
                <tbody>@forelse($plans as $plan)
                    <tr><th scope="row">{{ $plan->plan_code ?? '—' }}</th><td>{{ $plan->customer_name }}</td><td>{{ $plan->delivery_deadline->format('d/m/Y') }}</td>
                        <td>{{ number_format($plan->total_pcs, 0, ',', '.') }}</td><td><strong>{{ number_format($plan->total_boxes, 0, ',', '.') }}</strong></td>
                        <td>{{ $plan->creator->name }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('export.delivery-plans.show', $plan) }}">Lihat rencana</a></td></tr>
                @empty
                    <tr><td colspan="7" class="plan-empty"><i class="bi bi-truck" aria-hidden="true"></i><strong>{{ $search ? 'Tidak ada rencana yang cocok' : 'Belum ada rencana delivery' }}</strong><span>{{ $search ? 'Coba kode rencana atau nama customer lain.' : 'Buat rencana melalui input manual atau Excel.' }}</span></td></tr>
                @endforelse</tbody>
            </table>
        </div>
        @if($plans->hasPages())<div class="plan-panel-body">{{ $plans->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
@endsection
