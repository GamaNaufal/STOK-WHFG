<section class="plan-panel" id="receiving-pending-queue" aria-labelledby="receiving-pending-title">
    <div class="plan-panel-heading"><h2 id="receiving-pending-title">Antrean penempatan / pemindahan</h2><span>{{ $transactions->total() }} transaksi</span></div>
    <p class="plan-help receiving-table-note">Satu baris mewakili satu transaksi penerimaan. Urutan mengikuti box yang paling awal diterima dan masih menunggu penempatan.</p>
    <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Antrean transaksi penerimaan ekspor">
        <table class="table plan-table"><thead><tr><th>Transaksi</th><th>Waktu penerimaan</th><th>Operator</th><th>Total box</th><th>Sudah ditempatkan</th><th>Menunggu</th><th>Aksi</th></tr></thead><tbody>
        @forelse($transactions as $transaction)
            <tr><th scope="row">{{ $transaction->code }}<small class="fulfillment-customer">{{ $transaction->placed_boxes > 0 ? 'Sebagian ditempatkan' : 'Menunggu penempatan' }}</small></th>
                <td>{{ $transaction->received_at->format('d/m/Y H:i') }}</td><td>{{ $transaction->operator_name }}</td>
                <td>{{ $transaction->total_boxes }}</td><td>{{ $transaction->placed_boxes }}</td><td>{{ $transaction->pending_boxes }}@if($transaction->waiting_boxes)<small class="d-block text-muted">{{ $transaction->waiting_boxes }} menunggu Oke FIFO</small>@endif</td>
                <td><div class="plan-inline-actions"><a class="btn btn-sm btn-outline-primary" href="{{ $transaction->kind === 'batch' ? route('export.receiving.transactions.show', ['batch' => $transaction->transaction_id]) : route('export.receiving.transactions.legacy', ['stockBox' => $transaction->transaction_id]) }}" aria-label="Atur penempatan {{ $transaction->code }}">Atur penempatan</a><a class="btn btn-sm btn-outline-secondary" href="{{ $transaction->kind === 'batch' ? route('export.receiving.transactions.print', ['batch' => $transaction->transaction_id]) : route('export.receiving.transactions.legacy-print', ['stockBox' => $transaction->transaction_id]) }}" target="_blank" rel="noopener" aria-label="Cetak panduan {{ $transaction->code }}">Cetak</a></div></td></tr>
        @empty<tr><td colspan="7" class="plan-empty">Tidak ada penempatan yang menunggu.</td></tr>@endforelse
        </tbody></table>
    </div>
    @if($transactions->hasPages())<div class="plan-panel-body">{{ $transactions->links('pagination::bootstrap-5') }}</div>@endif
</section>
