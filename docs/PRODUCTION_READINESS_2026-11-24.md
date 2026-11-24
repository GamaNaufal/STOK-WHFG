# Pemeriksaan kesiapan upgrade production — 24 November 2026

## Keputusan saat ini

**Belum siap untuk langsung deploy ke production.** Project memiliki jalur
upgrade database existing, tetapi belum ada verifikasi lingkungan server,
rehearsal pada salinan production dan bukti backup yang berhasil direstore.
Regresi penuh juga masih mempunyai kegagalan yang harus ditinjau sebelum rilis.
Database production tidak diakses atau diubah dalam pemeriksaan ini.

Baseline dari daftar pengguna: 33 migration sudah Ran, terakhir
`2026_10_15_000001_create_role_feature_access_table`. Checkout `b0bbc60` memuat
63 migration: **30 tambahan**, sampai reservasi customer 16 November. Ini
perbandingan terhadap laporan pengguna, bukan pembacaan langsung schema server.
Log attachment `6fb73cf → 9451407` lebih lama daripada daftar Ran terbaru.

## Temuan yang menentukan kesiapan

| Temuan | Dampak dan tindak lanjut |
| --- | --- |
| Migration QA awal memotong deadline reaktivasi | Telah diperbaiki di checkout ini sesuai keputusan pengguna: deadline asal dipertahankan, hanya metadata legacy disalin. Pastikan revisi ini masuk commit/image final. Tidak memperbaiki otomatis server yang sudah menjalankan versi lama. |
| Penyatuan master dan registry, lalu penggunaan ulang ID box | Ada pemindahan FK, penggabungan master duplikat, perubahan unique index dan generated column. Preflight internal tersedia, tetapi engine/versi/collation dan konflik data production harus diuji pada salinan nyata. |
| Beberapa migration tidak dapat di-rollback otomatis | `down()` reuse/izin rinci menolak, penyatuan master tidak memecah kembali data. Kegagalan DDL MySQL dapat menyisakan perubahan sebagian; pemulihan perlu database cutoff beserta code/config yang cocok atau perbaikan maju yang ditinjau. |
| Dockerfile/Compose production tidak tersedia pada checkout | Mount source, persistent storage/database, jumlah instance dan pengelola worker/scheduler belum diketahui. Perintah rebuild, stop/start dan backup belum boleh ditetapkan dengan menebak nama service. |
| `vendor` dan `public/build` diabaikan Git | Pull source saja tidak menyiapkan dependency/aset terbaru. Siapkan vendor dari composer.lock, serta build/manifest/aset dari rilis yang sama. `public/hot` tidak boleh tertinggal. |
| Fitur customer mempunyai flag rollout, default true | Set `EXPORT_CUSTOMER_STOCK_WRITES_ENABLED=false` saat pemasangan; aktifkan sesudah semua penulis lama berhenti dan schema/cache seluruh instance sesuai. Box/delivery existing tidak otomatis dipetakan ke customer master. |
| Konversi hak akses dan grant fitur baru | Periksa akses efektif semua role sesudah migration, termasuk izin tulis customer. Keberhasilan login Admin Utama saja tidak cukup membuktikan izin operator benar. |
| Scheduler memutasi stok dan sesi | Hentikan seluruh penulis saat upgrade. Setelah verifikasi, jalankan kembali timeout/sync setiap menit dan ringkasan 07.00 WIB. Maintenance satu container tidak mencakup worker/cron/instance lain. |
| Suite backend belum seluruhnya lulus | Kegagalan laporan/Excel, seeder dan preview dashboard yang tercatat di memo tetap perlu ditinjau. Jangan memakai hasil tes terarah sebagai pernyataan seluruh aplikasi lulus. |

