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
