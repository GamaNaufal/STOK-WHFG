# Database Simplification Recommendations

**Current Status:** Database sudah dioptimasi tapi masih ada redundansi yang bisa dihilangkan

## Current Issues

### 1. **Redundant Box-Pallet Relationship** ⚠️

**Current:**

- `boxes` table (individual boxes)
- `pallet_boxes` table (junction table - boxes to pallets many-to-many)
- `pallet_items` table (summary of items per pallet)

**Problem:**

- Duplikasi data: pallet_items adalah summary dari pallet_boxes + boxes
- Jika ada box yang dihapus, perlu update 3 tabel
- Query lebih kompleks dengan joins 3 tabel

**Rekomendasi:**

```
SIMPLIFY: Gunakan pallet_items as single source of truth
- pallet_items berisi part_number + quantities
- boxes berisi detail individual box
- Link boxes → pallet_items (many-to-one)
- HAPUS pallet_boxes junction table
```

---

### 2. **Redundant Location Tracking** ⚠️

**Current:**

- `master_locations` (lokasi fisik dengan status)
- `stock_locations` (pallet di lokasi mana)

**Problem:**

- 2 tabel untuk concept yang sama
- `master_locations.current_pallet_id` + `stock_locations.warehouse_location` duplikasi info

**Rekomendasi:**

```
OPTION A - Gunakan master_locations saja:
- Tambah pallet_id langsung ke pallets table
- Hapus stock_locations
- master_locations.current_pallet_id = foreign key ke pallets

OPTION B - Rename & consolidate:
- stock_locations → pallet_locations
- Gunakan ini sebagai single source (relationship pallets → pallet_locations)
- master_locations hanya untuk metadata warehouse (code, capacity, type)
```

---

### 3. **Transaction Tables Could Merge** ⚠️

**Current:**

- `stock_inputs` (stok masuk ke warehouse)
- `stock_withdrawals` (stok keluar dari warehouse)

**Problem:**

- 2 tabel untuk 2 transaksi direction
- Logic inventory = query ke 2 tabel

**Rekomendasi:**

```
SIMPLIFY: Gunakan stock_transactions table
CREATE TABLE stock_transactions (
  - id
  - transaction_type: ENUM('input', 'withdrawal', 'adjustment')
  - pallet_item_id
  - quantity
  - user_id
  - created_at
  - ...
)

Benefit:
- Single source of truth untuk inventory movement
- Easier audit trail
- Simpler queries
```

---

### 4. **Delivery Pick Process** ⚠️

**Current:**

- `delivery_pick_sessions` (session scanning)
- `delivery_pick_items` (items dalam session)
- `delivery_scan_issues` (masalah saat scan)
- `delivery_completions` (completion record)

**Problem:**

- Banyak tabel untuk process yang simple
- delivery_completions bisa jadi field di delivery_pick_sessions

**Rekomendasi:**

```
SIMPLIFY:
1. Merge delivery_completions ke delivery_pick_sessions
   - Tambah: completed_at, redo_until, completion_status
   - Status: pending → completed bisa simpan di status enum

2. Keep delivery_pick_items (detail items)

3. Keep delivery_scan_issues (mungkin pindah ke generic issues table)
```

---

## Proposed Architecture

### CORE INVENTORY (Simplified)

```
pallets
├─ id
├─ pallet_number
├─ location_id (FK → master_locations) ← NEW
├─ created_at

pallet_items
├─ id
├─ pallet_id (FK)
├─ part_number
├─ box_quantity
├─ pcs_quantity
├─ created_at

boxes
├─ id
├─ box_number
├─ pallet_item_id (FK) ← CHANGED (was pallet_boxes)
├─ part_number
├─ pcs_quantity
├─ is_withdrawn
├─ created_at

master_locations
├─ id
├─ code
├─ capacity
├─ location_type (ENUM)
├─ is_occupied
├─ created_at

stock_transactions (MERGED from input + withdrawal)
├─ id
├─ transaction_type (input, withdrawal, adjustment)
├─ pallet_item_id (FK)
├─ quantity
├─ user_id
├─ created_at
```

### DELIVERY (Simplified)

