# Nomor part dokumen pada Stock Opname

Implementasi dan verifikasi 18 November 2026. Berlaku untuk Lokal dan Ekspor.
Perubahan ada di checkout pengguna, belum dideploy ke production.

## Pilihan pengguna

- Tampilan rincian, hasil scan dan Excel mendahulukan nomor part dokumen dari
  Master Part. Seluruh kode dokumen milik satu part ditampilkan dalam satu kolom,
  dipisahkan dengan ` / `. Nomor label/scan tetap tersedia untuk penelusuran.
- Satu kode dokumen hanya menunjuk satu part sesuai validasi master existing.
  Satu part boleh memiliki beberapa kode dokumen. Kode tersebut tidak membuat
  box, part atau kuantitas tambahan dalam rekap.
- Hasil opname lama yang belum memiliki nomor dokumen menampilkan pemetaan
  Master Part saat ini, dengan keterangan yang membedakannya dari catatan asli.

## Catatan baru dan hasil lama

`StockOpnamePartNumbers` membaca master bersama berdasarkan nomor label/scan.
Pembacaan dipersiapkan per kelompok, termasuk untuk log dan hasil sesi lama,
sehingga tidak melakukan satu query master untuk setiap box.

Snapshot baru menyimpan `document_part_numbers` dan `document_part_mapping`
di kolom JSON existing. Kondisi awal, setiap scan dan kondisi akhir tetap
merupakan catatan tersendiri. Setelah sesi selesai, pemetaan yang sudah tercatat
tidak mengikuti pengubahan atau penghapusan alias pada master.

Jika snapshot belum memiliki pemetaan, nomor dokumen hanya ditambahkan untuk
presentasi. Tidak menulis ulang session, item atau log scan historis. Sumbernya
diberi keterangan `Pemetaan Master Part saat ini`, pada layar dan kolom acuan
Excel. Waktu pembacaan master tersedia di Ringkasan. Pemetaan terkini tersebut
dapat berubah pada pembacaan berikutnya jika master diperbarui.

Master tanpa alias berarti nomor dokumen sama dengan nomor label/scan. Jika
master belum tersedia, nomor label tetap ditampilkan dengan keterangan bahwa
pemetaan belum tersedia. Ini bukan tebakan kode dokumen. Ketika pemetaan untuk
part tersebut tersedia, pembacaan menampilkan master terkini beserta sumbernya.

`changeKind` mengabaikan metadata pemetaan dokumen. Penambahan kolom JSON atau
perubahan alias saja tidak membuat box masuk kategori perubahan stok, tidak
menghapus scan yang sudah tercatat, dan tidak mengganti kondisi awal. Penggunaan
ulang ID Box tetap mengikuti pemeriksaan PK/penggunaan existing.

## Isi Excel

Tiga tab existing tetap berada di urutan pertama. Tab tambahan menyediakan
kelompok pemeriksaan yang bisa dibuka langsung tanpa memfilter daftar utama.

| Tab | Isi |
| --- | --- |
| Ringkasan | Sesi, petugas, waktu, total pemeriksaan dan cara membaca laporan |
| Semua Scan | Seluruh percobaan OK, NG dan duplikat, alasan serta kondisi saat scan |
| Rekonsiliasi Box | Seluruh box, hasil pemeriksaan dan kondisi awal serta akhir |
| Rekap per Part | Box dan PCS awal/akhir, sesuai, belum terscan, masuk, keluar, serta box berubah |
| Scan OK | Percobaan scan yang diterima sebagai scan pertama |
| Scan Error NG | Scan ditolak, termasuk input salah, beda lingkup dan box tidak tersedia, dengan alasan asli |
| Scan Duplikat | Percobaan berulang, termasuk waktu dan operatornya |
| Belum Terscan | Box yang masih ada di stok akhir dan belum terscan |
| Box Sesuai | Box yang masih ada di stok akhir dan sudah terscan |
| Perubahan Data | Box yang posisi atau datanya berubah, dengan kondisi awal serta akhir |
| Box Masuk | Box tanpa kondisi awal, termasuk yang kemudian keluar selama sesi |
| Box Keluar dan ID Diganti | Box yang sudah tidak ada di stok akhir |

Rekap per Part dikelompokkan berdasarkan nomor label/scan. Kondisi awal dan
akhir masuk ke kelompok part masing masing bila penggunaan box berganti part.
Baris TOTAL menjumlahkan setiap ukuran yang sama. Box masuk lalu keluar ada di
kedua tab terkait. Total percobaan scan berbeda dari jumlah box unik. Ringkasan
menjelaskan agar pengguna tidak menjumlahkan seluruh tab yang saling beririsan.

Kolom nomor part utama pada Semua Scan dan Rekonsiliasi Box sekarang memuat
nomor dokumen. Nomor label/scan, acuan pemetaan dan penggunaan ID Box ditambahkan
sebagai kolom tersendiri. Kolom jumlah PCS existing tetap numerik, ID/kode tetap
teks, tanggal tetap nilai tanggal Excel. Input menyerupai rumus tetap berupa
teks. Header dibekukan, filter tersedia, dan keterangan dapat membungkus.

Unduhan mencakup seluruh sesi, tidak mengikuti pencarian, tab atau pagination
layar. Izin baca/unduh, pembatasan lingkup dan syarat sesi selesai tetap berlaku.

## Verifikasi

- `StockOpnameTest`, `BoxNumberReuseTest` dan `PartDocumentNumberTest`: 40 tes,
  570 assertions lulus memakai SQLite in-memory. Lima tes opname baru mencakup
  Lokal/Ekspor, beberapa alias, pemetaan historis tanpa write, arsip, perubahan
  master tanpa perubahan stok palsu, master belum tersedia, literal rumus,
  penerimaan yang kemudian keluar, sesi aktif lama dan pembacaan 501 baris.
- Seluruh 169 tes frontend dan build Vite lulus. Pemeriksaan sintaks PHP dan
  `git diff --check` lulus.
- Browser Laravel dengan database SQLite/akun fiktif memeriksa rincian Lokal
  dan Ekspor, catatan lama, feedback nomor dokumen, fokus input setelah scan,
  unduhan kedua lingkup, desktop dan simulasi 390 px. Tidak ada error JavaScript
  atau overflow pada halaman. Ini bukan pengujian scanner fisik/ponsel nyata.
- Audio hanya dinonaktifkan pada konteks browser pengujian untuk menghindari
  penangkapan MP3 oleh IDM. Source pemutar suara aplikasi tidak diubah.
- Dua workbook hasil download dibaca dengan OpenPyXL: 12 tab, teks ID berawalan
  nol, kuantitas dan total sesuai, tidak ada sel formula atau error. Dua belas
  tab workbook contoh Lokal dirender. Excel desktop juga membuka contoh secara
  read-only dan menampilkan `00011001`, 12 tab, tanggal yang benar dan tinggi
  baris keterangan yang menyesuaikan teks.

Tidak ada migration baru, perubahan stok operasional, backfill histori,
pengubahan hak akses atau perintah deployment. Aktivasi production mengikuti
deployment kode/aset biasa dan master dokumen yang sudah dipakai aplikasi.
