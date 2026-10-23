@extends('shared.layouts.app')

@section('title', 'Scan Box Pengambilan')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/delivery-scan.css') }}">
@endsection

@section('content')
@php
    $isScanBlocked = $session->status === 'blocked';
@endphp
<div id="deliveryScanPage" class="delivery-scan-page">
    <header class="delivery-scan-header">
        <div class="delivery-scan-heading">
            <span class="delivery-scan-eyebrow">Picking / Scan final delivery</span>
            <h1><i class="bi bi-upc-scan" aria-hidden="true"></i> Scan box pengambilan</h1>
            <div class="delivery-scan-context">
                <span class="delivery-scan-order">Order #{{ $order->id }}</span>
                <span class="delivery-scan-customer">{{ $order->customer_name }}</span>
                <span class="delivery-scan-date">{{ $order->delivery_date->format('d M Y') }}</span>
            </div>
        </div>
        <nav class="delivery-scan-nav" aria-label="Navigasi scan delivery">
            <a href="{{ route('delivery.pick.print-preview', [$order->id, $session->id]) }}" target="_blank" rel="noopener" class="btn delivery-scan-nav-link">
                <i class="bi bi-printer" aria-hidden="true"></i> Cetak picklist
            </a>
            <a href="{{ route('delivery.pick.verification') }}" class="btn delivery-scan-nav-link">
                <i class="bi bi-arrow-left-circle" aria-hidden="true"></i> Picking Verification
            </a>
            <a href="{{ route('delivery.index') }}" class="btn delivery-scan-nav-link">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Delivery Schedule
            </a>
        </nav>
    </header>

    <div class="delivery-scan-grid">
        <div class="delivery-scan-controls">
            <section class="card delivery-scan-card delivery-scan-input-card" aria-labelledby="deliveryScanInputTitle">
                <div class="card-body">
                    <span class="delivery-scan-section-label">Pemindaian</span>
                    <h2 id="deliveryScanInputTitle">Scan ID box</h2>
                    <p class="delivery-scan-supporting">Gunakan scanner atau ketik ID box, lalu tekan Enter. Scan yang tidak sesuai akan memblokir sesi sampai disetujui admin.</p>
                    <div id="scanBlockedNotice" class="delivery-scan-blocked" role="status" @if(! $isScanBlocked) hidden @endif>
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        <div>
                            <strong>Sesi diblokir</strong>
                            <p>Scan dihentikan sampai issue disetujui admin. Muat ulang halaman setelah approval.</p>
                            <a href="{{ route('delivery.pick.scan', [$order->id, $session->id]) }}">Perbarui status sesi</a>
                        </div>
                    </div>
                    <div class="delivery-scan-field">
                        <label for="scanInput" class="form-label">ID Box</label>
                        <input type="text" id="scanInput" class="form-control" placeholder="Scan / input ID box" inputmode="numeric" aria-describedby="deliveryScanInputHelp" @disabled($isScanBlocked) @if(! $isScanBlocked) autofocus @endif>
                        <span id="deliveryScanInputHelp" class="delivery-scan-field-hint">Input dikosongkan dan kembali fokus setelah scan dikirim.</span>
                    </div>
                    <button type="button" class="btn btn-primary delivery-scan-submit" id="btnScan" @disabled($isScanBlocked)>
                        <i class="bi bi-check2-circle" aria-hidden="true"></i> Kirim scan
                    </button>
                    <div id="scanMessage" class="delivery-scan-message" role="status" aria-live="polite"></div>
                </div>
            </section>

            <section class="card delivery-scan-card delivery-scan-progress-card" aria-labelledby="deliveryScanProgressTitle">
                <div class="card-body">
                    <span class="delivery-scan-section-label">Progres sesi</span>
                    <h2 id="deliveryScanProgressTitle">Status sesi</h2>
                    <div class="delivery-scan-metrics">
                        <div class="delivery-scan-metric">
                            <span>Sisa box</span>
                            <strong id="remainingCount" aria-live="polite">{{ $session->items->where('status', 'pending')->count() }}</strong>
                        </div>
                        <div class="delivery-scan-metric">
                            <span>Status sesi</span>
                            <strong id="sessionStatus" class="delivery-scan-session-status" aria-live="polite">{{ strtoupper($session->status) }}</strong>
                        </div>
                    </div>
                    <div class="delivery-scan-session-actions">
                        <button type="button" class="btn btn-success" id="btnComplete" {{ ($session->status === 'blocked' || $session->items->where('status','pending')->count() > 0) ? 'disabled' : '' }}>
                            <i class="bi bi-check2" aria-hidden="true"></i> Selesaikan pengiriman
                        </button>
                        <button type="button" class="btn btn-outline-danger" id="btnCancelScan" data-can-cancel-blocked="{{ Auth::user()->role === 'admin' ? '1' : '0' }}"
                            @disabled($isScanBlocked && Auth::user()->role !== 'admin')
                            @if($isScanBlocked && Auth::user()->role !== 'admin') title="Menunggu approval admin. Operator tidak dapat membatalkan sesi blocked." @endif>
                            <i class="bi bi-x-circle" aria-hidden="true"></i> Batalkan scan
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div class="delivery-scan-results">
            <section class="card delivery-scan-card delivery-scan-list-card" aria-labelledby="deliveryScanListTitle">
                <div class="delivery-scan-list-heading">
                    <div>
                        <span class="delivery-scan-section-label">Referensi picking</span>
                        <h2 id="deliveryScanListTitle">Daftar box wajib</h2>
                    </div>
                    <span class="delivery-scan-total">{{ $session->items->count() }} box</span>
                </div>
                <div class="table-responsive delivery-scan-table-scroll" tabindex="0" role="region" aria-labelledby="deliveryScanListTitle" aria-describedby="deliveryScanTableHelp">
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
                                <tr data-box-id="{{ $item->box_id }}" class="{{ $item->status === 'scanned' ? 'table-success' : '' }}">
                                    <td class="fw-bold">{{ $item->box->box_number }}</td>
                                    <td>{{ $item->part_number }}</td>
                                    <td>{{ $item->pcs_quantity }}</td>
                                    <td>{{ optional(optional($item->box->pallets->first())->stockLocation)->warehouse_location ?? 'Unknown' }}</td>
                                    <td>
                                        @if($item->status === 'scanned')
                                            <span class="badge bg-success">Scanned</span>
                                        @else
                                            <span class="badge bg-secondary">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p id="deliveryScanTableHelp" class="delivery-scan-table-hint">Geser tabel untuk melihat semua kolom. ID box tetap terlihat saat digeser.</p>
            </section>

            @if($session->issues->where('status', 'pending')->count() > 0)
                <div class="alert alert-warning delivery-scan-issue" role="status">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Ada masalah scan. Menunggu approval admin warehouse.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
