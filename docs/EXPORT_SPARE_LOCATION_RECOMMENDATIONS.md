# Rekomendasi lokasi part cadangan Ekspor

Terverifikasi 18 November 2026 pada checkout pengguna.

## Alur operator

1. Part A masuk tanpa alokasi rencana delivery. Jika belum ada pallet cadangan
   berisi part A, operator memilih lokasi, misalnya A1, dan maksimal box pallet
   baru melalui pengaturan existing.
2. Penerimaan berikutnya dengan part A merekomendasikan pallet sebelumnya di
   lokasi saat ini. Jika seluruh tambahan masih muat, tujuan tersebut WAJIB.
   Contoh isi 3 dari maksimal 5 box lalu masuk 2 box, keduanya harus ke pallet
   tersebut. Operator tidak dapat mengganti atau membagi tujuannya. Aturan ini
   menggantikan pilihan bebas pada implementasi awal 18 November 2026.
3. Contoh A1 berisi 18 dari maksimal 20 box, lalu masuk 5 box. Penjelasan
   menyebut ruang tersisa 2 box dan 3 box melebihi batas. Operator dapat memilih
   **Isi sisa 2 box di A1**, yang menyiapkan pembagian 2 box ke A1 dan 3 box
   menunggu tujuan lain, atau **Pallet baru untuk seluruh 5 box**.
4. Tujuan baru dapat dipilih dari lokasi kosong dan diberi maksimal box. Batas
   dibagikan untuk seluruh kelompok yang menunjuk lokasi baru yang sama.
   Pembagian dan rincian ID Box tetap memakai urutan penerimaan existing.
5. Jika A1 penuh, alasan ditampilkan dan A1 tidak terpilih. Operator memilih
   tujuan lain. Setelah part A ditempatkan pada A2, penerimaan berikutnya
   mengikuti penempatan terbaru tersebut. Pemindahan pallet mengikuti lokasi
   aktualnya, tidak mengarahkan operator kembali ke lokasi historis yang kosong.

## Aturan pemilihan dan kapasitas

- Rekomendasi hanya untuk kelompok tanpa fulfillment rencana. Prioritas alokasi
  delivery serta tinjauan/picking FIFO tidak diubah.
- Kandidat berasal dari pilihan pallet existing yang tersedia, belum pensiun,
  memiliki lokasi dan masih berisi box part tersebut dalam state stored. Hanya
  pallet cadangan, tanpa export_planned_pallet_id, yang direkomendasikan.
- Jika ada beberapa kandidat, gunakan penempatan part terbaru berdasarkan
  stored_at, dengan ID box sebagai pembeda bila waktunya sama. Pallet terbaru
  yang penuh tetap dijelaskan, operator dapat memilih pallet lain secara manual.
- Box terhapus tidak menjadi kandidat atau isi fisik. Box expired yang masih
  berada pada pallet tetap menghitung ruang fisik dan dapat menjadi acuan part.
  Nomor part lain pada pallet yang sama juga menghitung kapasitas.
- Tujuan wajib lintas kelompok membagi ruang yang sama agar tidak memilih dua
  kelompok yang secara bersama melebihi batas. Saat operator mengubah pilihan,
  penjelasan menghitung pilihan seluruh kelompok pada transaksi tersebut.
- Null/0 tetap tanpa batas sesuai pengaturan existing. Peringatan jumlah 36/72
  box tidak menjadi batas keras dan tidak menggantikan maksimal box pallet.
- Draft pilihan bebas di tab dipulihkan saat refresh hanya jika masih sesuai
  aturan. Tujuan wajib menggantikan pilihan bebas dari aturan lama yang tidak
  valid. Penjelasan tujuan wajib selalu tampil, pembagian dan pallet baru tidak
  ditawarkan selama seluruh tambahan muat. Revisi daftar box yang berubah
  tetap meminta tinjauan ulang.
- Membuka halaman hanya membaca data. Tidak menyimpan default master,
  mengubah first_received_at, atau mencatat penempatan/reservasi otomatis.
- Pilihan per box tetap digunakan oleh cetak dan konfirmasi. Konfirmasi memakai
  mutex inventory, token replay, revision dan pemeriksaan kapasitas ulang dalam
  transaksi existing. Isi yang berubah setelah rekomendasi dapat ditolak oleh
  server tanpa penempatan sebagian. Tidak ada perubahan schema atau izin.

## Kode pallet dan pembuatan bersamaan

- Kode fisik memakai **PLT-EXP-001**, minimal tiga digit. Setelah 999 menjadi
  PLT-EXP-1000, tidak reset. Lokal tetap PLT-001 dengan allocator lokalnya.
- Identitas ekspor berasal dari PK auto-increment `export_stock_pallets.id`,
  yang dialokasikan database. Tidak membaca nomor terbesar dan tidak membuat
  counter dari browser. Dua transaksi tidak dapat mendapat PK/kode yang sama.
  Penerimaan, merge dan pengembalian picking tetap memakai mutex inventory dan
  model yang sama. Rollback boleh meninggalkan celah nomor.
- Format diterapkan melalui accessor dan formatter bersama, tidak mengganti ID,
  FK atau isi tabel. Pallet lama langsung tampil dalam format baru. Kode tetap
  ketika pallet dipindah, sedangkan posisi pallet 1..N dalam perencanaan tetap
  nomor urutan rencana yang terpisah.
