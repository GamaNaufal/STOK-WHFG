# Change Notes - 2026-06-19

## Ringkasan

Batch ini memperkuat keamanan withdrawal, konsistensi quantity delivery, integritas split, akurasi expired box, dan preservasi histori operasional.

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

- Umur box dihitung dari mapping `stock_input_boxes -> stock_inputs`.
- `boxes.created_at` dipakai sebagai fallback legacy.
- Shared box hanya menghasilkan satu row expired.
- Approval box not full sekarang membuat mapping `stock_input_boxes`.

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

## Validasi

- Full suite: 108 tests.
- Migration chain SQLite in-memory: berhasil.
- PHP syntax check: berhasil.
- MySQL migration lokal belum dijalankan karena server MySQL lokal tidak aktif saat validasi.
