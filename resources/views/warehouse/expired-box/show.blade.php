@extends('shared.layouts.app')
@section('title', $handling->reference)
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">
@endsection
@section('content')
<div class="expired-page">
    <header class="expired-page-header">
        <a href="{{ route('expired-box.index') }}">← Daftar expired dan penanganan</a>
        <h1 class="mt-3">{{ $handling->reference }}</h1>
        <p>{{ $handling->status_label }}</p>
    </header>
    @foreach(['success', 'error'] as $message)
        @if(session($message))<div role="status" class="alert alert-{{ $message === 'error' ? 'danger' : 'success' }}">{{ session($message) }}</div>@endif
    @endforeach
    @if($errors->any())<div role="alert" class="alert alert-danger"><strong>Periksa kembali isian:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="expired-page-panel p-4" aria-label="Identitas box">
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3"><small>ID box asal</small><div class="fw-bold">{{ $handling->source_box_number }}</div></div>
            <div class="col-sm-6 col-lg-3"><small>Part / PCS tetap</small><div class="fw-bold">{{ $handling->part_number }} / {{ $handling->pcs_quantity }} PCS</div></div>
            <div class="col-sm-6 col-lg-3"><small>Masuk asli · dasar umur/FIFO</small><div class="fw-bold">{{ $handling->original_stored_at->format('d M Y H:i') }}</div></div>
            <div class="col-sm-6 col-lg-3"><small>ID pengganti</small><div class="fw-bold">{{ $handling->replacement_box_number ?? 'Belum diterima' }}</div></div>
        </div>
    </section>
    <section class="expired-page-panel p-4"><h2>Bagian dari transaksi {{ $handling->transaction->reference }}</h2><p>Proses QA dan packing dilanjutkan bersama box lain di halaman transaksi.</p><a class="btn btn-primary" href="{{ route('expired-box.transactions.show', $handling->transaction_id) }}">Buka transaksi</a></section>
    @if($handling->status === 'completed')
    <section class="expired-page-panel p-4"><h2>Penggantian ID selesai</h2><p>{{ $handling->source_box_number }} → <strong>{{ $handling->replacement_box_number }}</strong></p><p>Batas aktif saat diterima: <strong>{{ $handling->usable_until->format('d M Y H:i:s') }}</strong>. Lihat Stok menunjukkan kondisi stok terkini.</p><p>Tujuan saat diterima: {{ $handling->destination_pallet }} / {{ $handling->destination_location }}.</p></section>
    @elseif($handling->status === 'rejected')
    <section class="expired-page-panel p-4"><h2>Box ditolak QA dan dihapus permanen</h2><p>Identitas lama tetap tercatat sebagai pernah digunakan. Snapshot sebelum penghapusan tersedia melalui unduhan jejak.</p></section>
    @endif
    <section class="expired-page-panel p-4">
        <h2>Jejak penanganan</h2>
        @if($originHandling)<p>Asal siklus sebelumnya: <a href="{{ route('expired-box.show', $originHandling) }}">{{ $originHandling->reference }} · {{ $originHandling->source_box_number }} → {{ $originHandling->replacement_box_number }}</a></p>@endif
        @if($nextHandling)<p>Penanganan berikutnya: <a href="{{ route('expired-box.show', $nextHandling) }}">{{ $nextHandling->reference }} · {{ $nextHandling->status_label }}</a></p>@endif
        @if($handling->qa_notes)<p><strong>Catatan QA:</strong> {{ $handling->qa_notes }}</p>@endif
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box"><table class="table"><thead><tr><th>Kejadian</th><th>Waktu</th><th>Petugas</th></tr></thead><tbody>
            @foreach(['sent_qa' => 'Diserahkan ke QA', 'qa_decided' => 'Hasil QA dicatat', 'sent_packing' => 'Diserahkan ke packing', 'received' => 'Hasil packing diterima'] as $event => $label)
            @if($handling->{$event.'_at'})<tr><td>{{ $label }}</td><td>{{ $handling->{$event.'_at'}->format('d M Y H:i:s') }}</td><td>{{ $users[$handling->{$event.'_by'}] ?? 'Petugas #'.$handling->{$event.'_by'} }}</td></tr>@endif
            @endforeach
        </tbody></table></div>
        <a class="btn btn-outline-secondary" href="{{ route('expired-box.trace', $handling) }}">Unduh jejak lengkap</a>
    </section>
</div>
@endsection
