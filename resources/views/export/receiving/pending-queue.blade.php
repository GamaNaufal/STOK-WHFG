<section class="plan-panel" id="receiving-pending-queue" aria-labelledby="receiving-pending-title">
    <div class="plan-panel-heading"><div><h2 id="receiving-pending-title">Antrean penempatan / pemindahan</h2><span>{{ $transactions->total() }} transaksi</span></div><form method="GET" action="{{ route('export.receiving.index') }}#receiving-pending-queue"><label class="form-label" for="pending-sort">Urutan waktu transaksi dibuat</label>@if($batch)<input type="hidden" name="batch" value="{{ $batch->id }}">@endif<select class="form-select" id="pending-sort" name="pending_sort" onchange="this.form.requestSubmit()"><option value="newest" @selected($sort === 'newest')>Terbaru dahulu</option><option value="oldest" @selected($sort === 'oldest')>Terlama dahulu</option></select></form></div>
    <p class="plan-help receiving-table-note">Rekomendasi stok dikelompokkan menjadi satu transaksi per pallet tujuan dan tinjauan FIFO. Penerimaan box baru tetap mengikuti transaksi scan massal. Urutan tampilan tidak mengubah FIFO.</p>
    <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Antrean transaksi penempatan ekspor">
        <table class="table plan-table"><thead><tr><th>Transaksi / tujuan</th><th>Waktu transaksi</th><th>Operator</th><th>Total box</th><th>Sudah ditempatkan</th><th>Menunggu</th><th>Aksi</th></tr></thead><tbody>
        @forelse($transactions as $transaction)
            @php
                $parameters = ['notice' => $transaction->transaction_id, 'pallet' => $transaction->planned_pallet_id];
                $detailUrl = match ($transaction->kind) {
                    'recommendation' => route('export.receiving.transactions.recommendation', $parameters),
                    'batch' => route('export.receiving.transactions.show', ['batch' => $transaction->transaction_id]),
                    default => route('export.receiving.transactions.legacy', ['stockBox' => $transaction->transaction_id]),
                };
                $printUrl = match ($transaction->kind) {
                    'recommendation' => route('export.receiving.transactions.recommendation-print', $parameters),
                    'batch' => route('export.receiving.transactions.print', ['batch' => $transaction->transaction_id]),
                    default => route('export.receiving.transactions.legacy-print', ['stockBox' => $transaction->transaction_id]),
                };
            @endphp
            <tr><th scope="row">{{ $transaction->code }}@if($transaction->kind === 'recommendation')<strong class="d-block text-primary">Pallet {{ $transaction->pallet_position }} · {{ $transaction->location_code }}</strong><small class="fulfillment-customer">{{ $transaction->plan_code }} · {{ $transaction->customer_name }}</small>@endif<small class="fulfillment-customer">{{ $transaction->placed_boxes > 0 ? 'Sebagian ditempatkan' : 'Menunggu penempatan' }}</small></th>
                <td>{{ $transaction->received_at->format('d/m/Y H:i') }}</td><td>{{ $transaction->operator_name }}</td>
                <td>{{ $transaction->total_boxes }}@if($transaction->recommendation_boxes)<small class="d-block text-muted">{{ $transaction->recommendation_boxes }} pada transaksi FIFO tersendiri</small>@endif</td><td>{{ $transaction->placed_boxes }}</td><td>{{ $transaction->pending_boxes }}@if($transaction->waiting_boxes)<small class="d-block text-muted">{{ $transaction->waiting_boxes }} menunggu Oke FIFO</small>@endif</td>
                <td><div class="plan-inline-actions"><a class="btn btn-sm btn-outline-primary" href="{{ $detailUrl }}" aria-label="Atur penempatan {{ $transaction->code }}">Atur penempatan</a><a class="btn btn-sm btn-outline-secondary" href="{{ $printUrl }}" target="_blank" rel="noopener" aria-label="Cetak panduan {{ $transaction->code }}">Cetak</a></div></td></tr>
        @empty<tr><td colspan="7" class="plan-empty">Tidak ada penempatan yang menunggu.</td></tr>@endforelse
        </tbody></table>
    </div>
    @if($transactions->hasPages())<div class="plan-panel-body">{{ $transactions->links('pagination::bootstrap-5') }}</div>@endif
</section>
