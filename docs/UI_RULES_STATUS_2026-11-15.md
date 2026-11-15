# Status penerapan rules UI/UX

Terakhir diverifikasi: 15 November 2026, termasuk penyelesaian empat prioritas sisa.

Status berikut berlaku pada area yang disebutkan, bukan seluruh aplikasi.
Ritme dashboard dan feedback tekan–lepas dipertahankan. Tidak mengubah transaksi,
validasi barcode, payload, atau hak akses server.

## Before dan after tahap lanjutan

| Area | Before | After |
| --- | --- | --- |
| Pesan sukses setelah redirect | Toast hijau, ikon kecil dan pesan satu tingkat. | Permukaan terang, aksen hijau, judul Berhasil, pesan terpisah dan centang lebih jelas. |
| Penegasan sukses akhir | Tidak dibedakan dari notifikasi sukses biasa. | Khusus opsi `emphasis: true` dan tipe success: penegasan ikon 240 ms. Centang melebar lalu memanjang sebelum menetap. Layout mengaktifkannya pada flash success server setelah proses/redirect. |
| Notifikasi scan / panggilan biasa | Fade Bootstrap, tampil 3.500 ms. | Tetap sama, tidak mendapat judul atau penegasan gerak tambahan. |
| Tombol tutup toast | Ukuran mengikuti ikon/Bootstrap. | Minimum 44 × 44 px, ikon ringkas, label Tutup notifikasi. |
| Lepas titik grafik | Sekitar 25,6 × 25,6 px pada root 16 px. | 44 × 44 px pada root 16 px, ikon tetap kecil. |
| Legenda grafik | Tinggi mengikuti teks kecil dan padding. | Minimum tinggi/lebar 44 px dengan jarak internal yang nyaman. |
| Tombol ikon dashboard | Dasar 40 px. | Dasar 44 px. |
| Pencarian Hak Akses | Sudah menerima variasi kapital/spasi, karakter lebar penuh belum cocok. | Unicode NFKC khusus pencarian: `ＳＴＯＫ　ＥＫＳＰＯＲ` cocok dengan Stok Ekspor. Pilihan dan key izin tetap persis. |
| Kelompok Hak Akses | Semua kelompok menampilkan seluruh izin sekaligus. | Kelompok pertama terbuka, lainnya ringkas; judul dan jumlah terpilih tetap terlihat. Ada Buka semua kelompok dan Ringkas kelompok. Tanpa JS, semua kelompok tetap terbuka. |
| Hasil pencarian izin | Baris dan kelompok disaring tanpa jumlah hasil atau pesan kosong. | Jumlah hasil diumumkan langsung. Kelompok yang cocok terbuka otomatis; hasil kosong memberi petunjuk mencoba kata lain/menghapus pencarian. |
| Kembali dari pencarian | Tidak ada keadaan buka/tutup kelompok yang perlu dipulihkan. | Menghapus pencarian memulihkan keadaan kelompok sebelum pencarian, tanpa mengubah pilihan. |

Pengelompokan memakai `details/summary` native, mendukung keyboard dan tidak
menambah animasi. Saat pencarian aktif, hasil dibuka otomatis dan kontrol buka/
ringkas semua nonaktif. Pilih semua dan Kosongkan tetap berlaku pada seluruh
izin, termasuk yang sedang tidak terlihat; penjelasannya tampil dekat pencarian.

Pesan dipasang dengan `textContent`. Notifikasi penutup memakai role status /
aria-live polite, tidak mengambil fokus, tetap dapat ditutup atau hilang otomatis.
Reduced motion meniadakan penegasan ikon.

## Tahap squash & stretch dan volume

| Area | Before tahap ini | After |
| --- | --- | --- |
| Centang sukses penutup | Membesar seragam, skala 90% → 112% → 100%. | Pose tekan `scale(1.08, .92)`, pose memanjang `scale(.92, 1.12)`, lalu `scale(1)`. Sumbu X/Y berbeda sehingga deformasi memberi kesan menekan dan kembali. Durasi tetap 240 ms. |
| Permukaan badge | Lingkaran hijau rata. | Rim tipis, cahaya inset sisi atas, sisi bawah lebih gelap dan bayangan luar halus memberi volume. Semua kedalaman bersifat statis. |

Tombol tekan–lepas, grafik, timer toast dan input scanner tidak diubah pada tahap
ini. Efek tetap terbatas pada keberhasilan penutup yang opt-in. Tidak menambah
wind-up atau lintasan melengkung ke input warehouse yang tidak membutuhkannya.

## Pemetaan seluruh rules

Tahap feedback pemuatan laporan menambahkan penjelasan untuk request yang lama:

| Area | Before | After |
| --- | --- | --- |
| Awal permintaan | Pesan Memperbarui laporan langsung muncul. | Dipertahankan, tanpa menunggu timer atau animasi. |
| Menunggu lama | Pesan yang sama bertahan sampai respons/gagal; retry tersedia setelah gagal. | Setelah 1.200 ms request berjalan, pesan menjelaskan bahwa hasil sebelumnya masih ditampilkan dan menyediakan Coba lagi, sambil tetap menunggu respons. |
| Memilih Coba lagi | Tersedia setelah gagal. | Juga tersedia saat menunggu lama; request browser lama dibatalkan dan versi respons lama tidak boleh mengganti hasil retry. Tidak melakukan retry otomatis. |
| Fokus keyboard saat retry | Tombol yang sedang difokuskan menjadi tersembunyi saat permintaan baru dimulai. | Fokus berpindah ke pesan status tanpa scroll. Navigasi filter biasa tidak diambil alih. |
| Indikasi hasil | Status berupa teks dengan warna umum. | Pesan menunggu lama amber, kegagalan merah; penjelasan selalu tetap berupa teks. |

Hasil sebelumnya dan proteksi unduhan/pagination yang masih menunggu filter
tetap dipertahankan. Tidak menambahkan timeout yang memutus request panjang,
angka progres palsu, animasi pemuatan baru, atau janji respons server 400 ms.

| Rule | Status dan penerapan |
| --- | --- |
| Squash & stretch | Diterapkan pada centang sukses penutup: sumbu X/Y berubah berbeda ketika menekan dan kembali. Tombol tetap memakai feedback tekan 98% yang telah disukai. |
| Anticipation wind-up | Tidak dipilih pada area saat ini: belum ada gerakan besar yang memerlukan persiapan; operasi berulang tidak diberi jeda awal. |
| Staging the focus | Notifikasi penutup memiliki urutan centang/judul/rincian. Hak Akses membuka hasil pencarian. Master part mendahulukan identitas/kapasitas; rencana delivery dan koreksi box memiliki urutan kelompok. |
| Pose to pose | Tiga pose penegasan centang diinterpolasi browser. |
| Follow through | Centang melewati ukuran akhir lalu menetap. |
| Slow in & slow out | Kurva sesuai tujuan: ease-out feedback dan kurva analitik asli. Spinner proses tetap linear. |
| Moving in arcs | Tidak dipilih pada area saat ini: tidak ada objek berpindah antarposisi atau gesture lempar pada area yang dikerjakan. |
| Secondary action | Penegasan ikon mendukung pesan sukses. Indikator download mendukung persiapan Excel, tanpa gerak pada data atau seluruh halaman. |
| Snappy timing | Feedback baru 240 ms, tekan langsung, pelepasan tombol 120 ms. Analitik tetap pengecualian sesuai evaluasi pengguna. |
| Exaggerated feedback | Tinggi centang sukses penutup mencapai 112% sebelum menetap. Tidak aktif untuk sukses scan biasa. |
| Solid drawing in 3D | Diterapkan pada badge sukses melalui rim, cahaya sisi atas dan bayangan sisi bawah/luar yang memberi volume pada permukaan. Tidak mengklaim seluruh aplikasi memakai transformasi 3D. |
| Overall appeal | Diterapkan pada komponen yang diperiksa: hierarki, warna status/validasi saat fokus, wrapping, radius, jarak footer, target dan gerak konsisten. Kelompok form memakai fondasi bersama. Kualitas rasa tetap memerlukan penilaian pengguna. |
| Fitts's law | Target kontrol grafik, tombol tutup bersama serta aksi tabel/modal diperbesar. |
| Bigger hit areas | Target tutup, tabel dan modal minimal 44 px pada kedua sumbu; ukuran ikon tetap ringkas, tanpa area klik bertumpuk. |
| Hick's law | Kelompok Hak Akses dan disclosure pemetaan master part membatasi pilihan yang terlihat sekaligus. Pemetaan berbeda/error dibuka; seluruh pilihan dan nilai tetap tersedia. |
| Miller's law | Kelompok pekerjaan pada Hak Akses, dua kelompok master part/koreksi box dan tiga bagian rencana delivery. Tidak memaksakan angka tujuh pada kategori bisnis. |
| Doherty threshold | Feedback laporan dan download langsung saat request dimulai. Laporan lama memberi penjelasan/retry; Excel memberi status persiapan dan konfirmasi server. Respons server di bawah 400 ms belum diukur atau dijanjikan. |
| Postel's law | Normalisasi bentuk input pencarian Hak Akses. Tidak melonggarkan validasi identitas stok. |
| Jakob's law | Pola Bootstrap/SweetAlert, tombol tutup, keyboard dan notifikasi dipertahankan. Kelompok izin memakai disclosure details/summary native. |
| Frequency of use | Penegasan harus opt-in; panggilan scan/sukses biasa tetap ringan. Scanner dan navigasi keyboard tidak mendapat gerak baru. |

## Download Excel

Tahap download menerapkan indikator pada tombol berukuran tetap, notifikasi
persiapan, pencegahan klik ganda, acknowledgement respons attachment server,
dan jalur error/retry/native. File tetap diunduh browser tanpa buffer Blob JS.
Status Excel siap berarti diserahkan ke browser, bukan sudah tersimpan ke disk.

