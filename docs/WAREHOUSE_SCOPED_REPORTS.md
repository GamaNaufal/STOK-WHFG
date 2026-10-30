# Laporan gabungan Domestik dan Ekspor

Terakhir diverifikasi: 30 Oktober 2026.

## Akses dan navigasi

Laporan tetap berada dalam fitur **Laporan dan Export Operasional** existing,
bukan menu ekspor terpisah. Pemilih lingkup Domestik/Lokal atau Ekspor dan lima
jenis laporan tersedia pada halaman input/pengambilan existing maupun laporan
baru. Pergantian lingkup/jenis mengatur ulang filter catatan; tidak ada opsi
penggabungan jumlah kedua lingkup.

Default izin reports kini untuk Admin Warehouse dan Supervisi; Admin utama
tetap memperoleh akses existing. Operator Export, Warehouse Operator, PPC dan
Sales tidak mendapatkan default akses. Pengaturan izin role yang tersimpan
tetap dihormati, termasuk pencabutan izin. Tidak ada izin operasi ekspor yang
diberikan kepada pembaca laporan; rincian laporan memiliki endpoint baca sendiri.

Grafik dan dashboard Admin/Supervisi belum diubah sesuai permintaan pengguna.
Tidak ada layanan, dependency, seeder, atau migration baru untuk pelaporan.

## Lima laporan

| Jenis | Ekspor | Acuan tanggal |
| --- | --- | --- |
| Penerimaan | Satu baris per box, termasuk yang sudah dikirim; referensi TRX untuk batch dan BOX untuk penerimaan legacy/koreksi | first_received_at |
| Pengiriman/pengambilan | Satu baris per box dari permintaan yang completed_at terisi; pallet siap saja belum outbound | completed_at |
| Stok aktif/cadangan | Box belum diarsipkan dengan state received/stored; status pending, spare, reserved, fulfilled atau ready | first_received_at |
| Pemenuhan permintaan | Planning yang sudah final/pernah masuk pemenuhan, termasuk delivery selesai yang sudah diarsipkan; kebutuhan, ditempatkan, reservasi, sisa, pallet siap dan waktu selesai | Tenggat delivery |
| Revisi/pengembalian | Satu baris per audit export_fulfillment/reduced, jumlah selisih kebutuhan sebelum/sesudah, tujuan dan rincian box snapshot | Konfirmasi revisi |

Penerimaan menggunakan lokasi penempatan pertama dari movement pertama,
sehingga relokasi/pengembalian berikutnya tidak mengganti histori lokasi awal.
Stok aktif menampilkan posisi fisik; tujuan reservasi tidak dianggap posisi box.
Stok keluar memakai komitmen/planning yang diarsipkan, tanpa bergantung pada
master part atau stok aktif. Pengembalian revisi bukan penerimaan atau pengiriman
baru. Rincian FIFO dan lokasi pengembalian dibaca dari snapshot audit, termasuk
fallback nomor pallet header untuk snapshot lama yang belum memilikinya per box.

Laporan domestik penerimaan/pengambilan tetap memakai controller, tampilan dan
export existing. Tiga pilihan tambahan memakai stok fisik layak pakai existing,
PCS kebutuhan/terpenuhi DeliveryOrder, dan StockWithdrawal reversed. Laporan
pengembalian domestik menyatakan waktu perubahan terakhir dan operator catatan
asal; tidak mengarang waktu konfirmasi retur yang tidak tersimpan. Kebutuhan
domestik memakai PCS, sedangkan pemenuhan ekspor memakai box; keduanya terpisah.

## Filter, rincian, dan Excel

WarehouseReportService menyatukan query tabel, ringkasan hasil dan Excel baru.
Filter mencakup periode inklusif, referensi/box/lokasi, nomor part, customer
(untuk jenis terkait), operator dan status. Format/scope/kind/tanggal divalidasi.
Pencarian TRX/BOX serta Pallet N/Pallet cadangan didukung. Filter part pada
pemenuhan dan retur ekspor memilih seluruh permintaan/transaksi yang memuat
part; angka tetap mencakup seluruh isi, sesuai keterangan pada halaman.

Laporan utama memakai 25 baris/halaman. Rincian pemenuhan/retur ekspor dan
kebutuhan domestik memakai 50 baris/halaman. Pembaca tidak diarahkan ke halaman
operasi yang memerlukan izin lain. Riwayat tetap terbaca setelah stok/delivery
diarsipkan, tanpa membuka kembali reservasi, mengubah audit atau inventory.

