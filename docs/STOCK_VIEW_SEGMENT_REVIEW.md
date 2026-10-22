# Lihat Stok — toolbar pencarian dan tampilan

18 Oktober 2026. Preferensi utama: apple-design, mengikuti DESIGN_PREFERENCES.md.

## Cakupan

Pencarian, urutan, dan navigasi Part Number / Box ID / Pallet / Box Not Full.
Backend, hak akses, perhitungan stok, export, tabel, dan modal detail tidak diubah.
Pilihan urutan menggunakan sortOptions yang sudah disediakan controller.

| Sebelum | Sesudah |
| --- | --- |
| Pencarian dan pilihan tampilan dipisahkan kartu ringkasan | Satu toolbar sebelum ringkasan |
| Mode berupa kelompok tombol panjang | Navigasi berlabel dengan aria-current, dua kolom di ponsel |
| Pencarian mengandalkan placeholder | Label terlihat, petunjuk, pencarian aktif dan tautan hapus |
| Saran berupa div dan berubah bobot saat hover | Tombol native, fokus terlihat, Tab/Enter dan Escape |
| Urutan terutama di header tabel | Select menggunakan pilihan backend; sorting tabel tetap tersedia |

## Verifikasi

- 20 tes regresi, 327 assertions lulus: StockViewApiContractTest,
  FeatureAccessManagementTest, UiReviewPreviewTest.
- Pemeriksaan sementara SQLite memory: empat mode merender sukses dan mempertahankan
  search, viewMode, sortMode (1 tes, 16 assertions). Berkas pemeriksaan dihapus.
- Browser menggunakan hasil render Blade dengan stok kosong dan saran simulasi,
  bukan data produksi. Desktop dan viewport 390 × 844 diperiksa; toolbar tidak overflow.
- Tab memfokuskan saran; Enter mengirim GET dengan search/view_mode/sort yang benar
  ke endpoint pratinjau. Escape menutup saran dan memfokuskan input.
- Hasil render mode Box Not Full menunjukkan pilihan aktif dan select urutan.
- git diff --check bersih. Tidak menguji perangkat sentuh fisik atau transaksi stok
  melalui browser; regresi backend diperiksa lewat tes aplikasi.

Tahap selanjutnya: audit tabel hasil dan modal detail Lihat Stok.

## Tabel hasil dan modal detail — 18 Oktober 2026

- Tabel hasil empat mode: ukuran teks dan padding konsisten, angka tabular,
  dekorasi identitas dikurangi, kontrol minimal 44px, caption serta scope kolom.
- Modal part/pallet: judul aksesibel, tombol tutup berlabel, header solid,
  area isi bergulir, tabel dapat difokuskan dan digulir pada layar kecil.
- Pesan pencarian kosong kini menyebut stok, bukan hanya nomor part.
- Backend, syarat izin, aksi edit/hapus, endpoint, dan template cetak tidak diubah.
- 31 tes regresi lulus (389 assertions): StockViewApiContractTest,
  StockViewBoxEditAuditTest, FeatureAccessManagementTest, UiReviewPreviewTest.
- Render sementara SQLite memory: empat mode dan dua endpoint detail sukses
  (6 assertions). Fixture awal Sales ditolak 403 sesuai hak akses; preview memakai Admin.
- Browser: modal part dan pallet berisi data fixture diperiksa, termasuk viewport
  390px. Isi modal tidak overflow horizontal; tabel lebar bergulir dalam wadah.
  API browser memakai respons fixture hasil tes, bukan database operasional.
- Tidak menjalankan aksi edit, hapus, atau cetak dari browser. Perangkat fisik
  dan data besar belum diverifikasi. Berkas/server preview sudah dibersihkan.

## Edit Box, riwayat, dan konfirmasi hapus — 18 Oktober 2026

- Ketiga modal memakai header solid, judul aksesibel, tombol tutup berlabel,
  area gulir, kontrol 44px, dan footer yang tetap terlihat.
- Label Edit Box terhubung dengan field; alasan koreksi memiliki petunjuk
  minimal 3 karakter dan pencatatan audit. Aturan/payload koreksi tetap.
