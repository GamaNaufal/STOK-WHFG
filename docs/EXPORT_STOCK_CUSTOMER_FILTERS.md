# Filter customer dan nomor part stok Ekspor

Diverifikasi 19 November 2026. Perubahan langsung pada checkout pengguna.

Pilihan customer sebelumnya hanya memengaruhi Kesiapan Customer dan berasal
dari delivery aktif. Daftar stok tetap global. Kini pilihan berasal dari Master
Customer dan memengaruhi daftar, ringkasan, isi pallet, pagination serta Excel.

## Cakupan customer

Atas konfirmasi pengguna, stok terkait adalah gabungan:

- box yang memiliki reservasi kepemilikan customer tersebut;
- box yang memiliki komitmen delivery dengan relasi ke customer tersebut,
  termasuk box Umum atau pengecualian customer lain yang sudah dialokasikan.

Query memakai `whereHas` dalam satu kelompok OR sehingga box tidak berlipat
ketika memiliki kedua relasi. Filter pencarian, part, lokasi dan status tetap
di luar kelompok tersebut. Stok umum bebas dengan part sama tidak otomatis masuk.
Pemilik dan customer delivery ditampilkan terpisah pada baris box.

Pilihan `Semua customer` tidak membatasi kepemilikan/delivery. Master tanpa
delivery tersedia. Master nonaktif tetap dapat dipilih untuk membaca stok lama,
dengan label Nonaktif. Pilihan memakai `customer:ID` yang divalidasi pada daftar,
isi pallet dan Excel. Nama legacy bebas atau ID tidak dikenal ditolak. Snapshot
nama lama dan nama master yang diubah tidak menentukan identitas customer.
Pemetaan delivery lama tetap melalui tindakan pemetaan existing, tanpa backfill.

## Filter dan jumlah

Sesuai koreksi pengguna tanggal 19 November 2026, toolbar menyediakan kolom
ketik nomor part, tanpa dropdown atau saran, dan pilihan Terikat delivery /
Tanpa delivery. Koreksi berikutnya pada tanggal yang sama memakai pencocokan
sebagian, mulai satu karakter. Teks dicari di awal, tengah atau akhir tanpa
membedakan kapitalisasi, `P`, `PAR`, `001` atau `extra` dapat langsung cocok.
`PART-001` juga mencakup `PART-001-EXTRA`. Karakter %, _ dan ! diperlakukan sebagai
teks literal. Pencarian utama `q` tetap mencari potongan teks.
Filter diterapkan otomatis setelah jeda ketik 250 ms atau langsung dengan Enter.
Mengganti customer mempertahankan nomor yang diketik, nomor tanpa stok terkait
menghasilkan daftar kosong. Mengedit part membersihkan filter PCS/box lama dari
tautan kebutuhan. Back/Forward memulihkan teks yang diketik. Katalog part server
dan respons AJAX dihapus karena tidak digunakan lagi.
Tautan kebutuhan yang membawa nomor part dan PCS/box tetap memakai pencocokan
part persis untuk mempertahankan jumlah kebutuhan per part/kapasitas. Saat
operator mengetik, PCS/box dibersihkan dan pencarian sebagian berlaku.
Reset membersihkan filter daftar sambil mempertahankan customer, sesuai pola lama.

Ringkasan Total stok terkait, Terikat delivery dan Tanpa delivery memuat box dan
PCS. Total adalah gabungan relasi di atas, bukan penjumlahan reservasi pemilik
dan komitmen delivery. Ringkasan fisik Stok Bersih / Sedang Diproses / Siap Kirim
serta jumlah terkait mengikuti customer, part, pencarian dan lokasi, sebelum
filter kategori atau keterikatan delivery. Catatan pada layar menjelaskan cakupan
ini. Progres delivery tetap menampilkan kebutuhan aktif keseluruhan customer.
Pembanding kebutuhan memakai stok bersih milik customer yang belum terikat
delivery, sehingga tautan jumlah cocok konsisten dengan daftar terfilter.

State inventory, eligibility expired, FIFO, reservasi/pengecualian, lokasi fisik,
hak akses dan transaksi tulis tetap memakai aturan existing. Tidak ada migration
atau perubahan data operasional. Excel mempertahankan kolom, tipe ID/PCS/tanggal,
proteksi teks, izin dan feedback unduhan existing.

