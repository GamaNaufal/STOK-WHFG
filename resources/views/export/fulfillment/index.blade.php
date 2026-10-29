@extends('shared.layouts.app')
@section('title', 'Pemenuhan Permintaan Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page fulfillment-page">
    <header class="plan-header"><div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Pemenuhan permintaan</h1><p>Pantau pemenuhan rencana pallet berdasarkan box yang sudah dikonfirmasi penempatannya.</p></div><a class="btn btn-outline-primary" href="{{ request()->fullUrl() }}"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Perbarui tampilan</a></header>
    <div class="fulfillment-summary" aria-label="Ringkasan hasil pencarian">
        <div><span>Rencana dipantau</span><strong>{{ number_format($counts->sum(), 0, ',', '.') }}</strong></div>
        <div><span>Dalam pemenuhan</span><strong>{{ number_format($counts->get('partial', 0), 0, ',', '.') }}</strong></div>
        <div><span>Terpenuhi</span><strong>{{ number_format($counts->get('complete', 0), 0, ',', '.') }}</strong></div>
    </div>
    <form method="GET" class="fulfillment-filters" aria-label="Filter pemenuhan permintaan">
        <div><label class="form-label" for="fulfillment-search">Kode rencana atau customer</label><input class="form-control" type="search" id="fulfillment-search" name="q" maxlength="100" value="{{ $search }}" placeholder="Cari rencana delivery"></div>
        <div><label class="form-label" for="fulfillment-status">Status pemenuhan</label><select class="form-select" id="fulfillment-status" name="status"><option value="">Semua status</option>@foreach($states as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }} ({{ $counts->get($key, 0) }})</option>@endforeach</select></div>
        <div class="plan-inline-actions"><button class="btn btn-primary" type="submit">Tampilkan</button>@if($search !== '' || $status !== '')<a class="btn btn-outline-secondary" href="{{ route('export.fulfillment.index') }}">Reset</a>@endif</div>
    </form>
    <p class="plan-help mb-3">Urutan mengikuti tenggat terdekat. Reservasi menunggu penempatan belum dihitung sebagai terpenuhi.</p>
    <section class="plan-panel" aria-labelledby="fulfillment-list-title">
        <div class="plan-panel-heading"><h2 id="fulfillment-list-title">Rencana yang dipantau</h2><span>{{ $plans->total() }} rencana</span></div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Daftar pemenuhan permintaan"><table class="table plan-table fulfillment-overview-table"><thead><tr><th scope="col">Rencana / customer</th><th scope="col">Batas pengiriman</th><th scope="col">Pallet</th><th scope="col">Butuh box</th><th scope="col">Terpenuhi</th><th scope="col">Reservasi</th><th scope="col">Sisa</th><th scope="col">Status</th><th scope="col">Aksi</th></tr></thead><tbody>
            @forelse($plans as $plan)
                <tr><th scope="row"><span>{{ $plan->plan_code ?? 'Tanpa kode' }}</span><small class="fulfillment-customer">{{ $plan->customer_name }}</small></th>
                    <td>{{ \Illuminate\Support\Carbon::parse($plan->delivery_deadline)->format('d/m/Y') }}</td><td>{{ $plan->pallet_count }}</td>
                    <td>{{ number_format($plan->metrics['required'], 0, ',', '.') }}</td><td>{{ number_format($plan->metrics['fulfilled'], 0, ',', '.') }}</td><td>{{ number_format($plan->metrics['reserved'], 0, ',', '.') }}</td><td>{{ number_format($plan->metrics['remaining'], 0, ',', '.') }}</td>
                    <td>@include('export.fulfillment.status', ['state' => $plan->fulfillment_status])<small class="fulfillment-progress-label">{{ $plan->metrics['percent'] }}% ditempatkan</small></td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('export.fulfillment.show', $plan->delivery_id) }}" aria-label="Lihat pemenuhan {{ $plan->plan_code ?? $plan->customer_name }}">Lihat detail</a></td></tr>
            @empty
                <tr><td colspan="9" class="plan-empty"><i class="bi bi-clipboard-data" aria-hidden="true"></i><strong>{{ $search !== '' || $status !== '' ? 'Tidak ada rencana yang cocok' : 'Belum ada permintaan untuk dipenuhi' }}</strong><span>{{ $search !== '' || $status !== '' ? 'Ubah pencarian atau status untuk melihat rencana lainnya.' : 'Finalisasi perencanaan pallet agar kebutuhannya muncul di sini.' }}</span>@if($search === '' && $status === '' && auth()->user()->canAccess('export_pallet_plans'))<a class="btn btn-outline-primary mt-3" href="{{ route('export.pallet-plans.index') }}">Buka perencanaan pallet</a>@endif</td></tr>
            @endforelse
        </tbody></table></div>
        @if($plans->hasPages())<div class="plan-panel-body">{{ $plans->links('pagination::bootstrap-5') }}</div>@endif
    </section>
    <p class="plan-help">Rencana yang dibuka untuk revisi tetap terlihat dengan status Perlu revisi. Data dimuat {{ now()->format('d/m/Y H:i') }}.</p>
</div>
@endsection
