@extends('shared.layouts.app')
@section('title', 'Transaksi Expired Box')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">@endsection
@section('content')
@php($ageAsOf = now())
<div class="expired-page">
    <header class="expired-page-header expired-transaction-header">
        <div><span class="expired-page-eyebrow">QA & Packing</span><h1>Transaksi Expired Box</h1><p>Satu penyerahan, satu transaksi sampai seluruh box selesai.</p></div>
        <a class="btn btn-primary" href="{{ route('expired-box.transactions.create') }}">Buat transaksi</a>
    </header>
    @foreach(['success', 'error'] as $message)
        @if(session($message))<div role="status" class="alert alert-{{ $message === 'error' ? 'danger' : 'success' }}">{{ session($message) }}</div>@endif
    @endforeach
    @if($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <form method="GET" class="d-flex flex-wrap gap-2 mb-3"><label class="visually-hidden" for="transaction-search">Cari transaksi, ID box, part, pallet, atau lokasi</label><input id="transaction-search" class="form-control" style="max-width:28rem" name="search" value="{{ request('search') }}" placeholder="Transaksi, ID box, part, pallet, atau lokasi"><button class="btn btn-outline-primary">Cari</button>@if(request('search'))<a class="btn btn-outline-secondary" href="{{ route('expired-box.index') }}">Atur ulang</a>@endif</form>
    <nav class="expired-page-jumps" aria-label="Bagian halaman"><a href="#expired-title">Expired <strong>{{ $expiredBoxes->count() }}</strong></a><a href="#warning-title">Warning <strong>{{ $warningBoxes->count() }}</strong></a><a href="#transactions-title">Transaksi <strong>{{ $transactions->total() }}</strong></a></nav>
    <section class="expired-page-panel" aria-labelledby="expired-title">
        <header class="expired-page-panel-heading">
            <div><span class="expired-page-section-kicker is-expired">Perlu penanganan</span><h2 id="expired-title">Box expired</h2><p>Belum diserahkan ke QA dan tidak tersedia untuk delivery. Umur dihitung dari tanggal masuk asli sampai {{ $ageAsOf->format('d M Y H:i') }}.</p></div>
            <span class="expired-page-panel-count">{{ $expiredBoxes->count() }} box{{ request('search') ? ' sesuai pencarian' : '' }}</span>
        </header>
        @include('warehouse.expired-box.inventory-table', ['inventoryBoxes' => $expiredBoxes, 'tableLabel' => 'Daftar box expired dan umur saat ini', 'emptyMessage' => request('search') ? 'Tidak ada box expired yang sesuai pencarian.' : 'Tidak ada box expired yang menunggu penyerahan ke QA.'])
    </section>
    <section class="expired-page-panel" aria-labelledby="warning-title">
        <header class="expired-page-panel-heading"><div><span class="expired-page-section-kicker is-warning">Mendekati expired</span><h2 id="warning-title">Warning · masih tersedia untuk FIFO</h2><p>Umur 9 hingga kurang dari 12 bulan sejak tanggal masuk asli. Box belum expired, tidak perlu Handle.</p></div><span class="expired-page-panel-count">{{ $warningBoxes->count() }} box{{ request('search') ? ' sesuai pencarian' : '' }}</span></header>
        @include('warehouse.expired-box.inventory-table', ['inventoryBoxes' => $warningBoxes, 'tableLabel' => 'Daftar box warning dan umur saat ini', 'emptyMessage' => request('search') ? 'Tidak ada box warning yang sesuai pencarian.' : 'Tidak ada box warning.'])
    </section>
    <section class="expired-page-panel" aria-labelledby="transactions-title">
        <header class="expired-page-panel-heading"><div><h2 id="transactions-title">Daftar transaksi</h2><p>Buka transaksi untuk melanjutkan tahapnya. Daftar box tetap bersama dari QA hingga penerimaan.</p></div></header>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Daftar transaksi penanganan"><table class="table expired-page-table mb-0"><thead><tr><th>Transaksi</th><th>Isi transaksi</th><th>Status</th><th>Hasil</th><th>Tindakan</th></tr></thead><tbody>
        @forelse($transactions as $transaction)
            <tr data-transaction-row="{{ $transaction->id }}"><td><a class="fw-bold" href="{{ route('expired-box.transactions.show', $transaction) }}">{{ $transaction->reference }}</a><small class="d-block text-muted">{{ $transaction->created_at->format('d M Y H:i') }}</small></td><td><strong>{{ $transaction->handlings->count() }} box</strong><small class="d-block">{{ number_format($transaction->handlings->sum('pcs_quantity')) }} PCS</small></td><td><span class="expired-transaction-status is-{{ $transaction->stage }}">{{ $transaction->status_label }}</span></td><td>{{ $transaction->handlings->where('status', 'completed')->count() }} diterima<small class="d-block">{{ $transaction->handlings->where('status', 'rejected')->count() }} ditolak QA</small></td><td><a class="btn btn-outline-primary" href="{{ route('expired-box.transactions.show', $transaction) }}">{{ $transaction->action_label }}</a></td></tr>
        @empty<tr><td colspan="5" class="text-center py-5"><strong>Belum ada transaksi{{ request('search') ? ' yang cocok' : '' }}.</strong><p class="mb-0">{{ request('search') ? 'Coba nomor transaksi, ID box lama atau baru, atau nomor part.' : 'Pilih box yang diserahkan bersamaan melalui Buat transaksi.' }}</p></td></tr>@endforelse
        </tbody></table></div>
        <div class="p-3">{{ $transactions->links('pagination::bootstrap-5') }}</div>
    </section>
    <details class="expired-page-panel"><summary class="p-3">Riwayat aktivasi mekanisme lama</summary><section aria-labelledby="legacy-title">
        <header class="expired-page-panel-heading"><div><h2 id="legacy-title">Riwayat aktivasi mekanisme lama</h2><p>Catatan historis ini bukan bukti pemeriksaan QA atau penyerahan ke packing.</p></div></header>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box"><table class="table expired-page-table mb-0"><thead><tr><th>ID box</th><th>Petugas</th><th>Handle lama</th><th>Batas aktif lama</th></tr></thead><tbody>
            @forelse($handledHistory as $report)<tr><td>{{ $report->box_number }}</td><td>{{ $report->handler?->name ?? '—' }}</td><td>{{ $report->handled_at?->format('d M Y H:i') }}</td><td>{{ $report->reactivated_until?->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4">Tidak ada riwayat aktivasi lama.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-3">@if(method_exists($handledHistory, 'links')){{ $handledHistory->links('pagination::bootstrap-5') }}@endif</div>
    </section></details>
</div>
@endsection
@section('scripts')
<script src="{{ asset('js/expired-box-bulk.js') }}" defer></script>
@endsection
