@extends('shared.layouts.app')
@section('title', 'Perencanaan Pallet Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
<div class="delivery-plan-page">
    <header class="plan-header"><div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Perencanaan pallet</h1><p>Pilih rencana delivery, lalu tentukan isi dan lokasi setiap pallet.</p></div></header>
    <form method="GET" class="plan-search"><label class="form-label" for="pallet-search">Cari kode rencana atau customer</label><div class="plan-inline-actions"><input class="form-control" type="search" id="pallet-search" name="q" value="{{ $search }}" maxlength="100"><button class="btn btn-outline-primary">Cari</button></div></form>
    <section class="plan-panel"><div class="plan-panel-heading"><h2>Rencana delivery</h2><span>{{ $deliveries->total() }} rencana</span></div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Daftar rencana untuk pembagian pallet"><table class="table plan-table"><thead><tr><th>Kode rencana</th><th>Customer</th><th>Batas pengiriman</th><th>Total box</th><th>Perencanaan</th><th>Aksi</th></tr></thead><tbody>
            @forelse($deliveries as $delivery)
                <tr><th scope="row">{{ $delivery->plan_code ?? '—' }}</th><td>{{ $delivery->customer_name }}</td><td>{{ $delivery->delivery_deadline->format('d/m/Y') }}</td><td>{{ number_format($delivery->total_boxes, 0, ',', '.') }}</td><td>{{ $delivery->palletPlan?->status_label ?? 'Belum dibuat' }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('export.pallet-plans.show', $delivery) }}">{{ $delivery->palletPlan ? 'Lihat perencanaan' : 'Rencanakan pallet' }}</a></td></tr>
            @empty
                <tr><td colspan="6" class="plan-empty"><strong>{{ $search ? 'Tidak ada rencana yang cocok' : 'Belum ada rencana delivery' }}</strong><span>{{ $search ? 'Coba kode atau customer lain.' : 'Buat rencana delivery terlebih dahulu.' }}</span></td></tr>
            @endforelse
        </tbody></table></div>
        @if($deliveries->hasPages())<div class="plan-panel-body">{{ $deliveries->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
@endsection
