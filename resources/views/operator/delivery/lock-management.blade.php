@extends('shared.layouts.app')

@section('title', 'Delivery Lock Management')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/delivery-lock-management.css') }}">
@endsection

@section('content')
@php
    $blockedCount = $activeLocks->where('status', 'blocked')->count();
    $scanningCount = $activeLocks->where('status', 'scanning')->count();
@endphp
<div id="deliveryLockManagementPage" class="delivery-lock-management-page">
    <header class="delivery-lock-header">
        <div>
            <span class="delivery-lock-eyebrow">Delivery / Administrasi sesi</span>
            <h1><i class="bi bi-shield-lock" aria-hidden="true"></i> Pengelolaan lock delivery</h1>
            <p>Pantau sesi yang masih mengunci proses picking dan scan. Terminate hanya untuk sesi yang perlu dihentikan secara administratif.</p>
        </div>
        <a href="{{ route('delivery.index') }}" class="btn delivery-lock-back">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Delivery Schedule
        </a>
    </header>

    <div class="delivery-lock-summary" aria-label="Ringkasan lock aktif">
        <div class="delivery-lock-summary-item">
            <span>Total lock aktif</span>
            <strong>{{ $activeLocks->count() }}</strong>
        </div>
        <div class="delivery-lock-summary-item is-scanning">
            <span>Scanning</span>
            <strong>{{ $scanningCount }}</strong>
        </div>
        <div class="delivery-lock-summary-item is-blocked">
            <span>Blocked</span>
            <strong>{{ $blockedCount }}</strong>
        </div>
    </div>

    <section class="card delivery-lock-panel" aria-labelledby="activeLocksTitle">
        <div class="delivery-lock-panel-header">
            <div>
                <span class="delivery-lock-eyebrow">Perlu pemantauan</span>
                <h2 id="activeLocksTitle">Sesi yang mengunci proses</h2>
                <p>Terminate membatalkan sesi, melepas lock box, dan menghapus issue pending pada sesi tersebut. Isi alasan sebelum melanjutkan.</p>
            </div>
            <span class="delivery-lock-count">{{ $activeLocks->count() }} sesi</span>
        </div>
        <div class="delivery-lock-scroll" tabindex="0" role="region" aria-label="Sesi delivery dengan lock aktif" aria-describedby="activeLocksHelp">
            <table class="table delivery-lock-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Sesi / Order</th>
                        <th scope="col">Operator</th>
                        <th scope="col">Status</th>
                        <th scope="col">Box / Issue</th>
                        <th scope="col">Terakhir diperbarui</th>
                        <th scope="col">Hentikan sesi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activeLocks as $lock)
                        <tr>
                            <td class="delivery-lock-identity">
                                <strong>Sesi #{{ $lock->id }}</strong>
                                <span>Order #{{ $lock->delivery_order_id }} · {{ $lock->order?->customer_name ?? 'Customer tidak tersedia' }}</span>
                                @if($lock->order?->delivery_date)
                                    <small>Delivery {{ $lock->order->delivery_date->format('d M Y') }}</small>
                                @endif
                            </td>
                            <td class="delivery-lock-owner">{{ $lock->creator?->name ?? 'Operator tidak tersedia' }}</td>
                            <td>
                                <span class="delivery-lock-status {{ $lock->status === 'blocked' ? 'is-blocked' : 'is-scanning' }}">
                                    <i class="bi {{ $lock->status === 'blocked' ? 'bi-exclamation-circle' : 'bi-upc-scan' }}" aria-hidden="true"></i>
                                    {{ $lock->status === 'blocked' ? 'Blocked' : 'Scanning' }}
                                </span>
                            </td>
                            <td class="delivery-lock-counts">
                                <span><strong>{{ (int) $lock->items_count }}</strong> box</span>
                                <span class="{{ (int) $lock->pending_issue_count > 0 ? 'has-issue' : '' }}"><strong>{{ (int) $lock->pending_issue_count }}</strong> issue pending</span>
                            </td>
                            <td class="delivery-lock-date">
                                @if($lock->updated_at)
                                    <time datetime="{{ $lock->updated_at->toIso8601String() }}">{{ $lock->updated_at->format('d M Y') }}<small>{{ $lock->updated_at->format('H:i') }}</small></time>
                                @else
                                    <span>-</span>
                                @endif
                            </td>
                            <td class="delivery-lock-action">
                                <form method="POST" action="{{ route('delivery.pick.lock.terminate', $lock->id) }}" class="js-terminate-lock-form" data-session-id="{{ $lock->id }}" data-order-id="{{ $lock->delivery_order_id }}">
                                    @csrf
                                    <label for="lock-reason-{{ $lock->id }}">Alasan terminate</label>
                                    <div class="delivery-lock-action-controls">
                                        <input type="text" id="lock-reason-{{ $lock->id }}" name="reason" class="form-control" maxlength="500" required placeholder="Tulis alasan singkat">
                                        <button type="submit" class="btn delivery-lock-terminate">
                                            <i class="bi bi-x-octagon" aria-hidden="true"></i> Terminate
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="delivery-lock-empty">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                <strong>Tidak ada lock aktif</strong>
                                <span>Semua sesi delivery saat ini bebas dari lock picking dan scan.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p id="activeLocksHelp" class="delivery-lock-table-hint">Geser tabel untuk melihat detail dan aksi. Sesi serta order tetap terlihat saat digeser.</p>
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.js-terminate-lock-form').forEach((form) => {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const reasonInput = form.querySelector('input[name="reason"]');
            const reason = String(reasonInput?.value || '').trim();

            if (!reason) {
                reasonInput?.focus();
                WarehouseAlert.error({ title: 'Input Tidak Valid', message: 'Alasan terminate wajib diisi.' });
                return;
            }

            WarehouseAlert.confirm({
                title: 'Terminate sesi #' + form.dataset.sessionId + '?',
                message: 'Sesi pada order #' + form.dataset.orderId + ' akan dibatalkan. Lock box dilepas dan issue pending pada sesi ini dihapus.',
                confirmText: 'Ya, Terminate',
                confirmColor: '#DC2626',
                onConfirm: () => {
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) submitButton.disabled = true;
                    form.submit();
                }
            });
        });
    });
</script>
@endsection
