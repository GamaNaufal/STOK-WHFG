# Tinjauan Stok FIFO setelah Finalisasi Pallet

Verifikasi awal 30 Oktober 2026; diperbarui 3 November 2026 pada branch Export-plan.

## Alur operator

Setelah perencanaan pallet difinalisasi, allocator memeriksa stok ekspor sesuai
part dan snapshot PCS/box. Prioritas kebutuhan tetap tenggat delivery, ID delivery,
lalu nomor pallet; stok dipilih berdasarkan first_received_at, lalu ID stok.
Reservasi existing tidak dicabut untuk mengisi rencana lain.

Stok existing yang ditemukan untuk rencana yang baru final direservasi segera
agar tidak diambil permintaan lain. Pop-up **Stok FIFO untuk rencana pallet**
menampilkan jumlah, ID Box/part/PCS, tanggal penerimaan pertama, posisi fisik
asal dan tujuan pallet/lokasi. Daftar maksimal 100 baris per halaman, urut FIFO.
Pagination membuka kembali pop-up pada halaman berikutnya.

Sebelum **Oke, masukkan ke antrean**, tugas tersebut belum aktif pada antrean dan
belum bisa dikonfirmasi penempatannya, termasuk lewat POST langsung. Konfirmasi
ini wajib: hanya ada satu tombol Oke, tanpa Tinjau nanti/tombol X. Escape dan
klik di luar tidak menutup pop-up. Jika halaman ditinggalkan, reservasi tetap
tersimpan dan pop-up muncul lagi saat rencana dibuka. GET tidak mengakui hasil.

Setelah Oke, tugas aktif pada antrean penempatan/pemindahan sebagai **satu
transaksi per pallet tujuan dan gelombang rekomendasi FIFO**. Contoh: 12 box
untuk Pallet 1 dari beberapa sumber menjadi satu transaksi; 8 box untuk Pallet 2
menjadi transaksi lain. Tambahan dari revisi membuat transaksi baru, sehingga
daftar yang sedang dikerjakan tidak bertambah diam-diam. Penerimaan box baru
melalui scan massal tetap memakai transaksi penerimaan existing. Operator tetap
di halaman planning setelah Oke. Tombol Oke tidak memindahkan box, mengubah tanggal FIFO,
occupancy, pergerakan atau placed_at; pemenuhan tetap menunggu konfirmasi fisik.

Hasil finalisasi terakhir tetap dapat dilihat melalui **Tinjau alokasi FIFO**
setelah masuk antrean. Tinjauan ini menampilkan posisi fisik saat ini, tujuan dan
status (menunggu/sudah ditempatkan), dengan satu tombol **Tutup**. Tidak ada
formulir aktivasi ulang, reservasi atau mutasi stok ketika ditinjau. Daftar
dipersiapkan bersama halaman hasil sehingga tombol tinjau membuka modal lokal
tanpa loading tambahan. Pagination tetap membuka halaman hasil berikutnya.
Hasil revisi sebelumnya dapat ditinjau saat planning dibuka untuk revisi, tanpa
memaksa pengakuan atas hasil yang sudah bukan revisi final terkini.

Jika tidak ada stok cocok, pop-up menyatakan hasil kosong. Penerimaan box baru
setelah itu tetap mengikuti alokasi/penerimaan existing dan tidak ditahan oleh
tinjauan kosong. Tinjauan ini mewakili hasil pencarian saat finalisasi tersebut.

## Penyimpanan, akses dan konsistensi

Migration `2026_10_30_000001_create_export_stock_allocation_notices.php` menambah:

- export_stock_allocation_notices: header per plan/revision, pembuat dan waktu/
  aktor pengakuan. Pasangan plan/revision unik.
- export_fulfillment_boxes.allocation_notice_id: FK nullable ke header tinjauan.
  Reservasi lama tetap NULL, sehingga antrean dan datanya tidak berubah.

ExportStockAllocationNoticeService membuat header dalam transaksi finalisasi.
Allocator mengaitkan reservasi baru untuk plan tersebut, selain box yang sudah
berada di tujuan dan tidak memerlukan tugas fisik baru. Finalisasi tetap menjaga
prioritas kebutuhan lama yang lebih dahulu. Hasil kosong juga disimpan.

POST `/export/pallet-plans/{deliveryPlan}/stock-allocation/{notice}/acknowledge`
(export.pallet-plans.acknowledge-stock) memakai izin export_pallet_plans existing.
Service mengunci mutex ekspor, delivery, plan, lalu header tinjauan; memeriksa
keanggotaan, final/revision terkini dan mencatat audit atomik. Replay tidak
membuat audit/antrean ganda dan mempertahankan aktor pertama. Pengguna yang hanya
berizin planning tetap kembali ke planning, tanpa mendapat akses stok tambahan.

