# Feedback download Excel

Terakhir diverifikasi: 15 November 2026.

Download Excel tetap memakai browser native. Tidak mengubah isi workbook,
nama file, query bisnis, hak akses, status inventory, atau schema database.

## Before dan after

| Area | Before | After |
| --- | --- | --- |
| Klik Export Excel | Permintaan native berjalan tanpa status bersama pada halaman. | Tombol segera mendapat indikator dan aria-busy, disertai notifikasi Menyiapkan Excel. |
| Ukuran tombol | Ikon download biasa. | Indikator menggantikan ikon di tempat yang sama; lebar tombol tidak berubah saat menyiapkan. Teks tombol tetap. |
| Permintaan lama | Pengguna belum mendapat penjelasan bersama. | Setelah 1,8 detik: Masih menyiapkan Excel. Setelah 60 detik tanpa konfirmasi: Belum terkonfirmasi, pemantauan/spinner berhenti dan tombol pulih. Unduhan native tetap berjalan; periksa daftar unduhan sebelum memakai Unduh langsung. Tidak menyatakan gagal/sukses hanya karena waktu. |
| File siap | Status ditangani browser. | Setelah respons attachment Excel dikonfirmasi server: Excel siap, file diserahkan ke browser, periksa daftar unduhan browser. |
| Klik ganda | Dapat mengirim request berulang, termasuk bila respons pertama sangat cepat. | URL yang sama memakai satu job selama pending dan menolak aktivasi ulang selama 1 detik setelah konfirmasi siap. Klik kedua dalam rangkaian multi-click link/tombol form juga ditahan. URL berbeda dan unduhan sengaja berikutnya tetap tersedia. |
| Kegagalan / sesi berakhir | Browser dapat berpindah ke respons error/login. | Halaman asal tetap, pesan aman, Coba lagi dan Unduh langsung tersedia. Status gagal tidak diberi tanda sukses. |
| Filter Stok Ekspor | Submit GET native dengan formaction export. | Snapshot FormData GET dan formaction tetap dipakai, termasuk mode, customer leading zero, dan field berulang. |
| Keyboard / ponsel | Mengikuti kontrol existing. | Target status minimal 44 px; fokus kembali ke tombol asal saat panel ditutup, dan ke pesan saat retry. |
| Penutupan sukses | Menunggu 6 detik dan tertunda selama hover/fokus, masih menampilkan Tutup. | Status Excel siap tertutup otomatis setelah 3 detik tanpa tertahan hover/fokus. Tutup disembunyikan pada status sukses; fokus di panel dikembalikan ke tombol asal. Pesan error tetap menyediakan tindakan pemulihan. |

## Prinsip desain

- Doherty threshold: acknowledgement segera, tanpa menunggu file selesai.
- Staging the focus: satu notifikasi per permintaan dengan judul dan penjelasan.
- Secondary action: indikator ringan mendukung aksi download.
- Jakob's law: file tetap diserahkan ke sistem download browser.
- Frequency of use: tanpa confetti, wind-up, atau animasi tambahan pada data.
- Reduced motion: spinner statis dengan status teks tetap terbaca.

Spinner berputar linear hanya selama proses. Batas animasi transisi singkat
tidak dijadikan batas durasi pembuatan file. Tidak menjanjikan selesai dalam
400 ms dan tidak mengklaim file sudah tersimpan ke disk.

## Cakupan tombol

- Seluruh link laporan `data-report-export`: barang masuk, pengiriman/detail,
  records Lokal/Ekspor dan dashboard analitik.
- Tiga mode Stock View Lokal: per part, box dan pallet.
- Export form Stok Ekspor `data-stock-export`.
- Audit Trail, laporan operasional legacy dan stock opname selesai Lokal/Ekspor.
- Unduh template Excel pada form rencana delivery ekspor, mengikuti izin impor existing.

Tanpa JavaScript/Bootstrap/crypto, jalur native tetap berlaku. Klik dengan
Ctrl/Meta/Shift atau target tab lain mempertahankan perilaku browser. Link yang
sedang terlarang/nonaktif dan laporan yang filternya belum siap tidak diproses
oleh helper. Middleware otorisasi tetap menjadi pengambil keputusan server.

## Mekanisme

`public/js/excel-download.js` mendelegasikan klik dan submit agar tetap bekerja
setelah AJAX mengganti isi laporan. Helper menambahkan `_excel_token` acak
32 digit hex pada request GET melalui iframe tersembunyi. Filter diambil saat
pengguna menekan tombol, lalu tidak diubah oleh perubahan form berikutnya.

