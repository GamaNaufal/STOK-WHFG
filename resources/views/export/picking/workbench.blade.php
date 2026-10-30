@if($issue)
    <section class="plan-panel"><div class="plan-panel-heading"><h2>Pengambilan diblokir · issue ekspor #{{ $issue->id }}</h2><span>{{ $issue->status === 'pending' ? 'Menunggu approval admin' : 'Menunggu konfirmasi penempatan' }}</span></div><div class="plan-panel-body">
        <p>Box salah: <strong>{{ $issue->box_number }}</strong> · {{ $issue->reason }}</p>
        @if($issue->part)<p>Nomor part sesuai approval: <strong>{{ $issue->part->part_number }}</strong> · {{ $issue->pcs_quantity }} PCS / box.</p>@endif
        @if($issue->return_location_code)<div class="receiving-destination"><p>Kembalikan box ke</p><div class="receiving-destination-values"><div><span>Pallet</span><strong>{{ $issue->returnPallet?->display_name ?? 'Pallet cadangan baru' }}</strong></div><div><span>Lokasi</span><strong>{{ $issue->return_location_code }}</strong></div></div></div>@else<p class="alert alert-warning">Lokasi belum diketahui. Admin Warehouse harus menentukan tujuan dan melengkapi nomor part jika box belum terdaftar.</p>@endif
        @if($issue->notes)<p>Catatan admin: {{ $issue->notes }}</p>@endif
        @if($issue->status === 'awaiting_return' && (int)$issue->reported_by === auth()->id())
            <form method="POST" action="{{ route('export.receiving.picking.return', $issue) }}">@csrf<input type="hidden" name="revision" value="{{ $issue->revision }}"><input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="placement_confirmed" id="pick-return-confirm" value="1" required><label class="form-check-label" for="pick-return-confirm">Box {{ $issue->box_number }} sudah saya letakkan di tujuan pengembalian di atas.</label></div><button class="btn btn-primary" type="submit">Konfirmasi penempatan dan buka blokir</button></form>
        @elseif($issue->status === 'pending')<p class="plan-help">Approval harus diselesaikan oleh Admin Warehouse melalui Scan Issues dan Approval. Blokir tetap tersimpan meskipun halaman ditutup.</p><a class="btn btn-outline-primary" href="{{ route('export.receiving.picking.show', ['pallet'=>$pallet->id]) }}">Periksa status approval</a>
        @else<p class="plan-help">Menunggu operator pelapor {{ $issue->reporter?->name }} mengonfirmasi pengembalian.</p>@endif
    </div></section>
@else
    <section class="plan-panel"><div class="plan-panel-body">
        <form method="POST" action="{{ route($helper ? 'export.picking.seek' : 'export.receiving.picking.scan', ['pallet'=>$pallet->id]) }}" data-export-pick-scan novalidate>
            @csrf<input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
            <label class="form-label" for="export-pick-box">Scan nomor box (8 angka)</label><div class="receiving-field-action"><input class="form-control" id="export-pick-box" name="box_number" inputmode="numeric" pattern="[0-9]{8}" maxlength="64" autocomplete="off" autofocus required aria-describedby="export-pick-hint"><button class="btn btn-primary" type="submit">{{ $helper ? 'Cari box' : 'Verifikasi box' }}</button></div><p class="plan-help mt-2 mb-0" id="export-pick-hint">Scan label box yang utuh. Nomor pendek seperti 123 ditolak tanpa membuat approval.</p>
        </form>
    </div></section>
@endif
<section class="plan-panel"><div class="plan-panel-heading"><h2>Rekomendasi box untuk diambil</h2><span>{{ $rows->count() }} box</span></div>
    <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Rekomendasi pengambilan FIFO"><table class="table plan-table"><thead><tr><th>Nomor box / part</th><th>Ambil dari</th><th>Pertama diterima</th><th>Verifikasi pengambilan</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><th scope="row">{{ $row->box_number }}<small class="fulfillment-customer">{{ $row->stockBox->part_number }} · {{ $row->pcs_quantity }} PCS</small></th><td>{{ $row->stockBox->currentPallet?->display_name }} · {{ $row->stockBox->currentPallet?->location_code }}</td><td>{{ $row->first_received_at->format('d/m/Y H:i') }}</td><td><span class="fulfillment-status {{ $row->requires_scan && !$row->picking_ready ? 'fulfillment-status-paused' : 'fulfillment-status-complete' }}">{{ $row->allocation_notice_id === null ? 'Alokasi saat penerimaan · tanpa scan tambahan' : (!$row->requires_scan ? 'Satu jenis part · tanpa scan satuan' : ($row->picking_ready ? 'Sudah diverifikasi' : 'Wajib scan · pallet campuran')) }}</span></td></tr>@empty<tr><td colspan="4" class="plan-empty"><strong>Tidak ada pengambilan stok tersimpan</strong><span>Periksa antrean penempatan dan revisi rencana terbaru.</span></td></tr>@endforelse
    </tbody></table></div>
</section>
