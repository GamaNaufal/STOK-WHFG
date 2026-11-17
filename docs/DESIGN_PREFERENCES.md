# Preferensi UI/UX STOK WHFG

Ditetapkan 16 Oktober 2026 atas permintaan pengguna. Acuan utama: skill
`apple-design`; prinsip emil-design-eng tetap berguna jika selaras.

## Arah desain

Antarmuka operasional yang tenang, jelas, dan langsung merespons. Gunakan
prinsip Apple, bukan tiruan macOS atau iOS. Pertahankan Bootstrap, pola navigasi
web yang dikenal, identitas teal Warehouse FG, dan warna status bisnis.

- Font sistem, optical sizing otomatis. Judul lebih rapat; teks isi memiliki
  line-height nyaman. Besaran dan jarak berbasis rem agar dapat diperbesar.
- Latar abu-abu netral, konten putih solid, separator halus, radius konsisten.
  Hierarki ditentukan oleh ukuran, bobot, jarak dan posisi, bukan banyak gradien.
- Aksen teal untuk tindakan utama dan pilihan aktif; merah untuk bahaya,
  amber untuk peringatan, hijau untuk keberhasilan. Status selalu disertai teks.
- Gunakan koma sebagai pemisah klausa pada teks antarmuka, popup, validasi,
  keterangan audit, dan laporan (preferensi 13 November 2026).
- Translusensi hanya pada toolbar atas yang melayang di atas konten. Jangan
  menumpuk lapisan transparan atau mengaburkan teks/tabel/form.
- Feedback tekan langsung; aksi tetap berjalan pada click/release. Tidak ada
  animasi masuk berurutan, hover yang mengangkat kartu, atau perubahan posisi
  saat input scanner mendapat fokus. Keyboard tidak menunggu animasi.
- Jangan menambah gesture, spring library, suara atau haptik tanpa kebutuhan
  operasional. Jika kelak ada drag, gerakan harus mengikuti pointer 1:1,
  dapat diinterupsi, dan mempertahankan kecepatan saat dilepas.
- Kontrol sentuh minimal 44px pada perangkat coarse pointer. Fokus keyboard
  terlihat, label terhubung, tabel lebar digulir dalam wadahnya.
- Hormati reduced motion, reduced transparency dan increased contrast.
  Browser tanpa backdrop-filter harus tetap memiliki latar solid yang terbaca.
- Semua perubahan presentasi menjaga route, payload, otorisasi, transaksi,
  perilaku scanner, dan status bisnis. Template cetak/PDF/email terpisah.

Implementasi bersama: `public/css/application-ui.css` untuk struktur dan
aksesibilitas; `public/css/apple-design.css` untuk token serta tampilan utama.
Login memakai fondasi yang sama melalui stylesheet Apple yang dimuat terakhir.
Aturan khusus halaman harus memakai token ini atau menjelaskan pengecualiannya.

Verifikasi perubahan dengan data kosong/berisi, keyboard, desktop, viewport
ponsel, dan tes regresi relevan. Bedakan simulasi browser dari perangkat nyata.

## Dashboard analitik — 3 November 2026

Pengguna meminta dashboard Admin Utama/Supervisi lebih hidup dan interaktif.
Khusus dashboard ini, gunakan animasi terbatas untuk angka KPI, penggambaran
garis, ring pemenuhan, komposisi stok dan pergantian rincian. Interaksi pointer
horizontal pada grafik meninjau titik data; klik menahan titik, keyboard memberi
alternatif yang setara. Hindari gerakan dekoratif tanpa akhir, angka tren fiktif
dan penggabungan satuan PCS lokal dengan box ekspor. Animasi mengikuti
preferensi kurangi gerakan perangkat (prefers-reduced-motion) tanpa tombol
pengaturan pada dashboard. Preferensi operasional/scanner
dan navigasi halaman lain tetap berlaku.

## Penyempurnaan animasi — 15 November 2026

Atas arahan pengguna, terapkan prinsip animasi secara selektif berdasarkan
tujuan dan frekuensi penggunaan. Setelah menilai iterasi pertama, pengguna lebih
menyukai before pada sebagian besar area, dengan pengecualian feedback tombol.
Pertahankan tekan langsung dan pelepasan halus 120 ms. Pulihkan ritme before
pada analitik, popup, modal, toast, dan pemilih lingkup.

Count-up KPI 700 ms, garis 850 ms, ring 800 ms dan batang 700 ms kembali sesuai
karakter dashboard yang diinginkan. Pedoman 300 ms tidak diberlakukan sebagai
batas mutlak untuk penggambaran data analitik. Klik tab rincian memakai transisi
220 ms; navigasi keyboard tetap langsung. Hindari gerakan pada input scanner,
pertahankan reduced motion serta target sentuh. Nilai kualitas dari kontinuitas
dan keterbacaan gerak, bukan hanya durasi. Rincian koreksi before/after dan
verifikasi: `docs/MOTION_REFINEMENTS_2026-11-15.md`.

Tahap lanjutan pada tanggal yang sama: penegasan sukses 240 ms hanya untuk
notifikasi penutup yang diaktifkan eksplisit, bukan scan biasa. Centang dapat
melewati ukuran akhir lalu menetap; judul/pesan, warna hijau dan kedalaman
permukaan menegaskan hasil. Pertahankan timer notifikasi dan fokus keyboard.
Kontrol grafik/tutup diperbesar tanpa membesarkan ikon. Normalisasi input hanya
untuk pencarian, tidak untuk identitas inventory. Status setiap rule dan catatan
before/after: `docs/UI_RULES_STATUS_2026-11-15.md`.