- Riwayat memiliki kolom detail lebih lebar dan indikator loading berstatus.
  Pesan error di-escape sebelum dimasukkan ke HTML.
- Teks hapus permanen dikoreksi menjadi hapus dari stok aktif sesuai soft delete
  pada controller. Riwayat tetap tersimpan; tidak menjanjikan fasilitas restore.
- 31 tes lulus, 389 assertions. Render fixture dan endpoint history: 2 assertions.
- Browser fixture: Edit Box termasuk peringatan part tidak ada di master,
  riwayat kosong, serta konfirmasi hapus pada 390px. Tidak mengirim simpan/hapus.
  Riwayat panjang dan perangkat fisik belum diuji. Preview dibersihkan.

## Audit interaksi lengkap — 18 Oktober 2026

Acuan utama: `apple-design`; pemeriksaan detail interaksi menggunakan
`emil-design-eng`. Fokus tahap ini adalah feedback, validasi, pemulihan error,
keyboard, dan kesinambungan konteks. Controller, payload, otorisasi, aturan
inventory, serta isi template cetak tetap dipertahankan.

| Before | After | Why |
| --- | --- | --- |
| Detail gagal dapat meninggalkan modal kosong atau indikator loading yang rusak | Error terlihat di dalam modal, tersedia Coba lagi, spinner direset setiap permintaan | Pengguna tahu penyebab dan cara melanjutkan |
| Modal part, pallet, riwayat, edit, dan hapus menumpuk serta berebut fokus | Satu modal aktif; modal asal dan fokus tombol dipulihkan saat kembali | Navigasi keyboard dan konteks data tetap jelas |
| Escape pada pencarian Select2 ikut menutup Edit Box | Escape menutup pilihan part; modal edit tetap terbuka | Mencegah pembatalan yang tidak disengaja |
| Alasan pendek bisa masuk ke konfirmasi lewat pengisian programatis | Pemeriksaan panjang alasan sebelum konfirmasi, pesan dan fokus pada field | Validasi tetap konsisten dengan ketentuan server |
| Simpan/hapus belum memberi feedback proses yang memadai; error dapat tersembunyi di belakang modal | Label Menyimpan/Menghapus, kontrol dinonaktifkan selama request, error di dialog, input pulih saat gagal | Mencegah submit berulang dan menjaga data koreksi |
| Simpan hanya memuat ulang detail, sehingga tabel/ringkasan utama dapat tertinggal | Seluruh halaman dimuat ulang pada URL yang sama; pesan sukses muncul setelah reload | Angka dan aksi selalu memakai data terbaru |
| Peringatan menjanjikan konversi otomatis ke Not Full | Teks menjelaskan persetujuan request serta validasi server | Feedback sesuai proses bisnis yang berlaku |
| Respons HTML/error server menampilkan pesan teknis parsing | Pesan Indonesia yang dapat dipahami, tersedia jalur mencoba kembali | Pengguna dapat memulihkan kegagalan |
| Pencarian tanpa hasil menyarankan melakukan input stok | Petunjuk mengganti kata kunci atau menghapus pencarian | Tidak mendorong input ulang atas stok yang hanya tersaring |
| Simbol loading/riwayat rusak dan pesan Select2 berbahasa Inggris | Teks loading/riwayat diperbaiki, hasil master part kosong berbahasa Indonesia | Keterbacaan konsisten |

### Matriks pemeriksaan

