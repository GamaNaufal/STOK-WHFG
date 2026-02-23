@extends('shared.layouts.app')

@section('title', 'Picking Verification')

@section('styles')
<style>
    .verification-hero {
        background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
        color: #fff;
        padding: 26px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.18);
        margin-bottom: 20px;
    }

    .verification-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .verification-card .card-header {
        background: #f9fafb;
        border-bottom: 1px solid #e5e7eb;
        font-weight: 700;
        color: #0C7779;
        padding: 14px 18px;
    }

    .verification-table thead th {
        color: #0C7779;
        font-weight: 700;
        font-size: 12px;
        text-transform: uppercase;
        background: #f9fafb;
        border-bottom: 2px solid #e5e7eb;
        padding: 12px 14px;
        white-space: nowrap;
    }

    .verification-table tbody td {
        padding: 13px 14px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
    }

    .verification-table tbody tr:hover {
        background: #f8fffe;
    }

    .badge-ready {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
        font-weight: 700;
    }

    .badge-not-ready {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
        font-weight: 700;
    }

    .btn-verify {
        background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 7px 12px;
        font-weight: 600;
    }

    .btn-verify:disabled {
        background: #e5e7eb;
        color: #9ca3af;
        cursor: not-allowed;
    }
</style>
@endsection

@section('content')
<div class="verification-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-1"><i class="bi bi-upc-scan"></i> Picking Verification</h1>
        <p class="mb-0" style="opacity:.92;">Pilih jadwal delivery yang ready, lalu mulai verifikasi scan box.</p>
    </div>
    <a href="{{ route('delivery.index') }}" class="btn btn-light">
        <i class="bi bi-arrow-left"></i> Kembali ke Delivery
    </a>
</div>

<div class="card verification-card">
    <div class="card-header"><i class="bi bi-calendar-check"></i> Jadwal Delivery Aktif</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table verification-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Order</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>Total Box</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="ps-3 fw-bold">#{{ $order->id }}</td>
                            <td>{{ $order->delivery_date?->format('d M Y') }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>
                                <span class="fw-bold">{{ (int) ($order->total_box_to_pick ?? 0) }}</span>
                                <small class="text-muted"> box</small>
                            </td>
                            <td>
                                <span class="badge {{ !empty($order->is_ready_to_pick) ? 'badge-ready' : 'badge-not-ready' }}">
                                    {{ !empty($order->is_ready_to_pick) ? 'READY' : 'NOT READY' }}
                                </span>
                                @if(!empty($order->readiness_reason))
                                    <div class="small text-danger mt-1">{{ $order->readiness_reason }}</div>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-verify js-start-verification" data-order-id="{{ $order->id }}" {{ !empty($order->is_ready_to_pick) ? '' : 'disabled' }}>
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
