# Catatan contoh impor Rencana Delivery Ekspor

Dicatat 28 Oktober 2026 dari file lokal pengguna `Test 1.xlsx`.
Pemeriksaan hanya membaca workbook dan menjalankan parser preview; tidak mengubah
file sumber, membuat rencana, atau mengakses database operasional.

## Deployment yang dicatat

- Pengguna membuka aplikasi production melalui Chrome; tidak perlu instalasi
  tambahan pada masing-masing PC. Input tersedia melalui form manual dan Excel.
- Build melalui `npm ci` dan `npm run build`; sertakan `public/build`.
  Git pull saja tidak menghasilkan direktori hasil build tersebut.
- Impor Excel diproses server. Pembacaan foto, dependency OCR, worker/model/WASM,
  serta aset vendor/ocr dihapus atas permintaan pengguna pada 28 Oktober 2026.
- Migration yang pending harus ditinjau sebelum production, termasuk migration
  QA/packing dengan proses cutover. Deployment production belum dilakukan.

## Struktur contoh Excel

- Satu worksheet: `YR Seminggu 4`, area berformat A1:P468.
- A3: teks hari dan tanggal. A4: `Customer : ...`.
- B5:B6: `Part No`, dengan nilai part pada B7:B284.
- C5:C6: `QTY(PCS)`.
- D6: `Qty2(Pcs)`, nilainya menggunakan formula C dikurangi E.
- E6: `RABU`, tidak menjadi input rencana delivery pada tahap ini.
- G6: `Qty/Bx`; H6: `Bx`. Kolom persiapan/KET dan total pallet tidak menjadi
  input rencana pada tahap ini. Kapasitas sistem tetap memakai master ekspor.
- Ada 278 baris nomor part: 59 mempunyai jumlah positif dan 219 tidak mempunyai
  jumlah di C. Nilai positif C dan hasil formula D sama pada file contoh ini.
- Berdasarkan kapasitas di file, jumlah H7:H284 adalah 662 box. Footer G285
  bernilai 644 karena rumus hanya menjumlahkan H7:H249; baris 250:284 tidak masuk.
  Jangan memakai footer sebagai sumber total sistem.

## Keputusan dan implementasi yang dikonfirmasi

Pengguna mengonfirmasi jumlah rencana mengikuti C (`QTY(PCS)`), bukan D
(`Qty2(Pcs)`). Baris dengan jumlah kosong atau nol diabaikan, disertai peringatan
berapa baris kosong dan berapa bernilai nol. Nilai negatif, pecahan atau tidak
terbaca tetap ditampilkan untuk dikoreksi, tidak diam-diam diabaikan.

Kode di header dokumen, contoh `RDYR0925` pada M2, menjadi kode rencana delivery.
Kode dibaca dari header RD atau label Kode Rencana Delivery/Plan Code, disimpan
sebagai `plan_code`, bisa dikoreksi, muncul pada daftar/detail, serta bisa dicari.
Migration 000005 menambahkan kolom nullable agar rencana lama tetap valid. Tidak
ada aturan keunikan kode tambahan yang diminta; relasi memakai ID internal.

Parser mempertahankan nol awal dan suffix pada kode part. Preview tidak membuat
rencana, dan hasil tetap wajib diperiksa sebelum simpan. Impor dengan semua
jumlah kosong/nol ditolak sambil menjelaskan jumlah baris yang diabaikan.

Verifikasi 28 Oktober 2026: file asli menghasilkan 59 baris, 219 jumlah kosong,
0 jumlah nol, kode RDYR0925 dan 1.639.700 PCS. Dengan kapasitas master preview
yang mengikuti G pada file, total sistem 662 box. Browser membuktikan peringatan,
pembatalan hasil impor, penyimpanan kode dan pencarian kode. Seluruh regresi
309 tes backend (2.534 assertions), 52 tes frontend, dan build lulus. Database
test/preview terpisah; database operasional serta file sumber tidak diubah.
