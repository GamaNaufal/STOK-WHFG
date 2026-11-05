<header class="expired-page-header warehouse-scope-header">
    <div class="warehouse-scope-heading"><span class="expired-page-eyebrow">QA & Packing · {{ $scopeLabel }}</span><h1>Expired Box dan Handle</h1><p>Pantau box expired, proses QA, dan penerimaan hasil packing.</p></div>
    <div class="expired-workspace-actions">
        @if(count($scopeUrls) === 2)@include('shared.warehouse-scope-switch', ['scopeLabel' => 'Lingkup penanganan box'])@else<span class="expired-transaction-status">{{ $scopeLabel }}</span>@endif
        <a class="btn btn-primary" href="{{ route($expiredRoutePrefix.'transactions.create') }}">Buat transaksi</a>
    </div>
</header>
@foreach(['success', 'error'] as $message)
    @if(session($message))<div class="alert alert-{{ $message === 'error' ? 'danger' : 'success' }}" role="status">{{ session($message) }}</div>@endif
@endforeach
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="expired-workspace-summary" aria-label="Ringkasan {{ $scopeLabel }}">
    <div><strong>{{ $expiredCount }}</strong><span>box expired</span></div><div><strong>{{ $processCount }}</strong><span>transaksi berjalan</span></div><div><strong>{{ $warningCount }}</strong><span>box warning</span></div>
</div>
<nav class="expired-workspace-tabs" aria-label="Daftar penanganan">
    @foreach(['expired' => ['Perlu ditangani', $expiredCount], 'process' => ['Dalam proses', $processCount], 'history' => ['Riwayat', $historyCount], 'warning' => ['Warning', $warningCount]] as $key => [$label, $count])
    <a data-expired-link href="{{ route($expiredRoutePrefix.'index', array_merge(request()->except(['tab', 'transaction_page', 'page']), ['tab' => $key])) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }} <span>{{ $count }}</span></a>
    @endforeach
</nav>
<form method="GET" action="{{ route($expiredRoutePrefix.'index') }}" class="expired-workspace-filter" data-expired-filter>
    <input type="hidden" name="tab" value="{{ $tab }}">
    <label class="visually-hidden" for="expired-search">Cari nomor part, ID Box, pallet, lokasi atau transaksi</label>
    <input id="expired-search" name="search" class="form-control" type="search" value="{{ $search }}" placeholder="Cari nomor part, ID Box, pallet, lokasi atau transaksi" autocomplete="off">
    @if(in_array($tab, ['expired', 'warning']))<label class="visually-hidden" for="expired-location">Lokasi</label><select id="expired-location" class="form-select" name="location_id"><option value="">Semua lokasi</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->code }}</option>@endforeach</select>@endif
    <noscript><button type="submit" class="btn btn-outline-primary">Terapkan</button></noscript>
</form>
<p class="expired-workspace-feedback" data-expired-feedback role="status" aria-live="polite"></p>
@if(in_array($tab, ['expired', 'warning']))
<section class="expired-page-panel" aria-labelledby="expired-group-title">
    <header class="expired-page-panel-heading"><div><h2 id="expired-group-title">{{ $tab === 'warning' ? 'Warning · masih tersedia untuk FIFO' : 'Kelompok box expired' }}</h2><p>{{ $tab === 'warning' ? 'Box mendekati expired dan belum perlu diserahkan ke QA.' : 'Pilih kelompok part dan PCS untuk membuat satu transaksi.' }}</p></div></header>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Kelompok box {{ $tab }}"><table class="table expired-page-table mb-0"><thead><tr><th>Nomor part</th><th>PCS/box</th><th>Jumlah box</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
    @forelse($inventoryGroups as $key => $group)
    @php($blocked = $group->filter(fn ($box) => !empty($box->delivery_id) || !empty($box->source_ready_at))->count())
    <tr><td><strong>{{ $group->first()->part_number }}</strong>@if($group->first()->is_not_full ?? false)<small class="d-block">Box Not Full</small>@endif</td><td>{{ number_format($group->first()->pcs_quantity) }}</td><td>{{ $group->count() }} box</td><td>@if($blocked)<span class="expired-transaction-status is-at_qa">{{ $blocked }} terikat permintaan</span>@else{{ $tab === 'warning' ? 'Belum expired' : 'Siap ditangani' }}@endif</td><td>@if($tab === 'expired')<a class="btn btn-outline-primary" href="{{ route($expiredRoutePrefix.'transactions.create', ['group' => $key]) }}">Lihat box</a>@else<details><summary>Lihat umur</summary>@foreach($group as $box)<p class="mb-2"><strong>{{ $box->box_number }}</strong> · {{ $box->warehouse_location ?? 'Belum ada lokasi' }}@include('warehouse.expired-box.box-age')</p>@endforeach</details>@endif</td></tr>
    @empty<tr><td colspan="5" class="expired-page-table-empty">{{ $search ? 'Tidak ada box yang sesuai pencarian.' : ($tab === 'warning' ? 'Tidak ada box warning.' : 'Tidak ada box expired yang menunggu penyerahan ke QA.') }}</td></tr>@endforelse
    </tbody></table></div>
    <footer class="expired-workspace-guidance">@if($scope === 'export')Box terikat permintaan harus dilepas melalui revisi sebelum diserahkan ke QA.@else Box assigned atau pick-locked harus dilepas melalui revisi sebelum penyerahan QA.@endif Satu transaksi untuk satu nomor part dan PCS per box yang sama.</footer>
