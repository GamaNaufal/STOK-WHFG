@extends('shared.layouts.app')

@section('title', 'Koreksi Delivery Order')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/pages/sales-delivery-order.css') }}">
@endsection

@section('content')
@php
    $initialItems = collect(old('items', $order->items->map(fn ($item) => [
        'part_number' => $item->part_number,
        'quantity' => $item->quantity,
    ])->all()));
    if ($initialItems->isEmpty()) $initialItems = collect([['part_number' => '', 'quantity' => '']]);
@endphp
<div class="sales-order-page sales-order-edit-page">
    <a href="{{ route('delivery.create') }}" class="sales-order-back-link">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke pesanan saya
    </a>
    <header class="sales-order-page-header">
        <span class="sales-order-eyebrow">Sales / Koreksi pesanan</span>
        <h1>Perbaiki Delivery Order #{{ $order->id }}</h1>
        <p>Periksa catatan PPC, perbarui data yang diperlukan, lalu kirim ulang untuk ditinjau.</p>
    </header>

    <div class="sales-order-edit-layout">
        <form action="{{ route('delivery.update', $order->id) }}" method="POST"
            class="form-card sales-order-form" id="sales-order-form" data-sales-order-form="edit">
            @csrf
            @method('PUT')
            <div class="card-header">
                <span class="sales-order-card-icon is-correction"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
                <div>
                    <h2>Koreksi permintaan</h2>
                    <p>Nomor part dan jumlah PCS dapat diperbarui sebelum diajukan kembali.</p>
                </div>
            </div>
            <div class="card-body">
                <section class="sales-order-ppc-note" aria-labelledby="sales-order-ppc-note-title">
                    <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                    <div>
                        <h3 id="sales-order-ppc-note-title">Catatan dari PPC</h3>
                        <p>{{ $order->notes ?: 'Tidak ada catatan tambahan dari PPC.' }}</p>
                    </div>
                </section>
                @include('operator.delivery.partials.sales-order-fields')
                <div class="sales-order-submit-panel">
                    <p><i class="bi bi-info-circle" aria-hidden="true"></i> Setelah dikirim ulang, status berubah menjadi <strong>menunggu</strong> untuk review PPC.</p>
                    <button type="submit" class="btn btn-primary sales-order-submit"><i class="bi bi-send" aria-hidden="true"></i> Kirim ulang koreksi</button>
                </div>
            </div>
        </form>

        <aside class="sales-order-edit-context" aria-labelledby="sales-order-context-title">
            <span class="sales-order-eyebrow">Alur koreksi</span>
            <h2 id="sales-order-context-title">Pesanan dikembalikan PPC</h2>
            <p>Perubahan akan menggantikan daftar item sebelumnya. PPC perlu meninjau ulang sebelum pesanan diproses.</p>
            <span class="sales-order-context-status"><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Perlu koreksi</span>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/pages/sales-delivery-order.js') }}"></script>
@endsection
