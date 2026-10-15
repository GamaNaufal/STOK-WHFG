# Audit dan perbaikan UI/UX

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

Validasi akhir dicatat setelah implementasi.
