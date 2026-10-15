# Audit dan perbaikan UI/UX

Preferensi terbaru (16 Oktober 2026): **apple-design**, sesuai permintaan pengguna.
Pedoman permanen: `docs/DESIGN_PREFERENCES.md`. Bagian di bawah mencatat baseline
audit sebelumnya; evaluasi Apple Design ada pada bagian akhir.

Tanggal: 15 Oktober 2026. Acuan: skill emil-design-eng dan source Blade/CSS.
Lingkup: seluruh halaman interaktif yang memakai layout aplikasi, serta login.
Template cetak, PDF, dan email tidak termasuk perubahan visual layar.

| Modul | Before | After yang dituju | Alasan |
| --- | --- | --- | --- |
| Navigasi | Sidebar sempit pada laptop, label panjang terdesak, placeholder fitur kosong | Lebar sidebar stabil, label membungkus, ruang kosong dihapus | Navigasi lebih mudah dipindai |
| Komponen dasar | Header besar tidak konsisten, fokus keyboard lemah, warna teks pucat | Header ringkas, outline fokus, warna teks terbaca | Hierarki dan aksesibilitas konsisten |
| Stok | Input scan bergerak saat fokus, panel berlebihan, tabel padat | Fokus stabil, panel ringan, tabel dapat digulir dengan keyboard | Mendukung pekerjaan scan berulang |
| Delivery | Tabel mobile kehilangan header, kartu/tombol bergerak saat hover | Header tabel tetap tersedia, gerakan dekoratif dihapus | Mempertahankan hubungan kolom dan data |
| Laporan dan audit | Header/filter memakan ruang, angka dan tabel sulit dipindai | Header ringkas, filter konsisten, angka tabular | Memudahkan pembacaan data |
| Administrasi dan master | Tombol ikon tanpa nama, form dan kartu tidak seragam | Nama aksesibel, kontrol dan kartu konsisten | Aksi lebih jelas |
| Profil dan login | Target ikon password kecil, label login tidak terhubung | Target 44px, label dan autocomplete benar | Form lebih nyaman digunakan |
| Dashboard | Kontras lemah, badge role hilang, label statistik ambigu | Kartu ringkas, badge role, label sesuai data | Ringkasan lebih akurat secara visual |

Urutan implementasi: komponen bersama dan navigasi, stok, delivery, laporan/audit,
administrasi/master/profil, login. Perubahan hanya presentasi dan aksesibilitas;
route, payload, izin, query, serta aturan transaksi dipertahankan.

## Hasil implementasi

Perbaikan pada tabel audit telah diterapkan melalui CSS layar bersama,
komponen Blade, serta penyesuaian halaman per modul. Tambahan dari pemeriksaan visual:

- Aturan `.container-fluid > .row` dibatasi ke baris kerangka utama agar grid
  di dalam halaman tetap mengikuti breakpoint Bootstrap.
- Header scan tidak lagi menggunakan margin negatif yang memotong judul di ponsel.
- Menu mobile memakai satu header merek, dengan tombol buka/tutup bernama jelas.
- Tombol aksi delivery memiliki target sentuh minimum 44px di layar kecil.
- Modal split memiliki judul aksesibel, nama kontrol, kontras yang lebih kuat,
  dan tabel yang dapat digulir di dalam dialog.
- Angka ringkasan stok/laporan memakai warna lebih gelap dan angka tabular.
- Pembuat pratinjau sementara diubah menjadi tes render tanpa menulis artefak HTML.

## Validasi

- Seluruh suite: **200 tes lulus, 1.253 assertion**.
- Tes render mencakup **26 halaman** dengan database SQLite in-memory, termasuk
  stok, expired box, delivery, approval, laporan, audit, master, hak akses, profil,
  dan login. Data contoh tidak ditulis ke database operasional.
- Template Blade berhasil dikompilasi; pemeriksaan whitespace diff bersih.
- Pemeriksaan visual representatif dilakukan pada viewport desktop 1366px dan
  ponsel 390px: dashboard, delivery, form stok, laporan input, tambah pengguna,
  dan login. Menu mobile dan modal split dibuka tanpa menyimpan transaksi.
- Tidak semua kombinasi data, role, dan kondisi modal diperiksa secara visual.
  Viewport ponsel adalah simulasi browser, bukan pengujian perangkat fisik.
- PDF, cetak, email, backend, route, dan aturan transaksi tidak diubah.

## Audit menyeluruh dengan Apple Design — 16 Oktober 2026

