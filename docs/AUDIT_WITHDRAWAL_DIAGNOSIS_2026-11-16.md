# Diagnosis ID Box tidak tercatat pada audit pengambilan

Tanggal verifikasi: 16 November 2026 (WIB).
Source: checkout pengguna pada commit `c6e233a`.
Lingkup awal: diagnosis. Perbaikan kode kemudian diterapkan atas instruksi pengguna;
lihat bagian Status perbaikan. Database operasional dan deployment tidak disentuh.
Versi produksi dan isi record Audit #8337 belum tersedia.

## Kesimpulan

Pada source sebelum patch, bug masih ada, khususnya penyelesaian pengambilan melalui sesi
delivery lokal dan audit pembatalannya melalui Redo. Snapshot batch menyimpan
`box_id` (primary key internal), tetapi tidak menyimpan `box_number` (nomor pada label).
Presenter membaca `box_number`; jika key tidak ada, hasilnya `Tidak tercatat`.

Reproduksi memakai endpoint completion sebenarnya: lima box, part `949322-3750`,
masing-masing 200 PCS, lokasi A23, dengan satu box cadangan agar pallet tetap berlokasi.
Audit menyimpan total 1.000 PCS/5 box, seluruh lokasi A23, tetapi kelima nomor box tampil
`Tidak tercatat`. Transaksi withdrawal tetap terbentuk dan delivery selesai.
Ini sangat cocok dengan screenshot, tetapi belum membuktikan isi record produksi #8337.

Masalah tidak membutuhkan stok terhapus atau nomor label tidak valid untuk terjadi.
Bug terdapat pada isi snapshot audit; pengujian ini tidak menunjukkan hilangnya stok.

## Rantai penyebab dan riwayat

1. `app/Http/Controllers/DeliveryPickController.php:1331` memanggil
   `AuditService::logBatchStockWithdrawal()` setelah box menjadi withdrawn dan
   ringkasan/lokasi pallet diperbarui. Redo memanggil logger yang sama pada baris 1464.
2. `app/Services/AuditService.php:174` membentuk setiap baris `boxes` dengan
   `box_id`, part, PCS, dan lokasi. Tidak ada `box_number`.
3. `app/Support/AuditLogPresenter.php:141` meminta `box_number` dan baris 167
   menggunakan `Tidak tercatat` jika field itu tidak ada. Presenter tidak membaca
   inventory terkini untuk melengkapi snapshot historis.
4. `tests/Feature/AuditDetailPresentationTest.php:54` memang memastikan ID internal
   tidak ditampilkan sebagai nomor label. Perlindungan tersebut benar, tetapi tes
   belum menuntut logger batch menyimpan nomor label.

`git blame` menunjukkan pemetaan batch ini berasal dari commit `3ed0a080`
(30 Januari 2026) dan belum diperbaiki sampai source sekarang. Presenter dengan
fallback tersebut ditambahkan pada commit `ee7d29e` (14 Oktober 2026).
Ini menjelaskan kompatibilitas bermasalah antara snapshot lama dan detail audit,
serta menjelaskan mengapa transaksi baru pada versi sekarang juga tetap terkena.
Riwayat ini bukan identifikasi versi server produksi.

## Cakupan dan temuan terkait

