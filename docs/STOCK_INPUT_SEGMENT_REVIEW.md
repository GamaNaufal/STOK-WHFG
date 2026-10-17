# Input Stok — perbaikan tampilan

18 Oktober 2026. Acuan utama apple-design dan DESIGN_PREFERENCES.md.

| Sebelum | Sesudah |
| --- | --- |
| Konten hanya memakai dua pertiga ruang desktop | Konten penuh dengan lebar maksimum 70rem |
| Tombol scan, simpan, cetak, dan reset sama dominan | Scan/Simpan memakai teal; aksi sekunder memakai permukaan netral |
| Box memakai baris Bootstrap yang menumpuk pada ponsel | Grid empat kolom desktop dan dua kolom ponsel; identitas panjang membungkus |
| CSS halaman menargetkan semua card dan tombol | CSS dibatasi pada stock-input-page |
| Pesan dan beberapa kontrol tanpa nama aksesibel | Status/alert, petunjuk input terhubung, nama tombol lokasi dan tutup error |

ID elemen, autofocus, disabled state, handler JavaScript, endpoint, payload,
session, validasi scan, pemilihan pallet/lokasi, penyimpanan, dan cetak dipertahankan.
Tidak ada perubahan proses bisnis atau hak akses.

## Verifikasi

- 35 tes lulus, 400 assertions: filter StockInput, UiReviewPreviewTest,
  FeatureAccessManagementTest.
- Render sementara dengan SQLite memory lulus; preview browser memakai HTML Blade
  dan respons pallet simulasi dengan dua box termasuk nomor part panjang.
- Desktop dan viewport 390px: autofocus ID Box terlihat, Part awal disabled,
  grid box tidak overflow, tombol aksi tersusun vertikal pada ponsel.
- Tidak menjalankan scan, simpan, reset, atau cetak terhadap data operasional.
  Scanner fisik, pencarian lokasi live, dan sesi panjang belum diuji di browser.
- git diff --check bersih. Server dan berkas preview sementara dibersihkan.

## Audit interaksi dan error — 18 Oktober 2026

Audit lanjutan setelah pengguna menunjukkan bahwa pemeriksaan sebelumnya belum
mencakup kondisi invalid, error, dan klik aksi.

Temuan dan perbaikan:
- Error Simpan tanpa lokasi berada di atas viewport (terukur sekitar -641px).
  Pesan aksi kini berada sebelum tombol, menerima fokus, dan tetap terlihat.
- Error ID Box dipindahkan dekat field; input invalid diberi aria-invalid.
  Input kosong ID Box/No Part mendapat petunjuk, dan error scan dapat muncul lagi
  setelah ditutup. Penutupan error umum tidak lagi membuang elemen DOM.
- Scan yang sedang diproses menolak pengiriman ganda; tombol pulih saat request
  selesai. Enter pada input tidak diteruskan ke handler dokumen yang redundan.
- Pencarian pallet/lokasi yang gagal memberi pesan pemulihan dalam dropdown.
- Reset/Batal memeriksa respons server dan menampilkan error tanpa reload saat
  gagal. Konfirmasi menjelaskan bahwa stok tersimpan tetap ada.
- Simpan memeriksa validitas field tanggal, menangani respons non-JSON, dan
  menampilkan kegagalan dekat tombol. Data input dipertahankan setelah gagal.

Pemeriksaan browser menggunakan HTML Blade asli, JavaScript aplikasi, dan router
fixture lokal terpisah. Respons server disimulasikan; tidak menyentuh stok nyata.

| Skenario | Hasil |
| --- | --- |
| ID kosong, huruf, kurang dari 8 digit | Pesan terlihat; fokus kembali ke ID Box |
| ID duplikat (422) | Pesan server tampil; input dapat dicoba lagi |
| Scan ID valid, part invalid (422), lalu part valid | Disabled state berpindah sesuai tahap; error dapat dipulihkan |
| No Part kosong | Petunjuk tampil tanpa request |
| Simpan tanpa lokasi | Error di dekat aksi dan mendapat fokus |
| Simpan dengan respons lambat | Label Menyimpan dan tombol disabled |
| Simpan gagal (500) | Error terlihat, tombol pulih, daftar box tetap ada |
| Simpan dengan respons HTML/non-JSON | Pesan respons server tidak dapat dibaca |
| Konflik lokasi | Pesan pemulihan dan pilihan lokasi dimuat ulang |
| Tanggal 2099-01-01 | Ditolak sebelum request; field invalid |
| Simpan berhasil | Kembali ke halaman input; sesi fixture kosong |
| Lookup lokasi/pallet gagal | Pesan error dalam dropdown |
| Pilih pallet ditolak server | Pesan terlihat; pencarian kembali aktif |
| Batal dalam dialog Mulai Palet Baru | Input dipertahankan |
| Reset dan pembatalan gagal | Error terlihat; halaman tidak reload |
| Reset berhasil | Kembali siap scan dengan sesi fixture kosong |
| Penyiapan cetak gagal | Pesan terlihat di area aksi pada 390px |
| Tutup error, masukkan input invalid lagi | Pesan muncul kembali |

Validasi akhir: node --check lulus; 35 tes Laravel lulus (400 assertions).
Batas pemeriksaan: scanner/printer fisik, dialog cetak berhasil, putus jaringan
sesungguhnya, respons pencarian berlomba, dan seluruh kombinasi sesi belum diuji.
Router/berkas/sesi PHP fixture dibersihkan setelah pemeriksaan.
