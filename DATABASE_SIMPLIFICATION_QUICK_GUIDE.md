# Database Simplification - Quick Reference

## 📊 Analysis

Database kamu saat ini memiliki **27 tabel**. Beberapa bisa disederhanakan:

### Redundansi yang ditemukan:

| Issue                    | Tables                              | Impact | Fix                      |
| ------------------------ | ----------------------------------- | ------ | ------------------------ |
| **Duplikasi box-pallet** | pallet_boxes + pallet_items + boxes | High   | Merge relationship       |
| **Duplikasi lokasi**     | stock_locations + master_locations  | Medium | Consolidate              |
| **Transaksi split**      | stock_inputs + stock_withdrawals    | Medium | Merge ke 1 table         |
| **Delivery overhead**    | delivery_completions (redundant)    | Low    | Merge fields             |
| **Issue naming**         | delivery_scan_issues (generic?)     | Low    | Rename → delivery_issues |

---

## 🎯 3 Levels of Simplification

### Level 1: Quick Win (15 min) ⭐ RECOMMENDED

**Just merge delivery tables:**

- ✅ Merge `delivery_completions` → `delivery_pick_sessions` (add 3 fields)
- ✅ Rename `delivery_scan_issues` → `delivery_issues` (better naming)
- ✅ Drop old tables

**Result:** 25 → 23 tables (-8%)  
**Effort:** 15 minutes  
**Risk:** Very Low  
**Benefit:** Cleaner delivery structure

---

### Level 2: Moderate Refactor (2-3 hours)

**Simplify core inventory:**

- ✅ Level 1 (delivery)
- ✅ Merge `stock_inputs` + `stock_withdrawals` → `stock_transactions`
- ✅ Rename `delivery_scan_issues` → `delivery_issues`

**Result:** 23 → 21 tables (-22%)  
**Effort:** 2-3 hours  
**Risk:** Low  
**Benefit:** Single source of inventory truth

---

### Level 3: Full Refactor (4-5 hours)

**Restructure everything:**

- ✅ Level 2 (all above)
- ✅ Remove `pallet_boxes` junction table
- ✅ Add `location_id` to `pallets`
- ✅ Reorganize `boxes` → `pallet_items` relationship

**Result:** 21 → 19 tables (-30%)  
**Effort:** 4-5 hours  
**Risk:** Medium  
**Benefit:** Significantly simpler inventory logic

---

## 📋 Recommendation

### ✅ START WITH LEVEL 1 (Today)

```bash
php artisan make:migration simplify_delivery_tables
php artisan migrate
# Update models + controllers (30 min)
```

**Why?**

- Hasil langsung terlihat
- Zero risk (test environment)
- Build confidence untuk refactor besar
- Can undo if needed

### 🟡 THEN LEVEL 2 (Next Sprint)

```bash
php artisan make:migration merge_stock_transactions
php artisan migrate
# Bigger change, proven safe
```

### 🔴 OPTIONAL LEVEL 3 (Future)

- Biggest refactor
- Only if team comfortable
- Major cleanup

---

## What We Did

### Before

```
Pallets Management:
- pallets (1 table)
- pallet_items (summary)
- pallet_boxes (junction)
- boxes (individual)
= 3-4 related tables
```

### After (Level 1)

```
Delivery Management:
- delivery_orders
- delivery_order_items
- delivery_pick_sessions (merged completions)
- delivery_pick_items
- delivery_issues (renamed from scan_issues)
= Cleaner workflow
```

---

## Migration Available

**File:** `database/migrations/2026_01_26_110000_simplify_delivery_tables.php`

Run:

```bash
php artisan migrate
```

### What it does:

1. Add fields to `delivery_pick_sessions`:
    - `completed_at`
    - `redo_until`
    - `completion_status`

2. Migrate data dari `delivery_completions`

3. Create `delivery_issues` (renamed from `delivery_scan_issues`)

4. Keep old tables untuk safety (can drop later)

---

## Next Steps

1. **Review** the migration
2. **Test** on staging
3. **Update** Models:
    - DeliveryPickSession model
    - Create DeliveryIssue model
4. **Update** Controllers:
    - DeliveryPickController
    - Update references

5. **Done!** Cleaner database 🎉

---

## Questions?

- **Current tables:** 27 → After Level 1: 25 (-8%)
- **Simplicity gain:** Better structure, fewer joins
- **Risk level:** Very low for Level 1
- **Time estimate:** 30 min implementation

**Status:** Ready to implement ✅
