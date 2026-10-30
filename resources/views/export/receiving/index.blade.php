@extends('shared.layouts.app')
@section('title', 'Penerimaan Transfer Box Ekspor')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-receiving.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page receiving-page" id="export-receiving">
    <header class="plan-header"><div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Penerimaan Transfer Box Ekspor</h1><p>Scan banyak box terlebih dahulu, lalu proses untuk melihat pembagian lokasi dan pallet.</p></div><a class="btn btn-outline-primary" href="{{ route('export.stock.index') }}">Stok ekspor</a></header>
    @if($errors->any())<div class="alert alert-danger" id="receiving-errors" role="alert" tabindex="-1"><strong>Daftar belum berhasil diproses.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif

        <div class="receiving-workspace receiving-bulk-workspace">
            <section class="plan-panel" aria-labelledby="receiving-scan-title"><div class="plan-panel-heading"><h2 id="receiving-scan-title">Scan box dan part</h2><span class="plan-help">PCS otomatis</span></div><div class="plan-panel-body">
                <form id="export-bulk-scan-form" novalidate data-scan-feedback data-batches-url="{{ route('export.receiving.batches.start') }}" data-check-url="{{ route('export.receiving.check-box') }}" data-stock-url="{{ route('export.stock.index') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label" for="receive-box-number">1. Scan nomor box</label><div class="receiving-field-action"><input class="form-control" id="receive-box-number" type="text" inputmode="numeric" autocomplete="off" maxlength="8" pattern="[0-9]{8}" aria-describedby="receive-box-help"><button class="btn btn-outline-primary" type="button" id="scan-box-button">Cek box</button></div><p class="plan-help" id="receive-box-help">Nomor box 8 digit. Enter untuk lanjut ke nomor part.</p></div>
                    <div class="mb-3"><label class="form-label" for="receive-part-number">2. Scan nomor part</label><input class="form-control" id="receive-part-number" type="text" autocomplete="off" maxlength="256"><p class="plan-help">Enter menambahkan box ke daftar, lalu scan box berikutnya.</p></div>
                    <button class="btn btn-primary w-100" type="submit" id="add-scan-button">Tambahkan ke daftar scan</button>
                    <button class="btn btn-link mt-1" type="button" id="reset-scan-input">Ganti nomor box</button>
                    <div class="receiving-scan-outcome" id="scan-outcome" role="status" aria-live="polite"><strong id="scan-outcome-label">Siap scan</strong><span id="receiving-scan-status">Scan nomor box, kemudian nomor part.</span></div>
                    <a id="receiving-existing-box" class="plan-back mt-2 mb-0" hidden>Buka box yang sudah tercatat</a>
                </form>
            </div></section>
            <section class="plan-panel" aria-labelledby="receiving-draft-title"><div class="plan-panel-heading"><h2 id="receiving-draft-title">Daftar scan</h2><strong><span id="scan-count">{{ count($state['items']) }}</span> / 1.000 box</strong></div>
                <p class="plan-help receiving-table-note" id="scan-summary">Daftar tersimpan sebagai draft sampai diproses.</p>
                <div class="receiving-draft-scroll" role="region" tabindex="0" aria-label="Daftar box yang sudah di-scan"><table class="table plan-table receiving-draft-table"><thead><tr><th>No.</th><th>Nomor box</th><th>Nomor part</th><th>PCS</th><th>Aksi</th></tr></thead><tbody id="scan-items"></tbody></table><p class="receiving-draft-empty" id="scan-empty">Belum ada box dalam daftar scan.</p></div>
                <div class="receiving-bulk-actions">
                    <form method="POST" id="process-batch-form" action="{{ $batch ? route('export.receiving.batches.process', $batch) : '#' }}">@csrf<input type="hidden" name="revision" id="batch-revision" value="{{ $state['revision'] }}"><button class="btn btn-primary" type="submit" id="process-batch-button" disabled>Proses penerimaan</button></form>
                    <button class="btn btn-outline-danger" type="button" id="discard-batch-button" disabled>Kosongkan daftar</button>
                </div>
                <p class="plan-help receiving-table-note">Tujuan lokasi/pallet muncul setelah Proses penerimaan. Scan belum menambah stok fisik atau pemenuhan.</p>
            </section>
        </div>
        <script type="application/json" id="receiving-batch-bootstrap">@json($state)</script>
        <noscript><div class="alert alert-warning">Aktifkan JavaScript untuk scan bulk dan umpan balik OK/NG.</div></noscript>
    @include('export.receiving.pending-queue')
</div>
@endsection
@section('scripts')
@include('shared.partials.scan-feedback')
@vite('resources/js/export-stock-receiving.js')
@endsection