Hak Akses menerapkan progressive disclosure per kelompok pekerjaan: judul dan
jumlah terpilih tetap terlihat, hasil pencarian dibuka otomatis, dan keadaan
kelompok dipulihkan setelah pencarian dihapus. Gunakan details/summary native,
tanpa animasi atau perubahan grant. Status hasil/hasil kosong harus terbaca,
dan tindakan massal menjelaskan bahwa seluruh izin termasuk yang tersembunyi
tetap tercakup. Tanpa JavaScript, seluruh kelompok tersedia dalam keadaan terbuka.

Centang sukses penutup memakai squash/stretch berbeda pada sumbu X/Y selama
240 ms. Badge diberi rim, cahaya dan bayangan halus untuk volume statis. Tombol
tekan–lepas tetap seperti hasil yang telah disukai. Wind-up dan lintasan arcs
tidak dipilih pada area saat ini karena belum ada gesture/perpindahan objek
yang memerlukannya.

Untuk pemuatan laporan, acknowledgement tetap langsung. Setelah 1,2 detik
request berlangsung, beri penjelasan menunggu dan pilihan retry sambil hasil
sebelumnya tetap terlihat. Retry membatalkan request browser lama, tidak
dijalankan otomatis. Saat tombol retry disembunyikan, fokus keyboard beralih
ke status tanpa scroll. Tidak memakai progres buatan atau menjanjikan waktu server.

Download Excel mendapat feedback persiapan langsung dan indikator yang
menggantikan ikon tanpa mengubah lebar tombol. Nyatakan file siap setelah
konfirmasi respons server, lalu arahkan pengguna ke daftar unduhan browser.
Pertahankan native download, filter, hak akses, reduced motion dan fallback.
Tidak memakai persentase buatan atau mengklaim file tersimpan di disk. Detail:
`docs/EXCEL_DOWNLOAD_FEEDBACK.md`.

Atas arahan pengguna, notifikasi Excel siap menutup otomatis 3 detik setelah
konfirmasi server, termasuk ketika di-hover/difokuskan. Sukses tidak menampilkan
Tutup. Kembalikan fokus ke tombol asal saat panel menghilang; error tetap
menyediakan tindakan pemulihan. Batas ini mengikuti sinyal siap/diserahkan ke
browser, karena penyimpanan akhir ditangani browser.

Lindungi download dari klik beruntun, termasuk sampai 1 detik setelah konfirmasi
siap yang sangat cepat. Bila ack tidak terbaca selama 60 detik, hentikan spinner
dan pemantauan, pulihkan tombol, lalu tampilkan Belum terkonfirmasi dengan arahan
memeriksa unduhan browser. Request native tetap berjalan; waktu tidak menjadi
bukti sukses atau gagal.

Sisa empat prioritas diselesaikan pada tanggal yang sama: samakan feedback
template Excel dengan unduhan lain, gunakan target tombol tutup/tabel/modal
minimal 44 px, pertahankan warna validasi ketika fokus, dan beri kelompok
pekerjaan pada form. Master part mendahulukan identitas/kapasitas, pemetaan kode
dokumen memakai disclosure native dengan ringkasan pilihan. Pemetaan berbeda
atau error terbuka, validasi kolom tersembunyi membukanya sebelum fokus. Nilai
tetap terkirim saat disclosure ditutup, tanpa JS seluruh bagian tersedia.
Rencana delivery menampilkan urutan tiga bagian, modal koreksi memisahkan data
box dan alasan audit. Tidak menambah wind-up, arcs atau gerak pada scan.
Catatan before/after dan verifikasi: `docs/UI_RULES_COMPLETION_2026-11-15.md`.

Sesudah rencana lima animasi disetujui, tambahkan gerak kontekstual pada panel
input Excel (180/120 ms), menu akun (160/120 ms, skala 97% dari pemicu),
disclosure pemetaan master part (fade 160/120 ms dan chevron), penegasan ikon
hasil impor (180 ms), serta crossfade file ke centang download (160 ms).
Aktivasi keyboard, validasi, inisialisasi dan reduced motion tetap langsung.
Gerak dapat dibalik dari pose saat ini, tanpa menahan aksi bisnis. Animasi tabel
hasil, baris scan dan perpindahan input tidak ditambahkan. Before/after dan
verifikasi: `docs/UI_CONTEXTUAL_MOTION_2026-11-15.md`.

## Pergantian tampilan stok Ekspor — 18 November 2026

Atas permintaan pengguna, Daftar stok dan Reservasi customer memakai satu kontrol
dengan indikator geser 200 ms. Pertahankan posisi, ukuran dan header yang sama
di kedua tampilan. Gerak hanya pada indikator, data tabel tetap diam. Perubahan
halaman dapat memakai fade 120 ms. Navigasi mulai langsung, tanpa menunggu animasi.
Keyboard dan reduced motion tetap langsung. Tautan native, Back/Forward, filter
dan fungsi setiap halaman dipertahankan; browser tanpa View Transitions tetap
dapat berpindah halaman. Pembatalan navigasi mengembalikan indikator ke halaman
yang benar.