Pelindung klik beruntun tetap aktif sampai 1 detik setelah konfirmasi siap,
termasuk respons sangat cepat. Bila ack tidak terbaca selama 60 detik, spinner
dan pemantauan berhenti, tombol pulih dan status Belum terkonfirmasi mengarahkan
pengguna memeriksa daftar unduhan. Request native tetap berjalan. Seluruh 162
tes frontend lulus; browser menguji duplikasi setelah ready pada link/form serta
status tanpa ack (batas dipercepat hanya di fixture), termasuk viewport 390 px.
Seluruh frontend, enam tes middleware baru dan 37 tes regresi ekspor lulus.
Rincian before/after, cakupan dan batas verifikasi: `docs/EXCEL_DOWNLOAD_FEEDBACK.md`.

## Penyelesaian sisa pekerjaan

Empat prioritas dari pembahasan terakhir telah selesai: pemerataan feedback
download termasuk template, konsistensi form/modal, target klik kecil, dan
pengelompokan form. Wind-up/arcs berstatus tidak relevan pada interaksi yang
dikerjakan, sesuai frekuensi operasi dan tidak adanya gesture perpindahan objek.
Catatan before/after serta verifikasi 70 tes backend / 1.301 assertions, 162 tes
frontend dan browser desktop/390 px: `docs/UI_RULES_COMPLETION_2026-11-15.md`.

## Implementasi dan verifikasi

- `public/js/toast.js`: opsi emphasis, markup penutup, teks aman, semantik.
  Pemanggilan dua argumen existing tetap didukung.
- `resources/views/shared/layouts/app.blade.php`: opsi pada flash success server,
  tanpa popup baru atau penundaan redirect.
- `public/css/apple-design.css`: tampilan, ikon, target tutup dan reduced motion.
- `public/css/operational-dashboard.css`: ukuran kontrol, durasi analitik tetap.
- `public/js/pages/feature-access.js`: normalisasi pencarian, buka/tutup kelompok,
  status hasil dan pemulihan setelah pencarian. `resources/views/admin/access/index.blade.php`
  serta `public/css/pages/access-management.css` menyediakan disclosure dan kontrolnya.
- 24 tes frontend terkait lulus, termasuk dependency/revoke izin, pencarian
  lebar penuh, teks notifikasi berbahaya, sukses biasa/penutup, timer dan cleanup,
  analytics, report browser, pemulihan kelompok, hasil kosong, pilihan tersembunyi,
  feedback lambat dan pembatalan respons/timer lama saat retry.
  Sintaks JavaScript dan diff --check lulus.
- 12 tes backend FeatureAccessManagementTest lulus, 360 assertions, memakai
  konfigurasi testing SQLite in-memory. Tidak mengubah database operasional.
- Pratinjau CSS/JS asli memeriksa desktop 1280 px dan viewport 390 px. Computed
  style mengonfirmasi penegasan 240 ms, tutup dan lepas titik 44 × 44 px,
  serta legenda setinggi 44 px. Pencarian lebar penuh menyaring tanpa mengubah
  pilihan; scan biasa tidak menerima kelas penegasan.
- Tahap kelompok diperiksa di browser pada desktop dan viewport 390 px:
  Enter membuka summary, filter membuka kelompok cocok, penghapusan filter
  mengembalikan keadaan sebelumnya, hasil kosong memberi petunjuk, dan checkbox
  terpilih tetap sama. Contoh ponsel tidak memiliki overflow horizontal.
- Reduced motion diperiksa pada source. Tidak mengganti preferensi OS, tidak
  memakai database operasional, belum menguji scanner atau ponsel fisik.
- Tahap squash/stretch dan volume: 22 tes frontend terkait dijalankan ulang dan
  lulus; diff --check lulus. Browser mengonfirmasi matriks pose tekan
  `(1.08, .92)`, pose memanjang `(.92, 1.12)`, akhir `(1, 1)` dan durasi 240 ms.
  Pratinjau menyediakan gerak lambat 5× serta penahan pose. Desktop dan viewport
  390 px diperiksa, tanpa overflow pada contoh. Tes backend tidak diulang untuk
  perubahan CSS ini; hasil 12 tes di atas berasal dari tahap kelompok Hak Akses.
- Tahap feedback laporan: `public/js/report-browser.js` mengelola timer 1.200 ms,
  retry dan fokus status; `public/css/apple-design.css` mengatur warna/wrapping
  serta target retry 44 px. Timer dibersihkan saat selesai, dibatalkan, atau
  digantikan request baru. 24 tes frontend dijalankan ulang dan lulus.
- Browser memeriksa simulasi cepat, lambat, gagal lalu retry, serta keyboard
  pada viewport 390 px. Hasil lama tetap ada ketika busy, link Excel terlindungi,
  retry memulihkan hasil dan link, serta fokus berada pada status. Tidak ada
  overflow pada contoh ponsel. Ini simulasi jaringan, bukan benchmark backend.

Riwayat koreksi ritme: `docs/MOTION_REFINEMENTS_2026-11-15.md`.
