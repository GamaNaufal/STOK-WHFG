# Penyelesaian sisa penerapan rules UI/UX

Terakhir diverifikasi: 15 November 2026.

Empat prioritas sisa dari pembahasan terakhir telah diselesaikan dalam satu
rangkaian. Acuan utama tetap apple-design dan preferensi ritme hasil evaluasi
pengguna. Penerapan berpusat pada komponen bersama, master part, rencana delivery
ekspor dan modal koreksi box. Transaksi, payload, route dan izin server tetap.

## Before dan after

| Area / rule | Before tahap ini | After |
| --- | --- | --- |
| Download template, Doherty / Jakob | Unduh template Excel pada rencana delivery belum memakai feedback download bersama. | Memakai helper yang sama dengan laporan: indikator persiapan, konfirmasi server, pencegahan klik ganda, error/fallback dan sukses menutup otomatis 3 detik. Native download dan izin impor existing tetap. |
| Target klik, Fitts / bigger hit areas | Tombol tutup bersama dapat berukuran 32 px; aksi tabel pada desktop dapat berukuran 36–40 px. | Tombol tutup bersama minimal 44 × 44 px. Tombol Bootstrap dan aksi operasional existing di tabel/modal minimal 44 px pada kedua sumbu, tanpa memperbesar ikon atau menumpuk area klik tak terlihat. |
| Konsistensi, overall appeal | Border/fokus umum dapat menimpa warna validasi. Judul modal dan teks error panjang belum punya aturan wrapping bersama yang lengkap. | Input is-invalid / aria-invalid=true tetap merah ketika difokuskan, termasuk outline keyboard. Judul, pesan error dan bantuan dapat membungkus teks; footer modal memakai jarak bersama. |
| Master part, Hick / Miller / staging | Kode label, pemetaan dokumen dan kapasitas berada pada satu alur panjang, termasuk ketika kode dokumen sama. | Kelompok 01 mendahulukan identitas dan kapasitas. Kelompok 02 memakai details/summary native untuk pemetaan dokumen, dengan ringkasan nilai pilihan. |
| Edit / error pemetaan | Semua pemetaan selalu ditampilkan. | Pemetaan berbeda atau error server langsung terbuka. Jika bagian ditutup lalu kolom wajib belum diisi, validasi membukanya dan browser memfokuskan kolom tersebut. |
| Data form | Seluruh field tersedia dalam form biasa. | Field tetap berada dalam form dan ikut FormData meski disclosure ditutup. Leading zero, slash, baris kode, name, batas panjang dan nilai checkbox dipertahankan. |
| Fallback master part | Form dapat diisi tanpa enhancement pemetaan. | Markup details awalnya terbuka, sehingga seluruh field tetap tersedia tanpa script master part. Browser native menangani keyboard disclosure. |
| Rencana delivery, Miller / staging | Tiga bagian pekerjaan sudah tersedia tanpa penanda urutan visual. | Urutan 01 sumber input, 02 tujuan pengiriman dan 03 kebutuhan part lebih mudah dipindai. Kolom penting tetap langsung tersedia. |
| Modal koreksi box, Miller / overall appeal | Seluruh kolom berada dalam satu kelompok visual. | Fieldset 01 Data box dan 02 Alasan koreksi memperjelas apa yang diperbaiki dan alasan auditnya. Semua kolom wajib tetap terlihat. |

## Keputusan animasi

Penegasan sukses, squash/stretch, pose to pose, follow through dan volume badge
yang sudah diterapkan tetap dipertahankan. Feedback tekan langsung/pelepasan
120 ms, penegasan sukses 240 ms dan ritme analitik yang dipilih pengguna tetap.
Disclosure dan pergantian fokus tidak menambah animasi atau jeda.

Anticipation wind-up dan moving in arcs sudah dievaluasi dan berstatus tidak
relevan untuk interaksi yang dikerjakan: tidak ada gesture lempar atau perpindahan
objek yang membutuhkan persiapan/lintasan tersebut. Keputusan ini mengikuti
frequency of use pada operasi warehouse, bukan pekerjaan implementasi tertunda.

## Verifikasi

- 70 tes backend / 1.301 assertions lulus: ExportDeliveryPlanTest,
  PartDocumentNumberTest, ExcelDownloadStatusTest, GranularFeatureAccessTest dan
  FeatureAccessManagementTest. Memakai konfigurasi testing SQLite in-memory.
- Satu pengujian sementara untuk render pratinjau dan stream template asli lulus,
  12 assertions. Koneksi SQLite in-memory diperiksa eksplisit sebelum render.
  ZIP serta XML workbook template hasil controller valid.
- Seluruh 162 tes frontend lulus; sintaks master-part-form.js dan diff --check
  pada file perubahan lulus.
- Browser memakai HTML hasil render Blade, CSS asli dan script halaman dengan
  data fiktif. Form valid hanya menampilkan FormData pada fixture, tidak menyimpan
  ke database operasional. Unduhan contoh melayani stream template kosong yang
  dihasilkan controller saat testing.
- Master part: Enter membuka/menutup summary; memilih kode berbeda memfokuskan
  textarea; submit kolom wajib kosong membuka disclosure dan memfokuskannya.
  FormData mempertahankan `001-ABC`, `DOC/SECOND`, kapasitas dan pilihan saat
  disclosure tertutup. Error server tetap terbuka dan border/fokus merah.
- Fixture tanpa script master-part-form.js tetap menampilkan seluruh field.
  Form edit mempertahankan kode label, kapasitas dan pemetaan existing.
- Template: double click menghasilkan satu iframe, aria-busy pulih setelah
  ready, Tutup tersembunyi pada sukses, lalu panel hilang otomatis.
- Desktop: tombol tutup modal 44 × 44 px dan tombol footer setinggi 44 px;
  Escape menutup modal dan mengembalikan fokus ke pemicu Bootstrap.
- Viewport 390 × 844: form master/edit, rencana delivery, modal dan tabel master
  tidak menyebabkan overflow horizontal halaman. Tabel lebar bergulir dalam
  wadahnya. Tombol tabel, hapus baris delivery dan template setinggi 44 px.
  Body modal dapat digulir dan footer tetap berada dalam viewport.
- Console halaman yang diperiksa tanpa warning/error. Reduced motion mengikuti
  aturan existing; tahap ini tidak menambah gerak baru. Ponsel fisik belum diuji.

## File utama

- `public/css/apple-design.css`: hierarki form/disclosure, validasi, wrapping,
  target klik dan footer modal bersama.
- `public/js/pages/master-part-form.js`: enhancement pemetaan, fokus dan invalid.
- `resources/views/operator/part-settings/{create,edit,_document-numbers}.blade.php`:
  kelompok identitas/kapasitas dan pemetaan native.
- `resources/views/export/delivery-plans/form.blade.php`: download template dan
  urutan bagian pekerjaan.
- `resources/views/shared/stock-view/partials/modals.blade.php`: kelompok koreksi.

Aset tahap ini berupa CSS/JS public dan Blade, sehingga tidak memerlukan build
Vite, migration atau deployment untuk verifikasi lokal. Peta seluruh rules dan
riwayat tahap sebelumnya: `docs/UI_RULES_STATUS_2026-11-15.md`.