```
delivery_orders
├─ id
├─ status (ENUM)
├─ created_at

delivery_order_items
├─ id
├─ delivery_order_id (FK)
├─ part_number
├─ quantity
├─ fulfilled_quantity
├─ created_at

delivery_pick_sessions (MERGED with completions)
├─ id
├─ delivery_order_id (FK)
├─ status (pending, scanning, approved, completed, cancelled)
├─ completed_at ← NEW
├─ redo_until ← NEW
├─ created_at

delivery_pick_items
├─ id
├─ pick_session_id (FK)
├─ box_id (FK)
├─ status (pending, scanned, verified)
├─ created_at

delivery_issues (RENAMED from delivery_scan_issues)
├─ id
├─ pick_session_id (FK)
├─ issue_type (scan_mismatch, box_damaged, etc)
├─ status (pending, resolved)
├─ created_at
```

---

## Implementation Plan

### Phase 1: Core Inventory Refactor ✓ (Medium Effort)

1. Add `location_id` to `pallets` table
2. Add `pallet_item_id` to `boxes` table
3. Migrate data from pallet_boxes
4. Drop `pallet_boxes` table
5. Drop `stock_locations` table
6. Update models & relationships

**Migration Steps:**

```bash
1. php artisan make:migration migrate_boxes_to_pallet_items
2. php artisan make:migration merge_stock_locations_to_pallets
3. php artisan migrate
4. Update Models (Box, Pallet, PalletItem)
5. Update Controllers & Services
```

**Effort:** 2-3 hours  
**Risk:** Medium (data migration)  
**Benefit:** 40% simpler inventory logic

---

### Phase 2: Transaction Table ✓ (Medium Effort)

1. Create `stock_transactions` table
2. Migrate data from stock_inputs + stock_withdrawals
3. Drop old tables
4. Create TransactionService
5. Update all withdrawal/input logic

**Effort:** 2-3 hours  
**Risk:** Medium (audit trail preservation)  
**Benefit:** Single source of truth for inventory

---

### Phase 3: Delivery Simplification ✓ (Low Effort)

1. Add fields to `delivery_pick_sessions`
2. Drop `delivery_completions` table
3. Update DeliveryPickController
4. Rename `delivery_scan_issues` → `delivery_issues`

**Effort:** 1-2 hours  
**Risk:** Low  
**Benefit:** Cleaner delivery workflow

---

## Before & After Comparison

| Aspect                      | Before | After  | Improvement |
| --------------------------- | ------ | ------ | ----------- |
| **Tables**                  | 27     | 20     | -26%        |
| **Joins (Inventory Query)** | 4-5    | 2-3    | -40%        |
| **Redundant Data**          | High   | Low    | Cleaner     |
| **Query Complexity**        | Medium | Simple | Easier      |
| **Maintenance**             | Harder | Easier | Better      |

---

## What NOT to Change

### Keep As-Is:

- ✅ `part_settings` (useful for standardization)
- ✅ `delivery_order_items` (necessary detail)
- ✅ `delivery_pick_items` (audit trail)
- ✅ Laravel system tables (cache, jobs, sessions, migrations)
- ✅ Authentication (users, password_reset)

---

## Risk Assessment

### Low Risk:

- Merge stock_inputs + stock_withdrawals (complete history)
- Rename delivery_scan_issues
- Merge delivery_completions

### Medium Risk:

- Drop pallet_boxes (need careful data migration)
- Drop stock_locations (but keep as history)
- Reorganize boxes ↔ pallet_items relationship

### Mitigation:

- Backup database sebelum start
- Create comprehensive migrations
- Test with sample data
- Keep old tables as archive first
- Update code gradually

---

## Recommendation

### 🟢 Do Phase 3 (Delivery) First

- Low risk, quick win
- Gives confidence for bigger changes

### 🟡 Do Phase 1 (Inventory) Next

- Medium effort but high benefit
- Core system improvement
- Easier after Phase 3

### 🟡 Do Phase 2 (Transactions) Last

- Medium effort
- Biggest impact on simplicity
- Can do after team is confident

---

## Quick Win (15 minutes)

Just want simpler database without big refactor?

✅ **Minimal Changes:**

1. Consolidate `stock_inputs` + `stock_withdrawals` → `stock_transactions`
2. Merge `delivery_completions` → `delivery_pick_sessions`
3. No data migration needed - just add columns, migrate data, drop old tables

This gives 50% of the benefit with 10% of the effort.

---

**Status:** Ready for discussion & planning
