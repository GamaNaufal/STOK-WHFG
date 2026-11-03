@php
    $mandatoryRows = $rows->where('requires_scan', true);
    $verifiedCount = $mandatoryRows->where('picking_ready', true)->count();
    $remainingCount = $mandatoryRows->count() - $verifiedCount;
@endphp
<div class="verification-grid">
<div class="verification-controls">
@if($issue)
    <section class="plan-panel"><div class="plan-panel-heading"><h2>Pengambilan diblokir · issue ekspor #{{ $issue->id }}</h2><span>{{ $issue->status === 'pending' ? 'Menunggu approval admin' : 'Menunggu konfirmasi penempatan' }}</span></div><div class="plan-panel-body">
        <p>Box salah: <strong>{{ $issue->box_number }}</strong> · {{ $issue->reason }}</p>
        @if($issue->part)<p>Nomor part sesuai approval: <strong>{{ $issue->part->part_number }}</strong> · {{ $issue->pcs_quantity }} PCS / box.</p>@endif
        @if($issue->return_location_code)<div class="receiving-destination"><p>Kembalikan box ke</p><div class="receiving-destination-values"><div><span>Pallet</span><strong>{{ $issue->returnPallet?->display_name ?? 'Pallet cadangan baru' }}</strong></div><div><span>Lokasi</span><strong>{{ $issue->return_location_code }}</strong></div></div></div>@else<p class="alert alert-warning">Lokasi belum diketahui. Admin Warehouse harus menentukan tujuan dan melengkapi nomor part jika box belum terdaftar.</p>@endif
        @if($issue->notes)<p>Catatan admin: {{ $issue->notes }}</p>@endif
        @if($issue->status === 'awaiting_return' && (int)$issue->reported_by === auth()->id())
            <form method="POST" action="{{ route('export.receiving.picking.return', $issue) }}">@csrf<input type="hidden" name="revision" value="{{ $issue->revision }}"><input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="placement_confirmed" id="pick-return-confirm" value="1" required><label class="form-check-label" for="pick-return-confirm">Box {{ $issue->box_number }} sudah saya letakkan di tujuan pengembalian di atas.</label></div><button class="btn btn-primary" type="submit">Konfirmasi penempatan dan buka blokir</button></form>
        @elseif($issue->status === 'pending')<p class="plan-help">Approval harus diselesaikan oleh Admin Warehouse melalui Scan Issues dan Approval. Blokir tetap tersimpan meskipun halaman ditutup.</p><a class="btn btn-outline-primary" href="{{ route('export.receiving.picking.show', $pickParameters) }}">Periksa status approval</a>
        @else<p class="plan-help">Menunggu operator pelapor {{ $issue->reporter?->name }} mengonfirmasi pengembalian.</p>@endif
    </div></section>
@elseif($helper || $mandatoryRows->isNotEmpty())
    <section class="card verification-card"><div class="card-body">
        <span class="verification-section-label">{{ $helper ? 'Pencarian box' : 'Pengambilan wajib' }}</span><h2>Scan ID Box</h2><p class="verification-supporting">{{ $helper ? 'Periksa apakah box sesuai daftar rekomendasi.' : 'Cocokkan setiap box dari pallet campuran sebelum dipindahkan.' }}</p>
        <form method="POST" action="{{ route($helper ? 'export.picking.seek' : 'export.receiving.picking.scan', $pickParameters) }}" data-export-pick-scan data-scan-feedback novalidate>
            @csrf<input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
            <label class="form-label" for="export-pick-box">Scan ID Box (8 angka)</label><div class="verification-field"><input class="form-control export-picking-input" id="export-pick-box" name="box_number" inputmode="numeric" pattern="[0-9]{8}" maxlength="64" autocomplete="off" autofocus required aria-describedby="export-pick-hint"><button class="btn verification-submit" type="submit"><i class="bi bi-upc-scan" aria-hidden="true"></i> {{ $helper ? 'Cari box' : 'Verifikasi box' }}</button></div><p class="verification-field-hint" id="export-pick-hint">Scan label box yang utuh. ID Box pendek seperti 123 ditolak tanpa membuat approval.</p>
        </form>
    </div></section>
@else
    <section class="card verification-card"><div class="card-body"><h2>Tanpa scan satuan</h2><p class="verification-supporting mb-0">Transaksi ini tidak memiliki pengambilan yang wajib di-scan. Lanjutkan konfirmasi penempatan setelah box benar-benar diletakkan di tujuan.</p></div></section>
@endif
    <section class="card verification-card"><div class="card-body"><span class="verification-section-label">Progres pengambilan wajib</span><div class="verification-metrics"><div class="verification-metric"><span>Sudah diverifikasi</span><strong>{{ $verifiedCount }} <small>box</small></strong></div><div class="verification-metric"><span>Belum diverifikasi</span><strong>{{ $remainingCount }} <small>box</small></strong></div></div><p class="verification-supporting mb-0">{{ $helper ? 'Mode pencarian tidak mengubah progres verifikasi wajib.' : 'Verifikasi bukan konfirmasi penempatan. Lanjutkan penempatan setelah pengambilan sesuai.' }}</p>@if($helper && $mandatoryRows->isNotEmpty())<div class="verification-actions"><a class="btn btn-primary" href="{{ route('export.receiving.picking.show', $pickParameters) }}">Buka verifikasi wajib</a></div>@endif</div></section>
</div>
<section class="card verification-card"><div class="verification-list-heading"><div><span class="verification-section-label">Daftar rekomendasi</span><h2>Box untuk diambil</h2></div><span class="verification-total">{{ $rows->count() }} box</span></div>
    <div class="verification-table-scroll" role="region" tabindex="0" aria-label="Rekomendasi pengambilan FIFO"><table class="table verification-table align-middle mb-0"><thead><tr><th>ID Box / part</th><th>Ambil dari</th><th>Pertama diterima</th><th>Verifikasi pengambilan</th></tr></thead><tbody>
        @forelse($rows as $row)
            @php($scanned = $row->requires_scan && $row->picking_ready)
            <tr data-pick-box-number="{{ $row->box_number }}" @class(['table-success' => $scanned])>
                <th scope="row">{{ $row->box_number }}<small class="fulfillment-customer">{{ $row->stockBox->part_number }} · {{ $row->pcs_quantity }} PCS</small><small class="export-pick-scanned" data-pick-scanned @if(!$scanned) hidden @endif><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Sudah di-scan</small></th>
                <td>{{ $row->stockBox->currentPallet?->display_name }} · {{ $row->stockBox->currentPallet?->location_code }}</td>
                <td>{{ $row->first_received_at->format('d/m/Y H:i') }}</td>
                <td><span class="fulfillment-status {{ $row->requires_scan && !$row->picking_ready ? 'fulfillment-status-paused' : 'fulfillment-status-complete' }}">{{ $row->allocation_notice_id === null ? 'Alokasi saat penerimaan · tanpa scan tambahan' : (!$row->requires_scan ? 'Satu jenis part · tanpa scan satuan' : ($row->picking_ready ? 'Sudah diverifikasi' : 'Wajib scan · pallet campuran')) }}</span></td>
            </tr>
        @empty<tr><td colspan="4" class="plan-empty"><strong>Tidak ada pengambilan stok tersimpan</strong><span>Periksa antrean penempatan dan revisi rencana terbaru.</span></td></tr>@endforelse
    </tbody></table></div>
</section>

</div>
