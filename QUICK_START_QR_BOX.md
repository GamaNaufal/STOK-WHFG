# QUICK START GUIDE - QR BOX SCANNING SYSTEM

## 🎯 Ringkas Perubahan

### Flow Lama → Flow Baru

**LAMA (Deprecated):**

```
Packing Dept → Input Palet (UI) → Warehouse → Cari Palet → Input Lokasi
```

**BARU (Current):**

```
Admin → Buat QR Box → Warehouse → Scan QR → Auto Palet → Input Lokasi
```

---

## 👨‍💼 Untuk ADMIN

### Tugas: Membuat Kode QR untuk Box

#### Steps:

1. **Login** → Dashboard
2. **Menu** → Klik "Kelola Box QR"
3. **Create** → Klik tombol "Buat Box Baru"
4. **Form:**
    - No Box: `BOX-001` (harus unik)
    - Part Number: Pilih dari dropdown (e.g., PN-A001)
    - Jumlah PCS: `100`
5. **Generate** → Klik "Buat Box & Generate QR"
6. **Result** → QR code langsung tampil
7. **Print** → Cetak untuk ditempel di box fisik

#### QR Code Berisi:

```
BOX-001|PN-A001|100
```

(Format: NoBox|PartNumber|JumlahPCS)

#### Tips:

- ✅ Buat satu QR per box
- ✅ Setiap input = QR berbeda
- ✅ QR bisa diprint berkali-kali
- ✅ Nomor box HARUS unik (tidak boleh duplikat)

---

## 👷 Untuk WAREHOUSE OPERATOR

### Tugas: Scan QR Box & Input Stok

#### Steps:

**STEP 1: Scan QR Box**

1. **Buka** → Stock Input page (`/stock-input`)
2. **Focus** di input field (ada kursor otomatis)
3. **Scan** QR code dari box pertama
    - Gunakan barcode scanner/mobile scanner
    - Atau input manual: `BOX-001|PN-A001|100` + Enter
4. **Result:**
    - ✅ Box berhasil terscan
    - ✅ Palet auto-generate: `PLT-20260120-001`
    - ✅ Lihat detail palet muncul di bawah

**STEP 2: Scan Box Berikutnya (Opsional)**

1. **Scan** QR code dari box kedua
2. **Result:**
    - ✅ Ditambah ke palet yang sama
    - ✅ Jumlah box bertambah
    - ✅ Total PCS terupdate

**STEP 3: Repeat**

- Scan semua box dalam palet yang sama
- Setiap scan auto-increment box count

**STEP 4: Input Lokasi**

1. **Scroll** ke bawah, lihat "Step 2: Tentukan Lokasi Penyimpanan"
2. **Input** lokasi:
    - Format: `[RAK]-[BARIS]-[POSISI]`
    - Contoh: `A-1-1`, `B-2-3`, `C-3-5`
3. **Save** → Klik "Simpan Stok"
4. **Confirm** → Muncul success message

**STEP 5: Palet Baru**

1. **Klik** "Mulai Palet Baru"
2. **Confirm** → Session clear
3. **Ready** → Scan box untuk palet berikutnya

#### Error Messages & Solusi:

| Error                               | Penyebab               | Solusi                                |
| ----------------------------------- | ---------------------- | ------------------------------------- |
| "Format QR code tidak valid"        | Input format salah     | Scan ulang atau input: BOX\|PART\|PCS |
| "Box tidak ditemukan di sistem"     | Box belum dibuat admin | Minta admin untuk buat QR box dulu    |
| "Box ini sudah ada dalam palet ini" | Scan box yang sama 2x  | Jangan scan box yang sama             |
| "Masukkan lokasi penyimpanan"       | Lokasi kosong          | Isi format: A-1-1                     |

#### Tips:

- 📱 Gunakan tablet/mobile untuk scan
- 🔍 Pastikan QR code jelas
- 📝 Lokasi harus konsisten dengan sistem penyimpanan
- 💾 Save otomatis clear session (ready palet baru)

---

## 🗂️ Struktur Sistem

```
Admin
├── Buat QR Box
│   ├── Input: No Box, Part, PCS
│   ├── Generate: QR Code
│   └── Output: QR untuk print

Warehouse
├── Scan QR Box #1
│   ├── Auto-generate Palet #1
│   ├── Session store: Palet ID
│   └── Display: Palet details
├── Scan QR Box #2
│   ├── Check: Palet aktif?
│   ├── Add to: Palet #1
│   └── Update: Box count, Total PCS
├── Repeat...
└── Input Lokasi
    ├── Save: Stock Location
    ├── Create: Stock Input records
    └── Clear: Session
```

---

## 📊 Database Flow