Operasi DDL tertentu pada MySQL melakukan implicit commit, sehingga satu batch
migration bukan transaksi atomik untuk seluruh upgrade. Lihat
[dokumentasi MySQL](https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html).
Urutan migration dan cache mengikuti
[Laravel migrations](https://laravel.com/docs/12.x/migrations) dan
[Laravel deployment](https://laravel.com/docs/12.x/deployment), dengan tambahan
penghentian penulis serta rekonsiliasi yang diperlukan project ini.

## Perubahan preservasi reaktivasi

File `2026_10_23_000001_create_expired_box_handlings.php` masih pending pada
baseline production pengguna. Revisi menghapus assignment `reactivated_until =
now()`, tetap menyalin deadline ke `legacy_reactivated_until` untuk box yang
belum withdrawn/deleted. Tidak mengubah PCS, tanggal FIFO, status, lokasi,
assignment, history maupun deadline existing.

Deadline yang sudah habis tetap habis. Box handled tidak diaktifkan kembali.
Box dengan deadline valid tetap mengikuti penjaga status/assignment existing dan
akan menjadi tidak eligible ketika deadline asal tercapai, termasuk saat belum
ada scheduler berikutnya. QA/packing baru tetap mengikuti alur existing.

Jika schema server ternyata sudah melewati migration QA versi lama, **berhenti
dan laporkan**. Mengubah file yang sudah Ran tidak membuat Laravel mengulangnya.
Pemulihan deadline pada server tersebut membutuhkan penilaian penggunaan box
terkini; jangan menyalin metadata legacy secara massal.

## Pemeriksaan lokal

- PHP lokal 8.3.33; `composer validate --no-check-publish` dan
  `composer check-platform-reqs --no-dev` lulus. Ini memeriksa lingkungan lokal,
  bukan extension/platform image production.
- Seluruh 180 tes frontend lulus; `npm run build` lulus (Vite 7.3.7).
- Tes terarah `ProductionBaselineUpgradeTest` dan `ExpiredBoxReactivationTest`:
  **24 tes / 238 assertions lulus** pada SQLite in-memory.
- Uji baru membuat schema hingga baseline 15 Oktober, mengisi enam kondisi box
  (deadline valid/habis/null, handled, withdrawn, deleted), master, user,
  delivery processing, sesi picking dan audit, lalu menjalankan seluruh pending.
  Proyeksi seluruh kolom existing, PK/count, deadline, relasi picking, registry,
  pemeriksaan penyatuan dan batas expiry diverifikasi. Semua data fiktif.
- Sintaks PHP file yang berubah dan sintaks Bash preflight lulus. Alur shell
  diuji memakai Docker mock (berhasil, APP_DIR khusus dan container berhenti);
  diagnostik PHP juga menolak driver SQLite dengan exit 1 sebagaimana dirancang.
  Belum diuji pada Docker/server production; laporan server masih dibutuhkan.
- Regresi penuh final: **787 tes / 8.450 assertions, 780 lulus, tujuh gagal,
  tanpa error** pada SQLite in-memory, durasi 4 menit 48 detik. Seluruh tes
  upgrade/reaktivasi terbaru lulus. Tujuh kegagalan berada pada area yang sudah
  dicatat memo sebelum perubahan ini:

| File tes | Kegagalan yang masih perlu ditinjau |
| --- | --- |
| `OperationalReportRangeTest` | Satu kegagalan: rentang tiga hari memuat tambahan satu hari. |
| `ReportTextSafetyTest` | Dua kegagalan: tipe sel kuantitas Excel dan pemulihan value binder setelah unduhan gagal. |
| `SystemIntegrityRegressionTest` | Satu kegagalan: pembandingan snapshot user setelah seeder berbeda tipe boolean/int dan urutan atribut. Ini belum membuktikan akun berubah; tes belum lulus dan seeder tidak dijalankan pada production. |
| `UiReviewPreviewTest` | Tiga kegagalan: ekspektasi isi dashboard, durasi nol/tanggal scan kosong, dan alasan/status/catatan scan issue. |

Laporan pengujian disimpan lokal pada `storage/app/deploy-*-2026-11-24.*`
(diabaikan Git). Tidak menjalankan migration pada database operasional.

## Bukti yang diperlukan sebelum keputusan deploy

1. Laporan `production-preflight.sh` dan jawaban operator, termasuk konfigurasi
   deploy tanpa secret. Pastikan migration/schema aktual sesuai baseline.
2. Rilis final bernomor commit/digest dengan source, dependency dan aset lengkap.
3. Backup final database, code/image/config/.env/APP_KEY dan storage persistent;
   hasil restore teruji pada lingkungan terpisah dengan engine/versi yang sama.
4. Rehearsal seluruh 30 migration pada salinan production, pencatatan durasi,
   konflik/metadata lock dan pembandingan kolom bisnis, deadline, stok/PCS/lokasi,
   history, transaksi pending dan izin. Master yang digabung dan audit migration
   harus dibandingkan sesuai perubahan yang memang direncanakan.
5. Penilaian dan penyelesaian kegagalan regresi; uji penerimaan alur operasional,
   laporan/Excel dan scanner yang dipakai operator pada staging.
6. Window maintenance, PIC, prosedur penghentian semua penulis dan pemulihan
   yang sudah ditinjau. Setelah transaksi baru dibuka, restore backup cutoff
   memerlukan rekonsiliasi transaksi baru.

Panduan langkah operator dan command tahap pengumpulan informasi tersedia di
[panduan upgrade](PRODUCTION_UPGRADE_FROM_2026_10_15.md). Tahap pertama hanya
pengumpulan informasi; perintah perubahan production menunggu bukti di atas.
