@extends('shared.layouts.app')

@section('title', 'Picking Verification Scan')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/delivery-verification.css') }}">
@endsection

@section('content')
@php
    $isVerificationBlocked = $session->status === 'blocked';
    $total = $session->items->count();
    $done = collect($verifiedBoxIds)->count();
    $remaining = max(0, $total - $done);
@endphp
<div id="deliveryVerificationPage" class="delivery-verification-page">
    <header class="verification-header">
        <div class="verification-heading">
            <span class="verification-eyebrow">Picking / Pemeriksaan box</span>
            <h1><i class="bi bi-upc-scan" aria-hidden="true"></i> Picking Verification</h1>
            <div class="verification-context">
                <span class="verification-order">Order #{{ $order->id }}</span>
                <span>{{ $order->customer_name }}</span>
                <span>{{ $order->delivery_date->format('d M Y') }}</span>
            </div>
        </div>
        <a href="{{ route('delivery.pick.verification') }}" class="btn verification-back-link">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Kembali ke daftar
        </a>
    </header>

    <div class="verification-grid">
        <div class="verification-controls">
            <section class="card verification-card" aria-labelledby="verificationScanTitle">
                <div class="card-body">
                    <span class="verification-section-label">Langkah 1 / Verifikasi</span>
                    <h2 id="verificationScanTitle">Scan ID box</h2>
                    <p class="verification-supporting">Pindai atau ketik ID box pada picklist, lalu tekan Enter. Jika ID tidak sesuai, periksa box dan scan ulang; progres tidak bertambah. Box expired, termasuk yang masa Handle-nya habis, juga ditolak tanpa memblokir sesi.</p>
                    <div id="verificationBlockedNotice" class="verification-blocked" role="status" @if(! $isVerificationBlocked) hidden @endif>
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        <div>
                            <strong>Sesi diblokir</strong>
                            <p>Scan dihentikan sampai issue disetujui admin.</p>
                            <a href="{{ route('delivery.pick.verify', [$order->id, $session->id]) }}">Perbarui status sesi</a>
                        </div>
                    </div>
                    <div class="verification-field">
                        <label for="scanInput" class="form-label">ID Box</label>
                        <input type="text" id="scanInput" class="form-control" placeholder="Scan / input ID Box" inputmode="numeric" aria-describedby="verificationScanHelp" @disabled($isVerificationBlocked) @if(! $isVerificationBlocked) autofocus @endif>
                        <span id="verificationScanHelp" class="verification-field-hint">Input dikosongkan dan kembali fokus setelah scan dikirim.</span>
                    </div>
                    <button type="button" class="btn btn-primary verification-submit" id="btnScan" @disabled($isVerificationBlocked)>
                        <i class="bi bi-check2-circle" aria-hidden="true"></i> Kirim scan
                    </button>
                    <div id="scanMessage" class="verification-message" role="status" aria-live="polite"></div>
                </div>
            </section>

            <section class="card verification-card" aria-labelledby="verificationProgressTitle">
                <div class="card-body">
                    <span class="verification-section-label">Status sesi</span>
                    <h2 id="verificationProgressTitle">Progres verifikasi</h2>
                    <div class="verification-metrics">
                        <div class="verification-metric">
                            <span>Sudah diverifikasi</span>
                            <strong><span id="verifiedCount">{{ $done }}</span><small> / <span id="totalCount">{{ $total }}</span></small></strong>
                        </div>
                        <div class="verification-metric">
                            <span>Sisa box</span>
                            <strong id="remainingCount">{{ $remaining }}</strong>
                        </div>
                    </div>
                    <div class="verification-timeout">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                        <div>
                            <strong>Batas tidak aktif</strong>
                            <p id="verificationIdleStatus" role="status">Sesi otomatis dibatalkan setelah 10 menit tanpa aktivitas di halaman ini.</p>
                        </div>
                    </div>
                    <div class="verification-actions">
                        <a href="{{ route('delivery.pick.scan', [$order->id, $session->id]) }}" id="btnToFinal" class="btn btn-success {{ $remaining > 0 || $isVerificationBlocked ? 'disabled' : '' }}" aria-disabled="{{ $remaining > 0 || $isVerificationBlocked ? 'true' : 'false' }}">
                            <i class="bi bi-arrow-right-circle" aria-hidden="true"></i> Lanjut ke Scan Final Delivery
                        </a>
                        <button type="button" class="btn btn-outline-danger" id="btnCancelVerification" data-can-cancel-blocked="{{ Auth::user()->role === 'admin' ? '1' : '0' }}"
                            @disabled($isVerificationBlocked && Auth::user()->role !== 'admin')
                            @if($isVerificationBlocked && Auth::user()->role !== 'admin') title="Menunggu approval admin. Operator tidak dapat membatalkan sesi blocked." @endif>
                            <i class="bi bi-x-circle" aria-hidden="true"></i> Batalkan Verifikasi
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <section class="card verification-card verification-list" aria-labelledby="verificationListTitle">
            <div class="verification-list-heading">
                <div>
                    <span class="verification-section-label">Referensi scan</span>
                    <h2 id="verificationListTitle">Box dalam picklist</h2>
                </div>
                <span class="verification-total">{{ $total }} box</span>
            </div>
            <div class="verification-table-scroll" tabindex="0" role="region" aria-label="Daftar box picking" aria-describedby="verificationTableHelp">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">ID Box</th>
                            <th scope="col">Part</th>
                            <th scope="col">PCS</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody id="requiredBoxes">
                        @foreach($session->items as $item)
                            @php $isVerified = in_array($item->box_id, $verifiedBoxIds, true); @endphp
                            <tr data-box-id="{{ $item->box_id }}" class="{{ $isVerified ? 'table-success' : '' }}">
                                <td class="fw-bold">{{ $item->box->box_number }}</td>
                                <td>{{ $item->part_number }}</td>
                                <td>{{ $item->pcs_quantity }}</td>
                                <td>{{ optional(optional($item->box->pallets->first())->stockLocation)->warehouse_location ?? 'Unknown' }}</td>
                                <td>
                                    @if($isVerified)
                                        <span class="badge bg-success">Verified</span>
                                    @else
                                        <span class="badge bg-secondary">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p id="verificationTableHelp" class="verification-table-hint">Geser tabel untuk melihat semua kolom. ID box tetap terlihat saat digeser.</p>
        </section>
    </div>
