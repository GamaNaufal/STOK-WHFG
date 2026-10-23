@extends('shared.layouts.app')
@php
    $titles = ['start' => 'Serahkan beberapa box ke QA', 'qa' => 'Catat hasil QA beberapa box', 'packing' => 'Serahkan beberapa box ke packing', 'receive' => 'Terima beberapa box dari packing'];
@endphp
@section('title', $titles[$action])
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">
@endsection
@section('content')
<div class="expired-page">
    <header class="expired-page-header"><a href="{{ route('expired-box.index') }}">← Kembali ke daftar</a><h1 class="mt-3">{{ $titles[$action] }}</h1><p>{{ $records->count() }} box dipilih. Setiap box tetap memiliki riwayat dan pasangan ID sendiri.</p></header>
    <div class="alert alert-info">Seluruh pilihan disimpan bersama. Jika satu box gagal validasi, tidak ada tindakan pada pilihan ini yang disimpan.</div>
    @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Perbaiki isian berikut; seluruh pilihan belum disimpan.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('expired-box.bulk.store') }}">
        @csrf<input type="hidden" name="action" value="{{ $action }}">
        @if($action === 'qa')
        <section class="expired-page-panel p-4">
            <h2>Hasil QA dapat berbeda pada setiap box</h2><p>Lolos berarti barang sudah kembali ke warehouse. Tidak lolos menghapus box operasional permanen dengan jejak tetap tersimpan.</p>
            <div class="row g-3"><div class="col-md-4"><label for="common-qa" class="form-label">Isi hasil yang sama</label><select class="form-select" id="common-qa"><option value="">Pilih jika hasilnya sama</option><option value="passed">Lolos QA</option><option value="rejected">Tidak lolos QA · hapus permanen</option></select></div><div class="col-md-5"><label for="common-notes" class="form-label">Catatan bersama (opsional)</label><input class="form-control" id="common-notes" maxlength="2000"></div><div class="col-md-3 d-flex align-items-end"><button type="button" class="btn btn-outline-primary" data-apply-qa>Terapkan ke semua baris</button></div></div>
        </section>
        @elseif($action === 'receive')
        <section class="expired-page-panel p-4">
            <h2>Tujuan penerimaan bersama</h2><p>Semua box pilihan ditempatkan pada satu pallet. Gunakan aksi terpisah bila tujuan berbeda. Part dan PCS wajib tetap sama dengan asal.</p>
            <div class="row g-3">
                <div class="col-md-6"><label for="batch-pallet" class="form-label">Pallet tujuan</label><select id="batch-pallet" name="pallet_id" class="form-select"><option value="">Buat satu pallet baru untuk semua box</option>@foreach($pallets as $pallet)<option value="{{ $pallet->id }}" @selected((string) old('pallet_id') === (string) $pallet->id)>{{ $pallet->pallet_number }} · {{ $pallet->stockLocation->warehouse_location }}</option>@endforeach</select></div>
                <div class="col-md-6"><label for="batch-location" class="form-label">Lokasi kosong untuk pallet baru</label><select id="batch-location" name="location_id" class="form-select"><option value="">Pilih jika membuat pallet baru</option>@foreach($availableLocations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }}</option>@endforeach</select></div>
                <div class="col-md-6"><label for="common-issued" class="form-label">Isi waktu penerbitan ID yang sama (opsional)</label><input id="common-issued" class="form-control" type="datetime-local" step="1"></div>
                <div class="col-md-6 d-flex align-items-end"><button type="button" class="btn btn-outline-primary" data-apply-issued>Terapkan waktu ke semua baris</button></div>
            </div>
            <p class="mt-3 mb-0">Setiap ID memiliki batas aktif satu bulan sejak diterbitkan. Umur/FIFO tetap mengikuti tanggal masuk asli. ID yang sudah melewati batas diterima sebagai expired.</p>
        </section>
        @elseif($action === 'packing')
        <section class="expired-page-panel p-4"><h2>Pengantar untuk box pilihan</h2><p>Sertakan pengantar bersama barang. Setiap baris memuat referensi penanganan dan ID asal agar tidak tertukar.</p><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('expired-box.bulk.review', ['action' => 'print', 'ids' => $records->pluck('id')->all()]) }}">Cetak pengantar bersama</a></section>
        @endif
        <section class="expired-page-panel p-4" aria-label="Daftar box yang akan diproses">
            @foreach($records as $index => $record)
            @php($number = $action === 'start' ? $record->box_number : $record->source_box_number)
            <fieldset class="expired-bulk-row">
                <legend>Box {{ $number }}</legend>
                <input type="hidden" name="rows[{{ $index }}][id]" value="{{ $record->id }}">
                <p>@if($action !== 'start'){{ $record->reference }} · @endif<strong>{{ $record->part_number }}</strong> · {{ $record->pcs_quantity }} PCS</p>
                @if($action === 'start')
                    <p class="mb-0">Masuk asli: {{ \Illuminate\Support\Carbon::parse($record->stored_at)->format('d M Y H:i') }} · Asal: {{ $record->pallet_number ?? 'Tanpa pallet aktif' }}</p>
                @elseif($action === 'qa')
                    <div class="row g-3"><div class="col-md-5"><label for="decision-{{ $index }}" class="form-label">Hasil QA box {{ $number }}</label><select id="decision-{{ $index }}" name="rows[{{ $index }}][decision]" class="form-select" data-qa-decision data-notes-id="notes-{{ $index }}" required><option value="">Pilih hasil QA</option><option value="passed" @selected(old("rows.$index.decision") === 'passed')>Lolos QA · kembali ke warehouse</option><option value="rejected" @selected(old("rows.$index.decision") === 'rejected')>Tidak lolos QA · hapus permanen</option></select></div><div class="col-md-7"><label for="notes-{{ $index }}" class="form-label">Catatan box {{ $number }} · wajib jika ditolak</label><textarea id="notes-{{ $index }}" name="rows[{{ $index }}][notes]" class="form-control" rows="2" maxlength="2000">{{ old("rows.$index.notes") }}</textarea></div></div>
                @elseif($action === 'receive')
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="new-box-{{ $index }}">ID baru pengganti {{ $number }}</label><input class="form-control" id="new-box-{{ $index }}" name="rows[{{ $index }}][box_number]" value="{{ old("rows.$index.box_number") }}" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="off" data-new-box required><small class="form-text">Enter scanner berpindah ke ID baru baris berikutnya.</small></div>
                        <div class="col-md-6"><label class="form-label" for="issued-{{ $index }}">Waktu ID pengganti {{ $number }} diterbitkan</label><input class="form-control" id="issued-{{ $index }}" type="datetime-local" step="1" name="rows[{{ $index }}][label_issued_at]" min="{{ $record->sent_packing_at->format('Y-m-d\TH:i:s') }}" value="{{ old("rows.$index.label_issued_at") }}" data-issued required></div>
                        <div class="col-md-6"><label class="form-label" for="part-{{ $index }}">Part pada hasil box {{ $number }}</label><input class="form-control" id="part-{{ $index }}" name="rows[{{ $index }}][part_number]" value="{{ old("rows.$index.part_number", $record->part_number) }}" required></div>
                        <div class="col-md-6"><label class="form-label" for="pcs-{{ $index }}">PCS aktual box {{ $number }}</label><input class="form-control" id="pcs-{{ $index }}" type="number" min="1" name="rows[{{ $index }}][pcs_quantity]" value="{{ old("rows.$index.pcs_quantity", $record->pcs_quantity) }}" required></div>
                    </div>
                @endif
                @foreach($errors->get("rows.$index.*") as $messages)@foreach($messages as $message)<p class="text-danger mt-2 mb-0">{{ $message }}</p>@endforeach @endforeach
            </fieldset>
            @endforeach
            <div class="form-check my-3"><input id="batch-confirm" class="form-check-input" type="checkbox" name="confirmed" value="1" required><label for="batch-confirm" class="form-check-label">
                @if($action === 'start')Seluruh box pilihan sudah diserahkan ke QA.
                @elseif($action === 'qa')Hasil QA setiap box sudah diperiksa. Box lolos sudah kembali ke warehouse; saya memahami box ditolak akan dihapus permanen.
                @elseif($action === 'packing')Seluruh box pilihan dan pengantar sudah diserahkan ke packing.
                @else Seluruh box sudah diterima dan dicocokkan dengan pengantar, ID lama–baru, part, serta PCS masing-masing.
                @endif
            </label></div>
            <button class="btn btn-primary" type="submit">Simpan {{ $records->count() }} box bersama</button>
            <a class="btn btn-outline-secondary" href="{{ route('expired-box.index') }}">Kembali tanpa menyimpan</a>
        </section>
    </form>
</div>
@endsection
@section('scripts')
<script src="{{ asset('js/expired-box-bulk.js') }}" defer></script>
@endsection