- Pencarian pindah pallet menerima PLT-EXP-001, termasuk huruf kecil, dan alias
  label EX-000001 lama. Respons selalu membawa format baru. Pencarian stok dan
  detail pergerakan pada riwayat memakai format baru. Snapshot audit tersimpan
  tidak ditulis ulang. Tidak ada migration atau counter baru.

## Implementasi

- `ExportSparePlacementPolicy::forGroups` membaca penempatan per part dalam
  satu query agregat dan menghitung tujuan wajib atau pilihan overflow.
- `forReceipts` membaca seluruh box part yang masih menunggu dalam receipt,
  termasuk saat request hanya menyertakan satu box atau tidak mengirim
  return_batch. Urutan part mengikuti urutan penerimaan yang diberikan server.
  `assertPlacements` dipanggil sekali sebelum seluruh mutasi penempatan dalam
  konfirmasi, di bawah mutex inventory. Pilihan overflow tetap berlaku untuk
  satu konfirmasi, tidak diubah diam-diam sesudah box pertamanya ditempatkan.
- Aturan wajib berlaku pada penerimaan external yang belum ditempatkan dan
  tidak mendapat fulfillment rencana. Relokasi stok tersimpan, QA/packing dan
  hasil pecah tetap mengikuti validasi dan tujuan prosesnya masing-masing.
- `ExportStockViewService::recommendSpareLocations` memakai policy yang sama.
- `ExportReceivingTransactionController::detail` menambahkan hasil rekomendasi
  setelah katalog pilihan tersedia, termasuk penerimaan bulk dan legacy.
- Modal satu box dan panduan cetak ikut policy. Tujuan wajib otomatis tercetak,
  pilihan cetak lain yang tidak valid diberi peringatan tanpa lokasi fiktif.
- Partial `export/receiving/spare-placement` menampilkan penjelasan dan pilihan.
  `spare-recommendation.mjs` menghitung alasan dari kapasitas bersama, sedangkan
  `spare-placement.mjs` menyiapkan pembagian, memulihkan draft yang valid dan
  mengalihkan draft lama ke tujuan wajib bila seluruh tambahan muat.

## Verifikasi

- Suite penuh SQLite in-memory, 772 tes / 8.199 assertions, 765 lulus dan tujuh
  kegagalan baseline yang sudah tercatat pada OperationalReportRangeTest,
  ReportTextSafetyTest, SystemIntegrityRegressionTest seeder dan tiga
  UiReviewPreviewTest dashboard. Tidak ada error. Seluruh 362 tes terkait
  ekspor / 3.928 assertions lulus pada run yang sama.
- Pemeriksaan tambahan untuk membagi dua box yang wajib masuk satu pallet,
  bersama kasus bulk/satu box/modal/cetak/replay, lulus 1 tes / 27 assertions.
  Cakupan baru juga memeriksa seluruh 5 box ke pallet baru, subset receipt
  overflow, perubahan batas saat formulir terbuka, legacy tanpa batas,
  penomoran independen dari lokal, pencarian alias dan kode setelah pemindahan.
- Fixture merge, Not Full, FIFO dan reservasi customer yang memerlukan stok
  lama tersebar di beberapa pallet kini menyiapkan inventory existing secara
  eksplisit. Pengujian proses bisnisnya dipertahankan, penerimaan baru tidak
  diberi pengecualian untuk melewati aturan wajib.
- Seluruh 172 tes frontend lulus. Vite build, Pint tiga file baru, sintaks PHP
  terkait dan git diff --check lulus.
- Dua proses PHP dan koneksi MySQL 8.4.3 diuji pada port 33317 serta direktori
  data probe terpisah. Tiga race lulus, dua operator dengan part berbeda membuat
  kode PLT-EXP-001 dan PLT-EXP-002, dua receipt berisi masing-masing dua box
  berebut sisa dua ruang dengan tepat satu berhasil dan yang kalah tetap dua
  box received, serta dua operator yang memilih lokasi kosong sama hanya
  membentuk satu pallet. Instance ditutup normal dan data probe dibersihkan,
  laporan hasil dipertahankan. Bukan koneksi ke database operasional.
- Browser memakai HTML Laravel dari database SQLite in-memory dengan akun dan
  stok fiktif. Diperiksa tujuan wajib dengan kode baru, hilangnya pilihan bagi/
  pallet baru saat 3/5 + 2 box, pengalihan draft aturan lama dari A2 ke A1,
  penuh, pembagian 2+3, seluruh 5 box ke pallet baru dengan batasnya, refresh
  dan aktivasi keyboard. Viewport desktop 1366 px dan simulasi ponsel 390 px,
  tanpa overflow halaman, console error kosong. Konfirmasi server/cetak diuji
  melalui tes backend, bukan transaksi browser ke database operasional atau
  printer/scanner fisik.
- Tidak menjalankan migration, mengubah database operasional, atau melakukan
  deployment. Aset build lokal sudah dibuat, deployment memerlukan source,
  stylesheet terkait dan hasil build seperti biasa.

Fixture dan tangkapan layar tersimpan privat pada
`storage/app/private/spare-recommendation-*` dan `export-pallet-*`, diabaikan Git.
Hasil konkurensi ada pada `export-pallet-mysql-probe/result.json`, beserta
script `export-pallet-concurrency.php` untuk probe terisolasi.
