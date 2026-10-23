@extends('shared.layouts.app')
@section('title', 'Expired Box · QA & Packing')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">
@endsection
@section('content')
<div class="expired-page" id="expired-box-page">
    <header class="expired-page-header">
        <span class="expired-page-eyebrow">Kesehatan stok</span>
        <h1>Expired Box</h1>
        <p>Serahkan ke QA, catat hasil pemeriksaan, lalu terima ID pengganti dari packing.</p>
    </header>
    <div class="expired-page-guidance">
        <p><strong>Warning:</strong> Box belum expired, tidak perlu Handle. Tetap dapat masuk FIFO.</p>
        <p><strong>Handle:</strong> Menyatakan barang sudah diserahkan ke QA. Stok tetap tidak tersedia selama pemeriksaan dan packing.</p>
        <p><strong>Hasil packing:</strong> Satu box pengganti, part dan PCS sama. Batas aktif satu bulan sejak ID baru diterbitkan; umur/FIFO mengikuti tanggal masuk asli.</p>
    </div>
    @foreach(['success', 'error'] as $message)
        @if(session($message))<div role="status" class="alert alert-{{ $message === 'error' ? 'danger' : 'success' }}">{{ session($message) }}</div>@endif
    @endforeach
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="GET" class="p-3 d-flex flex-wrap gap-2"><label for="handling-search" class="visually-hidden">Cari ID box, part, pallet, atau lokasi</label><input class="form-control" style="max-width:24rem" id="handling-search" name="search" value="{{ request('search') }}" placeholder="ID box, part, pallet, atau lokasi"><button class="btn btn-outline-primary">Cari</button><a class="btn btn-outline-secondary" href="{{ route('expired-box.index') }}">Atur ulang</a></form>
    <section class="expired-page-panel" aria-labelledby="expired-title">
        <header class="expired-page-panel-heading"><div><h2 id="expired-title">Perlu diserahkan ke QA</h2><p>Termasuk box dari mekanisme Handle lama. Box assigned atau pick-locked harus diselesaikan dahulu.</p></div><span>{{ $expiredBoxes->count() }} box</span></header>
        <form id="bulk-start" method="GET" action="{{ route('expired-box.bulk.review') }}" class="expired-bulk-toolbar" data-bulk-selection="start">
            <input type="hidden" name="action" value="start">
            <button type="button" class="btn btn-outline-secondary" data-select-all="start" hidden>Pilih semua · maks. 50</button>
            <button type="button" class="btn btn-outline-secondary" data-select-none="start" hidden>Kosongkan pilihan</button>
            <span data-selection-count="start" aria-live="polite">Pilih box melalui kotak centang.</span>
            <button class="btn btn-primary" data-review="start">Tinjau penyerahan QA</button>
        </form>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box">
            <table class="table expired-page-table mb-0"><thead><tr><th>Pilih</th><th>ID box</th><th>Part</th><th>Masuk asli</th><th>Asal pallet / lokasi</th><th>Tindakan</th></tr></thead><tbody>
            @forelse($expiredBoxes as $box)
                <tr><td><label class="expired-bulk-check"><input type="checkbox" form="bulk-start" name="ids[]" value="{{ $box->id }}" data-bulk-item="start"><span class="visually-hidden">Pilih box {{ $box->box_number }}</span></label></td><td>{{ $box->box_number }}</td><td>{{ $box->part_number }}</td><td>{{ $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at)->format('d M Y') : '—' }}</td><td>{{ $box->pallet_number ?? '—' }} / {{ $box->warehouse_location ?? 'Lokasi tidak aktif' }}</td><td>
                    <form method="POST" action="{{ route('expired-box.handle', $box->id) }}">@csrf
                        <button class="btn btn-primary" type="submit">Handle · sudah diserahkan ke QA</button>
                    </form>
                </td></tr>
            @empty<tr><td colspan="6" class="text-center py-4">Tidak ada box yang menunggu penyerahan ke QA.</td></tr>@endforelse
            </tbody></table>
        </div>
    </section>
    <section class="expired-page-panel" aria-labelledby="handling-title">
        <header class="expired-page-panel-heading"><div><h2 id="handling-title">Penanganan QA & packing</h2><p>Proses yang belum selesai ditampilkan terlebih dahulu. Riwayat tetap tersedia setelah box ditolak atau diganti.</p></div></header>
        <form id="bulk-handling" method="GET" action="{{ route('expired-box.bulk.review') }}" class="expired-bulk-toolbar" data-bulk-selection="handling">
            <div><label for="bulk-action" class="form-label">Tindakan untuk pilihan</label><select id="bulk-action" name="action" class="form-select"><option value="qa">Catat hasil QA</option><option value="packing">Serahkan ke packing</option><option value="receive">Terima hasil packing</option><option value="print">Cetak pengantar bersama</option></select></div>
            <button type="button" class="btn btn-outline-secondary" data-select-all="handling" hidden>Pilih semua sesuai tindakan</button>
            <button type="button" class="btn btn-outline-secondary" data-select-none="handling" hidden>Kosongkan pilihan</button>
            <span data-selection-count="handling" aria-live="polite">Pilihan hanya pada halaman ini, maksimal 50 box.</span>
            <button class="btn btn-primary" data-review="handling">Tinjau pilihan</button>
        </form>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box"><table class="table expired-page-table mb-0"><thead><tr><th>Pilih</th><th>Referensi</th><th>ID lama → ID baru</th><th>Part / PCS</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($handlings as $handling)
            <tr><td>@if($handling->status !== 'rejected')<label class="expired-bulk-check"><input type="checkbox" form="bulk-handling" name="ids[]" value="{{ $handling->id }}" data-bulk-item="handling" data-state="{{ $handling->status }}"><span class="visually-hidden">Pilih box {{ $handling->source_box_number }}</span></label>@endif</td><td>{{ $handling->reference }}</td><td>{{ $handling->source_box_number }} → {{ $handling->replacement_box_number ?? 'Belum terbit' }}</td><td>{{ $handling->part_number }} / {{ $handling->pcs_quantity }}</td><td>{{ $handling->status_label }}</td><td><a class="btn btn-outline-primary" href="{{ route('expired-box.show', $handling) }}">Buka penanganan</a></td></tr>
            @empty<tr><td colspan="6" class="text-center py-4">Belum ada penanganan yang cocok.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-3">{{ $handlings->links('pagination::bootstrap-5') }}</div>
    </section>
    <section class="expired-page-panel" aria-labelledby="warning-title">
        <header class="expired-page-panel-heading"><h2 id="warning-title">Warning · masih tersedia untuk FIFO</h2><span>{{ $warningBoxes->count() }} box</span></header>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box"><table class="table expired-page-table mb-0"><thead><tr><th>ID box</th><th>Part</th><th>Tanggal masuk asli</th></tr></thead><tbody>
            @forelse($warningBoxes as $box)<tr><td>{{ $box->box_number }}</td><td>{{ $box->part_number }}</td><td>{{ \Illuminate\Support\Carbon::parse($box->stored_at)->format('d M Y') }}</td></tr>@empty<tr><td colspan="3" class="text-center py-4">Tidak ada box warning.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <section class="expired-page-panel" aria-labelledby="legacy-title">
        <header class="expired-page-panel-heading"><div><h2 id="legacy-title">Riwayat aktivasi mekanisme lama</h2><p>Catatan historis ini bukan bukti pemeriksaan QA atau penyerahan ke packing.</p></div></header>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box"><table class="table expired-page-table mb-0"><thead><tr><th>ID box</th><th>Petugas</th><th>Handle lama</th><th>Batas aktif lama</th></tr></thead><tbody>
            @forelse($handledHistory as $report)<tr><td>{{ $report->box_number }}</td><td>{{ $report->handler?->name ?? '—' }}</td><td>{{ $report->handled_at?->format('d M Y H:i') }}</td><td>{{ $report->reactivated_until?->format('d M Y H:i') }}</td></tr>@empty<tr><td colspan="4" class="text-center py-4">Tidak ada riwayat aktivasi lama.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-3">@if(method_exists($handledHistory, 'links')){{ $handledHistory->links('pagination::bootstrap-5') }}@endif</div>
    </section>
</div>
@endsection
@section('scripts')
<script src="{{ asset('js/expired-box-bulk.js') }}" defer></script>
@endsection
