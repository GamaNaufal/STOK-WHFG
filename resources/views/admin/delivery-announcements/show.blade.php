@extends('admin.delivery-announcements.layout')
@section('title', 'Detail Pengumuman Delivery')
@section('announcement-content')
@php($usage = $announcement->usage())
<a class="plan-back" href="{{ route('delivery-announcements.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar pengumuman</a>
<header class="plan-header">
    <div><span class="plan-eyebrow">Lokal dan Ekspor</span><h1>{{ $announcement->customer_name }}</h1><p>Part {{ $announcement->part_number }} · Kuota per {{ $announcement->unit }}</p></div>
    <div class="plan-inline-actions">
        <a class="btn btn-outline-primary" href="{{ route('delivery-announcements.edit', $announcement) }}">Edit pengumuman</a>
        <form method="POST" action="{{ route('delivery-announcements.status', $announcement) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="enabled" value="{{ $announcement->enabled ? 0 : 1 }}">
            <button class="btn btn-outline-secondary" type="submit">{{ $announcement->enabled ? 'Nonaktifkan' : 'Aktifkan kembali' }}</button>
        </form>
    </div>
</header>
<section class="plan-panel">
    <div class="plan-panel-heading"><h2>Instruksi operator</h2>@include('admin.delivery-announcements.badge')</div>
    <div class="plan-panel-body">
        <p class="announcement-message">{{ $announcement->message }}</p>
        <dl class="announcement-metrics mb-0">
            <div><dt>Kuota awal</dt><dd>{{ number_format($announcement->quota, 0, ',', '.') }}</dd></div>
            <div><dt>{{ $announcement->quota_type === 'box' ? 'Sudah dikirim' : 'Delivery mendapat instruksi' }}</dt><dd>{{ number_format($usage['used'], 0, ',', '.') }}</dd></div>
            @if($announcement->quota_type === 'box')<div><dt>Box direservasi</dt><dd>{{ number_format($usage['reserved'], 0, ',', '.') }}</dd></div>@endif
            <div><dt>Masih tersedia</dt><dd>{{ number_format($usage['remaining'], 0, ',', '.') }}</dd></div>
        </dl>
    </div>
</section>
<section class="plan-panel">
    <div class="plan-panel-heading"><div><h2>Riwayat penayangan</h2><p class="plan-help mb-0">Halaman dan dokumen yang memuat instruksi, beserta akun dan waktu penayangan.</p></div></div>
    @if($history->isNotEmpty())
        <ul class="announcement-history" aria-label="Riwayat penayangan pengumuman">
            @foreach($history as $entry)
                <li>
                    <dl class="announcement-event">
                        <div><dt>Delivery</dt><dd><strong>{{ $entry->delivery_reference }}</strong><small class="d-block">{{ $entry->scope === 'export' ? 'Ekspor' : 'Lokal' }}</small></dd></div>
                        <div><dt>Ditampilkan di</dt><dd>{{ \App\Services\DeliveryAnnouncementDisplay::SURFACES[$entry->surface] ?? $entry->surface }}@if($entry->context)<small class="d-block">{{ $entry->context }}</small>@endif</dd></div>
                        <div><dt>Akun</dt><dd>{{ $entry->actor_name ?? '—' }}</dd></div>
                        <div><dt>Waktu (WIB)</dt><dd><small class="d-block">Pertama {{ \Illuminate\Support\Carbon::parse($entry->first_seen_at)->format('d/m/Y H:i') }}</small><small class="d-block">Terakhir {{ \Illuminate\Support\Carbon::parse($entry->last_seen_at)->format('d/m/Y H:i') }}</small></dd></div>
                        <div class="announcement-event-message"><dt>Instruksi saat itu</dt><dd><details>
                            <summary>Lihat instruksi</summary><p class="announcement-message mb-1">{{ $entry->message }}</p>
                            @php($numbers = json_decode($entry->box_numbers, true))
                            <small>{{ $numbers ? 'ID Box '.implode(', ', $numbers) : ($announcement->quota_type === 'delivery' ? 'Semua box part ini' : 'Menunggu penetapan ID Box') }}</small>
                        </details></dd></div>
                    </dl>
                </li>
            @endforeach
        </ul>
        <div class="plan-panel-body">{{ $history->links('pagination::bootstrap-5') }}</div>
    @else
        <div class="plan-empty"><strong>Belum ditampilkan</strong><span>Riwayat muncul setelah pengguna membuka halaman atau membuat dokumen yang memuat instruksi ini.</span></div>
    @endif
</section>
@endsection
