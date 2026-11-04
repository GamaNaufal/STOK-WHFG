@extends('shared.layouts.app')
@section('title', 'Stock Opname '.$scopeLabel)
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/stock-opname.css') }}?v={{ filemtime(public_path('css/pages/stock-opname.css')) }}">
@endsection
@section('content')
<div class="delivery-plan-page opname-page">
    <header class="plan-header">
        <div><span class="plan-eyebrow">Pemeriksaan gudang · {{ $scopeLabel }}</span><h1>Stock opname {{ $scopeLabel }}</h1><p>Periksa seluruh ID Box, lalu tinjau kesesuaian stok dan history sesi.</p></div>
        <span class="opname-scope"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Lingkup {{ $scopeLabel }}</span>
    </header>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="plan-panel opname-start">
        @if($active)
            <div><span class="opname-badge">Sesi aktif</span><h2>{{ $active->code }}</h2><p>Dimulai {{ $active->started_at->format('d/m/Y H:i') }} oleh {{ $active->starter->name }}.</p><p><strong>{{ $activeSummary['found'] }}</strong> box sesuai · {{ $activeSummary['missing'] }} belum terscan · {{ $activeSummary['ng'] }} scan NG.</p></div>
            <a class="btn btn-primary" href="{{ route($base.'.show', $active) }}">{{ $canManage ? 'Lanjutkan scan' : 'Lihat progres' }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        @else
            <div><h2>Mulai pemeriksaan {{ $scopeLabel }}</h2><p>Semua box yang masih tercatat, termasuk cadangan, reservasi, expired, dan proses QA/packing, menjadi acuan sesi. Penerimaan dan pengiriman tetap berjalan.</p></div>
            @if($canManage)
                <form method="POST" action="{{ route($base.'.start') }}">@csrf<input type="hidden" name="request_token" value="{{ $startToken }}"><button class="btn btn-primary" type="submit"><i class="bi bi-upc-scan" aria-hidden="true"></i> Mulai sesi opname</button></form>
            @else<p class="plan-help">Operator atau admin dapat memulai sesi baru.</p>@endif
        @endif
    </section>
    <section class="plan-panel">
        <div class="plan-panel-heading"><h2>History stock opname {{ $scopeLabel }}</h2><span>{{ $history->total() }} sesi selesai</span></div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="History stock opname {{ $scopeLabel }}">
            <table class="table plan-table mb-0">
                <thead><tr><th>Sesi / operator</th><th>Waktu pemeriksaan</th><th>Box sesuai</th><th>Belum terscan</th><th>Scan NG</th><th></th></tr></thead>
                <tbody>
                @forelse($history as $entry)
                    <tr><th>{{ $entry->code }}<small>{{ $entry->starter->name }}</small></th><td>{{ $entry->started_at->format('d/m/Y H:i') }}<small>Selesai {{ $entry->finished_at->format('d/m/Y H:i') }}</small></td><td>{{ $entry->summary['found'] }}</td><td>{{ $entry->summary['missing'] }}</td><td>{{ $entry->summary['ng'] }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route($base.'.show', $entry) }}">Lihat hasil</a></td></tr>
                @empty<tr><td colspan="6" class="opname-empty">Belum ada sesi opname {{ $scopeLabel }} yang selesai.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        @if($history->hasPages())<div class="plan-panel-body">{{ $history->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
@endsection