WarehouseRecordsExport membaca semua hasil query filter, termasuk halaman lain,
dan mencantumkan lingkup pada setiap baris. Nomor box/referensi/teks disimpan
sebagai teks (leading zero tetap, teks mirip formula tidak dieksekusi). Tanggal
dan jumlah bertipe numerik dengan format tanggal Excel; header teal, freeze
dan autofilter tersedia. Export pengambilan domestik juga menerapkan filter
nomor part dan operator, sehingga seluruh filter sama dengan tabel.

## AJAX filter dan pencarian

Pemilih lingkup/jenis, tanggal, lokasi, operator dan status langsung diterapkan.
Pencarian teks memakai debounce 300 ms dan mendukung komposisi teks/IME. Tombol
Terapkan/Tampilkan laporan disembunyikan setelah JavaScript aktif; GET biasa
tetap berfungsi jika JavaScript tidak tersedia. Pagination utama dan rincian,
reset filter, serta browser Back/Forward berjalan lewat AJAX.

ReportPageResponse mengirim JSON html/title/url untuk Accept application/json,
berisi partial laporan tanpa layout/navigasi. HTML biasa tetap memakai view
existing. Kedua respons private/no-store dan Vary Accept. Stylesheet laporan
dimuat sekali pada halaman awal untuk perpindahan jenis/lingkup yang berbeda.
public/js/report-browser.js adalah module browser langsung, tanpa entry/build
Vite atau konfigurasi PC baru.

AbortController dan versi permintaan mencegah respons lama mengganti filter
terbaru, termasuk selama debounce. Fokus/caret pencarian dan posisi scroll
dipertahankan. URL, judul, ringkasan dan export Excel diperbarui bersama setelah
respons berhasil. Filter baru mereset pagination; perpindahan lingkup/jenis
mereset filter catatan. Pilihan operator/status tetap terlihat pada hasil kosong.

Koneksi/validasi gagal mempertahankan hasil sebelumnya dan menyediakan Coba lagi.
Tanggal akhir sebelum tanggal awal mendapat pesan validasi. Export dan tautan
pagination ditahan ketika filter masih diproses/gagal, agar hasil lama tidak
dianggap sesuai filter baru. Reset/filter baru tetap dapat digunakan.

Route baru memakai reports.* existing:
- GET reports/records
- GET reports/records/export
- GET reports/records/{record}

URL reports/stock-input, reports/withdrawal dan kedua export existing juga
mendukung scope=export. Tanpa scope, tetap domestik. ExportFulfillmentService
overview menerima includeArchived opsional untuk laporan saja; default operasional
tetap mengecualikan arsip. Tidak ada perubahan pencatatan atau dashboard.

## Verifikasi

Regresi backend akhir: 450 tes lulus (4.100 assertions).

31 tes laporan/izin/regresi domestik lulus (474 assertions), termasuk lima
laporan, identitas box sama lintas lingkup, pengarsipan, pengembalian, batas
tanggal, lokasi awal, akses langsung, revokasi izin, rincian tanpa izin operasi,
30 baris melewati pagination, workbook Excel numerik/ID teks dan formula teks.

Query MySQL lokal stock diverifikasi secara baca saja, termasuk filter part
JSON retur dan tanggal. Hasil saat pemeriksaan: ekspor 8 penerimaan/8 stok aktif/
0 pengiriman/1 pemenuhan/0 revisi; domestik 4 stok aktif/2 permintaan/0 retur.
Tidak ada fixture MySQL, migration atau perubahan production remote.

Browser SQLite preview memeriksa perpindahan lingkup dari halaman domestik,
pengiriman arsip empat box, filter satu box, unduhan Excel, rincian pemenuhan
empat box, retur stok cadangan, viewport 390px dan konsol tanpa error. Data
contoh hanya memakai export-fulfillment-preview.sqlite.

Pembaruan AJAX: 454 tes backend (4.191 assertions), 87 tes frontend lulus;
23 tes terkait backend (258 assertions) dan 7 tes khusus loader meliputi respons
parsial, pemisahan lingkup, pagination/rincian arsip, pilihan pada hasil kosong,
validasi, kesesuaian Excel, race respons, pembatalan debounce dan retry.
Browser memeriksa pencarian domestik 6 dari 16 catatan, perpindahan ke ekspor,
27 hasil/pagination, Back, pencarian satu box, reset dan Coba lagi setelah
tanggal salah, fokus pencarian, Excel URL dan viewport 390px tanpa overflow
halaman atau exception JavaScript. Mutasi fixture hanya SQLite preview khusus;
tidak ada migration, perubahan data MySQL atau production remote.
