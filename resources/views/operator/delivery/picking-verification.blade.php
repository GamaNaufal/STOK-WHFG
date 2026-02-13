@extends('shared.layouts.app')

@section('title', 'Picking Verification')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-upc-scan"></i> Picking Verification</h1>
        <p class="text-muted mb-0">Pilih jadwal delivery, lalu mulai verifikasi scan box.</p>
    </div>
    <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali ke Delivery
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-light fw-bold">Jadwal Delivery Aktif</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Order</th>
                        <th>Customer</th>
                        <th>Tanggal</th>
                        <th>Total Item</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="ps-3 fw-bold">#{{ $order->id }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>{{ $order->delivery_date?->format('d M Y') }}</td>
                            <td>{{ $order->items->count() }}</td>
                            <td>
                                <span class="badge {{ $order->status === 'approved' ? 'bg-primary' : 'bg-warning text-dark' }}">
                                    {{ strtoupper($order->status) }}
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-success js-start-verification" data-order-id="{{ $order->id }}">
                                    <i class="bi bi-play-circle"></i> Mulai Verifikasi
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Tidak ada jadwal delivery aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
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
                    alert(data.message || 'Gagal membuat sesi picking.');
                }
            })
            .catch(() => {
                btn.disabled = false;
                if (typeof showToast === 'function') {
                    showToast('Gagal koneksi.', 'danger');
                } else {
                    alert('Gagal koneksi.');
                }
            });
        });
    });
</script>
@endsection
