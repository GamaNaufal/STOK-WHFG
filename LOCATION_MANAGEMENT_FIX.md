# Location Management Fix

**Issue:** Ketika delivery diambil, stok berkurang tapi lokasi masih terisi penuh seharusnya kosong.

**Root Cause:**

- Sistem tidak auto-update `master_locations.is_occupied` ketika semua item di pallet sudah habis diambil
- Tidak ada logic untuk vacate (mengosongkan) lokasi setelah stok habis

## Solution Implemented

### 1. **Enhanced MasterLocation Model** ✅

**File:** [app/Models/MasterLocation.php](app/Models/MasterLocation.php)

#### New Methods:

- `isPalletEmpty()` - Check apakah pallet masih ada stok
- `autoVacateIfEmpty()` - Auto vacate location jika pallet kosong
- `vacate()` - Manually vacate location

#### Implementation:

```php
/**
 * Check jika pallet masih punya stok
 */
public function isPalletEmpty()
{
    if (!$this->current_pallet_id) {
        return true;
    }

    $pallet = $this->currentPallet;
    if (!$pallet) {
        return true;
    }

    // Cek apakah masih ada items dengan quantity > 0
    return !$pallet->items()
        ->where(function ($q) {
            $q->where('pcs_quantity', '>', 0)
              ->orWhere('box_quantity', '>', 0);
        })
        ->exists();
}

/**
 * Auto vacate jika pallet empty
 */
public function autoVacateIfEmpty()
{
    if ($this->isPalletEmpty()) {
        $this->vacate();
    }
}
```

### 2. **Updated DeliveryPickController** ✅

**File:** [app/Http/Controllers/DeliveryPickController.php](app/Http/Controllers/DeliveryPickController.php)

#### Changes:

- Import `MasterLocation` model
- Auto-vacate location setelah setiap box diambil

#### Code:

```php
// After updating pallet_items in complete() method:
if ($pallet) {
    $masterLocation = MasterLocation::where('current_pallet_id', $pallet->id)->first();
    if ($masterLocation) {
        $masterLocation->autoVacateIfEmpty();
    }
}
```

### 3. **Updated StockWithdrawalController** ✅

**File:** [app/Http/Controllers/StockWithdrawalController.php](app/Http/Controllers/StockWithdrawalController.php)

#### Changes:

- Import `MasterLocation` model
- Auto-vacate di `confirm()` method
- Auto-vacate di `store()` method

#### Impact:

- Baik delivery pick maupun manual withdrawal sekarang auto-update location status

### 4. **New LocationManagementService** ✅

**File:** [app/Services/LocationManagementService.php](app/Services/LocationManagementService.php)

#### Features:

- `autoVacateEmptyPallets()` - Batch update semua empty locations
- `updateLocationStatus()` - Update location untuk satu pallet
- `occupyLocation()` - Safely occupy location
- `getUtilizationPercentage()` - Get warehouse utilization stats

#### Usage:

```php
// Setelah withdrawal atau picking selesai
LocationManagementService::updateLocationStatus($pallet);

// Get statistics
$utilization = LocationManagementService::getUtilizationPercentage();
$available = LocationManagementService::getAvailableLocationsCount();
```

### 5. **Improved Database Schema** ✅

**Migration:** `2026_01_26_100003_improve_location_tracking.php`

#### Changes:

- Add index pada `master_locations.current_pallet_id` untuk faster lookups
- Add UNIQUE constraint pada `current_pallet_id` (satu pallet hanya di satu lokasi)

## How It Works

### Before (Broken)

```
1. Box diambil dari delivery
2. ✅ StockWithdrawal created
3. ✅ Box marked as withdrawn
4. ✅ PalletItem quantity decreased
5. ❌ Location still marked as occupied (BUG!)
```

### After (Fixed)

```
1. Box diambil dari delivery
2. ✅ StockWithdrawal created
3. ✅ Box marked as withdrawn
4. ✅ PalletItem quantity decreased
5. ✅ CHECK: Apakah pallet masih ada stok?
6. ✅ IF EMPTY: Location auto-vacated (is_occupied = false)
```

## Flow Diagram

```
Delivery Pick Complete
    ↓
For each picked item:
    ↓
    Update PalletItem quantity
    ↓
    Get MasterLocation for this pallet
    ↓
    Check: isPalletEmpty()?
    ├─ YES → vacate() [is_occupied = false]
    └─ NO → keep occupied
    ↓
Done
```

## Benefits

✅ **Data Integrity** - Location status selalu akurat  
✅ **Automatic** - Tidak perlu manual update  
✅ **Consistent** - Works for delivery pick & manual withdrawal  
✅ **Efficient** - Only vacates when truly empty  
✅ **Auditable** - Can track location history

## Testing

### Test Case 1: Single Box Pallet

```
1. Pallet A punya 1 box (500 PCS)
2. Ambil box tersebut untuk delivery
3. Expected: Lokasi menjadi kosong (is_occupied = false)
4. Result: ✅ PASS
```

### Test Case 2: Multi-Box Pallet

```
1. Pallet B punya 3 boxes
2. Ambil 2 boxes
3. Expected: Lokasi masih terisi (is_occupied = true)
4. Ambil box ke-3
5. Expected: Lokasi menjadi kosong (is_occupied = false)
6. Result: ✅ PASS
```

### Test Case 3: Partial Quantity Withdrawal

```
1. PalletItem punya 1000 PCS di 10 boxes
2. Ambil 500 PCS (5 boxes)
3. Expected: Lokasi masih terisi
4. Ambil sisa 500 PCS (5 boxes)
5. Expected: Lokasi kosong
6. Result: ✅ PASS
```

## Migration Info

**New Migration:** `2026_01_26_100003_improve_location_tracking.php`

Run:

```bash
php artisan migrate
```

---

**Status:** ✅ Fixed - Location management now auto-updates
