@php
    $receiptGroups = $records->groupBy('group_key');
    $selectedGroup = old('group_key', $receiptGroups->count() === 1 ? $receiptGroups->keys()->first() : '');
    $destinationMode = old('destination_mode', $pallets->isNotEmpty() ? 'existing' : 'new');
@endphp
<form method="POST" action="{{ route($expiredRoutePrefix.'transactions.update', $transaction) }}" data-packing-receipt data-scan-url="{{ route($expiredRoutePrefix.'transactions.scan', $transaction) }}">
    @csrf
    <input type="hidden" name="action" value="receive">
    <input type="hidden" name="transaction_id" value="{{ $transaction->id }}">
    <input type="hidden" name="receipt_token" value="{{ old('receipt_token', (string) \Illuminate\Support\Str::uuid()) }}">
    <section class="expired-page-panel p-4">
        <h2>Scan hasil packing</h2>
        <p>Scan ID Box baru dari packing sebanyak box yang diterima sekarang. Part dan PCS mengikuti kelompok transaksi; tidak perlu memasangkan label lama dan baru.</p>
        <label for="receipt-group" class="form-label">Part / PCS per box</label>
        <select id="receipt-group" name="group_key" class="form-select mb-3" data-receipt-group required>
            @if($receiptGroups->count() > 1)<option value="">Pilih kelompok transaksi lama</option>@endif
            @foreach($receiptGroups as $key => $group)
            <option value="{{ $key }}" data-remaining="{{ $group->count() }}" @selected($selectedGroup === $key)>{{ $group->first()->part_number }} · {{ number_format($group->first()->pcs_quantity) }} PCS/box · {{ $group->count() }} box menunggu</option>
            @endforeach
        </select>
        @if($receiptGroups->count() > 1)<p class="expired-form-feedback mb-3">Transaksi lama memiliki beberapa kelompok. Terima satu kelompok setiap penyimpanan agar PCS dan FIFO sesuai.</p>@endif
        <div class="expired-receipt-scan-controls" data-scan-feedback>
            <div><label for="packing-box" class="form-label">ID Box baru dari packing</label><input id="packing-box" class="form-control" data-packing-scan inputmode="numeric" maxlength="8" pattern="[0-9]{8}" autocomplete="off" aria-describedby="packing-scan-help packing-feedback"><small id="packing-scan-help">8 angka · tekan Enter untuk menambahkan. Stok bertambah setelah penerimaan disimpan.</small></div>
            <button type="button" class="btn btn-outline-primary" data-add-packing-box>Tambahkan</button>
        </div>
        <p id="packing-feedback" class="expired-receipt-feedback" data-packing-feedback role="status" aria-live="polite">Siap scan ID Box baru.</p>
        <div class="expired-receipt-count" data-receipt-count aria-live="polite">0 box siap diterima.</div>
        <div class="table-responsive expired-receipt-boxes" tabindex="0" role="region" aria-label="Daftar ID Box hasil scan"><table class="table expired-page-table"><thead><tr><th>ID Box baru</th><th>Pemeriksaan</th><th>Aksi</th></tr></thead><tbody data-packing-box-list>
        @foreach(old('new_box_numbers', []) as $number)
        <tr data-staged-box="{{ $number }}"><td><strong>{{ $number }}</strong><input type="hidden" name="new_box_numbers[]" value="{{ $number }}"></td><td data-box-state>Menunggu pemeriksaan</td><td><button type="button" class="btn btn-sm btn-outline-secondary" data-remove-box>Hapus<span class="visually-hidden"> {{ $number }}</span></button><button type="button" class="btn btn-sm btn-outline-primary ms-2" data-retry-box hidden>Periksa ulang<span class="visually-hidden"> {{ $number }}</span></button></td></tr>
        @endforeach
        </tbody></table></div>
        <p class="expired-form-feedback">Satu bulan aktif dihitung sejak penerimaan disimpan. Sistem mempertahankan tanggal FIFO asal tertua secara otomatis; hubungan ID lama–baru merupakan acuan data, bukan pencocokan label fisik.</p>
    </section>
    <section class="expired-page-panel p-4">
        <h2>Tujuan penyimpanan</h2><p>Seluruh box pada daftar scan ini ditempatkan ke satu pallet {{ $scopeLabel }}. Penerimaan berikutnya boleh memilih tujuan lain.</p>
        <div class="expired-destination-mode" role="group" aria-label="Jenis tujuan">
            <label><input type="radio" name="destination_mode" value="existing" @checked($destinationMode === 'existing')> Pallet tersedia</label>
            <label><input type="radio" name="destination_mode" value="new" @checked($destinationMode === 'new')> Pallet baru</label>
        </div>
        <div class="row g-3">
            <div class="col-12" data-existing-pallet><label for="batch-pallet" class="form-label">Pallet / lokasi</label><select id="batch-pallet" name="pallet_id" class="form-select"><option value="">Pilih pallet tersedia</option>@foreach($pallets as $pallet)<option value="{{ $pallet->id }}" data-boxes="{{ $pallet->boxes_count ?? 0 }}" @if($scope === 'export') data-box-limit="{{ $pallet->spareBoxLimit() }}" @endif @selected((string) old('pallet_id') === (string) $pallet->id)>{{ $scope === 'export' ? $pallet->display_name.' · '.$pallet->location_code.' · '.$pallet->capacityLabel($pallet->boxes_count) : $pallet->pallet_number.' · '.$pallet->stockLocation->warehouse_location }}</option>@endforeach</select></div>
            <div class="col-12" data-new-pallet-location><label for="batch-location" class="form-label">Lokasi kosong untuk pallet baru</label><select id="batch-location" name="location_id" class="form-select"><option value="">Pilih lokasi kosong</option>@foreach($availableLocations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }}</option>@endforeach</select></div>
        </div>
        @if($scope === 'export')<p class="expired-pallet-load" data-packing-load role="status" hidden></p><p class="expired-form-feedback">Hasil packing masuk stok cadangan ekspor. Alokasi permintaan mengikuti proses FIFO yang berlaku.</p>@endif
        <div class="form-check my-3"><input id="receipt-confirm" type="checkbox" class="form-check-input" name="confirmed" value="1" required><label for="receipt-confirm" class="form-check-label">Box yang discan sudah diterima secara fisik dan sesuai part, PCS, serta tujuan penyimpanan di atas.</label></div>
        <button class="btn btn-primary" type="submit" data-save-receipt disabled>Simpan penerimaan</button>
        <noscript><p class="text-danger mt-3">Aktifkan JavaScript di browser untuk memeriksa ID Box hasil scan.</p></noscript>
    </section>
</form>