</div>
@endsection

@section('scripts')
@include('shared.partials.scan-feedback')
<script src="{{ asset('js/pages/picking-verification-session.js') }}?v={{ filemtime(public_path('js/pages/picking-verification-session.js')) }}"
    data-activity-url="{{ route('delivery.pick.verify.activity', $session->id) }}"
    data-cancel-url="{{ route('delivery.pick.cancel', $session->id) }}"
    data-return-url="{{ route('delivery.pick.verification') }}"
    data-csrf="{{ csrf_token() }}"
    data-expires-in="{{ $session->status === 'scanning' && $session->verification_last_activity_at ? max(0, (int) now()->diffInSeconds($session->verification_last_activity_at->copy()->addMinutes(10))) : '' }}"></script>
<script>
    const scanInput = document.getElementById('scanInput');
    const btnScan = document.getElementById('btnScan');
    const scanMessage = document.getElementById('scanMessage');
    const remainingCount = document.getElementById('remainingCount');
    const verifiedCount = document.getElementById('verifiedCount');
    const totalCount = document.getElementById('totalCount');
    const btnToFinal = document.getElementById('btnToFinal');
    const btnCancelVerification = document.getElementById('btnCancelVerification');
    const verificationBlockedNotice = document.getElementById('verificationBlockedNotice');
    let isSessionBlocked = scanInput.disabled;

    let requestQueue = Promise.resolve();

    function showBlockedState() {
        isSessionBlocked = true;
        scanInput.disabled = true;
        btnScan.disabled = true;
        btnToFinal.classList.add('disabled');
        btnToFinal.setAttribute('aria-disabled', 'true');
        if (btnCancelVerification.dataset.canCancelBlocked !== '1') btnCancelVerification.disabled = true;
        verificationBlockedNotice.hidden = false;
    }

    function showMessage(message, type = 'success') {
        window.ScanFeedback?.[type === 'danger' ? 'error' : 'success']();
        const bg = type === 'success' ? '#ECFDF5' : '#FEF2F2';
        const border = type === 'success' ? '#10B981' : '#EF4444';
        const color = type === 'success' ? '#065F46' : '#991B1B';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
        scanMessage.innerHTML = `<div class="alert shadow-sm" style="background: ${bg}; border: none; border-left: 4px solid ${border}; color: ${color}; border-radius: 8px;"><i class="bi bi-${icon}"></i> ${message}</div>`;
    }

    function markRowVerified(boxId) {
        const numericBoxId = Number(boxId);

        const row = document.querySelector(`tr[data-box-id="${numericBoxId}"]`);
        if (!row) return;

        row.classList.add('table-success');
        const statusCell = row.querySelector('td:last-child');
        if (statusCell) {
            statusCell.innerHTML = '<span class="badge bg-success">Verified</span>';
        }
    }

    function syncCounters(remaining) {
        const total = parseInt(totalCount.textContent || '0', 10);
        const rem = parseInt(remaining || '0', 10);
        const verified = Math.max(0, total - rem);

        remainingCount.textContent = rem;
        verifiedCount.textContent = verified;

        if (rem === 0 && !isSessionBlocked) {
            btnToFinal.classList.remove('disabled');
            btnToFinal.setAttribute('aria-disabled', 'false');
        }
    }

    function submitScan() {
        if (window.verificationSessionClosing || isSessionBlocked) return;
        const boxNumber = scanInput.value.trim();
        scanInput.value = '';
        scanInput.focus();

        requestQueue = requestQueue.finally(() => processScanRequest(boxNumber));
    }

    function processScanRequest(boxNumber) {
        if (window.verificationSessionClosing || isSessionBlocked) return Promise.resolve();
        return fetch("{{ route('delivery.pick.verify.scan', $session->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ box_number: boxNumber })
        })
        .then(async (res) => {
            const data = await res.json();
            if (window.verificationSessionClosing) return;

            if (data.success) {
                showMessage(data.message, 'success');
                if (data.box_id) {
                    markRowVerified(data.box_id);
                }
                syncCounters(data.remaining);
                return;
            }

            showMessage(data.message || 'Scan verifikasi gagal.', 'danger');

            if (data.session_status === 'blocked') showBlockedState();

            if (res.status === 409) {
                window.verificationSessionClosing = true;
                scanInput.disabled = true;
                btnScan.disabled = true;
                btnToFinal.classList.add('disabled');
                setTimeout(() => {
                    window.location.href = "{{ route('delivery.pick.verification') }}";
                }, 900);
            }
        })
        .catch((error) => {
            showMessage('Gagal koneksi.', 'danger');
        });
    }

    scanInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitScan();
        }
    });

    btnScan.addEventListener('click', submitScan);
    btnToFinal.addEventListener('click', function (e) {
        if (btnToFinal.classList.contains('disabled')) e.preventDefault();
    });
</script>
@endsection
