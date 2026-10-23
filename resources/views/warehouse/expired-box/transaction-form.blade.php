    <form method="POST" action="{{ isset($transaction) ? route('expired-box.transactions.update', $transaction) : route('expired-box.bulk.store') }}">
        @csrf<input type="hidden" name="action" value="{{ $action }}">
        @if($action === 'qa')
        <section class="expired-page-panel p-4">
            <h2>Hasil QA dapat berbeda pada setiap box</h2><p>Lolos berarti barang sudah kembali ke warehouse. Tidak lolos menghapus box operasional permanen dengan jejak tetap tersimpan.</p>
            <div class="row g-3"><div class="col-md-4"><label for="common-qa" class="form-label">Isi hasil yang sama</label><select class="form-select" id="common-qa"><option value="">Pilih jika hasilnya sama</option><option value="passed">Lolos QA</option><option value="rejected">Tidak lolos QA · hapus permanen</option></select></div><div class="col-md-5"><label for="common-notes" class="form-label">Catatan bersama (opsional)</label><input class="form-control" id="common-notes" maxlength="2000"></div><div class="col-md-3 d-flex align-items-end"><button type="button" class="btn btn-outline-primary" data-apply-qa>Terapkan ke semua box</button></div></div><p class="expired-form-feedback" data-qa-feedback role="status" aria-live="polite">Pilihan bersama hanya membantu mengisi. Hasil akhir tiap box dapat diubah sebelum disimpan.</p>
        </section>
        @elseif($action === 'receive')
        <section class="expired-page-panel p-4">
            <h2>Tujuan penerimaan bersama</h2><p>Semua box lolos QA dalam transaksi ini diterima bersama pada satu pallet. Part dan PCS wajib tetap sama dengan asal.</p>
            <div class="row g-3">
                <div class="col-md-6"><label for="batch-pallet" class="form-label">Pallet tujuan</label><select id="batch-pallet" name="pallet_id" class="form-select"><option value="">Buat satu pallet baru untuk semua box</option>@foreach($pallets as $pallet)<option value="{{ $pallet->id }}" @selected((string) old('pallet_id') === (string) $pallet->id)>{{ $pallet->pallet_number }} · {{ $pallet->stockLocation->warehouse_location }}</option>@endforeach</select></div>
                <div class="col-md-6" data-new-pallet-location><label for="batch-location" class="form-label">Lokasi kosong untuk pallet baru</label><select id="batch-location" name="location_id" class="form-select"><option value="">Pilih lokasi kosong</option>@foreach($availableLocations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }}</option>@endforeach</select></div>
                <div class="col-md-6"><label for="common-issued" class="form-label">Isi waktu penerbitan ID yang sama (opsional)</label><input id="common-issued" class="form-control" type="datetime-local" step="1"></div>
                <div class="col-md-6 d-flex align-items-end"><button type="button" class="btn btn-outline-primary" data-apply-issued>Terapkan waktu ke semua box</button></div>
            </div>
            <p class="expired-form-feedback" data-issued-feedback role="status" aria-live="polite">Waktu penerbitan dapat diisi bersama, lalu diperiksa untuk tiap ID baru.</p>
            <p class="mt-3 mb-0">Setiap ID memiliki batas aktif satu bulan sejak diterbitkan. Umur/FIFO tetap mengikuti tanggal masuk asli. ID yang sudah melewati batas diterima sebagai expired.</p>
        </section>
        @elseif($action === 'packing')
        <section class="expired-page-panel p-4"><h2>Pengantar transaksi</h2><p>Sertakan pengantar bersama barang. Setiap baris memuat referensi penanganan dan ID asal agar tidak tertukar.</p><a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('expired-box.transactions.print', $transaction) }}">Cetak pengantar packing</a></section>
        @endif
        <section class="expired-page-panel p-4" aria-label="Daftar box yang akan diproses">
            @if($action === 'packing')
            <h2 class="expired-form-section-title">Box yang diserahkan ke packing</h2>
            <p>Pastikan {{ $records->count() }} box lolos QA berikut diserahkan bersama pengantar transaksi.</p>
            <div class="expired-packing-list">
                @foreach($records as $index => $record)
                <div class="expired-packing-item"><input type="hidden" name="rows[{{ $index }}][id]" value="{{ $record->id }}"><strong>{{ $record->source_box_number }}</strong><span>{{ $record->part_number }} · {{ number_format($record->pcs_quantity) }} PCS</span></div>
                @endforeach
            </div>
            @else
            @foreach($records as $index => $record)
            @php($number = $action === 'start' ? $record->box_number : $record->source_box_number)
            <fieldset class="expired-bulk-row" @if($action === 'qa') data-qa-row @endif>
                <legend>Box {{ $number }} <span class="expired-bulk-sequence">{{ $loop->iteration }} dari {{ $records->count() }}</span></legend>
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
            @endif
            <div class="form-check my-3"><input id="batch-confirm" class="form-check-input" type="checkbox" name="confirmed" value="1" required><label for="batch-confirm" class="form-check-label">
                @if($action === 'start')Seluruh box dalam transaksi sudah diserahkan ke QA.
                @elseif($action === 'qa')Hasil QA setiap box sudah diperiksa. Box lolos sudah kembali ke warehouse; saya memahami box ditolak akan dihapus permanen.
                @elseif($action === 'packing')Seluruh box dalam transaksi dan pengantar sudah diserahkan ke packing.
                @else Seluruh box sudah diterima dan dicocokkan dengan pengantar, ID lama–baru, part, serta PCS masing-masing.
                @endif
            </label></div>
            <button class="btn btn-primary" type="submit">{{ ['start' => 'Buat transaksi & catat penyerahan QA', 'qa' => 'Simpan hasil QA', 'packing' => 'Catat penyerahan ke packing', 'receive' => 'Simpan penerimaan & selesaikan transaksi'][$action] }}</button>
            <a class="btn btn-outline-secondary" href="{{ route('expired-box.index') }}">Kembali tanpa menyimpan</a>
        </section>
    </form>