```
Admin creates Box
├── Box table: id, box_number, part_number, pcs_quantity, qr_code, user_id

Warehouse scans Box
├── 1. Create Pallet (if first scan)
│   └── Pallet table: id, pallet_number
├── 2. Link Box to Pallet
│   └── Pallet_boxes table: pallet_id, box_id
└── 3. Update or Create PalletItem
    └── PalletItem table: pallet_id, part_number, box_qty, pcs_qty

Warehouse saves Lokasi
├── Create StockLocation
│   └── StockLocation table: pallet_id, warehouse_location
└── Create StockInput records
    └── StockInput table: pallet_id, item_id, lokasi, qty, ...
```

---

## 🔐 Akses Kontrol

| User Role     | Akses                                            |
| ------------- | ------------------------------------------------ |
| **Admin**     | ✅ Buat QR Box, ✅ View Reports, ✅ Manage Stock |
| **Warehouse** | ✅ Scan QR, ✅ Input Stok, ✅ View Stock         |
| **Guest**     | ❌ Semua                                         |

---

## ⌨️ Keyboard Shortcuts

| Aksi           | Shortcut                      |
| -------------- | ----------------------------- |
| Focus QR input | Tap input field / Ctrl+L      |
| Scan QR        | Barcode Scanner → Auto Enter  |
| Submit Lokasi  | Tab → Enter                   |
| Clear/Reset    | Button "Mulai Palet Baru"     |
| Print QR       | Ctrl+P (di halaman QR detail) |

---

## 🆘 Troubleshooting

### Problem: Scanner tidak baca QR

**Solution:**

1. Print QR code minimal 5cm × 5cm
2. Pastikan contrast cukup (print quality OK)
3. Clean scanner lens
4. Adjust scanner distance (biasanya 10-15cm)

### Problem: Palet tidak auto-generate

**Solution:**

1. Pastikan box sudah dibuat admin (ada di database)
2. Check format QR: `BOX|PART|PCS` (harus tepat)
3. Scan box pertama dulu (akan trigger generate palet)

### Problem: Lokasi tidak kebaca

**Solution:**

1. Format: `A-1-1` bukan `A 1 1` atau `A-1-1-1`
2. Pastikan RAK, BARIS, POSISI sesuai storage system
3. Check konsistensi dengan lokasi sebelumnya

### Problem: Session hilang / Palet reset

**Cause:**

- Browser di-refresh
- Timeout (default 2 jam)
- Logout-login

**Solution:**

- Data sudah disave di database
- Mulai scan palet baru (auto-generate baru)
- Check database untuk verifikasi data

---

## 📱 Supported Devices

| Device              | Kompatibel | Tips                          |
| ------------------- | ---------- | ----------------------------- |
| **Desktop**         | ✅         | Best untuk admin              |
| **Tablet**          | ✅         | Best untuk warehouse scanning |
| **Mobile**          | ✅         | OK, tapi screen kecil         |
| **Barcode Scanner** | ✅         | Plug & play, auto-enter       |

---

## 🎓 Training Checklist

### Admin Training

- [ ] Mengerti flow QR box creation
- [ ] Bisa membuat QR box baru
- [ ] Tahu cara print QR code
- [ ] Tahu bagaimana warehouse scan
- [ ] Tahu nomor box harus unik

### Warehouse Training

- [ ] Mengerti QR scan flow
- [ ] Bisa setup barcode scanner
- [ ] Tahu format lokasi: RAK-BARIS-POSISI
- [ ] Tahu cara clear session untuk palet baru
- [ ] Bisa handle error messages

---

## 📞 Support

**Issues:**

1. Check docs: `REVISI_QR_BOX_IMPLEMENTATION.md`
2. Check troubleshooting di bawah help button
3. Contact admin dengan screenshot error

---

## Version

**Current:** v1.0.0
**Release:** 20 Jan 2026
**Status:** Production Ready ✅

---

## Catatan Penting

⚠️ **JANGAN:**

- ❌ Hapus/edit QR box dari database
- ❌ Input nomor palet manual (auto-generate)
- ❌ Scan box yang sama 2x dalam palet
- ❌ Input lokasi tidak sesuai format

✅ **LAKUKAN:**

- ✅ Cetak QR code sebelum packing
- ✅ Scan dalam urutan yang benar
- ✅ Input lokasi dengan format tepat
- ✅ Report error ke admin
- ✅ Backup database regular

---

## Questions?

**Refer to:**

- Admin: `REVISI_QR_BOX_IMPLEMENTATION.md` - Full tech docs
- Warehouse: This document + on-screen help
- Everyone: `IMPLEMENTATION_SUMMARY.md` - Technical reference

---

**Happy Scanning! 🎉**
