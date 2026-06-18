# Panduan Fitur Pengiriman Sebagian dan Backorder

> Status: belum aktif untuk operasional. Antarmuka laporan backorder disembunyikan sampai aturan bisnis dan implementasi partial delivery diselesaikan end-to-end.

Panduan ini dibuat untuk user operasional yang memakai sistem

## Fitur Ini Untuk Apa

Fitur ini dipakai kalau order sudah siap diproses, tetapi stok di gudang belum cukup untuk mengirim semua barang sekaligus.

Dengan fitur ini, pengiriman tetap bisa jalan untuk barang yang tersedia. Sisa barang yang belum terkirim akan disimpan otomatis sebagai backorder.

## Yang Perlu Diketahui

- Pengiriman tetap harus lewat scan box.
- Kalau stok cukup, order selesai seperti biasa.
- Kalau stok tidak cukup, sistem akan kirim sebagian dulu.
- Sisa quantity yang belum terkirim akan muncul sebagai backorder.
- Backorder tetap harus diproses nanti sampai selesai.

## Alur Kerja Sederhana

1. Sales membuat order.
2. PPC menyetujui order.
3. Warehouse membuka order untuk diproses.
4. Operator scan box seperti biasa.
5. Sistem mengecek stok yang tersedia.
6. Jika stok cukup, order selesai.
7. Jika stok tidak cukup, sistem:
    - menyimpan jumlah yang berhasil dikirim,
    - menandai order sebagai pengiriman sebagian,
    - membuat backorder untuk sisa barang.

## Cara Memakai di Lapangan

Kalau Anda operator warehouse:

1. Buka order yang sudah approved.
2. Lakukan scan box satu per satu.
3. Ikuti hasil pengecekan sistem.
4. Jika semua barang tersedia, lanjutkan sampai selesai.
5. Jika stok kurang, ikuti proses partial delivery yang tersedia di sistem.
6. Pastikan scan tetap benar, karena mismatch tetap akan diblok.

## Arti Status yang Muncul

- **Full**: semua barang terkirim.
- **Partial**: sebagian barang terkirim, sisanya dibuat backorder.
- **Backorder**: order lanjutan untuk sisa barang yang belum terkirim.

## Apa yang Akan Terlihat di Sistem

- Order yang dikirim sebagian akan terlihat sebagai **Partial**.
- Backorder akan tampil terhubung dengan order asalnya.
- Riwayat delivery akan menampilkan label **Backorder** supaya mudah dibedakan.
- Di report, user bisa melihat jumlah backorder secara terpisah.

## Contoh Mudah

Misalnya ada order 100 pcs, tetapi stok yang tersedia baru 60 pcs.

Yang terjadi:

- 60 pcs dikirim dulu.
- Order asal diberi status partial.
- Sisa 40 pcs dibuat menjadi backorder.
- Saat stok sudah ada, backorder ini diproses lagi seperti order biasa.

## Yang Tidak Berubah

- Scan box tetap wajib.
- Proses approval tetap sama.
- Mismatch scan tetap harus diselesaikan dulu.
- Backorder bukan cancel order.

## Hal Penting Untuk Diingat

- Backorder adalah lanjutan dari order sebelumnya.
- Backorder bukan order baru yang berdiri sendiri.
- Jika masih ada sisa barang, sistem akan mencatatnya sampai benar-benar selesai dikirim.

## Ringkasan Singkat

Fitur ini membantu gudang tetap bisa jalan walaupun stok belum cukup untuk kirim penuh. Barang yang tersedia dikirim dulu, lalu sisanya disimpan sebagai backorder agar tidak hilang dari proses.
