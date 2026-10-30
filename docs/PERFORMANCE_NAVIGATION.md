# Performa navigasi

Verifikasi 29 Oktober 2026, branch Export-plan.

Pembaruan layout berikutnya pada hari yang sama memindahkan tabel antrean dari
Stok Ekspor ke Penerimaan Box Ekspor. Angka baseline di bawah dicatat sebelum
pemindahan tersebut; beban query/tabel antrean sekarang berada pada penerimaan.
Antrean kemudian dikelompokkan per transaksi dengan agregasi SQL dan pagination
20 transaksi. Baseline per-box di bawah bukan hitungan query terkini.

## Perubahan

- Bootstrap 5.3.0, Bootstrap Icons 1.11.0, SweetAlert2 11.26.25, kedua versi
  jQuery existing, dan Select2 4.1.0-rc.0 disajikan dari public/vendor. Semua
  versi tetap dan urutan script dipertahankan. Manifest mencatat URL sumber,
  SHA-256 dan lisensi MIT. Tidak ada unduhan saat aplikasi dijalankan.
- CSS kerangka dan JavaScript sidebar/pencarian/toast yang sebelumnya inline
  dipindah ke public/css/layout-shell.css dan public/js/{layout-shell,toast}.js.
  Partial Blade tetap menjadi titik include; query v=filemtime memperbarui
  cache saat file berubah. Data pencarian menu yang sesuai hak akses tetap
  dirender per request. Sekitar 31,5 KB tidak lagi diulang dalam setiap HTML.
- Draft scan tidak memuat katalog tujuan lokasi/pallet. Hasil batch memuatnya
  hanya bila ada cadangan yang menunggu penempatan. Halaman stok memuat katalog
  hanya saat detail cadangan dipilih. Pilihan masih divalidasi ulang pada POST.
- Antrean stok memakai total paginator sehingga tidak menghitung dua kali.
  Daftar hanya eager-load relasi yang ditampilkan; hierarki delivery lengkap
  tetap dimuat pada detail box dan hasil penerimaan.
- public/vendor/.htaccess memberi cache setahun/immutable pada CSS/JS/font dalam
  direktori versi tetap bila Apache mod_headers tersedia. Pada server lain,
  aset tetap berfungsi; atur cache statis sesuai prosedur deployment.

Hak akses tetap diperiksa setiap request. Tidak ada cache lintas request untuk
HTML, draft, stok atau izin. Tidak ada perubahan FIFO, transaksi, atau schema.

## Pengukuran

GET melalui kernel HTTP Laravel, operator ekspor pada SQLite preview yang sama,
tiga sampel setiap halaman. Data uji bukan MySQL operasional/production.
Ukuran berikut adalah byte HTML sebelum kompresi; hitungan query tidak termasuk
lookup aktor di luar request. Benchmark awal/akhir memakai draft dua baris dan
sembilan stock box yang sama, sebelum pengujian browser menambah data preview.

| Halaman | HTML sebelum | HTML sesudah | Query sebelum | Query sesudah |
| --- | ---: | ---: | ---: | ---: |
| Penerimaan | 47.269 B | 15.766 B | 6 | 3 |
| Stok ekspor | 48.423 B | 16.920 B | 15 | 9 |
| Rencana delivery | 46.101 B | 14.598 B | 4 | 4 |
| Perencanaan pallet | 45.021 B | 13.518 B | 4 | 4 |
| Pemenuhan | 48.542 B | 17.039 B | 4 | 4 |

HTML berkurang sekitar 65–70%. Empat dependensi CDN pada layout bersama menjadi
nol; font ikon juga berasal dari aplikasi. Pengambilan CSS Bootstrap dari CDN
saat belum tersimpan cache terukur 1,64 detik pada koneksi pengujian. Ini bukan
janji pengurangan durasi sebesar itu pada setiap navigasi yang sudah tercache.

Durasi kernel PHP bervariasi dengan boot/autoload dan beban Windows/OneDrive.
Pengukuran awal/akhir juga beririsan dengan regresi, sehingga tidak digunakan
untuk mengklaim persentase peningkatan waktu keseluruhan. Pengurangan byte,
query, dan dependensi eksternal adalah hasil yang dapat dibandingkan langsung.

`php artisan optimize` berhasil diuji memakai APP_CONFIG_CACHE,
APP_ROUTES_CACHE, APP_EVENTS_CACHE dan VIEW_COMPILED_PATH yang diarahkan ke
folder preview tersendiri. Uji berpasangan tiga sampel, median per halaman:

| Halaman | Tanpa cache Laravel | Dengan cache Laravel |
| --- | ---: | ---: |
| Penerimaan | 1.060 ms | 851 ms |
| Stok ekspor | 1.330 ms | 710 ms |
| Rencana delivery | 890 ms | 556 ms |
| Perencanaan pallet | 681 ms | 706 ms |
| Pemenuhan | 980 ms | 640 ms |

Variasi sampel masih besar; ini uji kesiapan konfigurasi deployment, bukan skor
Lighthouse atau benchmark production. Tidak mengaktifkan cache permanen pada
checkout development. Log JSON berada di storage/logs/navigation-*.jsonl.

## Deployment dan pengembangan

Sertakan public/vendor beserta font/lisensi, ketiga file shell baru, semua
partial/layout yang berubah, dan public/build. Tidak perlu migration atau
konfigurasi PC operator. Library lokal tidak bergantung pada npm saat runtime.

Pada deployment production setelah .env dan seluruh source final:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize
```

Gunakan APP_ENV=production dan APP_DEBUG=false pada environment production.
Jika konfigurasi/rute berubah setelah cache dibuat, bangun ulang cache tersebut.
Untuk kembali mengembangkan pada environment development yang sempat dicache,
gunakan php artisan optimize:clear sesuai prosedur environment tersebut.
OPcache PHP merupakan optimasi runtime tambahan; tidak dikonfigurasi pada PC
pengguna dalam perubahan ini. Laporan operasional masih mempunyai dependency
grafik eksternal yang terpisah dari layout bersama.

## Verifikasi

368 tes backend lulus (3.151 assertions), 57 tes frontend dan Vite build lulus.
Tes tambahan memeriksa checksum/path seluruh aset dan mencegah query katalog
tujuan yang tidak dibutuhkan. Browser Chromium memverifikasi perpindahan antar
halaman ekspor, pencarian menu/Enter, dialog konfirmasi/batal, scan, pemrosesan,
pilihan cadangan dan toast. Tidak ada error konsol; aset layout seluruhnya lokal.
Tidak ada perubahan database operasional atau production pada tugas ini.
