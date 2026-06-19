# Change Notes - 2026-06-19

## Ringkasan

Batch ini memperkuat keamanan withdrawal, konsistensi quantity delivery, integritas split, akurasi expired box, preservasi histori operasional, dan sumber kebenaran stok per box.

## Perubahan

### Withdrawal

- Endpoint fulfillment/withdrawal dibatasi untuk Warehouse Operator, Admin Warehouse, dan Admin.
- Controller menambahkan pemeriksaan role sebagai pertahanan tambahan.
- Withdrawal legacy sekarang wajib memenuhi quantity exact.
- Perubahan stok di tengah transaksi menyebabkan rollback.
- `fulfilled_quantity` diperbarui dari quantity aktual yang berhasil di-withdraw.
- Withdrawal hanya menerima quantity exact; partial withdrawal tidak tersedia.

### Delivery Split

- Nomor part duplicate pada payload ditolak.
- Ditambahkan unique constraint `(delivery_order_id, part_number)`.
- Data duplicate existing digabung saat migration.
- Restore split mengembalikan seluruh part child ke parent.
- Enum pick session MySQL ditambah status `stale`.

### Expired Box dan Audit Input

- Umur box menggunakan `boxes.created_at` agar koreksi tanggal per box langsung berlaku.
- Mapping `stock_input_boxes -> stock_inputs` dipakai sebagai fallback legacy.
- Shared box hanya menghasilkan satu row expired.
- Approval box not full sekarang membuat mapping `stock_input_boxes`.

### Konsistensi Stok Aktual

- Merge pallet membangun `pallet_items` dari box aktif unik, bukan ringkasan sumber yang mungkin basi.
- Occupancy lokasi menggunakan box aktif sebagai sumber utama.
- Box withdrawn, expired, handled, atau archived tidak dapat dimasukkan ulang.
- ID box yang pernah di-soft-delete tidak dapat digunakan ulang sehingga histori inbound tidak terputus.
- Koreksi box memperbarui QR, header stock input, part, PCS, dan tanggal umur box.
- Box yang sedang berada dalam sesi picking tidak dapat diedit atau dihapus.
- Query stok aktif mengabaikan pallet soft-deleted.
- Target pallet approval not-full wajib memiliki lokasi valid.

### Delivery Picking dan Shared Box

- Reserved box tanpa lokasi tidak lagi dianggap siap picking.
- Reserved box dipilih hanya sampai quantity exact dan tidak boleh melebihi sisa order.
- Completion memvalidasi ulang status, part, PCS, assignment, dan lokasi setiap box.
- Picklist dengan quantity kurang, lebih, atau part yang tidak dibutuhkan ditolak.
- Canonical pallet shared box menggunakan pivot lokasi valid terbaru.
- Saat shared box di-withdraw, ringkasan seluruh pallet terkait disinkronkan dari box aktif.

### Undo Withdrawal

- Undo memulihkan pallet yang ter-soft-delete.
- `stock_locations` dan occupancy master location dibuat kembali.
- Undo ditolak apabila lokasi asal sudah ditempati pallet lain.

### Preservasi Histori

- Palet menggunakan soft delete.
- Stock input tetap dapat membaca palet yang sudah diarsipkan.
- Penghapusan palet tidak lagi menghapus histori stock input.
- User menggunakan status `is_active`; aksi hapus menjadi nonaktifkan.
- Akun nonaktif ditolak saat login maupun request terautentikasi.
- Session database user yang dinonaktifkan dibersihkan.

### Route dan Laporan

- Route location `show` dan `create` yang tidak memiliki implementasi dihapus.
- Fitur backorder dihapus karena kebutuhan pemisahan delivery ditangani oleh split order.
- Parameter dan response partial withdrawal dihapus.
- Kolom legacy `delivery_pick_sessions.allow_partial` dihapus melalui migration.
- Filter, kartu, dan detail Backorder dihapus dari laporan operasional.
- `docs/BACKORDER_GUIDE.md` dihapus.

## Regression Test

Ditambahkan `tests/Feature/SystemIntegrityRegressionTest.php` untuk:

- authorization withdrawal;
- rollback exact quantity;
- fulfillment berdasarkan quantity aktual;
- split/restore multi-part;
- duplicate part split;
- expired box per mapping transaksi;
- deduplikasi shared box;
- histori setelah penghapusan palet/user;
- penolakan akun nonaktif;
- route lokasi yang tidak digunakan.

Ditambahkan `tests/Feature/StockTruthRegressionTest.php` untuk:

- merge dengan `pallet_items` sumber yang basi;
- reserved box berlebih;
- perubahan snapshot box setelah scan;
- canonical pallet untuk shared box;
- sinkronisasi koreksi QR, stock input, dan expiry;
- penolakan rescan box withdrawn.

## Validasi

- Full suite: 115 tests, 592 assertions.
- Migration chain SQLite in-memory: berhasil.
- PHP syntax check: berhasil.
- Seluruh migration telah dijalankan pada MySQL lokal.
- Backup pra-migration: `storage/app/backups/db_stock_before_stock_consistency_2026-06-19.sql`.
- Audit data setelah migration: tidak ditemukan anomali stok/lokasi/header transaksi.
