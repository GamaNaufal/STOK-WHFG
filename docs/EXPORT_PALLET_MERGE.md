# Merge Pallet Ekspor

Diverifikasi 4 November 2026. Buka **Warehouse → Merge Pallet**, lalu pilih
**Ekspor** jika akun memiliki akses ke kedua lingkup.
Operator Ekspor, Admin Warehouse, dan Admin Utama dapat menjalankannya sesuai
Hak Akses. Izin `export_merge_pallet` terpisah dari merge lokal.

## Cara menggunakan

1. Cari berdasarkan lokasi, pallet, part, customer, atau kode delivery. Pilih
   minimal dua pallet fisik yang berisi stok. Seluruh box sumber ikut digabung.
2. Pilih lokasi salah satu sumber atau lokasi ekspor kosong. Lokasi yang ditempati
   atau direservasi pihak lain tidak dapat dipilih.
3. Bila ada stok cadangan, pilih **Penuhi kebutuhan lebih dahulu** atau
   **Tambahkan seluruhnya sebagai kebutuhan baru**. Pilihan pertama hanya mengisi
   kebutuhan yang belum direservasi pada pallet terpilih; kelebihannya menambah
   delivery. Reservasi yang sudah ada tidak digantikan.
4. Klik **Tinjau merge**. Periksa tujuan, daftar ID Box, perubahan per kapasitas
   box, dan peringatan. Contoh: sisa 5 box belum direservasi, cadangan masuk 8 box:
   pilihan pertama menambah 3 box; pilihan kedua menambah 8 box.
5. Pindahkan box sesuai rincian, lalu centang konfirmasi fisik. Jika muncul,
   setujui pembatalan status siap dan/atau penambahan kebutuhan delivery.
6. Konfirmasi merge. Cetak ulang panduan rencana dengan revisi terbaru. Selesaikan
   scan picking/penempatan yang masih wajib dan konfirmasikan siap kembali.

## Aturan dan kasus uji manual

| Kasus | Hasil yang diharapkan |
| --- | --- |
| Dua cadangan bebas | Satu pallet cadangan baru; ID Box, PCS dan tanggal FIFO tetap. Lokasi lama selain tujuan menjadi kosong. |
| P1 kebutuhan 20, ditempatkan 10; P2 kebutuhan 16, ditempatkan 6, dalam RD yang sama | Hasil 36 kebutuhan, 16 ditempatkan, 20 belum ditempatkan. Nomor pallet terkecil dipertahankan. |
| Dua pallet permintaan dari RD berbeda, customer sama sekalipun | Ditolak. Tidak ada perubahan stok atau delivery. |
| Pallet permintaan + cadangan yang direservasi RD lain | Ditolak. Reservasi RD lain tidak diambil alih. |
| Cadangan + cadangan yang menyimpan reservasi untuk RD berbeda | Ditolak juga. Larangan lintas RD mencakup reservasi stok cadangan. |
| Cadangan + cadangan dengan reservasi dalam RD yang sama, belum mulai picking | Fisik sumber digabung; reservasi tetap menuju pallet tujuan semula. |
| Cadangan cocok dengan sisa kebutuhan yang sudah direservasi box lain | Reservasi lama tetap; cadangan menjadi tambahan kebutuhan dengan peringatan. |
| Cadangan lebih baru dibanding box bebas dengan part dan PCS sama di luar sumber | Ditolak; sistem menunjukkan ID Box dan lokasi FIFO yang harus diprioritaskan. |
| Pallet sudah siap | Perlu persetujuan pembatalan status siap. Hanya pallet terpilih dibuka; pallet lain tetap siap. |
| Pengambilan telah diverifikasi atau issue scan belum selesai | Ditolak sampai penempatan/pengembalian selesai. |
| Reservasi dari sumber campuran belum mulai diambil | Scan tetap wajib. Jika ikut pallet tujuan merge, dicatat menunggu verifikasi; tidak dihitung terpenuhi sebelum scan dan konfirmasi penempatan. |
| Reservasi masih di sumber lain yang tidak dipilih | Tetap di sumber; tujuan/antrean mengikuti pallet hasil merge. |
| Tinjauan FIFO belum dikonfirmasi saat revisi merge | Tinjauan baru dapat dibuka dari perencanaan dan dikonfirmasi dengan revisi terbaru. |
| Beberapa Not Full dengan part sama | Kapasitas fisik masing-masing dipertahankan. 80 + 20 + 20 PCS tetap 3 box. Request Not Full dikelompokkan per kapasitas, penyiapan/approval satu box per proses. |
| Data Not Full sudah disiapkan untuk target/sumber yang berubah | Data disiapkan ulang dan tetap melalui approval. Persetujuan sebelumnya tidak dipakai untuk membuat box tambahan. |
| Mengurangi kebutuhan setelah merge | Box terbaru dikembalikan terlebih dahulu; komposisi PCS aktual dan jumlah box delivery tetap sesuai. |
| Total kebutuhan hasil 36 / 72 box | Peringatan kuning / merah; tetap dapat dilanjutkan. |
| Stok/lokasi/rencana berubah sesudah pratinjau | Konfirmasi ditolak; tinjau ulang data. |
| Klik konfirmasi dua kali | Hanya satu merge, satu set perpindahan, dan satu audit. |

Merge memakai mutex inventory ekspor dan satu transaksi database. Tidak mencatat
penerimaan baru atau pengiriman. Pallet fisik lama dipertahankan sebagai histori
perpindahan; rincian rencana sebelum/sesudah tersimpan di audit dan catatan merge.
Rencana tetap final, jumlah PCS/box diperbarui, serta revisi bertambah. Selesaikan
permintaan tetap hanya tersedia setelah semua pallet memenuhi syarat dan siap.

## Deployment

Jalankan migration `2026_11_03_000002_support_export_pallet_merges.php` melalui
`php artisan migrate --force` setelah backup dan pengecekan environment deployment.
Migration tidak menggabungkan atau memindahkan stok existing. Kolom komposisi
nullable mempertahankan perhitungan lama sampai transaksi revisi/merge memerlukannya.
Permission baru diberikan pada konfigurasi role existing yang masih mengizinkan
alur dasarnya (penerimaan ekspor / merge warehouse); konfigurasi yang menonaktifkan
alur dasar tidak dibuka otomatis. Pencabutan melalui Hak Akses tetap berlaku.
