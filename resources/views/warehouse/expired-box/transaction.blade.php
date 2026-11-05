@extends('shared.layouts.app')
@section('title', $transaction->reference)
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">@endsection
@section('content')
@php
    $members = $transaction->handlings;
    $rejected = $members->where('status', 'rejected')->count();
    $received = $members->where('status', 'completed')->count();
    $step = array_search($transaction->stage, ['at_qa', 'awaiting_packing', 'at_packing', 'completed']);
    $steps = ['Pemeriksaan QA', 'Penyerahan packing', 'Penerimaan', 'Selesai'];
    if ($members->count() === $rejected) {
        $steps = ['Pemeriksaan QA', 'Selesai'];
        $step = 1;
    }
@endphp
<div class="expired-page">
    <a href="{{ route($expiredRoutePrefix.'index') }}">← Daftar transaksi</a>
    <header class="expired-page-header expired-transaction-header mt-3">
        <div><span class="expired-page-eyebrow">Transaksi penanganan · {{ $scopeLabel }}</span><h1>{{ $transaction->reference }} <span class="expired-scope-badge">{{ $scopeLabel }}</span></h1><p>{{ $members->pluck('part_number')->unique()->join(', ') }} · {{ $members->count() }} box · {{ number_format($members->sum('pcs_quantity')) }} PCS · Dibuat {{ $transaction->created_at->format('d M Y H:i') }}</p></div>
        <span class="expired-transaction-status is-{{ $transaction->stage }}">{{ $transaction->status_label }}</span>
    </header>
    <ol class="expired-transaction-steps" aria-label="Tahap transaksi">
        @foreach($steps as $label)
        <li class="{{ $loop->index < $step ? 'is-done' : '' }} {{ $loop->index === $step ? 'is-current' : '' }}" @if($loop->index === $step) aria-current="step" @endif><span>{{ $loop->iteration }}</span>{{ $label }}</li>
        @endforeach
    </ol>
    @foreach(['success', 'error'] as $message)
        @if(session($message))<div role="status" class="alert alert-{{ $message === 'error' ? 'danger' : 'success' }}">{{ session($message) }}</div>@endif
    @endforeach
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Transaksi belum disimpan.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if($rejected)
    <p class="expired-transaction-notice">{{ $rejected }} dari {{ $members->count() }} box ditolak QA. Jejaknya tetap di transaksi ini. @if($members->count() > $rejected){{ $members->count() - $rejected }} box lolos mengikuti proses packing.@else Tidak ada box yang dilanjutkan ke packing.@endif</p>
    @endif

    <div class="expired-receipt-summary" aria-label="Ringkasan penanganan">
        <div><span>Lolos QA</span><strong>{{ $members->whereNotNull('qa_decided_at')->where('status', '!=', 'rejected')->count() }}</strong></div>
        <div><span>Ditolak QA</span><strong>{{ $rejected }}</strong></div>
        <div><span>Sudah diterima</span><strong>{{ $received }}</strong></div>
        <div><span>Menunggu packing kembali</span><strong>{{ $members->where('status', 'at_packing')->count() }}</strong></div>
    </div>

    @if($action)
    <div class="expired-transaction-action-heading"><h2>{{ $transaction->action_label }}</h2><p>@if($action === 'receive'){{ $records->count() }} box menunggu hasil packing. Penerimaan boleh bertahap sesuai box yang sudah kembali.@else{{ $records->count() }} box dalam transaksi ini akan diproses bersama. Tidak perlu memilih box lagi.@endif</p></div>
    @include('warehouse.expired-box.transaction-form')
    @else
    <section class="expired-page-panel p-4"><h2>Transaksi selesai</h2><p class="mb-0">{{ $received }} box diterima dengan ID baru dan {{ $rejected }} box ditolak QA. Umur box tetap mengikuti tanggal masuk aslinya.</p></section>
    @endif

    <details class="expired-page-panel" aria-labelledby="transaction-items"><summary class="p-3" id="transaction-items">Rincian box asal dan QA · {{ $members->count() }} box</summary>
        <header class="expired-page-panel-heading"><div><h2>Seluruh box dalam transaksi</h2><p>Keanggotaan tetap sejak penyerahan awal. Buka jejak box untuk melihat petugas, waktu, dan riwayat lengkap.</p></div>
        </header>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Box anggota transaksi"><table class="table expired-page-table mb-0"><thead><tr><th>ID lama → ID baru</th><th>Part / PCS</th><th>Hasil QA</th><th>Status box</th><th>Batas aktif hasil packing</th><th>Jejak</th></tr></thead><tbody>
        @foreach($members as $member)
        <tr><td><strong>{{ $member->source_box_number }}</strong> → {{ $member->replacement_box_number ?? 'Belum diterima' }}@if($member->replacement_mapping === 'automatic_fifo')<small class="d-block text-muted">Acuan asal otomatis FIFO</small>@endif</td><td>{{ $member->part_number }}<small class="d-block">{{ $member->pcs_quantity }} PCS</small></td><td>{{ $member->qa_decided_at ? ($member->status === 'rejected' ? 'Tidak lolos' : 'Lolos') : 'Menunggu' }}@if($member->qa_notes)<small class="d-block">{{ $member->qa_notes }}</small>@endif</td><td>{{ $member->status_label }}</td><td>{{ $member->usable_until?->format('d M Y H:i') ?? '—' }}</td><td><a href="{{ route($expiredRoutePrefix.'show', $member) }}">Jejak box {{ $member->source_box_number }}</a></td></tr>
        @endforeach
        </tbody></table></div>
    </details>
    @if($transaction->receipts->isNotEmpty())
    <details class="expired-page-panel" aria-labelledby="packing-receipts"><summary class="p-3" id="packing-receipts">Riwayat penerimaan packing · {{ $transaction->receipts->count() }} penerimaan</summary>
        <header class="expired-page-panel-heading"><div><h2>Riwayat penerimaan packing</h2><p>Setiap penerimaan menyimpan waktu, petugas, ID Box baru, serta tujuan penyimpanan saat diterima.</p></div></header>
        <div class="table-responsive"><table class="table expired-page-table mb-0"><thead><tr><th>Diterima</th><th>Box / PCS</th><th>Tujuan</th><th>Petugas</th><th>ID Box baru</th></tr></thead><tbody>
        @foreach($transaction->receipts as $receipt)
        <tr><td>{{ $receipt->received_at->format('d M Y H:i:s') }}</td><td>{{ $receipt->handlings->count() }} box · {{ number_format($receipt->handlings->sum('pcs_quantity')) }} PCS</td><td>{{ $receipt->destination_pallet }} / {{ $receipt->destination_location }}</td><td>{{ $receipt->receiver?->name ?? 'Petugas #'.$receipt->received_by }}</td><td>{{ $receipt->handlings->pluck('replacement_box_number')->join(', ') }}</td></tr>
        @endforeach
        </tbody></table></div>
    </details>
    @endif
</div>
@endsection
@section('scripts')
<script src="{{ asset('js/expired-box-bulk.js') }}?v={{ filemtime(public_path('js/expired-box-bulk.js')) }}" defer></script>
@if($action === 'receive')
@include('shared.partials.scan-feedback')
<script src="{{ asset('js/expired-box-receiving.js') }}?v={{ filemtime(public_path('js/expired-box-receiving.js')) }}" defer></script>
@endif
@endsection
