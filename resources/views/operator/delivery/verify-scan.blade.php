@extends('shared.layouts.app')

@section('title', 'Picking Verification Scan')

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 text-gray-800">
                <i class="bi bi-upc-scan"></i> Picking Verification
            </h1>
            <p class="text-muted">Order #{{ $order->id }} | {{ $order->customer_name }} | {{ $order->delivery_date->format('d M Y') }}</p>
        </div>
        <a href="{{ route('delivery.pick.verification') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-bold">Scan ID Box (Verifikasi)</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">ID Box</label>
                    <input type="text" id="scanInput" class="form-control" placeholder="Scan / input ID Box" minlength="8" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" autofocus>
                </div>
                <button class="btn btn-primary" id="btnScan">
                    <i class="bi bi-check2-circle"></i> Submit Scan
                </button>
                <div id="scanMessage" class="mt-3"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-light fw-bold">Status Verifikasi</div>
            <div class="card-body">
                @php
                    $total = $session->items->count();
                    $done = collect($verifiedBoxIds)->count();
                    $remaining = max(0, $total - $done);
                @endphp
                <p class="mb-2">Sudah Diverifikasi: <strong id="verifiedCount">{{ $done }}</strong> / <strong id="totalCount">{{ $total }}</strong></p>
                <p class="mb-3">Sisa Box: <strong id="remainingCount">{{ $remaining }}</strong></p>
                <a href="{{ route('delivery.pick.scan', [$order->id, $session->id]) }}" id="btnToFinal" class="btn btn-success {{ $remaining > 0 ? 'disabled' : '' }}">
                    <i class="bi bi-arrow-right-circle"></i> Lanjut ke Scan Final Delivery
                </a>
                <button type="button" class="btn btn-outline-danger mt-2" id="btnCancelVerification"
                    @disabled($session->status === 'blocked' && Auth::user()->role !== 'admin')
                    @if($session->status === 'blocked' && Auth::user()->role !== 'admin') title="Menunggu approval admin. Operator tidak dapat membatalkan sesi blocked." @endif>
                    <i class="bi bi-x-circle"></i> Batalkan Verifikasi
                </button>
                <p class="small text-muted mt-3 mb-0" id="verificationIdleStatus" role="status">
                    Sesi otomatis dibatalkan setelah 10 menit tanpa aktivitas di halaman ini.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-bold">Daftar Box Wajib</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">ID Box</th>
                                <th>Part</th>
                                <th>PCS</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="requiredBoxes">
                            @foreach($session->items as $item)
                                @php $isVerified = in_array($item->box_id, $verifiedBoxIds, true); @endphp
                                <tr data-box-id="{{ $item->box_id }}" class="{{ $isVerified ? 'table-success' : '' }}">
                                    <td class="ps-3 fw-bold">{{ $item->box->box_number }}</td>
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
            </div>
        </div>
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

    let requestQueue = Promise.resolve();

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

        if (rem === 0) {
            btnToFinal.classList.remove('disabled');
        }
    }

    function submitScan() {
        if (window.verificationSessionClosing) return;
        const boxNumber = scanInput.value.trim();
        if (!boxNumber) {
            showMessage('ID Box wajib diisi.', 'danger');
            return;
        }

        if (!/^\d+$/.test(boxNumber)) {
            showMessage('ID Box hanya boleh angka.', 'danger');
            return;
        }

        if (boxNumber.length !== 8) {
            showMessage('ID Box harus 8 angka.', 'danger');
            return;
        }

        scanInput.value = '';
        scanInput.focus();

        requestQueue = requestQueue.finally(() => processScanRequest(boxNumber));
    }

    function processScanRequest(boxNumber) {
        if (window.verificationSessionClosing) return Promise.resolve();
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
</script>
@endsection
