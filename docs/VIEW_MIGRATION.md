# Controller View Path Migration Guide

## Struktur View Baru

Setelah reorganisasi, struktur views sekarang dikelompokkan berdasarkan role:

```
resources/views/
├── admin/           # Admin-only pages
├── supervisor/      # Supervisor pages
├── operator/        # Operator/warehouse pages
├── shared/          # Shared layouts
└── components/      # Reusable components
```

## Mapping View Paths (Old → New)

### Admin Views

| Old Path                | New Path                | Controller     |
| ----------------------- | ----------------------- | -------------- |
| `users.index`           | `admin.users.index`     | UserController |
| `users.create`          | `admin.users.create`    | UserController |
| `users.edit`            | `admin.users.edit`      | UserController |
| `admin.locations.index` | `admin.locations.index` | ✅ No change   |
| `admin.locations.edit`  | `admin.locations.edit`  | ✅ No change   |
| `admin.boxes.*`         | `admin.boxes.*`         | ✅ No change   |

### Supervisor Views

| Old Path                           | New Path                 | Controller                  |
| ---------------------------------- | ------------------------ | --------------------------- |
| `warehouse.box-not-full.approvals` | `supervisor.approvals`   | NotFullBoxRequestController |
| `audit.index`                      | `supervisor.audit.index` | AuditController             |

### Operator Views (formerly warehouse)

| Old Path                             | New Path                            | Controller                  |
| ------------------------------------ | ----------------------------------- | --------------------------- |
| `warehouse.part-settings.index`      | `operator.part-settings.index`      | PartSettingController       |
| `warehouse.part-settings.create`     | `operator.part-settings.create`     | PartSettingController       |
| `warehouse.part-settings.edit`       | `operator.part-settings.edit`       | PartSettingController       |
| `warehouse.stock-input.index`        | `operator.stock-input.index`        | StockInputController        |
| `warehouse.stock-withdrawal.index`   | `operator.stock-withdrawal.index`   | StockWithdrawalController   |
| `warehouse.stock-withdrawal.history` | `operator.stock-withdrawal.history` | StockWithdrawalController   |
| `warehouse.merge.index`              | `operator.merge.index`              | MergePalletController       |
| `warehouse.box-not-full.create`      | `operator.box-not-full.create`      | NotFullBoxRequestController |
| `warehouse.reports.*`                | `operator.reports.*`                | ReportController            |
| `delivery.index`                     | `operator.delivery.index`           | DeliveryOrderController     |
| `delivery.create`                    | `operator.delivery.create`          | DeliveryOrderController     |
| `delivery.edit`                      | `operator.delivery.edit`            | DeliveryOrderController     |
| `delivery.scan`                      | `operator.delivery.scan`            | DeliveryPickController      |
| `delivery.fulfill`                   | `operator.delivery.fulfill`         | DeliveryOrderController     |
| All other `delivery.*`               | `operator.delivery.*`               | DeliveryOrderController     |

### Shared Views

| Path                      | Status       |
| ------------------------- | ------------ |
| `shared.dashboard`        | ✅ No change |
| `shared.layouts.app`      | ✅ No change |
| `shared.stock-view.index` | ✅ No change |

## Controllers to Update

### 1. UserController

```php
// Before
return view('users.index', compact('users'));
return view('users.create');
return view('users.edit', compact('user'));

// After
return view('admin.users.index', compact('users'));
return view('admin.users.create');
return view('admin.users.edit', compact('user'));
```

### 2. PartSettingController

```php
// Before
return view('warehouse.part-settings.index', compact('parts'));
return view('warehouse.part-settings.create');
return view('warehouse.part-settings.edit', compact('partSetting'));

// After
return view('operator.part-settings.index', compact('parts'));
return view('operator.part-settings.create');
return view('operator.part-settings.edit', compact('partSetting'));
```

### 3. NotFullBoxRequestController

```php
// Before (approvals method)
return view('warehouse.box-not-full.approvals', compact('requests', 'historyRequests'));

// After
return view('supervisor.approvals', compact('requests', 'historyRequests'));

// Before (create method)
return view('warehouse.box-not-full.create', ...);

// After
return view('operator.box-not-full.create', ...);
```

### 4. StockInputController

```php
// Before
return view('warehouse.stock-input.index', ...);

// After
return view('operator.stock-input.index', ...);
```

### 5. StockWithdrawalController

```php
// Before
return view('warehouse.stock-withdrawal.index', ...);
return view('warehouse.stock-withdrawal.history', ...);

// After
return view('operator.stock-withdrawal.index', ...);
return view('operator.stock-withdrawal.history', ...);
```

### 6. MergePalletController

```php
// Before
return view('warehouse.merge.index', ...);

// After
return view('operator.merge.index', ...);
```

### 7. ReportController

```php
// Before
return view('warehouse.reports.stock-input', ...);
return view('warehouse.reports.withdrawal', ...);
return view('warehouse.reports.operational', ...);

// After
return view('operator.reports.stock-input', ...);
return view('operator.reports.withdrawal', ...);
return view('operator.reports.operational', ...);
```

### 8. DeliveryOrderController & DeliveryPickController

```php
// Before
return view('delivery.index', ...);
return view('delivery.create', ...);
return view('delivery.edit', ...);
return view('delivery.scan', ...);
return view('delivery.fulfill', ...);

// After
return view('operator.delivery.index', ...);
return view('operator.delivery.create', ...);
return view('operator.delivery.edit', ...);
return view('operator.delivery.scan', ...);
return view('operator.delivery.fulfill', ...);
```

### 9. AuditController

```php
// Before
return view('audit.index', ...);

// After
return view('supervisor.audit.index', ...);
```

## Script untuk Update Otomatis

Gunakan find-replace di semua controller files:

```bash
# Search for
'warehouse.part-settings.
# Replace with
'operator.part-settings.

# Search for
'warehouse.stock-input.
# Replace with
'operator.stock-input.

# Search for
'warehouse.stock-withdrawal.
# Replace with
'operator.stock-withdrawal.

# Search for
'warehouse.merge.
# Replace with
'operator.merge.

# Search for
'warehouse.box-not-full.approvals'
# Replace with
'supervisor.approvals'

# Search for
'warehouse.box-not-full.
# Replace with
'operator.box-not-full.

# Search for
'warehouse.reports.
# Replace with
'operator.reports.

# Search for
'delivery.
# Replace with
'operator.delivery.

# Search for
'users.
# Replace with
'admin.users.

# Search for
'audit.
# Replace with
'supervisor.audit.
```

## Testing Checklist

After updating controllers:

- [ ] Dashboard loads correctly
- [ ] Admin pages (users, locations, boxes)
- [ ] Supervisor pages (approvals, audit)
- [ ] Operator pages:
    - [ ] Stock input
    - [ ] Stock withdrawal & history
    - [ ] Part settings (index, create, edit)
    - [ ] Box not full request
    - [ ] Merge pallet
    - [ ] Delivery orders (all actions)
    - [ ] Reports (all types)

## Benefits

1. ✅ **Clear separation of concerns** by role
2. ✅ **Easier navigation** in codebase
3. ✅ **Better maintainability**
4. ✅ **Consistent naming** across the app
5. ✅ **Reusable components** for consistency
6. ✅ **Reduced code duplication**
