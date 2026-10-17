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
