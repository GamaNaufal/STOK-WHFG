@extends('shared.layouts.app')

@section('title', 'Picking Verification')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/picking-verification-list.css') }}">
@endsection

@section('content')
@php
    $readyCount = $orders->filter(fn ($order) => ! empty($order->is_ready_to_pick))->count();
@endphp
<div id="pickingVerificationListPage" class="picking-verification-list-page">
    <header class="verification-list-header">
        <div class="verification-list-heading">
            <span class="verification-list-eyebrow">Delivery / Picking</span>
            <h1><i class="bi bi-upc-scan" aria-hidden="true"></i> Picking Verification</h1>
            <p>Pilih jadwal yang siap untuk memulai atau melanjutkan verifikasi box.</p>
        </div>
        <a href="{{ route('delivery.index') }}" class="btn verification-list-back">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke Delivery
        </a>
    </header>

    <section class="card verification-list-panel" aria-labelledby="verificationOrdersTitle">
        <div class="verification-list-toolbar">
            <div>
                <span class="verification-list-eyebrow">Jadwal aktif</span>
                <h2 id="verificationOrdersTitle">Order untuk diverifikasi</h2>
                <p id="verificationOrdersHint">Urut tanggal pengiriman. Maksimal 100 jadwal aktif ditampilkan.</p>
            </div>
            <div class="verification-list-summary" aria-label="Ringkasan jadwal">
                <span><strong>{{ $orders->count() }}</strong> order</span>
                <span class="verification-list-summary-ready"><strong>{{ $readyCount }}</strong> aksi tersedia</span>
            </div>
        </div>
        <div class="verification-list-scroll" tabindex="0" role="region" aria-label="Daftar jadwal Picking Verification" aria-describedby="verificationOrdersHint">
            <table class="table verification-list-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Tanggal</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Total box</th>
                        <th scope="col">Status</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php
                            $isReady = ! empty($order->is_ready_to_pick);
                            $isOwnSession = ! empty($order->active_pick_owned_by_current_user);
                        @endphp
                        <tr class="{{ $isOwnSession ? 'verification-list-own-session' : '' }}">
                            <td class="verification-list-order">#{{ $order->id }}</td>
                            <td>{{ $order->delivery_date?->format('d M Y') }}</td>
                            <td class="verification-list-customer">{{ $order->customer_name }}</td>
                            <td class="verification-list-boxes">{{ (int) ($order->total_box_to_pick ?? 0) }} <small>box</small></td>
                            <td>
                                <span class="verification-list-status {{ $isOwnSession ? 'is-active' : ($isReady ? 'is-ready' : 'is-unavailable') }}">
                                    @if($isOwnSession)
                                        Sesi Anda
                                    @elseif(! empty($order->has_active_pick_session))
                                        Sesi aktif
                                    @elseif(! empty($order->has_pending_additional_approval))
                                        Menunggu approval
                                    @elseif($isReady)
                                        Siap
                                    @else
                                        Belum tersedia
                                    @endif
                                </span>
                            </td>
                            <td class="verification-list-action">
                                <button type="button" class="btn btn-verify js-start-verification" data-order-id="{{ $order->id }}" @disabled(! $isReady)>
                                    <i class="bi {{ $isOwnSession ? 'bi-arrow-repeat' : 'bi-play-circle' }}" aria-hidden="true"></i>
                                    {{ $isOwnSession ? 'Lanjutkan Verifikasi' : 'Mulai Verifikasi' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="verification-list-empty">Tidak ada jadwal delivery aktif untuk diverifikasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="verification-list-footnote">Geser tabel untuk melihat semua kolom. Nomor order tetap terlihat saat digeser.</p>
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.js-start-verification').forEach((btn) => {
        btn.addEventListener('click', () => {
            const orderId = btn.getAttribute('data-order-id');
            if (!orderId) return;

            btn.disabled = true;

            fetch(`/delivery-stock/${orderId}/start-verification`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then((res) => res.json())
            .then((data) => {
                if (data.verify_url) {
                    window.location.href = data.verify_url;
                    return;
                }

                btn.disabled = false;
                if (typeof showToast === 'function') {
                    showToast(data.message || 'Gagal membuat sesi picking.', 'danger');
                } else {
                    WarehouseAlert.error({ message: data.message || 'Gagal membuat sesi picking.' });
                }
            })
            .catch(() => {
                btn.disabled = false;
                if (typeof showToast === 'function') {
                    showToast('Gagal koneksi.', 'danger');
                } else {
                    WarehouseAlert.error({ message: 'Gagal koneksi.' });
                }
            });
        });
    });
</script>
@endsection
