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

## Akun awal Operator Export

Setelah migration penambahan role dijalankan, buat akun awal secara terpisah:

```bash
php artisan db:seed --class=ExportOperatorSeeder
```

- Email: `export@warehouse.local`
- Password awal: `password`
- Role: `export_operator` (Operator Export), akses bawaan Rencana Delivery dan master ekspor.

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

## Rencana Delivery Ekspor dan OCR browser

Target penggunaan adalah Chrome desktop. Operator Export mengisi customer,
batas tanggal pengiriman, nomor part dan jumlah PCS. Isi master nomor part
ekspor terlebih dahulu. Kapasitas dan total box dihitung otomatis; jumlah
nonkelipatan kapasitas ditolak. Rencana saat ini disimpan sebagai draft;
perencanaan pallet belum termasuk tahap ini.

Jalankan seluruh migration baru sebelum membuka fitur. Migration
2026_10_28_000004_create_export_delivery_plans membuat tabel rencana/item dan
mengaktifkan izin export_delivery_plans pada pengaturan Operator Export existing.
Tidak perlu menjalankan ulang seluruh seeder pada production.

Untuk membangun aset:

```bash
npm ci
npm run build
```

Build menyalin model bahasa, worker, WebAssembly Tesseract, dan OpenCV.js dari
dependency yang dikunci di package-lock.json ke public/vendor/ocr. Direktori
ini diabaikan Git dan dibuat ulang saat build. Jika build dilakukan di CI atau
komputer lain, sertakan **public/build dan public/vendor/ocr** dalam deployment.
Node hanya diperlukan saat build; pembacaan foto berjalan di browser pengguna.
Tidak perlu instalasi OCR/Python pada server, API key, atau layanan OCR terpisah.

Impor foto mendukung JPG/PNG/WebP maksimal 10 MB dan 40 megapiksel, dokumen cetak
dengan layout tabel contoh. Foto tetap berada di browser; hanya field yang sudah
diperiksa yang dikirim saat simpan. Pembacaan pertama memuat aset/model lokal
dan dapat lebih lambat. Tulisan tangan serta layout bebas belum didukung.

Impor Excel mendukung .xlsx/.xls maksimal 5 MB, 500 part, satu sheet berisi satu
customer. Gunakan template yang disediakan. Isi file dikirim ke endpoint preview
aplikasi, tanpa menyimpan workbook sebagai arsip. Formula tidak dihitung ulang;
gunakan nilai atau hasil formula yang sudah disimpan Excel. Kedua sumber impor
menampilkan field yang dapat dikoreksi dan meminta konfirmasi sebelum disimpan.

Kapasitas tiap item disimpan sebagai snapshot; perubahan master tidak mengubah
perhitungan rencana lama. Part yang dipakai rencana tidak dapat diganti kodenya
atau dihapus, termasuk jika rencana diarsipkan. Migration rencana sengaja
menolak rollback otomatis agar histori tidak hilang; rollback perlu ditinjau.
