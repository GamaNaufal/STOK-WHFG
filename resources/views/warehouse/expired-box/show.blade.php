@extends('shared.layouts.app')
@section('title', $handling->reference)
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/expired-box.css') }}">
@endsection
@section('content')
<div class="expired-page">
    <header class="expired-page-header">
        <a href="{{ route('expired-box.index') }}">← Daftar expired dan penanganan</a>
        <h1 class="mt-3">{{ $handling->reference }}</h1>
        <p>{{ $handling->status_label }}</p>
    </header>
    @foreach(['success', 'error'] as $message)
        @if(session($message))<div role="status" class="alert alert-{{ $message === 'error' ? 'danger' : 'success' }}">{{ session($message) }}</div>@endif
    @endforeach
    @if($errors->any())<div role="alert" class="alert alert-danger"><strong>Periksa kembali isian:</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="expired-page-panel p-4" aria-label="Identitas box">
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3"><small>ID box asal</small><div class="fw-bold">{{ $handling->source_box_number }}</div></div>
            <div class="col-sm-6 col-lg-3"><small>Part / PCS tetap</small><div class="fw-bold">{{ $handling->part_number }} / {{ $handling->pcs_quantity }} PCS</div></div>
            <div class="col-sm-6 col-lg-3"><small>Masuk asli · dasar umur/FIFO</small><div class="fw-bold">{{ $handling->original_stored_at->format('d M Y H:i') }}</div></div>
            <div class="col-sm-6 col-lg-3"><small>ID pengganti</small><div class="fw-bold">{{ $handling->replacement_box_number ?? 'Belum diterima' }}</div></div>
        </div>
    </section>
    @if($handling->status === 'at_qa')
    <section class="expired-page-panel p-4">
        <h2>Catat hasil QA</h2>
        <p>Untuk hasil lolos, pastikan barang sudah kembali ke warehouse sebelum mencatat keputusan.</p>
        <form method="POST" action="{{ route('expired-box.qa', $handling) }}">@csrf
            <label class="form-label" for="decision">Hasil pemeriksaan</label>
            <select required class="form-select mb-3" name="decision" id="decision"><option value="">Pilih hasil QA</option><option value="passed" @selected(old('decision') === 'passed')>Lolos QA · barang kembali ke warehouse</option><option value="rejected" @selected(old('decision') === 'rejected')>Tidak lolos QA · hapus box permanen</option></select>
            <label class="form-label" for="notes">Catatan / referensi hasil QA</label>
            <textarea class="form-control" id="notes" name="notes" maxlength="2000" rows="3" aria-describedby="notes-hint">{{ old('notes') }}</textarea>
            <p id="notes-hint" class="form-text">Wajib diisi jika ditolak. Penolakan menghapus data box operasional permanen; jejak penanganan tetap tersimpan.</p>
            <div class="form-check my-3"><input class="form-check-input" type="checkbox" id="qa-confirm" name="confirmed" value="1" required><label class="form-check-label" for="qa-confirm">Saya sudah memeriksa hasil QA dan memahami tindakan yang dipilih.</label></div>
            <button class="btn btn-primary">Simpan hasil QA</button>
        </form>
    </section>
    @elseif($handling->status === 'awaiting_packing')
    <section class="expired-page-panel p-4">
        <h2>Serahkan ke packing</h2><p>Cetak pengantar dan sertakan bersama box sampai hasil packing kembali. Warehouse mencatat ID baru sesuai label packing.</p>
        <a class="btn btn-outline-primary mb-3" target="_blank" rel="noopener" href="{{ route('expired-box.print', $handling) }}">Cetak pengantar packing</a>
        <form method="POST" action="{{ route('expired-box.packing', $handling) }}">@csrf
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="packing-confirm" name="confirmed" value="1" required><label class="form-check-label" for="packing-confirm">Barang dan pengantar sudah diserahkan ke packing.</label></div>
            <button class="btn btn-primary">Catat penyerahan ke packing</button>
        </form>
    </section>
    @elseif($handling->status === 'at_packing')
    <section class="expired-page-panel p-4">
        <h2>Terima hasil packing</h2>
        <p>Cocokkan referensi pengantar dengan box fisik. Jika pasangan ID belum pasti, jangan simpan penerimaan.</p>
        <a class="btn btn-outline-secondary mb-3" target="_blank" rel="noopener" href="{{ route('expired-box.print', $handling) }}">Cetak ulang pengantar</a>
        <form method="POST" action="{{ route('expired-box.receive', $handling) }}">@csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="box-number">Scan ID box baru · 8 digit</label><input class="form-control" id="box-number" name="box_number" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="off" value="{{ old('box_number') }}" required></div>
                <div class="col-md-6"><label class="form-label" for="issued-at">Waktu ID baru diterbitkan</label><input class="form-control" id="issued-at" type="datetime-local" step="1" name="label_issued_at" min="{{ $handling->sent_packing_at->format('Y-m-d\TH:i:s') }}" value="{{ old('label_issued_at') }}" required><small class="form-text">Satu bulan kalender dihitung dari waktu ini, bukan waktu penerimaan.</small></div>
                <div class="col-md-6"><label class="form-label" for="part-number">Part pada label baru</label><input class="form-control" id="part-number" name="part_number" value="{{ old('part_number') }}" required></div>
                <div class="col-md-6"><label class="form-label" for="pcs">PCS aktual</label><input class="form-control" type="number" min="1" id="pcs" name="pcs_quantity" value="{{ old('pcs_quantity') }}" required></div>
                <div class="col-md-6"><label class="form-label" for="pallet">Pallet tujuan</label><select class="form-select" id="pallet" name="pallet_id"><option value="">Buat pallet baru</option>@foreach($pallets as $pallet)<option value="{{ $pallet->id }}" @selected((string) old('pallet_id') === (string) $pallet->id)>{{ $pallet->pallet_number }} · {{ $pallet->stockLocation->warehouse_location }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="location">Lokasi untuk pallet baru</label><select class="form-select" id="location" name="location_id"><option value="">Pilih jika membuat pallet baru</option>@foreach($availableLocations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }}</option>@endforeach</select><small class="form-text">Pallet existing tetap menggunakan lokasinya saat ini.</small></div>
            </div>
            <p class="mt-3">Jika batas satu bulan sudah habis, hasil tetap dicatat sebagai expired dan belum tersedia untuk delivery.</p>
            <div class="form-check my-3"><input class="form-check-input" type="checkbox" id="receive-confirm" name="confirmed" value="1" required><label class="form-check-label" for="receive-confirm">Barang sudah diterima; pengantar, ID, part, dan PCS telah dicocokkan.</label></div>
            <button class="btn btn-primary">Simpan penerimaan ID baru</button>
        </form>
    </section>
    @elseif($handling->status === 'completed')
    <section class="expired-page-panel p-4"><h2>Penggantian ID selesai</h2><p>{{ $handling->source_box_number }} → <strong>{{ $handling->replacement_box_number }}</strong></p><p>Batas aktif saat diterima: <strong>{{ $handling->usable_until->format('d M Y H:i:s') }}</strong>. Lihat Stok menunjukkan kondisi stok terkini.</p><p>Tujuan saat diterima: {{ $handling->destination_pallet }} / {{ $handling->destination_location }}.</p></section>
    @else
    <section class="expired-page-panel p-4"><h2>Box ditolak QA dan dihapus permanen</h2><p>Identitas lama tetap tercatat sebagai pernah digunakan. Snapshot sebelum penghapusan tersedia melalui unduhan jejak.</p></section>
    @endif
    <section class="expired-page-panel p-4">
        <h2>Jejak penanganan</h2>
        @if($originHandling)<p>Asal siklus sebelumnya: <a href="{{ route('expired-box.show', $originHandling) }}">{{ $originHandling->reference }} · {{ $originHandling->source_box_number }} → {{ $originHandling->replacement_box_number }}</a></p>@endif
        @if($nextHandling)<p>Penanganan berikutnya: <a href="{{ route('expired-box.show', $nextHandling) }}">{{ $nextHandling->reference }} · {{ $nextHandling->status_label }}</a></p>@endif
        @if($handling->qa_notes)<p><strong>Catatan QA:</strong> {{ $handling->qa_notes }}</p>@endif
        <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel penanganan box"><table class="table"><thead><tr><th>Kejadian</th><th>Waktu</th><th>Petugas</th></tr></thead><tbody>
            @foreach(['sent_qa' => 'Diserahkan ke QA', 'qa_decided' => 'Hasil QA dicatat', 'sent_packing' => 'Diserahkan ke packing', 'received' => 'Hasil packing diterima'] as $event => $label)
            @if($handling->{$event.'_at'})<tr><td>{{ $label }}</td><td>{{ $handling->{$event.'_at'}->format('d M Y H:i:s') }}</td><td>{{ $users[$handling->{$event.'_by'}] ?? 'Petugas #'.$handling->{$event.'_by'} }}</td></tr>@endif
            @endforeach
        </tbody></table></div>
        <a class="btn btn-outline-secondary" href="{{ route('expired-box.trace', $handling) }}">Unduh jejak lengkap</a>
    </section>
</div>
@endsection
