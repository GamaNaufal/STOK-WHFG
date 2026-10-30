@extends('shared.layouts.app')

@section('title', 'Scan Issues')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/delivery-scan-issues.css') }}">
@endsection

@section('content')
<div id="deliveryScanIssuesPage" class="delivery-scan-issues-page">
    <header class="scan-issues-header">
        <div>
            <span class="scan-issues-eyebrow">Delivery / Penanganan scan</span>
            <h1><i class="bi bi-exclamation-circle" aria-hidden="true"></i> Issue scan delivery</h1>
            <p>Tinjau hasil scan yang bermasalah sebelum membuka kembali sesi picking.</p>
        </div>
        <a href="{{ route('delivery.pick.verification') }}" class="btn scan-issues-back">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Picking Verification
        </a>
    </header>

    <section class="card scan-issues-panel" aria-labelledby="pendingIssuesTitle">
        <div class="scan-issues-panel-header">
            <div>
                <span class="scan-issues-eyebrow">Perlu keputusan</span>
                <h2 id="pendingIssuesTitle">Menunggu approval</h2>
                <p>Domestik: approval membuka blokir sesi. Ekspor: setelah approval, operator wajib mengonfirmasi penempatan pengembalian sebelum blokir dibuka.</p>
            </div>
            <span class="scan-issues-count scan-issues-count-pending">{{ $issues->count() }} pending</span>
        </div>
        <div class="scan-issues-scroll" tabindex="0" role="region" aria-label="Issue scan yang menunggu approval" aria-describedby="pendingIssuesHelp">
            <table class="table scan-issues-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Scan &amp; alasan</th>
                        <th scope="col">Waktu</th>
                        <th scope="col">Catatan admin</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($issues as $issue)
                        @if($issue instanceof \App\Models\ExportPickingIssue)
                            @include('operator.delivery.export-issue-row', ['history' => false])
                        @else
                        <tr>
                            <td class="scan-issues-order">
                                <span class="badge text-bg-secondary mb-2">Domestik / Lokal</span>
                                <strong>#{{ $issue->session->delivery_order_id }}</strong>
                                <small>{{ $issue->session->order?->customer_name ?? 'Customer tidak tersedia' }}</small>
                            </td>
                            <td>
                                <code class="scan-issues-code">{{ $issue->scanned_code }}</code>
                                <small class="scan-issues-reason">{{ $issue->reason }}</small>
                            </td>
                            <td>
                                <span class="scan-issues-date">{{ $issue->created_at->format('d M Y') }}</span>
                                <small class="scan-issues-time">{{ $issue->created_at->format('H:i') }}</small>
                            </td>
                            <td>
                                <label class="visually-hidden" for="issue-notes-{{ $issue->id }}">Catatan approval untuk order #{{ $issue->session->delivery_order_id }}</label>
                                <input type="text" id="issue-notes-{{ $issue->id }}" name="notes" class="form-control scan-issues-notes"
                                       form="approve-issue-{{ $issue->id }}" required maxlength="500"
                                       placeholder="Alasan / tindak lanjut">
                            </td>
                            <td class="scan-issues-action">
                                <form id="approve-issue-{{ $issue->id }}" method="POST" action="{{ route('delivery.pick.issue.approve', $issue->id) }}">
                                    @csrf
                                    <button type="submit" class="btn scan-issues-approve">
                                        <i class="bi bi-check-circle" aria-hidden="true"></i> Setujui issue
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="scan-issues-empty">
                                <i class="bi bi-check-circle" aria-hidden="true"></i>
                                <strong>Tidak ada issue yang menunggu</strong>
                                <span>Tidak ada scan yang menunggu keputusan admin saat ini.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p id="pendingIssuesHelp" class="scan-issues-table-hint">Geser tabel untuk melihat semua kolom. Nomor order tetap terlihat saat digeser.</p>
    </section>

    <section class="card scan-issues-panel" aria-labelledby="historyIssuesTitle">
        <div class="scan-issues-panel-header">
            <div>
                <span class="scan-issues-eyebrow">Keputusan sebelumnya</span>
                <h2 id="historyIssuesTitle">Riwayat issue</h2>
                <p>Menampilkan {{ $historyIssues->count() }} keputusan terbaru, maksimal 200.</p>
            </div>
        </div>
        <div class="scan-issues-scroll" tabindex="0" role="region" aria-label="Riwayat issue scan" aria-describedby="historyIssuesHelp">
            <table class="table scan-issues-table scan-issues-history-table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Scan &amp; alasan</th>
                        <th scope="col">Diselesaikan</th>
                        <th scope="col">Status</th>
                        <th scope="col">Catatan admin</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($historyIssues as $issue)
                        @if($issue instanceof \App\Models\ExportPickingIssue)
                            @include('operator.delivery.export-issue-row', ['history' => true])
                        @else
                        <tr>
                            <td class="scan-issues-order">
                                <span class="badge text-bg-secondary mb-2">Domestik / Lokal</span>
                                <strong>#{{ $issue->session->delivery_order_id }}</strong>
                                <small>{{ $issue->session->order?->customer_name ?? 'Customer tidak tersedia' }}</small>
                            </td>
                            <td>
                                <code class="scan-issues-code is-history">{{ $issue->scanned_code }}</code>
                                <small class="scan-issues-reason">{{ $issue->reason }}</small>
                            </td>
                            <td>
                                <span class="scan-issues-date">{{ optional($issue->resolved_at)->format('d M Y') ?? 'Belum tercatat' }}</span>
                                @if($issue->resolved_at)
                                    <small class="scan-issues-time">{{ $issue->resolved_at->format('H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="scan-issues-status {{ $issue->status === 'approved' ? 'is-approved' : 'is-other' }}">
                                    {{ strtoupper($issue->status) }}
                                </span>
                            </td>
                            <td class="scan-issues-history-notes">{{ $issue->notes ?? '—' }}</td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="scan-issues-empty">
                                <i class="bi bi-inbox" aria-hidden="true"></i>
                                <strong>Belum ada riwayat issue</strong>
                                <span>Keputusan issue yang selesai akan tampil di sini.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p id="historyIssuesHelp" class="scan-issues-table-hint">Geser tabel untuk membaca kode scan dan catatan lengkap.</p>
    </section>
</div>
<datalist id="export-approval-parts">@foreach($exportParts as $part)<option value="{{ $part->part_number }}">{{ $part->qty_box }} PCS / box</option>@endforeach</datalist>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('form[id^="approve-issue-"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const notesInput = document.querySelector(`[name="notes"][form="${this.id}"]`) || this.querySelector('[name="notes"]');
            if (!notesInput) {
                WarehouseAlert.error({
                    title: 'Field Notes Tidak Ditemukan',
                    message: 'Silakan refresh halaman lalu coba lagi.'
                });
                return;
            }
            const notes = notesInput.value.trim();

            if (!notes) {
                WarehouseAlert.error({
                    title: 'Notes Diperlukan',
                    message: 'Harap masukkan catatan approval sebelum melanjutkan.'
                });
                notesInput.focus();
                return;
            }

            const safeNotes = document.createElement('span');
            safeNotes.textContent = notes;

            WarehouseAlert.confirm({
                title: 'Setujui issue scan?',
                message: 'Approval membuka blokir sesi picking. Scan bermasalah tidak otomatis diverifikasi.',
                warningItems: [
                    'Scan yang salah tidak dihitung sebagai box terverifikasi',
                    'Catatan akan tersimpan di riwayat'
                ],
                infoText: `<strong>Catatan:</strong> ${safeNotes.innerHTML}`,
                confirmText: 'Ya, Setujui',
                confirmColor: '#0b7072',
                onConfirm: () => {
                    this.submit();
                }
            });
        });
    });
</script>
@endsection
