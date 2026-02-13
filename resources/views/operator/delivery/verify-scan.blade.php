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
                    <input type="text" id="scanInput" class="form-control" placeholder="Scan / input ID Box" autofocus>
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
<script>
    const scanInput = document.getElementById('scanInput');
    const btnScan = document.getElementById('btnScan');
    const scanMessage = document.getElementById('scanMessage');
    const remainingCount = document.getElementById('remainingCount');
    const verifiedCount = document.getElementById('verifiedCount');
    const totalCount = document.getElementById('totalCount');
    const btnToFinal = document.getElementById('btnToFinal');
    let audioContext = null;

    function playSuccessBeep() {
        try {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }

            const now = audioContext.currentTime;
            const playTone = (frequency, startAt, duration, volume = 0.85) => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.type = 'square';
                oscillator.frequency.setValueAtTime(frequency, startAt);

                gainNode.gain.setValueAtTime(0.0001, startAt);
                gainNode.gain.exponentialRampToValueAtTime(volume, startAt + 0.02);
                gainNode.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                oscillator.start(startAt);
                oscillator.stop(startAt + duration + 0.01);
            };

            playTone(650, now, 0.26, 0.85);
            playTone(820, now + 0.30, 0.28, 0.85);
            playTone(980, now + 0.62, 0.22, 0.8);
        } catch (e) {}
    }

    function playMismatchBeep() {
        try {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }

            const now = audioContext.currentTime;
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.type = 'square';
            oscillator.frequency.setValueAtTime(520, now);
            oscillator.frequency.exponentialRampToValueAtTime(260, now + 0.42);

            gainNode.gain.setValueAtTime(0.0001, now);
            gainNode.gain.exponentialRampToValueAtTime(0.9, now + 0.03);
            gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.46);

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            oscillator.start(now);
            oscillator.stop(now + 0.48);
        } catch (e) {}
    }

    function showMessage(message, type = 'success') {
        scanMessage.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    }

    function markRowVerified(boxId) {
        const row = document.querySelector(`tr[data-box-id="${boxId}"]`);
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
        const boxNumber = scanInput.value.trim();
        if (!boxNumber) {
            showMessage('ID Box wajib diisi.', 'danger');
            return;
        }

        scanInput.value = '';
        scanInput.focus();

        fetch("{{ route('delivery.pick.verify.scan', $session->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ box_number: boxNumber })
        })
        .then(async (res) => {
            const data = await res.json();

            if (data.success) {
                playSuccessBeep();
                showMessage(data.message, 'success');
                if (data.box_id) {
                    markRowVerified(data.box_id);
                }
                syncCounters(data.remaining);
                return;
            }

            playMismatchBeep();
            showMessage(data.message || 'Scan verifikasi gagal.', 'danger');
        })
        .catch(() => showMessage('Gagal koneksi.', 'danger'));
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