Global middleware `ExcelDownloadStatus` memeriksa respons sesudah middleware web:
GET, token valid, status sukses, disposition attachment, dan tipe/nama Excel.
Hanya respons tersebut menambahkan cookie `whfg_excel_<token>=ready`, host-only,
path `/`, SameSite Lax, berumur 120 detik dan Secure pada HTTPS. Cookie ini
readable oleh JS, berisi korelasi non-rahasia dan tidak mengubah cookie sesi.
Cookie dipasang di luar enkripsi cookie web; tes integrasi memverifikasi nilainya.

Client membaca acknowledgement setiap 250 ms, menghapusnya setelah dipakai,
menghentikan timer, dan memulihkan tombol. Setelah sukses, pelindung klik beruntun
bertahan 1 detik untuk URL yang sama; error tetap dapat dicoba ulang segera.
Respons error iframe dibaca sebagai
teks/JSON aman, bukan dimasukkan sebagai HTML. Request tidak diulang otomatis.

Cookie hanya membuktikan bahwa respons file siap diserahkan. Penulisan ke disk,
pembatalan Save As, dan kegagalan transfer setelah header tetap ditangani browser.
File tidak ditampung sebagai Blob JavaScript sehingga tidak menambah buffer
workbook penuh di halaman. Iframe sukses atau belum terkonfirmasi dipertahankan sampai pagehide agar
penghapusan dokumen tidak mengganggu unduhan native; timer sudah berhenti.

Tutup menyembunyikan status, bukan membatalkan proses pembuatan file. Job yang
masih berjalan tetap dapat dibuka kembali dari tombol yang sama. Jika konfirmasi
cookie tidak dapat dibaca selama 60 detik, pemantauan selesai dengan status
Belum terkonfirmasi, spinner berhenti dan tombol dipulihkan. Request native
tidak dibatalkan. Acknowledgement diperiksa sekali lagi pada batas waktu sebelum
menentukan status ini; sinyal terlambat tidak mengubah status yang sudah selesai.
Pengguna diarahkan memeriksa daftar unduhan sebelum memakai jalur native langsung.
Pagehide membersihkan
pemantauan, indikator dan iframe. Satu token per request memisahkan tab/job.

## File implementasi

- `app/Http/Middleware/ExcelDownloadStatus.php`, didaftarkan di `bootstrap/app.php`.
- `public/js/excel-download.js`, dimuat bersama pada layout aplikasi.
- `public/css/apple-design.css`, indikator dan notifikasi download.
- Atribut opt-in pada Stock View, audit, laporan legacy dan stock opname.
- Tombol laporan/AJAX dan form Stok Ekspor memakai atribut existing.

Tidak memerlukan migration atau build Vite untuk aset public ini.

## Verifikasi

- Enam tes ExcelDownloadStatusTest lulus, 34 assertions: cookie tidak terenkripsi,
  isi/header respons tetap, native tanpa token, token invalid/array, auth, error,
  HTML/PDF, HTTPS Secure, dan file non-GET.
- 37 tes regresi laporan/ekspor/stock opname lulus, 564 assertions. Database
  testing SQLite in-memory; tidak memakai database operasional.
- Seluruh 162 tes frontend lulus, termasuk tujuh tes tracker/filter/pelindung klik download,
  report loader, izin action dan notifikasi. Sintaks JS/PHP serta diff --check lulus.
- Browser desktop: native XLSX contoh benar-benar diterima. File ZIP/worksheet
  diperiksa valid dan hanya berisi data fiktif. Klik ganda menghasilkan satu
  iframe/job; cookie memulihkan aria-busy. Lebar tombol sebelum/saat busy identik.
- Browser memeriksa 403, redirect sesi berakhir, retry keyboard, form mode pallet
  dengan customer `001`, halaman asal tetap, dan status siap berdasarkan server.
- Viewport 390 px: notifikasi berada dalam layar, tombol 44 px, tidak ada overflow
  horizontal atau error console. Reduced motion diperiksa pada CSS, tanpa
  mengganti preferensi OS. Belum menguji scanner/perangkat ponsel nyata.
- Server pratinjau hanya melayani file uji fiktif; bukan deployment aplikasi.
- Revisi auto-close: empat tes tracker/filter lulus. Browser memverifikasi
  tombol Tutup tersembunyi saat siap, popup terhapus otomatis setelah 3 detik
  meskipun pesan difokuskan/di-hover, dan fokus kembali ke tombol download.
- Revisi lanjutan: tes meliputi batas 60 detik tanpa ack, ack tepat pada batas,
  pengabaian sinyal terlambat dan pelindung 1 detik. Browser menguji klik ulang
  setelah sukses melalui link lain dengan URL sama dan tombol form: masing-masing
  hanya menambah satu iframe. Simulasi cookie tidak terbaca mempercepat batas
  60 detik menjadi 3 detik hanya di fixture; status belum terkonfirmasi memulihkan
  tombol, menyembunyikan spinner/retry dan mempertahankan iframe native.
  Viewport 390 px tidak overflow; tindakan tetap 44 px dan console tanpa error.
