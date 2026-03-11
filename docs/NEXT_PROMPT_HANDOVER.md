# Next Prompt Handover (Accuracy Hardening)

Tanggal: 2026-03-11

Dokumen ini berisi catatan lanjutan untuk sesi berikutnya agar perbaikan akurasi laporan stok bisa langsung dilanjut tanpa re-analisis dari nol.

## Status Saat Ini

Perubahan yang sudah selesai:

- `stock_input_boxes` sudah ditambahkan sebagai source of truth relasi `stock_inputs <-> boxes`.
- Flow simpan input stok sudah menyimpan mapping box eksplisit per transaksi.
- Laporan detail Input Stock dan Pengambilan Stock sudah menampilkan kolom `ID Box`.
- UI redo delivery sudah pakai modal custom + pencarian lokasi + info conflict pallet.

Perubahan yang masih perlu dieksekusi agar akurasi benar-benar konsisten end-to-end:

## Pending Kritis (Harus Dikerjakan)

1. Sinkronkan `undo withdrawal` ke status box

- File: `app/Http/Controllers/StockWithdrawalController.php`
- Method: `undo($withdrawalId)`
- Masalah:
    - Undo saat ini mengembalikan `pallet_items` dan ubah status withdrawal ke `reversed`.
    - Belum mengembalikan status box (`is_withdrawn = false`, `withdrawn_at = null`) berdasarkan `box_id`.
- Dampak:
    - Potensi mismatch stok: item kembali, tapi box tetap withdrawn.

2. Akurasi snapshot `part_numbers` pada `StockInput`

- File: `app/Http/Controllers/StockInputController.php`
- Method: `createStockInputRecord(...)`
- Masalah:
    - `part_numbers` masih disusun dari `pallet->items()` (state pallet saat ini), bukan murni dari box transaksi baru.
- Target:
    - Isi `part_numbers` dari box yang benar-benar terekam di `stock_input_boxes` untuk transaksi tersebut.

3. Filter `part_number` report Input Stock harus berbasis `stock_input_boxes`

- File: `app/Http/Controllers/ReportController.php`
- Method: `stockInputReport(Request $request)`
- Masalah:
    - Filter `part_number` masih berbasis `pallet_items`.
- Target:
    - Filter lewat relasi box transaksi (`stock_input_boxes -> boxes.part_number`).

4. Part number pada tabel/export Input Stock harus dari box transaksi

- File:
    - `resources/views/operator/reports/stock-input.blade.php`
    - `app/Exports/StockInputExport.php`
- Masalah:
    - Tampilan part masih fallback ke `palletItem/pallet->items`.
- Target:
    - Derive part dan agregat qty dari `stockInput->boxes` (group by `part_number`) agar historis tidak berubah saat pallet berubah.

## Rekomendasi Eksekusi Prompt Berikutnya

1. Kerjakan poin 1-4 dalam satu batch commit.
2. Tambahkan test regression minimal:

- Undo withdrawal mengembalikan status box.
- Filter report input stock by part number valid terhadap `stock_input_boxes`.
- Tampilan/export input stock tetap konsisten walau `pallet_items` berubah setelah transaksi.

3. Jalankan test terkait:

- `tests/Feature/StockInputAndNotFullWorkflowTest.php`
- `tests/Feature/DeliveryFlowConsistencyTest.php`
- `tests/Feature/AuthorizationAndReportExportSanityTest.php`

## Catatan Penting

Karena data lama boleh di-reset, fokus desain harus ke akurasi transaksi baru (audit-grade) dan konsistensi antar:

- status box,
- stock_input_boxes,
- stock_withdrawals,
- report UI + export.
