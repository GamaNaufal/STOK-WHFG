# Catatan before dan after animasi

Terakhir diperbarui: 15 November 2026, koreksi setelah penilaian pengguna.

Pengguna menilai versi before lebih baik pada sebagian besar area; feedback
tekan–lepas tombol pada iterasi pertama dipertahankan. Koreksi ini memulihkan
ritme dan karakter versi before pada area lain. Tidak menerapkan seluruh rules
sebagai checklist atau menganggap durasi lebih singkat selalu lebih baik.

Tahap lanjutan sesudah koreksi ini menambahkan penegasan sukses penutup, target
kontrol dan toleransi pencarian. Ritme hasil koreksi tetap dipertahankan. Status
seluruh rules: `docs/UI_RULES_STATUS_2026-11-15.md`.

## Hasil koreksi ritme

| Area | Iterasi pertama, dikoreksi | Hasil perbaikan |
| --- | --- | --- |
| Tombol bersama | Feedback tekan langsung, kembali 120 ms. | Dipertahankan: mengecil ke 98% seketika saat ditekan dan kembali halus selama 120 ms. Aturan hover tidak menimpa active. |
| Tombol scanner dan keyboard | Tombol Stock Input dan fokus keyboard tidak bergeser. | Dipertahankan. Tidak menunda operasi input. |
| Grafik garis / area | Garis 240 ms, area 120 ms setelah jeda 120 ms. | Ritme before dipulihkan: garis 850 ms, area 600 ms, dengan kurva asli. |
| Ring / batang komposisi | Ring 240 ms, batang 180 ms. | Ritme mengisi dipulihkan: ring 800 ms, batang 700 ms. |
| Angka KPI | Count-up dihapus. | Count-up 700 ms kembali, dengan pelambatan saat mendekati nilai akhir. Nilai akhir tetap tersedia bagi screen reader. |
| Tab rincian | Panel langsung berganti. | Klik mouse/sentuh: transisi 220 ms dengan fade dan gerak 4 px seperti before. Keyboard, inisialisasi, dan pemulihan: langsung tanpa animasi panel. |
| Popup konfirmasi | Masuk 200 ms, keluar 140 ms; animasi ikon dimatikan. | Animasi SweetAlert2 asli kembali: masuk 300 ms, keluar 150 ms, beserta animasi ikon. |
| Modal / toast | Fade modal/backdrop 180 ms, toast 120 ms dengan kurva bersama. | Gaya before dipulihkan: modal 150 ms ease-out, backdrop/toast mengikuti Bootstrap 150 ms linear. |
| Pemilih Lokal/Ekspor | Indikator 180 ms dengan kurva kuat; crossfade halaman dihapus. | Transisi before 200 ms beserta kurva dan view transition halaman dipulihkan. |
| Area klik popup | Minimum 44 × 44 px. | Dipertahankan; tidak mengubah teks atau tindakan konfirmasi. |
| Cache stylesheet | Versi stylesheet bersama memakai waktu modifikasi file. | Dipertahankan agar pembaruan CSS diterima browser. |

## Alasan koreksi

Pemangkasan durasi secara merata membuat grafik, ring, dan komposisi terlalu
cepat dibaca mata; penghapusan count-up dan transisi panel menghilangkan hubungan
visual. Durasi analitik asli dipulihkan sesuai penilaian pengguna atas before.
Batas 300 ms tetap berguna sebagai pedoman interaksi singkat, tetapi tidak lagi
dijadikan batas mutlak untuk penggambaran data analitik pada pekerjaan ini.

Feedback tombol dipertahankan karena langsung mengikuti tindakan pengguna dan
memiliki pelepasan yang halus. Tidak menambahkan library, gesture, suara, atau
animasi dekoratif baru. Ini koreksi terhadap area yang menurun pada iterasi
pertama, bukan penerapan keseluruhan aturan desain.

## File implementasi

- `public/css/apple-design.css`: feedback tombol, area klik popup, serta pemulihan gaya modal/toast dan animasi popup bawaan.
- `public/css/application-ui.css`: hover tidak menimpa keadaan active.
- `public/css/pages/warehouse-scope-switch.css`: dipulihkan ke versi before.
- `public/css/operational-dashboard.css`: durasi analitik asli dan transisi panel untuk pointer.
- `public/js/pages/operational-analytics.js`: count-up beserta cleanup dipulihkan; panel membedakan klik pointer dari keyboard.
- `resources/views/shared/layouts/app.blade.php`: cache busting stylesheet bersama dipertahankan.

Hak akses, route, payload, data inventory/delivery, dan waktu respons server tidak
diubah. Reduced motion tetap mengikuti OS; counter yang masih berjalan dihentikan
ke nilai akhir saat preferensi tersebut aktif.

## Verifikasi koreksi

- 14 tes frontend terkait analytics, report browser, dan teks notifikasi lulus.
  Pemeriksaan sintaks JavaScript dan `git diff --check` lulus.
- Pratinjau browser dengan CSS/JS asli dan data contoh mengonfirmasi garis 850 ms,
  ring 800 ms, batang 700 ms, scope 200 ms, popup asli 300 ms, dan count-up kembali.
- Klik tab mengaktifkan `od-panel-in` selama 220 ms. Tombol panah keyboard
  mengganti panel tanpa animasi dan mempertahankan fokus tab.
- Siklus pagehide/pageshow dashboard diperiksa setelah count-up dipulihkan.
- Pratinjau desktop dan viewport 390 px diperiksa. Contoh ponsel tidak memiliki
  overflow horizontal; ukuran tombol popup setelah animasi selesai minimal 44 px.
- Ini pratinjau komponen, bukan pengujian seluruh halaman aplikasi. Reduced
  motion diperiksa dari source; belum diuji pada scanner atau ponsel fisik.
- Hasil 13 tes backend / 138 assertions berasal dari iterasi pertama. Tidak
  diklaim sebagai tes yang dijalankan ulang untuk koreksi CSS/JS ini.

Rujukan konsep sesuai arahan pengguna: Raphael Salaja / userinterface.wiki,
Disney / The Illusion of Life, Jon Yablonski / Laws of UX, serta prinsip
frequency of use dari Emil Kowalski. Keberhasilan kualitas rasa tetap perlu
dinilai pengguna melalui pratinjau interaktif, tidak disimpulkan dari durasi saja.