| Jalur/kondisi | Hasil pemeriksaan |
| --- | --- |
| Completion delivery lokal | Terbukti: semua nomor label hilang dari snapshot batch. |
| Redo delivery lokal | Terbukti lewat endpoint Redo: audit `reversed` juga tidak menyimpan nomor label. |
| Withdrawal langsung satu part | Source menyimpan `box_number` pada baris 545 di StockWithdrawalController. Tidak memiliki kekurangan field yang sama. |
| Withdrawal langsung keranjang | Source menyimpan `box_number` pada baris 698 di controller yang sama. |
| Undo withdrawal langsung | Source mencatat status, batch, dan total tanpa tabel rincian box; bukan mekanisme lima baris tanpa nomor pada screenshot. |
| Histori lama setelah update | Field yang tidak pernah disimpan tetap hilang; deployment baru saja tidak melengkapi snapshot lama. |
| Pallet habis pada completion | Terbukti: `stock_withdrawals.warehouse_location` tetap A23, tetapi snapshot audit menjadi `Unknown`. Lokasi dibaca ulang setelah dibersihkan pada `DeliveryPickController:105–138`. |
| Box shared dengan beberapa relasi pallet legacy | Terbukti pada logger: lokasi A23 dari pallet pertama dipilih walaupun pivot terbaru yang valid berlokasi B02. Logger memakai `pallets->first()`, berbeda dengan resolusi lokasi completion yang mengurutkan pivot terbaru. |
| Box soft-deleted ketika logger dipanggil | Terbukti pada pemanggilan service: jumlah item tetap 5, tetapi rincian tinggal 4 dan total PCS menjadi 800. Relasi `DeliveryPickItem::box()` tidak memakai `withTrashed()`. Completion normal memvalidasi box lebih awal; kondisi ini bukan prasyarat bug utama. |
| Kegagalan menulis audit batch | Terbukti lewat injeksi exception sebelum insert: completion sukses, lima withdrawal terbentuk, tetapi audit pengambilan tidak ada. `AuditService::log()` menangkap exception dan hanya menulis log aplikasi. Ini menjelaskan entri hilang seluruhnya, bukan kolom nomor kosong. |
| Excel Audit Trail | Source export tidak menyediakan kolom ID Box. Unduhan ini sendiri tidak dapat dipakai untuk memastikan nomor box tercatat. |

Temuan lokasi, soft-delete, dan kegagalan tulis merupakan masalah terpisah dari
hilangnya `box_number`. Tidak ada bukti bahwa semua kondisi tersebut terjadi pada
Audit #8337. JSON keseluruhan yang tidak valid biasanya menghasilkan detail kosong,
bukan lima baris dengan part/PCS/lokasi yang masih terbaca seperti screenshot.
Jika `box_number` ada tetapi null/kosong, presenter menampilkan `Belum diisi`;
teks `Tidak tercatat` terutama mengarahkan pemeriksaan pada key yang tidak ada.

## Verifikasi

Semua pengujian menggunakan SQLite `:memory:` dan konfigurasi testing.
Tidak menjalankan migration pada database kerja/produksi.

- Enam tes diagnosis / 55 assertions lulus; tes mengharapkan gejala bug yang ada,
  sehingga kelulusannya **membuktikan reproduksi, bukan menyatakan bug diperbaiki**.
- Dua kelas tes existing, `AuditDetailPresentationTest` dan
  `DeliveryFlowConsistencyTest`: 29 tes / 197 assertions lulus.
- Tidak melakukan pengujian UI visual atau membaca database produksi.
- PHP awalnya tidak dapat membaca `vendor/autoload.php` dalam sandbox; eksekusi
  tes yang sama di luar sandbox berhasil. Tidak perlu mengganti path proyek.

Enam tes diagnosis telah dikonversi menjadi tes regresi perilaku yang benar di
`tests/Feature/StockWithdrawalAuditSnapshotTest.php`, dengan cakupan tambahan.
Jalankan dari root repository di PowerShell:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --do-not-cache-result tests/Feature/StockWithdrawalAuditSnapshotTest.php
```

Gunakan konfigurasi testing tanpa cached config; database pengujian SQLite in-memory.

## Pemeriksaan produksi untuk memastikan Audit #8337

Pemeriksaan berikut hanya membaca data; belum dijalankan oleh agent:

```sql
SELECT id, created_at, type, action, model, model_id, new_values
FROM audit_logs
WHERE id = 8337;
```

Periksa apakah model `DeliveryPickSession`, apakah `boxes` memiliki `box_id`
tanpa `box_number`, serta `session_id`/`delivery_order_id` yang tercatat. DO #707
merupakan petunjuk pada screenshot, bukan alasan memilih sesi terbaru order tersebut:
satu order bisa memiliki lebih dari satu sesi akibat Redo.

Setelah model sesi dikonfirmasi, telusuri referensi PK, termasuk soft-delete:

```sql
SELECT w.id AS withdrawal_id, w.pick_session_id, w.box_id,
       b.box_number, b.deleted_at AS box_deleted_at,
       w.part_number, w.pcs_quantity, w.warehouse_location,
       w.status, w.deleted_at AS withdrawal_deleted_at
