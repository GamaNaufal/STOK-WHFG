# Setup Local Development

## Prasyarat

- PHP 8.2
- Composer
- Node.js 22.12+ (untuk build Vite 7)
- MySQL

## Langkah Setup

1. Clone repository dari GitHub.
2. Install dependency backend:
    - `composer install`
3. Install dependency frontend:
    - `npm ci`
4. Copy environment:
    - `cp .env.example .env` (Windows: copy manual)
5. Set konfigurasi database di `.env`:
    - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
6. Generate app key:
    - `php artisan key:generate`
7. Jalankan migrasi:
    - `php artisan migrate`
8. Jika perlu data awal:
    - `php artisan db:seed`
9. Build assets:
    - `npm run dev` (development) atau `npm run build` (production build)
10. Permission:

- Pastikan `storage` dan `bootstrap/cache` writable.

## Catatan

- Login via `/login`.
- Role menentukan menu yang tampil.
- Aset UI bersama disertakan lokal di public/vendor. Saat deploy, sertakan
  seluruh folder tersebut serta public/css/layout-shell.css,
  public/js/layout-shell.js dan public/js/toast.js. Tidak perlu unduhan dari PC
  operator. Rincian benchmark dan cache production: docs/PERFORMANCE_NAVIGATION.md.

## Akun awal Operator Export

Setelah migration penambahan role dijalankan, buat akun awal secara terpisah:

```bash
php artisan db:seed --class=ExportOperatorSeeder
```

- Email: `export@warehouse.local`
- Password awal: `password`
- Role: `export_operator` (Operator Export), akses bawaan Rencana Delivery, Perencanaan Pallet dan master ekspor.

Seeder ini juga dipanggil oleh `UserSeeder`. Seeding ulang mempertahankan akun
existing, termasuk password dan status aktif yang sudah diperbarui. Perintah
khusus di atas tidak menjalankan seeder master data atau stok.

## Pemisahan master domestik dan ekspor

Jalankan migration terbaru sebelum menggunakan halaman master. Data master lama
mendapat lingkup `domestic` dengan ID dan relasi tetap; master `export` dimulai
kosong. Nomor part yang sama bisa mempunyai kapasitas berbeda per lingkup.
Lokasi domestik dan ekspor mewakili area fisik yang terpisah.

Operator Export mendapat menu Master Nomor Part Ekspor dan Master Lokasi Ekspor.
Admin Utama dapat mengelola keduanya dari menu terpisah. Master ekspor tidak
muncul di pencarian atau transaksi domestik. Migration izin menambahkan dua fitur
master pada pengaturan Operator Export existing; pencabutan berikutnya tetap
dilakukan melalui Hak Akses. Seeder master yang sudah ada tetap mengisi domestik.

Catatan deployment: setelah master ekspor mulai diisi, gunakan versi aplikasi
yang memahami lingkup data. Rollback ke query lama yang belum memfilter scope
dapat mencampurkan master. Migration scope sengaja mempertahankan struktur saat
rollback agar identitas dan data kedua lingkup tidak hilang.

## Rencana Delivery Ekspor

Target penggunaan adalah Chrome desktop. Operator Export mengisi customer,
batas tanggal pengiriman, kode rencana, nomor part dan jumlah PCS. Isi master nomor part
ekspor terlebih dahulu. Kapasitas dan total box dihitung otomatis; jumlah
nonkelipatan kapasitas ditolak. Rencana delivery disimpan sebagai draft;
pembagiannya tersedia melalui menu Perencanaan Pallet Ekspor.

Jalankan seluruh migration baru sebelum membuka fitur. Migration
2026_10_28_000004_create_export_delivery_plans membuat tabel rencana/item dan
mengaktifkan izin export_delivery_plans pada pengaturan Operator Export existing.
Tidak perlu menjalankan ulang seluruh seeder pada production.

Migration 2026_10_28_000005_add_plan_code_to_export_delivery_plans menambahkan
kode rencana nullable dan index pencarian. Rencana lama tetap mempunyai kode
kosong sampai dilengkapi. Kode dapat diisi manual atau dibaca dari header dokumen.

