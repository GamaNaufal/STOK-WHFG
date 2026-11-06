@extends('admin.delivery-announcements.layout')
@section('title', 'Pengumuman Delivery')
@section('announcement-content')
<header class="plan-header">
    <div><span class="plan-eyebrow">Lokal dan Ekspor</span><h1>Pengumuman Delivery</h1><p>Instruksi untuk customer dan nomor part tertentu. Satu kuota berlaku di kedua lingkup.</p></div>
    <a href="{{ route('delivery-announcements.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Buat pengumuman</a>
</header>
<section class="announcement-grid" aria-label="Daftar pengumuman">
    @forelse($announcements as $announcement)
        @php($usage = $announcement->usage())
        <article class="plan-panel">
            <div class="plan-panel-body announcement-card">
                <div class="announcement-card-heading">
                    <div><span class="plan-eyebrow">Part {{ $announcement->part_number }}</span><h2>{{ $announcement->customer_name }}</h2></div>
                    @include('admin.delivery-announcements.badge')
                </div>
                <p class="announcement-preview">{{ $announcement->message }}</p>
                <div class="announcement-card-footer">
                    <span><strong>{{ number_format($usage['remaining'], 0, ',', '.') }}</strong> {{ $announcement->unit }} tersedia</span>
                    <a href="{{ route('delivery-announcements.show', $announcement) }}" class="btn btn-outline-primary" aria-label="Buka pengumuman {{ $announcement->customer_name }} part {{ $announcement->part_number }}">Lihat detail</a>
                </div>
            </div>
        </article>
    @empty
        <div class="plan-panel plan-empty"><strong>Belum ada pengumuman</strong><span>Buat instruksi pertama untuk customer dan nomor part yang akan dikirim.</span></div>
    @endforelse
</section>
<div class="mt-3">{{ $announcements->links('pagination::bootstrap-5') }}</div>
@endsection
