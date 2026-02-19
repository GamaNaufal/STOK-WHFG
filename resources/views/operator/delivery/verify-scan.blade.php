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
    const requiredBoxRows = @json($session->items->map(function ($item) {
        return [
            'box_id' => $item->box_id,
            'box_number' => (string) $item->box->box_number,
        ];
    })->values());
    const initialVerifiedBoxIds = @json(array_values($verifiedBoxIds));

    let audioContext = null;
    let requestQueue = Promise.resolve();
    let activeOscillators = [];
    const requiredBoxNumberToId = new Map();
    const localVerifiedBoxIds = new Set(initialVerifiedBoxIds.map((id) => Number(id)));

    function normalizeBoxNumber(value) {
        return String(value || '').trim().toUpperCase();
    }

    requiredBoxRows.forEach((row) => {
        const boxId = Number(row.box_id);
        const normalized = normalizeBoxNumber(row.box_number);

        if (!Number.isNaN(boxId) && normalized) {
            requiredBoxNumberToId.set(normalized, boxId);
        }
    });

    function ensureAudioContext() {
        if (!audioContext) {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }

        if (audioContext.state === 'suspended') {
            audioContext.resume().catch(() => {});
        }
    }

    function registerOscillator(oscillator) {
        activeOscillators.push(oscillator);
        oscillator.onended = () => {
            activeOscillators = activeOscillators.filter((item) => item !== oscillator);
        };
    }

    function stopAllBeep() {
        if (!audioContext) return;

        const now = audioContext.currentTime;
        activeOscillators.forEach((oscillator) => {
            try {
                oscillator.stop(now);
            } catch (e) {}
        });
        activeOscillators = [];
    }

    function playSuccessBeep() {
        try {
            ensureAudioContext();
            stopAllBeep();

            const now = audioContext.currentTime;
            const playTone = (frequency, startAt, duration, volume = 0.7) => {
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(frequency, startAt);

                gainNode.gain.setValueAtTime(0.0001, startAt);
                gainNode.gain.exponentialRampToValueAtTime(volume, startAt + 0.01);
                gainNode.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                registerOscillator(oscillator);
                oscillator.start(startAt);
                oscillator.stop(startAt + duration + 0.01);
            };

            playTone(880, now, 0.09, 0.72);
            playTone(1180, now + 0.10, 0.11, 0.72);
        } catch (e) {}
    }

    function playMismatchBeep() {
        try {
            ensureAudioContext();

            const now = audioContext.currentTime;
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.type = 'square';
            oscillator.frequency.setValueAtTime(520, now);
            oscillator.frequency.exponentialRampToValueAtTime(320, now + 0.16);

            gainNode.gain.setValueAtTime(0.0001, now);
            gainNode.gain.exponentialRampToValueAtTime(0.45, now + 0.01);
            gainNode.gain.exponentialRampToValueAtTime(0.0001, now + 0.18);

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            registerOscillator(oscillator);
            oscillator.start(now);
            oscillator.stop(now + 0.19);
        } catch (e) {}
    }

    function showMessage(message, type = 'success') {
        scanMessage.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    }

    function markRowVerified(boxId) {
        const numericBoxId = Number(boxId);
        localVerifiedBoxIds.add(numericBoxId);

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
        const boxNumber = scanInput.value.trim();
        if (!boxNumber) {
            showMessage('ID Box wajib diisi.', 'danger');
            return;
        }

        scanInput.value = '';
        scanInput.focus();
        ensureAudioContext();

        const normalized = normalizeBoxNumber(boxNumber);
        const localBoxId = requiredBoxNumberToId.get(normalized);
        const canPlayOptimisticSuccess = Number.isInteger(localBoxId) && !localVerifiedBoxIds.has(localBoxId);

        if (canPlayOptimisticSuccess) {
            playSuccessBeep();
        }

        requestQueue = requestQueue.finally(() => processScanRequest(boxNumber, canPlayOptimisticSuccess));
    }

    function processScanRequest(boxNumber, hasOptimisticSuccess) {
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

            if (data.success) {
                if (!hasOptimisticSuccess) {
                    playSuccessBeep();
                }
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

    const unlockAudio = () => ensureAudioContext();
    window.addEventListener('pointerdown', unlockAudio, { once: true });
    window.addEventListener('keydown', unlockAudio, { once: true });
</script>
@endsection
