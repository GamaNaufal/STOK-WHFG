# Kode dokumen dan label Master Part

Diverifikasi 12 November 2026. Implementasi ini menambahkan pemetaan tanpa
mengganti identitas master, stok, atau transaksi existing.

## Penggunaan

1. Buka Master Nomor Part Bersama, tambah atau edit part.
2. Nomor part label / scan adalah nomor yang dipindai sehari-hari. Untuk part
   existing yang sudah dipakai, pertahankan nomor ini.
3. Jika nomor pada Excel delivery sama, biarkan pilihan **Sama dengan nomor
   part label / scan** tercentang. Jika berbeda, hilangkan centang dan isi kode
   dokumen. Beberapa kode diisi satu per baris, maksimal 100 kode.
4. Isi PCS standar per box, lalu simpan sekali. Master dan pemetaan disimpan
   dalam transaksi yang sama, termasuk audit.
5. Saat membuat rencana delivery, masukkan kode dari Excel atau kode label.
   Preview menunjukkan label / scan tujuan dan kapasitas. Periksa sebelum simpan.

Contoh: label `P71005079`, dokumen `7100-5079`, kapasitas 1.000 PCS. Penerimaan
tetap scan `P71005079`; delivery berisi `7100-5079` mengacu ke ID master yang
sama. Kode input disimpan pada item delivery dan ditampilkan pada detail/cetak.

Untuk produk baru, isi kode label, kode dokumen dan PCS dalam satu form yang
sama. Untuk kode dokumen baru pada produk existing, tambahkan baris pada form
edit master tersebut. Hak akses pengelolaan master tetap mengikuti izin parts.

## Aturan pemetaan

- Kode dicocokkan persis, termasuk kapitalisasi, nol awal, tanda hubung dan
  suffix. Spasi awal/akhir input form dipangkas seperti sebelumnya.
- `/` merupakan bagian kode literal. Aplikasi tidak memecah kode ini otomatis.
  Bila `A` dan `B` sudah dipastikan mengacu ke stok sama, daftarkan sebagai dua
  baris; bukan satu baris `A/B`.
- Kode dokumen tidak boleh mengacu ke dua master, atau sama dengan kode label
  master lain. Jika kedua kode sudah terdaftar terpisah, periksa stok/PCS dan
  transaksi terkait dahulu. Fitur ini tidak menggabungkan kedua master tersebut.
- Dua kode untuk satu master pada delivery yang sama ditolak. Operator harus
  memeriksa lalu menggabungkan PCS menjadi satu baris kebutuhan.
- Kode tidak dikenal/ambigu ditandai pada preview dan ditolak server. Impor
  Excel tidak membuat master, menyimpulkan alias, atau memperbarui PCS otomatis.
- Perubahan pemetaan berlaku pada input baru. Delivery existing menyimpan kode
  input, kode operasional, ID master dan kapasitas historis. Edit kuantitas pada
  baris berkode dokumen sama tetap memakai produk historis. Untuk koreksi produk
  yang memang salah, gunakan revisi delivery sesuai status, lalu periksa alokasi.
- Jika pemetaan berubah setelah preview baru dibuka, penyimpanan dengan tujuan
  berbeda ditolak dan operator diminta memuat ulang. Pengulangan request yang
  sebelumnya berhasil tetap mengembalikan hasil semula tanpa transaksi ganda.
- Penerimaan dan picking tetap memakai identitas operasional existing. Alias
  dokumen tidak mengganti label fisik atau mengubah stok yang sudah diterima.

## Struktur tambahan

- `part_document_numbers`: FK `part_setting_id`, kode dokumen unik, timestamps.
  MySQL memakai `utf8mb4_bin` untuk kode dokumen agar pembacaan persis.
- `export_delivery_plan_items.document_part_number`: nullable, snapshot kode
  input. Nilai null pada item lama berarti gunakan `part_number` existing.
- Tidak ada backfill alias dari kedua workbook pengguna. Entri yang sudah
  diverifikasi dapat dilengkapi melalui form edit; data ambigu tetap menunggu
  rekonsiliasi bisnis.

## Deployment

Migration baru: `2026_11_12_000001_add_part_document_numbers.php`.
Migration belum dijalankan pada database operasional lokal/production.

1. Periksa `php artisan migrate:status`, versi aplikasi, dan koneksi database
   tujuan. Tinjau migration lain yang pending secara terpisah.
2. Ambil backup database lengkap dan pastikan prosedur pemulihannya tersedia.
   Uji deployment pada salinan production, termasuk rencana aktif dan scan.
3. Gunakan jeda operasional saat memasang schema dan kode yang membutuhkannya.
   MySQL DDL dapat melakukan implicit commit; jangan menganggap migration schema
   bisa di-rollback sebagai satu transaksi database.
4. Terapkan migration baru pada target yang sudah diverifikasi, misalnya:

   ```sh
   php artisan migrate --path=database/migrations/2026_11_12_000001_add_part_document_numbers.php --force
   ```

5. Build aset dengan `npm run build` dari path project sebenarnya, sertakan
   `public/build` serta file CSS/JS di public, dan perbarui cache sesuai deployment
   existing. Alias drive PHP bukan lokasi untuk menjalankan Vite build.
6. Periksa jumlah master/box, jumlah PCS, FIFO, reservasi, delivery aktif, hak
   akses, form master, scan label, impor kode dokumen dan cetak sebelum membuka
   akses operasional kembali. Masukkan pemetaan terverifikasi secara bertahap.

Jika perlu kembali ke kode aplikasi sebelumnya, pertahankan tabel/kolom tambahan
agar pemetaan dan snapshot baru tidak hilang. Jangan menjalankan migration down
setelah fitur dipakai tanpa rencana preservasi datanya. Jangan memulihkan backup
lama di atas transaksi baru yang sudah masuk tanpa rekonsiliasi.

## Verifikasi

- 49 tes backend terkait lulus (437 assertions), termasuk transaksi master dan
  alias, benturan kode, slash literal, snapshot/replay, pemetaan berubah setelah
  preview, penerimaan label yang memenuhi delivery dokumen, dan cetak.
- Uji migration pada SQLite in-memory berisi master, box, dan delivery membuktikan
  data existing tetap sama; alias awal kosong dan snapshot baru nullable.
- 134 tes frontend serta Vite build lulus.
- Regresi lengkap menjalankan 635 tes pada tahap sebelum tiga tes tambahan:
  631 lulus dan empat gagal pada aturan route dashboard/details serta tiga
  tampilan dashboard. Kegagalan juga muncul saat suite dashboard/akses dijalankan
  terpisah; implementasi ini tidak mengubah file area tersebut.
- Empat halaman HTML berhasil dirender dari SQLite in-memory. Pemeriksaan visual
  desktop/ponsel belum selesai karena browser IAB tidak dapat menjangkau localhost
  dan akses browser Brave tidak diizinkan. Belum diuji dengan scanner fisik atau
  database MySQL production.
