# Cetak Rencana Delivery dan Pallet Ekspor

Verifikasi 30 Oktober 2026 pada branch Export-plan.

## Penggunaan

Kedua jenis cetak tersedia setelah perencanaan pallet final dan revision delivery
yang dirujuk masih sesuai. Pada detail delivery terdapat **Cetak rencana delivery**.
Pada hasil perencanaan terdapat **Cetak semua pallet** dan **Cetak pallet** di setiap
pallet. Pilih **Cetak / simpan PDF** pada halaman cetak melalui browser Chrome.

Rencana delivery memuat kode, customer, batas pengiriman, revisi, waktu finalisasi,
part, jumlah PCS, kapasitas PCS/box, jumlah box dan total. Kapasitas memakai
snapshot item rencana, sehingga perubahan master tidak mengubah cetakan existing.

Kartu pallet memuat nomor pallet dan lokasi berukuran besar, kode delivery,
customer, tenggat, revisi, nomor part dan jumlah box yang harus ada. Total box
dan waktu cetak dicantumkan. Peringatan 36/72 box tetap tersedia sebagai teks
agar dapat dibaca pada cetakan hitam-putih.

Sesuai pilihan pengguna, tinggi kartu mengikuti jumlah part. Kartu disusun
berurutan pada A4 portrait dengan margin 10 mm, sehingga beberapa kartu pendek
bisa berbagi halaman tanpa jumlah pallet tetap per lembar. Kartu yang muat utuh
dipertahankan satu bagian; kartu yang melebihi satu halaman dapat berlanjut.
Nomor pallet, lokasi dan judul kolom berada di thead agar diulang saat lanjut.
Cetak satu pallet hanya memuat kartu yang dipilih.

Gunakan revisi terbaru jika perencanaan dibuka kembali atau delivery berubah.
Cetakan adalah snapshot, tanpa perubahan stok, reservasi, pemenuhan atau audit.

## Akses dan implementasi

- GET `/export/delivery-plans/{deliveryPlan}/print`, nama
  `export.delivery-plans.print`, memakai izin export_delivery_plans.
- GET `/export/pallet-plans/{deliveryPlan}/print`, nama
  `export.pallet-plans.print`, memakai izin export_pallet_plans.
- GET `/export/pallet-plans/{deliveryPlan}/pallets/{pallet}/print`, nama
  `export.pallet-plans.print-pallet`, memakai izin export_pallet_plans.
  Pallet dicari melalui relasi planning delivery tersebut; ID asing ditolak 404.

ExportPlanPrintController memeriksa ExportPalletPlan::isFinalFor. Belum ada
planning, draft, revisi tidak sesuai atau cancelled ditolak 409; delivery yang
diarsipkan tidak dapat di-resolve (404). Link disembunyikan saat tidak siap.
Izin delivery dan pallet tetap independen. Respons private/no-store.

Template standalone: resources/views/export/print. Aset lokal tunggal:
public/css/pages/export-plan-print.css. Tidak memuat kerangka/sidebar aplikasi,
CDN atau dependency runtime baru. Tidak ada migration, seeder atau izin baru.
Deployment menyertakan controller/model/rute/view/CSS terbaru dan membangun
ulang route cache jika digunakan. Cetak menggunakan HTML browser; Dompdf hanya
dipakai untuk QA internal, bukan endpoint PDF baru.

## Verifikasi

44 tes backend terkait lulus (509 assertions), termasuk empat tes cetak:
final/revision, snapshot kapasitas, read-only, keanggotaan pallet, tautan,
draft/reopen/cancel/arsip dan pemisahan izin.

QA PDF A4 memakai renderer Dompdf existing: 12 part delivery satu halaman,
20 kartu pallet dengan 1–5 part menjadi enam halaman (3–4 kartu pada halaman
penuh), satu pallet terpilih satu halaman, delivery 80 part panjang enam
halaman, dan satu pallet 80 part tiga halaman. Semua halaman dirender dan
diperiksa; ekstraksi teks memeriksa 80 part lengkap tanpa duplikasi serta
pallet 1–20 lengkap/urut. Angka halaman ini milik renderer QA, bukan jaminan
jumlah halaman pada Chrome/printer.

Browser Chromium desktop memeriksa tombol final, cetak semua/satu pallet,
delivery, tampilan kartu dan ketiadaan link pada draft, tanpa error konsol.
Printer fisik belum diuji. Fixture hanya pada SQLite preview terpisah;
database operasional/production tidak diubah.
