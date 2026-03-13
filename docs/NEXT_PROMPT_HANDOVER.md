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

## Update 2026-03-13

Semua pending kritis pada dokumen ini sudah dieksekusi:

- `StockWithdrawalController::undo()` sekarang mengembalikan status box (`is_withdrawn=false`, `withdrawn_at=null`) dan re-occupy lokasi terkait.
- `StockInputController::createStockInputRecord()` sekarang menghitung `pcs_quantity`, `box_quantity`, dan `part_numbers` dari box transaksi yang benar-benar di-attach.
- Filter `part_number` di report input stock sekarang memakai relasi `stock_input_boxes -> boxes.part_number`.
- Tabel report dan export input stock sekarang derive part number dari `stockInput->boxes` (group by `part_number`), bukan dari state `pallet_items` saat ini.

Regression yang ditambahkan/diupdate:

- `tests/Feature/StockWithdrawalUndoConsistencyTest.php`
- `tests/Feature/ReportFilterMatrixTest.php`

Hasil test terkait: pass.

## Update 2026-03-13 (Migration Cleanup)

Migration sudah dirapikan agar schema final lebih banyak berada di migration create awal:

- `boxes` create migration sekarang sudah memuat kolom not-full + expired tracking.
- `not_full_box_requests` create migration sekarang sudah memuat `request_type`.
- `delivery_pick_sessions` create migration sekarang sudah memuat `verification_box_ids`.
- `delivery_pick_items` create migration sekarang sudah memuat unique `(pick_session_id, box_id)`.
- Unique `(box_id, status)` untuk `expired_box_reports` sekarang didefinisikan saat create table.

Migration patch yang sudah terserap dijadikan no-op agar chain migrasi tetap aman:

- `2026_02_01_000003_add_request_type_to_not_full_box_requests.php`
- `2026_02_24_130000_add_verification_box_ids_to_delivery_pick_sessions.php`
- `2026_02_24_140000_add_unique_box_status_to_expired_box_reports.php`

Catatan penting:

- `2026_02_01_000001_add_not_full_and_delivery_to_boxes.php` tetap dipakai untuk `assigned_delivery_order_id` karena urutan dependency (`delivery_orders` dibuat setelah `boxes`).
- `2026_02_24_120000_add_concurrency_guards_to_delivery_pick_and_withdrawals.php` tetap dipakai untuk guard di `stock_withdrawals`; bagian unique `delivery_pick_items` sudah dipindahkan ke base create migration.

Validasi:

- `php artisan migrate:fresh --force` sukses tanpa error.

## Pending Kritis (Harus Dikerjakan)

Tidak ada pending kritis tersisa dari batch ini.

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