Migration 2026_10_28_000006_create_export_pallet_planning menambahkan perencanaan
dan reservasi lokasi. Operator Export menyusun pallet dan lokasi, menyimpan draft,
lalu finalisasi jika seluruh alokasi sesuai. Izin export_pallet_plans ditambahkan
ke pengaturan role yang tersimpan. Detail revisi, pembatalan, dan batas tahap
pengerjaan tersedia di docs/EXPORT_PALLET_PLANNING.md. Tidak ada migrasi atau
input stok fisik ekspor dalam modul ini.

Untuk membangun aset:

```bash
npm ci
npm run build
```

Sertakan **public/build** dalam deployment jika build dilakukan di CI atau
komputer lain. Node hanya diperlukan saat build. Input rencana tersedia melalui
form manual atau impor Excel. Fitur pembacaan foto dan dependency OCR sudah
dihapus atas permintaan pengguna pada 28 Oktober 2026; aset vendor/ocr tidak
lagi dibuat atau diperlukan.

Impor Excel mendukung .xlsx/.xls maksimal 5 MB, 500 part, satu sheet berisi satu
customer. Gunakan template yang disediakan. Isi file dikirim ke endpoint preview
aplikasi, tanpa menyimpan workbook sebagai arsip. Formula tidak dihitung ulang;
gunakan nilai atau hasil formula yang sudah disimpan Excel. Hasil impor
menampilkan field yang dapat dikoreksi dan meminta konfirmasi sebelum disimpan.

Pada format contoh dengan kolom QTY(PCS) dan Qty2(Pcs), jumlah memakai QTY(PCS)
(kolom C), bukan Qty2(Pcs). Baris jumlah kosong/nol diabaikan dengan peringatan
jumlah masing-masing. Footer total box tidak dipakai; total tetap dihitung dari
jumlah part dan kapasitas master ekspor.

Kapasitas tiap item disimpan sebagai snapshot; perubahan master tidak mengubah
perhitungan rencana lama. Part yang dipakai rencana tidak dapat diganti kodenya
atau dihapus, termasuk jika rencana diarsipkan. Migration rencana sengaja
menolak rollback otomatis agar histori tidak hilang; rollback perlu ditinjau.

## Pembaruan Pemenuhan Permintaan Ekspor (29 Oktober 2026)

Setelah migration ekspor sebelumnya tersedia, modul pemantauan memerlukan:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_000001_create_export_fulfillment_monitoring.php
```

Migration ini menambah penanda rencana yang pernah final, tabel komitmen box
kosong, dan izin `export_fulfillment` pada pengaturan Operator Export existing.
Rencana final muncul otomatis di Pengiriman Ekspor / Pemenuhan Permintaan.
Tidak perlu konfigurasi PC atau layanan tambahan. Pemantauan bersifat read-only;
input/scan tersedia melalui modul penerimaan pada bagian berikutnya.
Rincian: docs/EXPORT_FULFILLMENT.md.
Jangan menjalankan migration QA/packing yang belum ditinjau hanya untuk modul
ini. Pada MySQL lokal stock, migration ini sudah teramati tersedia saat verifikasi.

## Penerimaan Box Ekspor (29 Oktober 2026)

Setelah migration pemenuhan di atas tersedia, jalankan secara terarah:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_000002_create_export_stock_receiving.php
npm run build
```

Sertakan public/build dan CSS halaman baru pada deployment. Migration menambah
empat tabel inventory ekspor, FK komitmen nullable dan izin export_stock_input;
stok domestik tidak disalin. Modul memakai scan nomor box lalu part, PCS dari
master ekspor, konfirmasi penempatan, serta FIFO stok lama. Nomor box boleh sama
lintas lingkup. Tidak perlu konfigurasi PC/layanan baru. Migration ini sudah
teramati tersedia pada MySQL lokal stock saat verifikasi. Detail dan batas alur:
docs/EXPORT_STOCK_RECEIVING.md. Jangan menjalankan cutover QA/packing yang belum
ditinjau hanya untuk mengaktifkan penerimaan ekspor.