@include('shared.partials.scan-feedback')
<script>
    const scanInput = document.getElementById('scanInput');
    const btnScan = document.getElementById('btnScan');
    const scanMessage = document.getElementById('scanMessage');
    const remainingCount = document.getElementById('remainingCount');
    const btnComplete = document.getElementById('btnComplete');
    const btnCancelScan = document.getElementById('btnCancelScan');
    const sessionStatus = document.getElementById('sessionStatus');
    const scanBlockedNotice = document.getElementById('scanBlockedNotice');
    let isSessionBlocked = scanInput.disabled;
    let scanQueue = Promise.resolve();

    function showBlockedState() {
        isSessionBlocked = true;
        sessionStatus.textContent = 'BLOCKED';
        scanInput.disabled = true;
        btnScan.disabled = true;
        btnComplete.disabled = true;
        if (btnCancelScan.dataset.canCancelBlocked !== '1') btnCancelScan.disabled = true;
        scanBlockedNotice.hidden = false;
    }

    function markRowScanned(boxId) {
        const row = document.querySelector(`tr[data-box-id="${boxId}"]`);
        if (!row) return;

        const statusCell = row.querySelector('td:last-child');
        if (statusCell) {
            statusCell.innerHTML = '<span class="badge bg-success">Scanned</span>';
        }

        row.classList.add('table-success');
    }

    function hydrateScannedRows() {
        document.querySelectorAll('#requiredBoxes tr[data-box-id]').forEach((row) => {
            const statusBadge = row.querySelector('td:last-child .badge');
            if (!statusBadge) {
                return;
            }

            if (statusBadge.textContent.trim().toLowerCase() === 'scanned') {
                row.classList.add('table-success');
            }
        });
    }

    function showMessage(message, type = 'success') {
        window.ScanFeedback?.[type === 'success' ? 'success' : 'error']();
        const bg = type === 'success' ? '#ECFDF5' : '#FEF2F2';
        const border = type === 'success' ? '#10B981' : '#EF4444';
        const color = type === 'success' ? '#065F46' : '#991B1B';
        const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
        scanMessage.innerHTML = `<div class="alert shadow-sm" style="background: ${bg}; border: none; border-left: 4px solid ${border}; color: ${color}; border-radius: 8px;"><i class="bi bi-${icon}"></i> ${message}</div>`;
    }

    function submitScan() {
        if (isSessionBlocked) return;
        const boxNumber = scanInput.value.trim();
        // Untuk barcode scanner hardware: setelah Enter/submit, bersihkan input
        scanInput.value = '';
        scanInput.focus();

        scanQueue = scanQueue.then(() => {
            if (!isSessionBlocked) return processScan(boxNumber);
        });
    }

    function processScan(boxNumber) {
        return fetch("{{ route('delivery.pick.scan.submit', $session->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ box_number: boxNumber })
        })
        .then(async res => {
            const data = await res.json();
            if (data.success) {
                showMessage(data.message, 'success');
                remainingCount.textContent = data.remaining;
                if (data.box_id) {
                    markRowScanned(data.box_id);
                }
                if (data.remaining === 0) {
                    btnComplete.disabled = false;
                }
            } else {
                showMessage(data.message || 'Scan gagal.', 'danger');

                if (data.session_status === 'blocked') showBlockedState();

                if (res.status === 409) {
                    sessionStatus.textContent = 'CANCELLED';
                    setTimeout(() => {
                        window.location.href = "{{ route('delivery.index') }}";
                    }, 900);
                }
            }
        })
        .catch(() => showMessage('Gagal koneksi.', 'danger'));
    }

    // Handle Enter key in input
    scanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitScan();
        }
    });

    btnScan.addEventListener('click', submitScan);

    btnComplete.addEventListener('click', function () {
        if (isSessionBlocked) return;
        fetch("{{ route('delivery.pick.complete', $session->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Pengiriman selesai.', 'success');
                window.location.href = "{{ route('delivery.index') }}";
            } else {
                showToast(data.message || 'Tidak bisa menyelesaikan.', 'danger');
            }
        })
        .catch(() => showToast('Gagal koneksi.', 'danger'));
    });

    btnCancelScan.addEventListener('click', function () {
        if (btnCancelScan.disabled) {
            showToast('Sesi blocked hanya bisa dibuka oleh admin.', 'warning');
            return;
        }

        WarehouseAlert.confirm({
            title: 'Batalkan Scan?',
            message: 'Batalkan proses scan ini? Box yang terkunci akan dilepas.',
            confirmText: 'Ya, Batalkan',
            confirmColor: '#DC2626',
            onConfirm: () => {
                fetch("{{ route('delivery.pick.cancel', $session->id) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message || 'Proses scan dibatalkan.', 'success');
                        window.location.href = "{{ route('delivery.index') }}";
                    } else {
                        showToast(data.message || 'Gagal membatalkan scan.', 'danger');
                    }
                })
                .catch(() => showToast('Gagal koneksi.', 'danger'));
            }
        });
    });

    hydrateScannedRows();
</script>
@endsection