| Area | Skenario dan hasil |
| --- | --- |
| Navigasi dan filter | Empat tab dibuka; pengurutan, pencarian Enter, reset, dan parameter URL diperiksa. Tes Laravel memeriksa empat mode dengan filter berisi/kosong. |
| Saran pencarian | Tab memfokuskan saran; Escape menutup saran dan kembali ke input. Pencarian ID box membuka detail pallet terkait. |
| Detail part/pallet | Data normal, loading lambat, buka-tutup saat request berjalan, error JSON/HTML, serta item pallet kosong diperiksa. Respons lama tidak menggantikan request yang lebih baru. |
| Riwayat | Data berisi dan kosong, error, serta Coba lagi setelah server pulih diperiksa. |
| Edit Box | Alasan kosong/pendek, PCS nol, tanpa perubahan, pilihan master part tanpa hasil, konfirmasi kembali, penolakan 422, respons HTML, dan sukses setelah reload diperiksa. Input tetap tersimpan saat gagal. |
| Hapus | Pembatalan box, konfirmasi isi pallet termasuk expired/Not Full, preview pallet gagal lalu berhasil melalui Coba lagi, proses hapus, penolakan server, dan sukses box diperiksa. Semua transaksi browser memakai respons simulasi. |
| Fokus | Part → pallet → riwayat/edit/hapus → kembali; fokus tombol asal dipulihkan. Escape Select2 tidak menutup modal edit. |
| Role | Tes memeriksa flag edit/hapus untuk Admin Utama, Admin Warehouse, Operator, SPV, PPC; Sales tetap 403 secara default. Tes izin dinamis dan pembatasan transaksi tetap lulus. |
| Ekspor | Tiga endpoint ekspor menghasilkan attachment XLSX (signature ZIP diperiksa). Klik Export Excel mode Pallet menghasilkan unduhan browser. |
| Cetak | Tombol Print dijalankan tanpa error JavaScript. Browser pengujian tidak mengekspos jendela/preview cetak untuk diperiksa; hasil cetak fisik belum terverifikasi. Jalur popup diblokir ditangani di modal dan ditinjau lewat source. |
| Responsif | Viewport 390 × 844: modal dan tombol terlihat, tabel lebar bergulir di wadahnya, tanpa overflow halaman. |

### Bukti dan batas pengujian

- 33 tes regresi, 441 assertions lulus: seluruh `StockView*`,
  `FeatureAccessManagementTest`, dan `UiReviewPreviewTest`.
- Tes baru `StockViewNavigationTest` mempertahankan pemeriksaan empat mode,
  hasil kosong, ekspor XLSX, dan matriks role sebagai regresi permanen.
- Fixture browser dihasilkan dari Blade dan API menggunakan SQLite memory
  (18 assertions). Respons mutasi sukses/gagal/lambat disimulasikan; perubahan
  database, audit, soft delete, dan penolakan box delivery diperiksa oleh tes
  Laravel yang sudah ada. Database operasional tidak dimutasi.
- Pemeriksaan sintaks JavaScript dan `git diff --check` lulus.
- Belum memverifikasi printer/scanner/perangkat sentuh fisik, dataset besar,
  atau seluruh kombinasi gangguan jaringan. Preview bersifat sementara;
  screenshot tidak disertakan dalam laporan kepada pengguna.

## Pemeriksaan ulang seluruh interaksi — 22 Oktober 2026

- Ditinjau ulang: empat mode dan sort, pencarian/saran/keyboard, hasil kosong,
  detail part/pallet, riwayat, edit/hapus dan konfirmasinya, retry/error/loading,
  ekspor/cetak, fokus modal, serta hak akses. Aturan transaksi dan endpoint tetap.
- Diperbaiki: info box tanpa pallet tidak lagi tampil sebagai error; teks perubahan
  riwayat yang rusak menjadi terbaca; aksi baris menyebut identitas stok untuk
  pembaca layar; status saran, sort, dan pil urutan tersampaikan secara semantik.
- Pada layar kecil, kolom identitas hasil tetap terlihat saat tabel digeser;
  petunjuk geser ditampilkan pada viewport sempit. Reduced motion dihormati
  saat baris hasil pencarian disorot.
- Setelah perubahan, 41 tes Laravel/526 assertions lulus, meliputi kontrak API,
  mode/filter/ekspor, role, audit edit dan penjagaan edit/hapus. Sintaks JS
  dan `git diff --check` lulus.
- Fixture UI terisolasi pada 390 × 844 memverifikasi overflow horizontal, kolom
  identitas tetap di tempat setelah scroll keyboard, saran/Escape, `aria-sort`,
  nama aksi dan warna/status pesan info. Ini bukan pengujian data produksi.
  Cetak fisik, perangkat sentuh/screen reader, dan dataset besar masih terbuka.