## Penerimaan Bulk dan Halaman Stok Ekspor (29 Oktober 2026)

Sesudah migration penerimaan 000002 tersedia:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_000003_create_export_receiving_batches.php
npm run build
```

Migration hanya menambah export_receiving_batches dan export_receiving_batch_items;
tidak perlu seeder/permission baru. Sudah diterapkan pada MySQL lokal stock dengan
jumlah data existing tetap. Untuk production jalankan migration terarah tersebut,
sertakan public/build, CSS penerimaan dan public/js/scan-feedback.js terbaru.

Operator scan banyak pasangan nomor box/part dahulu, lalu Proses penerimaan
untuk melihat kelompok lokasi/pallet. Draft tersimpan setelah refresh, maksimal
1.000 box, dan belum masuk stok sebelum diproses. Indikator/audio OK/NG sama
dengan Stock Input. Menu Stok Ekspor tersendiri berada di /export/stock; memakai
izin export_stock_input existing. Tidak memerlukan library, layanan, atau
konfigurasi PC tambahan. Detail transaksi/FIFO: docs/EXPORT_STOCK_RECEIVING.md.

Antrean kini satu baris per transaksi penerimaan. Atur penempatan membuka halaman
khusus pada /export/receiving/transactions/{batch}; penerimaan lama tanpa batch
tetap tersedia. Perubahan ini memakai schema/izin existing, tanpa migration
atau seeder tambahan. Sertakan controller/service/view/rute terbaru dan bangun
ulang cache rute saat deployment jika sebelumnya memakai route cache.

Pembaruan 30 Oktober 2026 menambah sorotan tujuan dan panduan cetak per
transaksi. Sertakan ExportPlacementGuideService, controller/rute/view transaksi
terbaru, public/css/pages/export-placement-guide.css, CSS penerimaan dan hasil
`npm run build` pada deployment. Bangun ulang cache rute jika digunakan.
Tidak ada migration, seeder, dependency runtime atau konfigurasi PC tambahan.
Operator membuka Cetak panduan lalu Cetak / simpan PDF melalui Chrome;
pilihan lokasi cadangan ikut cetakan tanpa mengonfirmasi stok.

Nama menu menjadi Penerimaan Transfer Box Ekspor; rebuild config cache bila
deployment memakai config:cache agar label baru tampil. Peringatan jumlah box
per pallet (kuning mulai 36, merah mulai 72) memerlukan App\Support\ExportPalletLoad,
komponen Blade export-pallet-load, view/CSS terbaru dan public/build hasil build.
Tidak ada migration, seeder atau konfigurasi PC tambahan. Threshold hanya
peringatan; validasi dan izin existing tetap digunakan.

Cetak rencana delivery dan rincian pallet (30 Oktober 2026) memakai controller
ExportPlanPrintController, method ExportPalletPlan::isFinalFor, rute/view terbaru,
dan public/css/pages/export-plan-print.css. Sertakan seluruh file saat deploy;
bangun ulang route cache jika digunakan. Tidak ada migration, seeder atau
layanan PDF/konfigurasi PC tambahan. Cetak melalui browser setelah planning
final; kartu pallet tersusun otomatis pada A4. Rincian:
docs/EXPORT_PLAN_PRINTING.md.

## Tinjauan Stok FIFO setelah Finalisasi (30 Oktober 2026)

Sesudah migration penerimaan/batch tersedia, jalankan secara terarah:

```powershell
php artisan migrate --path=database/migrations/2026_10_30_000001_create_export_stock_allocation_notices.php
```

Migration menambah header tinjauan dan FK nullable pada komitmen. Data lama
mempertahankan antrean existing; tidak perlu seeder atau izin baru. Sudah
diterapkan pada MySQL lokal stock dengan backup dan verifikasi data tetap.
Sertakan seluruh perubahan model/service/controller/rute/view dan rebuild route
cache jika digunakan. Pop-up memakai Bootstrap existing; tidak ada dependency,
build JS baru atau konfigurasi PC tambahan. Detail alur Oke dan pengujian:
docs/EXPORT_FIFO_STOCK_REVIEW.md.
