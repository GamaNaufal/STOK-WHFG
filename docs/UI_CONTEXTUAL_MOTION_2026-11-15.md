# Lima animasi kontekstual WHFG

Terakhir diverifikasi: 15 November 2026.

Implementasi mengikuti rencana lima animasi yang disetujui pengguna, menggunakan
skill animate dan acuan apple-design. Gerak hanya membantu perpindahan keadaan
pada interaksi sesekali. Scan, tabel data, input, ritme dashboard dan feedback
tekan–lepas yang sudah dipilih pengguna dipertahankan.

## Before dan after

| Area | Before | After |
| --- | --- | --- |
| Mode input Excel | Panel muncul atau hilang langsung. | Pointer: masuk 180 ms dengan opacity dan offset Y 4 px, keluar 120 ms melalui lintasan yang sama. Tombol aktif langsung berubah. Keyboard langsung. |
| Menu akun | Menu Bootstrap langsung muncul/hilang. | Pointer: opacity dan skala 97% → 100%, masuk 160 ms dan keluar 120 ms dari sudut atas kanan dekat pemicu. Keyboard langsung; fokus, Escape dan pilihan menu tetap ditangani Bootstrap. |
| Pemetaan kode dokumen | Disclosure native langsung berganti. | Pointer: isi fade masuk 160 ms, keluar 120 ms, indikator chevron berputar 90°. Keyboard, inisialisasi, perubahan checkbox dan validasi langsung. Kolom tidak bergeser untuk dekorasi. |
| Hasil pembacaan Excel | Pesan teks mengumumkan hasil. | Pesan tetap langsung, disertai ikon sukses dengan opacity dan skala 92% → 100% selama 180 ms. Tabel/baris hasil tidak dianimasikan. Undo dan warning tetap. |
| Download Excel siap | Ikon file berubah warna setelah konfirmasi server. | Ikon file crossfade ke centang selama 160 ms untuk aktivasi pointer. Area ikon tetap, teks siap langsung. Konfirmasi server, pencegahan duplikasi, fallback dan auto-close 3 detik tetap. |

Kurva mengikuti token existing `--ui-ease-out`, dengan fallback
`cubic-bezier(0.23, 1, 0.32, 1)`. Tidak memasang library animasi baru.

## Interupsi dan aksesibilitas

- Helper surface membaca pose yang sedang terlihat sebelum membalik arah.
  Completion lama tidak boleh menutup konten yang baru dibuka kembali.
- Saat menutup, konten segera inert dan aria-hidden, kemudian hidden setelah
  efek selesai. Field tidak dihapus/dinonaktifkan sehingga nilainya tetap ada.
- Fokus atau keyboard menyelesaikan gerak aktif segera. Validasi membuka bagian
  yang diperlukan tanpa menunggu animasi; fokus tetap ke kolom yang bermasalah.
- Prefers-reduced-motion dan perubahan preferensi ketika efek berjalan
  menyelesaikan gerak ke keadaan akhir. Browser tanpa WAAPI memakai keadaan
  langsung. Menu pada browser tanpa discrete display transitions memakai
  perilaku Bootstrap langsung.
- Menu akun tetap komponen Bootstrap. Pengamatan input pointer dilakukan pada
  window capture sebelum delegasi Bootstrap; pemanggilan API setelah event
  selesai tidak dianggap klik pointer. Posisi statis menu tetap di sisi pemicu.
- Pesan dinamis tetap memakai textContent. Nilai inventory, barcode, payload,
  endpoint, otorisasi dan transaksi server tidak diubah.

## Verifikasi

- Seluruh 169 tes frontend lulus, termasuk tujuh tes baru untuk pembalikan cepat,
  stale completion, keyboard/fokus, perubahan preferensi, fallback WAAPI,
  pembukaan disclosure saat invalid, cleanup cue dan origin input menu akun.
- 70 tes backend / 1.301 assertions lulus: rencana delivery, pemetaan part,
  download dan hak akses. Memakai SQLite testing in-memory.
- Render fixture dan stream template asli terpisah lulus satu tes / 12 assertions.
  Form/data yang dipakai dalam pratinjau fiktif, bukan database operasional.
- Build Vite, pemeriksaan sintaks JS dan diff --check pada file tahap ini lulus.
- Browser: mode Excel menunjukkan pose antara (opacity sekitar .79 dan offset
  kurang dari 1 px) pada preview 5×, lalu menetap. Menu pointer memakai 160 ms
  dengan origin atas kanan; aktivasi Enter memakai 0 ms.
- Impor melalui file chooser pada fixture menampilkan satu baris fiktif dan
  penegasan ikon. Parser/server bisnis tetap dicakup regresi backend; endpoint
  preview impor hanya simulasi untuk memeriksa UI.
- Buka/tutup disclosure beruntun lalu Tab berakhir dengan bagian terbuka,
  opacity 1 dan fokus pada checkbox. Submit dengan kode dokumen wajib kosong
  membuka bagian dan memfokuskan textarea tanpa animasi.
- Simulasi reduced motion menghasilkan keadaan akhir langsung tanpa transform.
  Pengaturan OS tidak diganti; perangkat ponsel fisik belum diuji.
- Download native mendapat status siap setelah acknowledgement, centang muncul,
  Tutup tersembunyi dan panel hilang otomatis. Struktur heading tetap flex.
- Viewport 390 px: menu 240 px berada dalam layar, tanpa overflow horizontal.
  Satu error MutationObserver muncul ketika sesi otomasi berganti iframe;
  source aplikasi yang dimuat tidak memakai observer tersebut. Tab halaman
  langsung sesudahnya tanpa error/warning. Asal pesan sesi review belum dapat
  dipastikan, sehingga tidak diklaim sebagai perbaikan source aplikasi.

## Pratinjau dan file

Pratinjau lokal: http://127.0.0.1:8772/alive-review.html. Pilihan Before/After,
halaman, gerak lambat 5× dan simulasi reduced motion hanya ada pada fixture.
Before dibekukan dari source sebelum tahap ini. Pratinjau memakai HTML hasil
render Blade serta CSS/JS halaman, dengan cache aset dimatikan agar revisi tidak
tercampur. Sidebar dan aksi penyimpanan dalam fixture bukan lingkungan operasional.

- `public/js/ui-motion.js`: surface/cue/disclosure dan integrasi menu akun.
- `public/js/pages/master-part-form.js`: pemetaan native dengan enhancement.
- `resources/js/export-delivery-plan.js`: panel mode serta penegasan hasil impor.
- `public/js/excel-download.js`: origin aktivasi dan ikon siap.
- `public/css/apple-design.css`, `public/css/navigation.css` dan
  `public/css/pages/export-delivery-plans.css`: penanda visual dan transisi.
- Layout/profile-dropdown, dua form master part dan form rencana delivery
  memuat integrasi terkait.

Rujukan tahap sebelumnya: `docs/UI_RULES_COMPLETION_2026-11-15.md` dan
`docs/UI_RULES_STATUS_2026-11-15.md`.
