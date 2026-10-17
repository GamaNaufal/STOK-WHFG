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
