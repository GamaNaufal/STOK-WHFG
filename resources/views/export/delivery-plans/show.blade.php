@extends('shared.layouts.app')
@section('title', 'Rencana Delivery - '.$plan->customer_name)
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">@endsection
@section('content')
<div class="delivery-plan-page">
    <header class="plan-header">
        <div><a class="plan-back" href="{{ route('export.delivery-plans.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Rencana delivery</a><span class="plan-eyebrow">Pengiriman ekspor / {{ $printReady ? 'Perencanaan final' : 'Draft' }}</span><h1>{{ $plan->customer_name }}</h1><p>Kode rencana <strong>{{ $plan->plan_code ?? '—' }}</strong></p><p>Batas pengiriman {{ $plan->delivery_deadline->format('d/m/Y') }}</p></div>
        <div class="plan-inline-actions">@if($printReady)<a class="btn btn-outline-primary" href="{{ route('export.delivery-plans.print', $plan) }}" target="_blank" rel="noopener">Cetak rencana delivery</a>@endif<a class="btn btn-primary" href="{{ route('export.delivery-plans.edit', $plan) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Ubah rencana</a></div>
    </header>
    <x-delivery-announcement-notes :delivery="$plan" surface="export_delivery" />
    @unless($printReady)<p class="plan-help mb-3">Cetak rencana delivery tersedia setelah perencanaan pallet final.</p>@endunless
    @include('export.not-full.requirements',['notFullDelivery'=>$plan])
    <section class="plan-panel">
        @if(auth()->user()->canAccess('export_pallet_plans'))
            <div class="plan-panel-body"><a class="btn btn-outline-primary" href="{{ route('export.pallet-plans.show', $plan) }}">Perencanaan Pallet</a></div>
        @endif
        <div class="plan-panel-heading"><div><h2>Kebutuhan pengiriman</h2><p>Kapasitas box disimpan saat part ditambahkan ke rencana.</p></div><span>{{ $plan->items->count() }} nomor part</span></div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Kebutuhan part rencana delivery">
            <table class="table plan-table"><thead><tr><th scope="col">No</th><th scope="col">Nomor part</th><th scope="col">Jumlah PCS</th><th scope="col">PCS / box</th><th scope="col">Jumlah box</th></tr></thead>
                <tbody>@foreach($plan->items as $item)
                    <tr><td>{{ $loop->iteration }}</td><th scope="row">{{ $item->document_part_number ?? $item->part_number }}@if($item->document_part_number && $item->document_part_number !== $item->part_number)<small class="d-block text-muted">Label / scan: {{ $item->part_number }}</small>@endif</th><td>{{ number_format($item->pcs_quantity, 0, ',', '.') }}</td><td>{{ number_format($item->pcs_per_box, 0, ',', '.') }}</td><td><strong>{{ number_format($item->box_quantity, 0, ',', '.') }}</strong>@if($item->not_full_pcs)<small class="d-block text-warning-emphasis">Termasuk {{ \App\Services\ExportBoxRequirements::notFullLabel($item) }}</small>@endif</td></tr>
                @endforeach</tbody>
            </table>
        </div>
        <div class="plan-totals"><span>Total PCS <strong>{{ number_format($plan->total_pcs, 0, ',', '.') }}</strong></span><span>Total box <strong>{{ number_format($plan->total_boxes, 0, ',', '.') }}</strong></span></div>
    </section>
    <p class="plan-help">Dibuat oleh {{ $plan->creator->name }} · {{ $plan->created_at->format('d/m/Y H:i') }} · Revisi {{ $plan->revision }}
        @if($plan->source_type !== 'manual') · Sumber {{ $plan->source_type === 'photo' ? 'foto (riwayat)' : 'Excel' }}: {{ $plan->source_name }} @endif
    </p>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @if(auth()->user()->canDeleteExportDeliveryPlan())
    <section class="plan-panel" id="delete-delivery-plan" aria-labelledby="delete-delivery-title">
        <div class="plan-panel-heading"><h2 id="delete-delivery-title">Hapus rencana delivery</h2></div>
        <div class="plan-panel-body">
            <p class="plan-help mb-3">Klik Hapus rencana untuk melihat daftar box, posisi tercatat, dan reservasi lokasi terkait. Penghapusan baru dijalankan setelah tinjauan dan konfirmasi terakhir.</p>
            <a href="{{ route('export.delivery-plans.show', $plan) }}#delete-delivery-plan" class="btn btn-outline-danger" data-delete-delivery-preview data-preview-url="{{ route('export.delivery-plans.deletion-preview', $plan) }}"><i class="bi bi-trash" aria-hidden="true"></i> Hapus rencana</a>
        </div>
    </section>
    @endif
</div>
@endsection
@section('scripts')
<script src="{{ asset('js/pages/export-delivery-delete.js') }}?v={{ filemtime(public_path('js/pages/export-delivery-delete.js')) }}"></script>
@endsection