</section>
@else
<section class="expired-page-panel" aria-labelledby="expired-transactions-title">
    <header class="expired-page-panel-heading"><div><h2 id="expired-transactions-title">{{ $tab === 'history' ? 'Riwayat penanganan selesai' : 'Transaksi dalam proses' }}</h2><p>Buka transaksi untuk melanjutkan tahapnya dan melihat rincian setiap penerimaan.</p></div></header>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Daftar transaksi"><table class="table expired-page-table mb-0"><thead><tr><th>Transaksi</th><th>Part / isi</th><th>Tahap</th><th>Hasil</th><th>Aksi</th></tr></thead><tbody>
    @forelse($transactions as $transaction)
    <tr data-transaction-row="{{ $transaction->id }}"><td><strong>{{ $transaction->reference }}</strong><small class="d-block">{{ $transaction->created_at->format('d M Y H:i') }}</small></td><td>{{ $transaction->handlings->pluck('part_number')->unique()->join(', ') }}<small class="d-block">{{ $transaction->handlings->count() }} box · {{ number_format($transaction->handlings->sum('pcs_quantity')) }} PCS</small></td><td><span class="expired-transaction-status is-{{ $transaction->stage }}">{{ $transaction->status_label }}</span></td><td>{{ $transaction->handlings->where('status', 'completed')->count() }} diterima<small class="d-block">{{ $transaction->handlings->where('status', 'rejected')->count() }} ditolak QA</small></td><td><a class="btn btn-outline-primary" href="{{ route($expiredRoutePrefix.'transactions.show', $transaction) }}">{{ $transaction->action_label }}</a></td></tr>
    @empty<tr><td colspan="5" class="expired-page-table-empty">Belum ada transaksi{{ $search ? ' yang cocok' : ' pada tab ini' }}.</td></tr>@endforelse
    </tbody></table></div>
    <div class="expired-page-pagination" data-expired-pagination>{{ $transactions->links('pagination::bootstrap-5') }}</div>
</section>
@endif
@if($tab === 'history' && $handledHistory->count())
<details class="expired-page-panel"><summary class="p-3">Riwayat aktivasi mekanisme lama</summary>
    <div class="table-responsive"><table class="table expired-page-table"><thead><tr><th>ID Box</th><th>Part</th><th>Petugas</th><th>Handle lama</th><th>Batas aktif lama</th></tr></thead><tbody>
    @foreach($handledHistory as $report)<tr><td>{{ $report->box_number }}</td><td>{{ $report->part_number }}</td><td>{{ $report->handler?->name ?? '—' }}</td><td>{{ $report->handled_at?->format('d M Y H:i') }}</td><td>{{ $report->reactivated_until?->format('d M Y H:i') }}</td></tr>@endforeach
    </tbody></table></div><div class="expired-page-pagination" data-expired-pagination>{{ $handledHistory->links('pagination::bootstrap-5') }}</div>
</details>
@endif
