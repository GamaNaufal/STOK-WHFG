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

    let requestQueue = Promise.resolve();
    const requiredBoxNumberToId = new Map();
    const localVerifiedBoxIds = new Set();
    let selectedVoice = null;
    let speechPrimed = false;
    let speechPrimePromise = Promise.resolve();

    function normalizeBoxNumber(value) {
        return String(value || '').trim().toUpperCase();
    }

    function compactBoxNumber(value) {
        return normalizeBoxNumber(value).replace(/[^A-Z0-9]/g, '');
    }

    document.querySelectorAll('#requiredBoxes tr[data-box-id]').forEach((row) => {
        const boxId = Number(row.getAttribute('data-box-id'));
        const boxNumber = row.querySelector('td:first-child')?.textContent || '';
        const normalized = normalizeBoxNumber(boxNumber);
        const compact = compactBoxNumber(boxNumber);

        if (!Number.isNaN(boxId) && normalized) {
            requiredBoxNumberToId.set(normalized, boxId);
            if (compact) {
                requiredBoxNumberToId.set(compact, boxId);
            }
        }

        if (row.classList.contains('table-success')) {
            localVerifiedBoxIds.add(boxId);
        }
    });

    function selectSpeechVoice() {
        if (!('speechSynthesis' in window)) {
            return null;
        }

        const voices = window.speechSynthesis.getVoices();
        if (!voices || voices.length === 0) {
            return null;
        }

        const exactIndonesianLocal = voices.find((voice) => {
            return String(voice.lang || '').toLowerCase() === 'id-id' && voice.localService;
        });
        if (exactIndonesianLocal) {
            return exactIndonesianLocal;
        }

        const exactIndonesian = voices.find((voice) => String(voice.lang || '').toLowerCase() === 'id-id');
        if (exactIndonesian) {
            return exactIndonesian;
        }

        const anyIndonesianLocal = voices.find((voice) => {
            return String(voice.lang || '').toLowerCase().startsWith('id') && voice.localService;
        });
        if (anyIndonesianLocal) {
            return anyIndonesianLocal;
        }

        const anyIndonesian = voices.find((voice) => String(voice.lang || '').toLowerCase().startsWith('id'));
        if (anyIndonesian) {
            return anyIndonesian;
        }

        return voices[0] || null;
    }

    function primeSpeech() {
        if (!('speechSynthesis' in window)) {
            return;
        }

        window.speechSynthesis.getVoices();
        selectedVoice = selectSpeechVoice();
        if (speechPrimed) {
            return;
        }

        speechPrimePromise = new Promise((resolve) => {
            try {
                const warmup = new SpeechSynthesisUtterance('.');
                warmup.lang = 'id-ID';
                warmup.rate = 1;
                warmup.pitch = 1;
                warmup.volume = 0;
                if (selectedVoice) {
                    warmup.voice = selectedVoice;
                }

                let finished = false;
                const finish = () => {
                    if (finished) {
                        return;
                    }
                    finished = true;
                    speechPrimed = true;
                    resolve();
                };

                warmup.onend = finish;
                warmup.onerror = finish;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(warmup);
                setTimeout(finish, 180);
            } catch (error) {
                resolve();
            }
        });
    }

    function speakSuccessNow() {
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance('Benar');
        utterance.lang = 'id-ID';
        utterance.rate = 1.2;
        utterance.pitch = 1;
        utterance.volume = 1;
        if (selectedVoice) {
            utterance.voice = selectedVoice;
        }
        window.speechSynthesis.speak(utterance);
    }

    function playSuccessSpeech() {
        try {
            if (!('speechSynthesis' in window)) {
                return;
            }

            if (!speechPrimed) {
                primeSpeech();
                speechPrimePromise.finally(() => {
                    speakSuccessNow();
                });
                return;
            }

            speakSuccessNow();
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
        primeSpeech();

        const boxNumber = scanInput.value.trim();
        if (!boxNumber) {
            showMessage('ID Box wajib diisi.', 'danger');
            return;
        }

        const normalized = normalizeBoxNumber(boxNumber);
        const compact = compactBoxNumber(boxNumber);
        const preMatchedBoxId = requiredBoxNumberToId.get(normalized) ?? requiredBoxNumberToId.get(compact);

        if (preMatchedBoxId && localVerifiedBoxIds.has(Number(preMatchedBoxId))) {
            scanInput.value = '';
            scanInput.focus();
            playSuccessSpeech();
            showMessage('Box ini sudah diverifikasi.', 'warning');
            return;
        }

        scanInput.value = '';
        scanInput.focus();

        requestQueue = requestQueue.finally(() => processScanRequest(boxNumber));
    }

    function processScanRequest(boxNumber) {
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
                playSuccessSpeech();
                showMessage(data.message, 'success');
                if (data.box_id) {
                    markRowVerified(data.box_id);
                }
                syncCounters(data.remaining);
                return;
            }

            showMessage(data.message || 'Scan verifikasi gagal.', 'danger');

            if (res.status === 409) {
                setTimeout(() => {
                    window.location.href = "{{ route('delivery.index') }}";
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

    primeSpeech();
    window.addEventListener('pointerdown', primeSpeech, { once: true });
    window.addEventListener('keydown', primeSpeech, { once: true });
    scanInput.addEventListener('focus', primeSpeech, { once: true });
    scanInput.addEventListener('click', primeSpeech, { once: true });
    window.speechSynthesis?.addEventListener?.('voiceschanged', () => {
        selectedVoice = selectSpeechVoice();
    });
</script>
@endsection
