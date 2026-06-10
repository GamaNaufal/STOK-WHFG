# Rencana Perbaikan: Detail FIFO Modal & Indikator Box Not-Full (Revisi 5)

Sesuai arahan terbaru Anda, agar tampilan tabel jadwal delivery tetap bersih dan tidak penuh, detail FIFO tidak akan ditaruh di bawah baris tabel. 

Sebagai gantinya:
1. **Tombol "Detail FIFO"**: Jika sebuah delivery kekurangan stok (atau membutuhkan box *not-full*), tombol "Process" akan berubah menjadi tombol **"Detail FIFO"** yang aktif dan dapat diklik oleh siapa saja (Admin maupun Operator).
2. **Pop-up Detail FIFO**: Ketika tombol "Detail FIFO" diklik, modal rekomendasi akan muncul dan menampilkan daftar box FIFO yang siap diambil, serta baris peringatan khusus yang menjelaskan bahwa order tersebut tidak dapat diproses lebih lanjut karena membutuhkan box *not-full* beserta jumlah pcs sisa yang harus disiapkan oleh Admin.
3. **Pencegahan Proses**: Tombol konfirmasi "Proses Pengambilan" di dalam modal akan dinonaktifkan jika kebutuhan belum sepenuhnya terpenuhi.

---

## Proposed Changes

### StockWithdrawalController

#### [MODIFY] [StockWithdrawalController.php](file:///c:/Users/Asus/Documents/Project/StockYamato/app/Http/Controllers/StockWithdrawalController.php)

Ubah method `preview` agar tidak langsung mengembalikan error `422` ketika sisa stok tidak cukup. Sebaliknya, kembalikan daftar box yang *ada* (tersedia) beserta flag penanda `needs_not_full` dan jumlah kuantitas deficit:

```php
            if ($remainingQty > 0 && $plannedQty < $remainingQty && !$allowPartial) {
                // Diubah agar tetap mengembalikan data lokasi yang ada namun dengan flag needs_not_full
                return response()->json([
                    'success' => true,
                    'part_number' => $partNumber,
                    'requested_qty' => $requestedQty,
                    'planned_qty' => $plannedQty,
                    'needs_not_full' => true,
                    'not_full_pcs_needed' => $remainingQty - $plannedQty,
                    'locations' => array_merge($reservedLocations, $locations),
                ]);
            }
```

### Blade Views

#### [MODIFY] [index.blade.php](file:///c:/Users/Asus/Documents/Project/StockYamato/resources/views/operator/delivery/index.blade.php)

1. Ubah tombol aksi di tabel jadwal agar tombol untuk order dengan stok kurang tetap dapat diklik dengan nama **"Detail FIFO"**:
   ```html
                                @elseif($order->has_sufficient_stock)
                                    <button class="btn-process" onclick="openFulfillModal('{{ $order->id }}')">
                                        <i class="bi bi-box-seam"></i> Process
                                    </button>
                                @else
                                    <button class="btn-process" style="background: #f3f4f6; color: #4b5563; border: 1px solid #d1d5db;" onclick="openFulfillModal('{{ $order->id }}')">
                                        <i class="bi bi-info-circle"></i> Detail FIFO
                                    </button>
                                @endif
   ```

2. Perbarui fungsi Javascript `renderFulfillItems` di `index.blade.php` agar dapat menangani response preview yang memiliki flag `needs_not_full`:
   * Tampilkan tabel box FIFO yang ada.
   * Tampilkan pesan peringatan di bawah tabel: `"Kekurangan: Butuh Box Not Full (X Pcs)."` jika `data.needs_not_full` bernilai `true`.
   * Tandai `item.hasFifoError = true` agar tombol "Proses Pengambilan" di modal terkunci secara otomatis.

---

## Verification Plan

### Manual Verification
1. Daftarkan/buat Delivery Order dengan kebutuhan part A sebesar 110 pcs.
2. Pastikan di gudang hanya ada:
   - 2 box berukuran 50 pcs (total 100 pcs).
   - 1 box berukuran 20 pcs (tidak ada box berukuran 10 pcs).
3. Buka dashboard utama:
   - Tabel tetap bersih tanpa ada detail list box di bawah nama part.
   - Status stock part A menunjukkan `100 / 110`.
   - Tombol aksi berwarna abu-abu dengan tulisan **"Detail FIFO"** (dapat diklik).
4. Klik tombol **"Detail FIFO"**:
   - Modal pop-up terbuka.
   - Di dalam card part A, sistem menampilkan tabel rekomendasi berisi 2 box (masing-masing 50 pcs).
   - Di bawah tabel, muncul alert kuning/merah: **"Kekurangan: Butuh Box Not Full (10 Pcs). Proses pengambilan dikunci sampai box not-full tersedia."**
   - Tombol "Proses Pengambilan" di kanan bawah modal dinonaktifkan (disabled).