FROM audit_logs a
JOIN stock_withdrawals w ON w.pick_session_id = a.model_id
LEFT JOIN boxes b ON b.id = w.box_id
WHERE a.id = 8337 AND a.model = 'DeliveryPickSession'
ORDER BY w.id;
```

Bandingkan per baris snapshot dan referensi box; jangan menebak nomor dari nomor part,
lokasi, urutan baris, atau nomor label terkini. Pada versi sekarang label dapat dipakai
ulang; pemulihan harus mengikuti PK penggunaan lama. Jika data fisik telah dihapus
permanen, periksa bukti snapshot QA (`source_snapshot`/`history_snapshot`), audit
scan/verifikasi yang benar-benar tersedia, atau backup. Tidak semua histori lama
memiliki sumber tersebut. Ketidaktersediaan bukti harus tetap ditandai sebagai data
tidak tersedia, bukan diisi dengan dugaan.

## Arah perbaikan yang disarankan

1. Catat `box_number` sebagai string bersama PK dan, pada schema sekarang,
   penggunaan identitas. Bentuk snapshot dari box tervalidasi dan lokasi sebelum
   mutasi, atau ledger withdrawal yang baru dibuat; jangan membaca ulang lokasi
   inventory setelah dibersihkan.
2. Gunakan penulisan audit wajib dalam transaksi completion/Redo, sehingga
   kegagalan audit tidak membiarkan transaksi berhasil tanpa jejak pengambilan.
3. Ganti tes diagnosis dengan tes regresi perilaku yang benar saat implementasi:
   nomor dengan nol awal, pallet habis, shared legacy, Redo, audit gagal, dan
   snapshot tetap setelah stok berubah/dihapus atau label dipakai ulang.
4. Tangani histori lama sebagai pekerjaan tersendiri setelah preview kecocokan
   PK dan bukti tersedia. Simpan asal pemulihan bila ditampilkan sebagai informasi
   tambahan; jangan menyamakan data hasil pemulihan dengan snapshot asli.

## Status perbaikan

Patch aplikasi diterapkan pada checkout yang sama pada 16 November 2026:

- Completion membuat snapshot PK, nomor label sebagai string (termasuk nol awal),
  penggunaan identitas, part, PCS, dan lokasi canonical sebelum mutasi. Ledger
  withdrawal dan audit memakai lokasi yang sama, termasuk ketika pallet habis.
- Logger batch mewajibkan snapshot dari caller, tanpa membaca ulang inventory.
  Total PCS/box dihitung dari rincian snapshot yang benar-benar disimpan.
- Redo menyimpan seluruh identitas box dan lokasi tujuan konsolidasi yang
  ditentukan server, sesudah assignment berhasil. Legacy tanpa master location
  tetap diizinkan sesuai perilaku existing; snapshot lokasi null berarti belum
  diisi, bukan lokasi asal yang ditebak. Anggota box tidak lengkap ditolak.
- Audit batch completion/reversal, pemulihan Not Full, serta event Redo menggunakan
  penulisan wajib. Kegagalan audit me-rollback mutasi transaksi yang sama.
- Presenter tetap membaca snapshot historis saja. Tidak ada migrasi atau backfill,
  perubahan UI/hak akses, maupun perubahan format Excel Audit Trail.

Verifikasi akhir: 104 tes / 1.627 assertions lulus pada SQLite in-memory, meliputi
audit, completion/Redo, undo langsung, reuse label, laporan, pengumuman, guard pallet,
dan hak akses. Di dalamnya ada 13 tes regresi baru / 165 assertions, termasuk:
nomor label lima box, pallet habis, lokasi shared canonical, Redo ke lokasi asal/
relokasi/legacy tanpa master, snapshot setelah soft-delete/hard-delete/reuse,
completion penggunaan kedua, penolakan Redo anggota hilang, dan rollback saat audit
completion, reversal, event Redo atau pemulihan Not Full gagal.

Tidak melakukan deployment atau pemulihan record produksi #8337. Histori yang
belum menyimpan nomor label tetap memerlukan pemeriksaan bukti sesuai query di atas.
