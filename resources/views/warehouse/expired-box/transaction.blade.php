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
    <a href="{{ route('expired-box.index') }}">← Daftar transaksi</a>
    <header class="expired-page-header expired-transaction-header mt-3">
        <div><span class="expired-page-eyebrow">Transaksi penanganan</span><h1>{{ $transaction->reference }}</h1><p>{{ $members->count() }} box · {{ number_format($members->sum('pcs_quantity')) }} PCS · Dibuat {{ $transaction->created_at->format('d M Y H:i') }}</p></div>
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

    @if($action)
    <div class="expired-transaction-action-heading"><h2>{{ $transaction->action_label }}</h2><p>{{ $records->count() }} box dalam transaksi ini akan diproses bersama. Tidak perlu memilih box lagi.</p></div>
    @include('warehouse.expired-box.transaction-form')
    @else
    <section class="expired-page-panel p-4"><h2>Transaksi selesai</h2><p class="mb-0">{{ $received }} box diterima dengan ID baru dan {{ $rejected }} box ditolak QA. Umur box tetap mengikuti tanggal masuk aslinya.</p></section>
    @endif

    <section class="expired-page-panel" aria-labelledby="transaction-items">
        <header class="expired-page-panel-heading"><div><h2 id="transaction-items">Seluruh box dalam transaksi</h2><p>Keanggotaan tetap sejak penyerahan awal. Buka jejak box untuk melihat petugas, waktu, dan riwayat lengkap.</p></div>
        @if($transaction->stage !== 'at_qa' && $members->count() > $rejected)<a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('expired-box.transactions.print', $transaction) }}">Cetak pengantar</a>@endif</header>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Box anggota transaksi"><table class="table expired-page-table mb-0"><thead><tr><th>ID lama → ID baru</th><th>Part / PCS</th><th>Hasil QA</th><th>Status box</th><th>Batas aktif hasil packing</th><th>Jejak</th></tr></thead><tbody>
        @foreach($members as $member)
        <tr><td><strong>{{ $member->source_box_number }}</strong> → {{ $member->replacement_box_number ?? 'Belum terbit' }}</td><td>{{ $member->part_number }}<small class="d-block">{{ $member->pcs_quantity }} PCS</small></td><td>{{ $member->qa_decided_at ? ($member->status === 'rejected' ? 'Tidak lolos' : 'Lolos') : 'Menunggu' }}@if($member->qa_notes)<small class="d-block">{{ $member->qa_notes }}</small>@endif</td><td>{{ $member->status_label }}</td><td>{{ $member->usable_until?->format('d M Y H:i') ?? '—' }}</td><td><a href="{{ route('expired-box.show', $member) }}">Jejak box {{ $member->source_box_number }}</a></td></tr>
        @endforeach
        </tbody></table></div>
    </section>
</div>
@endsection
@section('scripts')<script src="{{ asset('js/expired-box-bulk.js') }}" defer></script>@endsection