## Implementasi dan verifikasi

- `ExportStockQueryService` memusatkan validasi, gabungan customer, filter
  keterikatan, pencarian sebagian part dan jumlah.
- `ExportStockController` memberikan cakupan sama untuk respons HTML/AJAX.
- `ExportStockCustomerReadiness` membaca master dan kebutuhan berdasarkan relasi
  customer, serta menghitung pembanding stok sesuai customer.
- Blade/CSS/stock-browser memakai input teks dan memperbarui jumlah tanpa
  navigasi penuh, mempertahankan fokus saat mengetik dan menolak respons stale.

Sebanyak 80 tes backend terkait lulus, 1.237 assertions, pada SQLite in-memory:
ExportStockFilterExportTest, ExportCustomerReservationTest, ExportStockModalTest,
ExportStockAdminTest, ExportStockReceivingTest, ExportStockAllocationNoticeTest,
FeatureAccessManagementTest dan ExportCustomerReservationMigrationTest. Cakupan
meliputi master tanpa delivery/nonaktif, rename, stok umum dengan komitmen,
deduplikasi, pengecualian lintas customer/pembatalannya, stok removed/deleted,
pallet campuran, isi modal, pagination, Excel nyata, part persis, part customer
berbeda, pilihan tidak valid dan otorisasi existing. Pembacaan tidak mengubah box.
Seluruh 180 tes frontend, build Vite, sintaks PHP/JS dan diff --check lulus.

Browser menggunakan server localhost dan database SQLite terpisah dengan tiga
customer serta enam box fiktif. Alpha menampilkan empat box / 350 PCS, dua
terikat delivery / 200 PCS dan dua tanpa delivery / 150 PCS. Part PART-001
menyaring menjadi tiga box, lalu Tanpa delivery menjadi satu. Isi modal pallet
menampilkan satu box tersebut dari enam box fisik. Customer tanpa stok memberi
hasil kosong, Reset dan Semua customer mengembalikan seluruh enam box.
Pada verifikasi awal, keyboard Home/ArrowDown/Tab mengubah pilihan part dan
memindahkan fokus dengan benar. Sesudah koreksi menjadi kolom ketik, tes frontend
memeriksa jeda ketik, Enter langsung, pergantian customer dan pemulihan teks.
Viewport 1366 dan 390 px tidak mengalami overflow halaman, kontrol select
memiliki tinggi 44 px, tabel digulir di wadahnya, konsol tanpa warning/error.
Ini simulasi browser, bukan pemeriksaan scanner atau perangkat ponsel fisik.

Bukti privat diabaikan Git: `storage/app/private/stock-customer-filter-desktop.jpg`,
`stock-customer-filter-mobile.jpg`, fixture SQLite dan script setup/runtime/router
dengan awalan sama. Server fixture ditutup setelah verifikasi. Build lokal sudah
diperbarui, deployment ke perangkat/server lain belum dilakukan.

Koreksi kolom ketik diverifikasi ulang: 18 tes ExportStockFilterExportTest /
384 assertions, enam tes export-stock-browser, build dan diff --check lulus.
Browser memeriksa input PART-001 memberi tiga box, PART-001-EXTRA dengan Enter
memberi satu, dan input kosong mengembalikan empat box customer Alpha. Fokus
tetap pada input setelah respons. Lebar 390 px tanpa overflow, input setinggi
44 px dan konsol bersih. Bukti terbaru: `stock-customer-part-input-desktop.jpg`
di direktori privat yang sama, screenshot dropdown sebelumnya bersifat historis.

Koreksi pencarian sebagian lulus 19 tes backend / 424 assertions. Tes mencakup
satu huruf, tiga karakter, potongan tengah, huruf kecil, karakter wildcard literal,
hasil kosong, cakupan customer, pagination, isi pallet dan Excel. Browser dengan
fixture yang sama memverifikasi `P` langsung memuat empat box Alpha dan `extra`
memuat satu box, tanpa kehilangan fokus atau error konsol. Bukti terbaru ada di
`storage/app/private/stock-customer-part-partial.jpg`. Tidak mengubah aset JS,
migration, data operasional, atau melakukan deployment ke server lain.