Audit mencakup kerangka navigasi, dashboard enam role, komponen bersama, stok
masuk/lihat/merge/pindah, box not full, expired box, sales dan approval delivery,
assignment, picking/verifikasi/final scan, issue/lock, laporan/audit, master data,
pengguna/hak akses, profil dan login. Source Blade/CSS serta interaksi bersama
ditinjau; ini bukan klaim bahwa seluruh kombinasi kondisi telah diuji visual.

| Prioritas / area | Before | After / keputusan | Prinsip |
| --- | --- | --- | --- |
| P1 — pesan profil | Slot pesan tertutup default string kosong; danger tampil sebagai info | Slot dan pesan eksplisit tampil; danger memakai error, role alert, ikon sesuai | Feedback, safety |
| P2 — pencarian global | Status buka dan pilihan keyboard tidak terhubung ke input; Escape tidak menutup hasil kosong | Combobox terhubung ke listbox, active descendant, Escape/Tab menutup | Agency, familiarity |
| P2 — hierarki seluruh modul | Header dan bagian konten sama-sama dominan; banyak gradien | Judul menyatu dengan halaman, bagian umum netral, warna tindakan/status tetap terarah | Simplicity, grouping |
| P2 — navigasi | Item aktif berupa blok pekat; toolbar tanpa perbedaan lapisan | Pilihan aktif lembut dengan indikator tepi, toolbar translusen terbatas, fallback solid | Wayfinding, materials |
| P2 — aksesibilitas visual | Belum ada reduced-transparency dan increased-contrast | Toolbar solid saat diminta; border lebih tegas; motion tidak bergeser saat reduced-motion | Flexibility |
| P2 — profil | Label tidak terhubung dan autofill tidak dijelaskan | ID/for serta autocomplete untuk identitas dan password | Predictability |
| P3 — dashboard | Label admin masih Admin IT, berbeda dengan terminologi akses | Label tampilan Admin Utama; nilai dan perhitungan tidak diubah | Familiarity |
| P3 — semua form/tabel | Radius, warna, ukuran dan bobot berbeda antarhalaman | Token permukaan/teks/separator bersama; font sistem dan optical sizing; kontrol konsisten | Craft |
| P3 — login | Gaya terpisah dari aplikasi | Mengikuti font, radius, aksen dan permukaan yang sama | Consistency |

### Keputusan desain

- Identitas teal dipertahankan. Apple Design diterapkan sebagai prinsip desain,
  bukan menyalin kontrol window macOS atau mengganti ikon dengan simbol Apple.
- Tabel dan form tetap solid untuk keterbacaan. Blur hanya pada toolbar, tidak
  bertumpuk, dan memiliki fallback tanpa blur.
- Tidak ditambahkan drag/swipe atau spring library: alur operasional saat ini
  memakai klik, keyboard dan scanner; animasi tambahan tidak diperlukan.
- Feedback tekan tidak menjalankan transaksi lebih awal; event bisnis tetap
  click/release. Pesan dan status keberhasilan/error tetap memiliki arti aslinya.

### Temuan yang memerlukan validasi lanjutan

- `public/js/pages/operator-stock-input.js` memiliki beberapa perpindahan fokus
  tertunda 300ms. Jangan menghapusnya sebelum menguji rangkaian scan fisik dan
  interaksi pesan; risiko kehilangan input lebih penting daripada gerakan kosmetik.
- Beberapa form lama masih memiliki label statis yang belum terhubung ke kontrol,
  terutama item berulang/dinamis. Profil, login dan input scan sudah diperbaiki;
  audit aksesibilitas penuh dengan pembaca layar masih diperlukan.
- Template PDF/cetak/email tidak diberi tema layar. Dark mode tidak ditambahkan;
  desain saat ini menetapkan tema terang yang konsisten.
- Uji perangkat fisik, semua kombinasi role/data, serta preferensi aksesibilitas
  OS nyata belum dilakukan. Aturan media CSS ditinjau, bukan dianggap teruji lewat
  simulasi OS.

### Verifikasi tahap Apple

- Suite aplikasi: **203 tes lulus, 1.263 assertion**.
- Setelah penyelarasan heading terakhir: 4 tes komponen/render lulus, 36 assertion;
  preview 26 halaman dan enam dashboard per role lulus, 32 assertion.
- Pemeriksaan visual pada viewport desktop 1366px dan mobile 390px mencakup
  dashboard, pengguna, input stok, delivery, akses, login, dan laporan.
- Interaksi pencarian diuji dengan keyboard, termasuk pilihan aktif dan Escape
  ketika hasil kosong. Tidak ada transaksi operasional yang dikirim saat preview.
- Pint pada tes komponen dan git diff --check lulus.
- Satu generasi preview sempat gagal karena dua proses tes bersamaan mengunci
  cache Blade di Windows; pengulangan secara berurutan lulus.
