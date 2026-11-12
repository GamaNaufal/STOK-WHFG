# Animasi gudang untuk login Warehouse FG

Animasi pendukung halaman `/login`, dibuat 11 November 2026. Form login tetap
memakai route, controller, CSRF, validasi, dan sesi Laravel yang sudah ada.
Tidak diperlukan migration, akun baru, atau perubahan konfigurasi production.

## Berkas

| Berkas | Kegunaan |
| --- | --- |
| `artifacts/warehouse-login/warehouse_fg_login_master.blend` | Scene Blender yang dapat diedit, termasuk kamera, objek, material, keyframe, dan branding |
| `artifacts/warehouse-login/warehouse_fg_login_master_90s.mp4` | Master H.264 1920 × 1080, 30 fps, tepat 2.700 frame / 90 detik, tanpa audio |
| `public/media/warehouse-login/warehouse_fg_login_desktop.mp4` | H.264 1280 × 720 dengan metadata streaming di awal berkas |
| `public/media/warehouse-login/warehouse_fg_login_poster.webp` | Poster desktop |
| `public/media/warehouse-login/warehouse_fg_login_mobile.webp` | Poster untuk ponsel dan tablet |
| `artifacts/warehouse-login/warehouse_fg_login_loop_test.mp4` | Lima detik terakhir diikuti lima detik pertama; sambungan pada detik ke-5 |
| `scripts/blender/warehouse_fg_login_animation.py` | Pembuatan dan render scene Blender |
| `scripts/blender/optimize_warehouse_login_render.py` | Salinan khusus render dengan geometri rigid digabung; master authoring tetap lengkap |
| `scripts/blender/encode_warehouse_login.py` | Encoding master, versi web, poster, serta klip uji loop |
| `scripts/blender/verify_warehouse_login.py` | Verifikasi metadata dan frame hasil encoding |

File master dan `.blend` merupakan aset authoring; aplikasi hanya menyajikan
berkas dalam `public/media/warehouse-login`. Tidak perlu menyalin master ke
web server. WebM bersifat opsional dan dapat dibuat dengan opsi `--webm` pada
script encoder.

## Storyboard dan komposisi

| Waktu | Adegan |
| --- | --- |
| 00:00–00:10 | Hitam menuju pengenalan gudang |
| 00:10–00:25 | Inbound, conveyor, dan pemindaian barcode |
| 00:25–00:45 | Forklift dan penyimpanan pallet |
| 00:45–01:00 | Picking dan verifikasi inventori |
| 01:00–01:15 | Outbound dan area loading |
| 01:15–01:19 | Gudang meredup sampai hitam |
| 01:19–01:25 | Logo, Warehouse FG, dan subtitle perusahaan |
| 01:25–01:30 | Branding meredup sampai hitam |

Panel desktop mempertahankan keseluruhan rasio 16:9 agar logo dan subtitle
tidak terpotong. Logo berasal dari `public/logo.png`, yang sama dengan gambar
referensi pengguna. Logo tidak digambar ulang sebagai merek baru.

## Perilaku halaman

Pada desktop mulai 1.024 px, video hanya dimuat setelah JavaScript mengecek
preferensi gerakan serta koneksi. Poster tetap tersedia tanpa JavaScript,
saat pemutaran otomatis ditolak, atau saat media gagal dimuat. Kontrol jeda
dapat dioperasikan menggunakan keyboard. Tab yang tidak terlihat tidak
memerlukan pemutaran aktif.

Di bawah 1.024 px, saat `prefers-reduced-motion: reduce`, pada mode hemat data,
atau koneksi lambat yang terdeteksi, halaman memakai poster. Master 1080p
tidak direferensikan oleh HTML. Form dan pesan kesalahan tetap berupa HTML
biasa; pengiriman form tidak bergantung pada modul media.

## Menghasilkan ulang aset

Gunakan Blender 5.3 dengan Eevee (`BLENDER_EEVEE`), Python 3, dan FFmpeg.
Script Blender menyediakan opsi CLI; lihat `--help` setelah pemisah `--`.
Simpan frame sementara di drive yang cukup luas. Satu render lengkap
menghasilkan 2.700 frame; encoder menolak frame yang hilang atau kosong.

Contoh alur lengkap dari root repository pada PowerShell:

```powershell
$blenderExe = 'C:/Program Files/Blender Foundation/Blender 5.3/blender.exe'
$frameDirectory = Join-Path $env:LOCALAPPDATA 'Temp/warehouse-fg-login-render'
$renderCopy = Join-Path $frameDirectory 'warehouse_fg_login_render_optimized.blend'
& $blenderExe -b --factory-startup --python scripts/blender/warehouse_fg_login_animation.py -- --build
& $blenderExe -b artifacts/warehouse-login/warehouse_fg_login_master.blend --python scripts/blender/optimize_warehouse_login_render.py -- --output $renderCopy
& $blenderExe -b $renderCopy --python scripts/blender/warehouse_fg_login_animation.py -- --render
py -3.11 scripts/blender/encode_warehouse_login.py $frameDirectory --ffmpeg C:/tools/ffmpeg.exe
py -3.11 scripts/blender/verify_warehouse_login.py --ffmpeg C:/tools/ffmpeg.exe --ffprobe C:/tools/ffprobe.exe
```

Langkah optimasi bersifat opsional. Master dapat dirender langsung. Salinan
render mengevaluasi bevel dan normal lalu menggabungkan mesh menurut material
dan induk geraknya. Keyframe, constraint pengangkatan pallet, roda, kamera,
material, UV logo, serta compositor tetap dipertahankan. File salinan tidak
menimpa master. Untuk melanjutkan rentang tertentu, tambahkan `--start` dan
`--end` setelah opsi `--render`; pastikan semua frame 1–2.700 tersedia sebelum
encoding.

Optimizer mempertahankan seluruh penghubung `EMPTY`. Sebelum menyimpan salinan,
script memeriksa matriks gerak serta target constraint terhadap master pada
26 frame dan membandingkan batas geometri pallet pada empat frame. Jika
pemeriksaan gagal, salinan tidak disimpan. Hasilnya dicatat pada
`render-optimization-report.json`.

Encoder mempertahankan jumlah frame dan resolusi master. Tidak memakai
interpolasi gerakan, pengulangan gambar diam, atau upscaling sebagai pengganti
render master. Seluruh output video dibuang kanal audionya, memakai pixel
format `yuv420p`, dan MP4 memakai `faststart`.

## Verifikasi

Laporan hasil encoding dan verifikasi disimpan di `artifacts/warehouse-login`.
Pemeriksaan browser mencakup lebar 360, 390, 768, 1.024, 1.440, dan 1.920 px,
preferensi kurangi gerakan, kegagalan media, keyboard, dan halaman tanpa
JavaScript. Pengujian viewport merupakan simulasi browser desktop dan tidak
menggantikan pemeriksaan pada ponsel atau scanner fisik.
