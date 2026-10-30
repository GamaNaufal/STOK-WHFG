@extends('shared.layouts.app')
@section('title', $plan ? 'Ubah Rencana Delivery Ekspor' : 'Rencana Delivery Ekspor Baru')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
@endsection

@section('content')
@php
    $initialRows = old('items', $rows) ?: [['part_number' => '', 'pcs_quantity' => '']];
    $initialSource = old('source_type', $plan?->source_type ?? 'manual');
@endphp
<div class="delivery-plan-page" id="delivery-plan-editor" data-import-url="{{ route('export.delivery-plans.import-excel') }}">
    <header class="plan-header">
        <div>
            <a class="plan-back" href="{{ route('export.delivery-plans.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Rencana delivery</a>
            <span class="plan-eyebrow">Pengiriman ekspor</span>
            <h1>{{ $plan ? 'Ubah rencana delivery' : 'Rencana delivery baru' }}</h1>
            <p>Isi kebutuhan pengiriman. Kapasitas box mengikuti master part ekspor.</p>
        </div>
    </header>
    @if($errors->any() && !$errors->has('duplicate'))
        <div class="alert alert-danger" role="alert">
            <strong>Periksa kembali rencana sebelum menyimpan.</strong>
            <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <section class="plan-panel" aria-labelledby="plan-import-title">
        <div class="plan-panel-heading"><div><h2 id="plan-import-title">Mulai dari input atau dokumen</h2><p>Satu dokumen untuk satu customer. Hasil pembacaan dapat dikoreksi sebelum disimpan.</p></div></div>
        <div class="plan-panel-body">
            <div class="plan-input-methods" aria-label="Cara mengisi rencana">
                <button type="button" class="btn btn-outline-secondary is-selected" data-input-mode="manual" aria-pressed="true">Isi manual</button>
                <button type="button" class="btn btn-outline-secondary" data-input-mode="excel" aria-pressed="false"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Dari Excel</button>
            </div>
            <div id="plan-excel-panel" class="plan-import-panel" hidden>
                <label class="form-label" for="plan-excel-file">File Excel</label>
                <input class="form-control" type="file" id="plan-excel-file" accept=".xlsx,.xls" aria-describedby="excel-help">
                <p class="plan-help" id="excel-help">Format .xlsx atau .xls, maksimal 5 MB dan 500 baris part. Gunakan satu sheet untuk satu customer.</p>
                <div class="plan-inline-actions">
                    <button type="button" class="btn btn-primary" id="plan-read-excel">Baca Excel</button>
                    <a class="btn btn-outline-secondary" href="{{ route('export.delivery-plans.template') }}">Unduh template Excel</a>
                </div>
            </div>
            <p id="plan-import-status" class="plan-import-status" role="status" aria-live="polite"></p>
            <div id="plan-import-warnings" class="alert alert-warning mt-3 mb-0" role="status" aria-live="polite" hidden>
                <strong>Periksa hasil impor</strong><ul id="plan-warning-list" class="mb-0 mt-2"></ul>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="plan-undo-import" hidden>Batalkan hasil impor</button>
        </div>
    </section>

    <form method="POST" action="{{ $plan ? route('export.delivery-plans.update', $plan) : route('export.delivery-plans.store') }}" id="delivery-plan-form">
        @csrf
        <input type="hidden" name="request_token" value="{{ old('request_token', (string)\Illuminate\Support\Str::uuid()) }}">
        @if($plan) @method('PUT') @endif
        <input type="hidden" name="revision" value="{{ old('revision', $plan?->revision ?? 1) }}">
        <input type="hidden" name="source_type" id="plan-source-type" value="{{ $initialSource }}">
        <input type="hidden" name="source_name" id="plan-source-name" value="{{ old('source_name', $plan?->source_name) }}">
        <section class="plan-panel" aria-labelledby="plan-customer-title">
            <div class="plan-panel-heading"><div><h2 id="plan-customer-title">Tujuan pengiriman</h2><p>Lengkapi kode rencana, customer dan batas tanggal pengiriman.</p></div></div>
            <div class="plan-panel-body plan-fields">
                <div><label class="form-label" for="plan-code">Kode rencana delivery</label>
                    <input type="text" class="form-control" name="plan_code" id="plan-code" maxlength="100" value="{{ old('plan_code', $plan?->plan_code) }}" placeholder="Contoh: RDYR0925" autocomplete="off" aria-describedby="plan-code-help">
                    <p class="plan-help" id="plan-code-help">Diambil dari dokumen jika terbaca. Dapat diisi atau dikoreksi secara manual.</p>
                </div>
                <div><label class="form-label" for="plan-customer">Nama customer</label>
                    <input type="text" class="form-control" name="customer_name" id="plan-customer" maxlength="255" value="{{ old('customer_name', $plan?->customer_name) }}" required autocomplete="off">
                </div>
                <div><label class="form-label" for="plan-deadline">Batas tanggal pengiriman</label>
                    <input type="date" class="form-control" name="delivery_deadline" id="plan-deadline" value="{{ old('delivery_deadline', $plan?->delivery_deadline?->format('Y-m-d')) }}" required>
                </div>
            </div>
        </section>
        <section class="plan-panel" aria-labelledby="plan-items-title">
            <div class="plan-panel-heading">
                <div><h2 id="plan-items-title">Nomor part dan kebutuhan</h2><p>Jumlah PCS wajib kelipatan kapasitas box. Box tidak penuh belum didukung.</p></div>
                <button type="button" class="btn btn-outline-primary" id="plan-add-item"><i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah part</button>
            </div>
            <div class="plan-table-scroll" role="region" aria-label="Daftar part rencana delivery, geser untuk melihat semua kolom" tabindex="0">
                <table class="table plan-table">
                    <thead><tr><th scope="col">No</th><th scope="col">Nomor part</th><th scope="col">Jumlah PCS</th><th scope="col">PCS / box</th><th scope="col">Jumlah box</th><th scope="col">Aksi</th></tr></thead>
                    <tbody id="plan-items"></tbody>
                </table>
            </div>
            <div class="plan-totals" aria-live="polite">
                <span><strong id="plan-row-count">0</strong> nomor part</span>
                <span>Total PCS <strong id="plan-total-pcs">—</strong></span>
                <span>Total box <strong id="plan-total-boxes">—</strong></span>
            </div>
        </section>
        <section class="plan-review" id="plan-review" @if($initialSource === 'manual') hidden @endif>
            <label for="plan-reviewed"><input class="form-check-input" type="checkbox" name="reviewed" id="plan-reviewed" value="1" @checked(old('reviewed', $plan ? '1' : '') === '1')> Saya sudah mencocokkan kode rencana, customer, tanggal, nomor part, dan jumlah PCS dengan dokumen sumber.</label>
        </section>
        @if($duplicateReview = session('delivery_duplicate_review'))
            <section class="plan-panel border-warning" id="plan-duplicate-review" aria-labelledby="duplicate-review-title">
                <div class="plan-panel-heading"><div><h2 id="duplicate-review-title">Rencana identik sudah tercatat</h2><p>Customer, kode, tanggal pengiriman, nomor part dan jumlahnya sama dengan rencana berikut. Isian ini belum disimpan.</p></div></div>
                <div class="plan-table-scroll" role="region" aria-label="Rencana pembanding duplikasi" tabindex="0"><table class="table plan-table"><thead><tr><th>Rencana</th><th>Customer</th><th>Tenggat</th><th>Status</th><th>Total box</th></tr></thead><tbody>
                    @foreach($duplicateReview['matches'] as $match)<tr><th>@unless($match['archived'])<a href="{{ route('export.delivery-plans.show', $match['id']) }}" target="_blank" rel="noopener">{{ $match['plan_code'] ?: 'RD-'.$match['id'] }} · #{{ $match['id'] }}</a>@else{{ $match['plan_code'] ?: 'RD-'.$match['id'] }} · #{{ $match['id'] }}@endunless</th><td>{{ $match['customer_name'] }}</td><td>{{ \Carbon\Carbon::parse($match['delivery_deadline'])->format('d/m/Y') }}</td><td>{{ $match['status'] }}</td><td>{{ number_format($match['total_boxes'], 0, ',', '.') }}</td></tr>@endforeach
                </tbody></table></div>
                <div class="plan-panel-body">
                    <input type="hidden" name="duplicate_review_token" value="{{ $duplicateReview['token'] }}">
                    <label class="form-label" for="plan-duplicate-reason">Alasan membuat rencana identik (wajib)</label><textarea class="form-control mb-3" id="plan-duplicate-reason" name="duplicate_reason" maxlength="500" required rows="3" autofocus>{{ old('duplicate_reason') }}</textarea>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="duplicate_confirmed" value="1" id="plan-duplicate-confirmed" required><label class="form-check-label" for="plan-duplicate-confirmed">Saya sudah meninjau rencana pembanding. Permintaan identik ini memang diperlukan dan bukan unggahan atau klik simpan yang terulang.</label></div>
                    <p class="plan-help mt-2">Alasan dan rencana pembanding dicatat pada audit. Jika isian rencana diubah, pemeriksaan duplikasi dilakukan kembali.</p>
                </div>
            </section>
        @endif
        <div class="plan-save-bar">
            <div><strong>Periksa rencana sebelum menyimpan</strong><p id="plan-save-help">Rencana disimpan sebagai draft. Jumlah box dihitung otomatis.</p></div>
            <div class="plan-inline-actions"><a class="btn btn-outline-secondary" href="{{ route('export.delivery-plans.index') }}">Kembali</a><button type="submit" class="btn btn-primary" id="plan-save" disabled>Simpan rencana</button></div>
        </div>
    </form>
    <datalist id="plan-part-options">@foreach($parts as $part)<option value="{{ $part->part_number }}">{{ $part->qty_box }} PCS / box</option>@endforeach</datalist>
    <template id="plan-row-template">
        <tr><td class="plan-row-number"></td><td><input type="text" class="form-control plan-part" maxlength="100" list="plan-part-options" autocomplete="off" required><p class="plan-row-error" aria-live="polite"></p></td>
            <td><input type="number" class="form-control plan-quantity" min="1" max="4294967295" step="1" inputmode="numeric" required></td>
            <td class="plan-capacity">—</td><td class="plan-boxes">—</td><td><button type="button" class="btn btn-sm btn-outline-danger plan-remove"><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Hapus baris</span></button></td>
        </tr>
    </template>
    <script type="application/json" id="plan-bootstrap">@json(['parts' => $parts, 'rows' => $initialRows, 'snapshots' => $rows])</script>
    <noscript><div class="alert alert-warning">Aktifkan JavaScript untuk mengisi dan memeriksa rencana delivery.</div></noscript>
</div>
@endsection

@section('scripts')
@vite('resources/js/export-delivery-plan.js')
@endsection