ExportReceivingTransactionService dan ExportStockBox::queueReady mengecualikan
reservasi yang belum diakui. Batch campuran memisahkan jumlah siap, menunggu
Oke, dan sudah ditempatkan. Detail stok/kelompok tetap menunjukkan status
menunggu Oke, tanpa formulir untuk box yang ditahan. Konfirmasi server selalu
memeriksa pengakuan; panduan cetak menandai baris tersebut **Menunggu Oke FIFO**.

Identitas transaksi rekomendasi adalah pasangan allocation_notice_id dan ID
pallet tujuan, berkode FIFO-<notice 6 digit>-<planned pallet 6 digit>. Pengelompokan
dihitung dari relasi existing, tanpa schema baru atau penulisan ulang riwayat
penerimaan. Box ini tidak menjadi tugas aktif pada transaksi penerimaan asal;
detail asal menyediakan tautan ke transaksi FIFO. Cetak asal tidak menggandakan
daftar rekomendasi, dan hitungan selesai memisahkan tugas yang dialihkan.

Detail/cetak di receiving/recommendations/{notice}/pallets/{pallet} dan /print
memuat anggota transaksi tersebut. Picking Verification menampilkan daftar per
transaksi dan memakai parameter ?notice=<id>, termasuk pada scan, replay token
dan pengembalian issue. Sumber campuran tetap wajib scan setiap box; sumber satu
jenis part bebas scan satuan. NG pencarian tidak membuat issue. NG wajib tetap
memblokir sampai approval dan konfirmasi pengembalian. Penerimaan baru tidak
memerlukan scan picking tambahan. POST penempatan memeriksa keanggotaan transaksi
di dalam mutex ekspor; setelah selesai kembali ke detail transaksi yang sama.

Revisi/pembatalan memakai alur pengembalian/pelepasan reservasi existing.
Finalisasi sebelum migration tidak dibuatkan tinjauan fiktif atau ditahan
secara retroaktif. Notice existing otomatis mengikuti pengelompokan terbaru.

## Deployment dan verifikasi

Pembaruan 3 November memakai schema/izin existing tanpa migration baru. Deploy
service/controller/model/rute/view terbaru dan perbarui cache aplikasi sesuai
prosedur deployment. Tidak ada dependency atau konfigurasi PC tambahan.

535 tes backend (4.956 assertions), 101 frontend dan Vite build lulus. Tujuh tes
baru mencakup sumber/part campuran per tujuan, penerimaan baru, revisi/gelombang
terpisah, isolasi transaksi, replay, penempatan bertahap dan pagination.
Query SQL diuji pada MySQL dengan READ ONLY/ROLLBACK tanpa perubahan data.
Browser SQLite terpisah memverifikasi dua transaksi untuk empat box (3/1),
cetak beranggotakan tepat tiga box Pallet 1, NG pencarian lintas transaksi,
scan wajib tiga box dari sumber campuran, dan penempatan satu box sumber satu
part tanpa scan tambahan. Bukti: storage/app/previews/recommendation-transactions-20261103/.

Catatan verifikasi awal 30 Oktober (sebelum pengelompokan per pallet tujuan):

Jalankan migration terarah setelah migration penerimaan/batch tersedia. Sertakan
model/service/controller/rute/view terbaru dan rebuild route cache jika digunakan.
Tidak ada permission, seeder, dependency atau konfigurasi PC baru.

107 tes backend terkait lulus (1.125 assertions), mencakup FIFO, partial stock,
penahanan API/antrean, replay, stale/foreign/revoked access, hasil kosong,
penerimaan baru, pagination 101 box, data lama dan batch campuran. Browser Chromium
desktop memverifikasi finalisasi dengan empat box stok: tiga tertua muncul pada
pop-up, belum ada transaksi asal pada antrean sebelum Oke, pop-up tetap ada setelah
kembali, dan satu transaksi asal muncul dengan tiga tugas setelah Oke. Posisi
fisik masih sumber, dua tujuan pallet benar, tanpa error konsol. Semua contoh
browser hanya pada SQLite preview.

Pembaruan konfirmasi wajib: 35 tes backend terkait lulus (424 assertions).
Browser memeriksa satu tombol, Escape/klik luar tidak menutup, aktivasi antrean,
dan tinjauan ulang dengan tombol Tutup tanpa aktivasi ulang, tanpa error konsol.
Perubahan ini memakai schema/izin yang sama, tanpa migration/dependency baru.

Migration sudah diterapkan pada MySQL lokal 127.0.0.1 / stock (APP_ENV local).
Jumlah data existing dan isi seluruh baris fulfillment diverifikasi tetap utuh:
8 box/8 komitmen ekspor, 1 pallet/3 pergerakan ekspor; data lokal/master tetap.
Tabel tinjauan baru kosong, tidak ada reservasi lama yang ditahan. Query antrean
dan pendingQuery valid di MySQL. Backup sebelum migration:
storage/app/backups/stock_allocation_notice_20261030_140820_3df0d6.json.
Production remote tidak diubah; fixture/tes tidak ditambahkan ke MySQL lokal.
