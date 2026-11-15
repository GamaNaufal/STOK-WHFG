# Penggunaan ulang ID Box

Diverifikasi 15 November 2026. Kebijakan ini menggantikan larangan penggunaan
ulang sepanjang histori. Berlaku untuk Lokal dan Ekspor sesuai persetujuan pengguna.

Nomor delapan digit dapat digunakan untuk penerimaan baru setelah penggunaan
sebelumnya berakhir, termasuk hapus manual (box/pallet), selesai kirim,
pengambilan stok, penolakan QA, dan penggantian label setelah packing selesai.
Part dan PCS penerimaan baru boleh berbeda sesuai validasi normal. Umur stok baru
mengikuti penerimaan baru, tanpa mengubah tanggal atau kuantitas riwayat lama.
Nomor yang masih dimiliki stok aktif, stok expired yang belum diproses, atau
barang dalam proses QA/packing tetap tidak dapat digunakan oleh box lain.
Reservasi Not Full pending/prepared dan issue picking terbuka juga tetap diperiksa.
Izin penghapusan dan larangan menghapus barang yang terikat proses aktif tetap berlaku.

## Identitas dan histori

- ID internal database tetap berbeda untuk setiap penerimaan. Relasi input,
  pengambilan, delivery, penempatan, dan QA mengacu ke ID internal tersebut.
- `box_identities` dipertahankan sebagai pengunci global dan penghitung
  `last_cycle`. `identity_cycle` menandai setiap penggunaan nomor pada `boxes`,
  `export_stock_boxes`, dan `export_fulfillment_boxes`. Data lama bernilai 1.
  Nomor dapat berpindah Lokal/Ekspor, sehingga nomor penggunaan dalam satu
  lingkup bisa meloncat. Nomor penggunaan tidak diedit oleh petugas.
- Klaim nomor dan pembuatan box satu transaksi. Baris registry dikunci dan stok
  pemilik nomor diperiksa dengan locking read. Kegagalan penerimaan membatalkan
  kenaikan penghitung. Jangan membuat box melalui raw insert/bulk insert yang
  melewati model.
- Riwayat lokal menyediakan pilihan penggunaan pada modal riwayat, ekspor pada
  halaman Riwayat box. Keduanya menampilkan histori dalam lingkup masing-masing.
  Riwayat picking ekspor dicocokkan melalui FK fulfillment, bukan nomor barcode.
  Detail Laporan Pengiriman dan sheet Rincian Box memuat nomor penggunaan.
  Snapshot QA untuk box yang dihapus permanen tetap dibaca dari jejak penanganan.
- Redo/pemulihan penggunaan lama ditolak setelah nomor pernah dipakai untuk
  penerimaan lain, termasuk bila penerimaan berikutnya sudah dihapus. Barang
  tersebut perlu ditinjau secara fisik dan diterima dengan ID lain yang tersedia.
  Redo biasa sebelum penggunaan ulang tetap mengikuti aturan existing.
- Scan Lokal/draft Ekspor menyimpan generasi saat scan dan menolak penyimpanan
  jika nomor telah dipakai lagi. Token penerimaan lama mengacu ke catatan lama,
  termasuk yang diarsipkan, tanpa membuat penerimaan baru.
  Draft ekspor existing dibackfill dengan penghitung saat cutover, draft lokal
  lama tanpa penanda/PK yang memakai nomor terdaftar diminta scan ulang. Sesi
  opname existing tanpa metadata penggunaan tetap mengenali stok yang tidak berubah.
- Stock opname tetap membandingkan kondisi per nomor barcode. Jika box berganti
  ID internal di tengah sesi, snapshot awal dipertahankan, kondisi kini diperbarui,
  status berubah, dan verifikasi penggunaan baru wajib scan ulang. Log scan lama
  tetap menyimpan snapshot penggunaan lama.

## Batas fisik

Barcode tetap delapan digit. Scanner tidak bisa membedakan dua label fisik lama
dan baru dengan nomor yang sama. Singkirkan label lama sebelum memakai nomornya
lagi. Penghitung penggunaan memisahkan histori sistem, bukan membedakan dua
label identik saat discan. Penggunaan ulang tidak memulihkan box atau delivery lama.

## Penerapan

Migration baru: `2026_11_15_000001_allow_box_number_reuse.php`.
Tidak membuat ulang database dan tidak menulis ulang transaksi lama. Migration
menambah penanda penggunaan, mengganti unique nomor menjadi unique nomor +
penggunaan, melonggarkan unique label pada histori QA, serta menjaga unique
request Not Full yang masih pending menggunakan kolom terhitung.

Uji di salinan database dengan versi engine production yang sama, lakukan backup,
kemudian hentikan sementara penulisan inventory/worker selama deployment source
dan migration. DDL MySQL dapat mengunci tabel dan melakukan implicit commit,
sehingga deployment ini jangan dijalankan bersamaan transaksi aktif. Setelah
`php artisan migrate --force` dan pembersihan cache sesuai prosedur deployment,
periksa scan aktif (ditolak), scan bekas hapus/kirim/QA (diterima), history dan
Redo sebelum membuka transaksi kembali. `down` sengaja menolak meratakan beberapa
penggunaan menjadi satu nomor unik lama. Tidak ada migration yang dijalankan
pada database operasional atau production dalam pengerjaan fitur ini.

## Verifikasi

Hasil akhir 15 November: 697 tes backend, 7.428 assertions, 694 lulus dan tiga
kegagalan dashboard UiReviewPreviewTest sesuai baseline, tanpa error. Seluruh
147 tes frontend dan diff --check lulus. Kasus khusus mencakup data sebelum
migration, pemisahan histori input/delivery/QA, reuse lintas lingkup, Not Full,
draft stale, Redo/restore, idempotensi token, rollback klaim serta opname sebelum
dan sesudah pergantian penggunaan. Semua tes memakai SQLite in-memory. Render
template diperiksa melalui respons aplikasi, tanpa klaim verifikasi visual browser.
