# Design System - Warehouse FG Yamato

## Brand Colors

- **Primary**: #0C7779 (Teal Dark)
- **Secondary**: #249E94 (Teal Light)
- **Success**: #10b981 (Green)
- **Danger**: #ef4444 (Red)
- **Warning**: #f59e0b (Orange)
- **Info**: #3b82f6 (Blue)

## Components

### 1. Alert (`<x-alert>`)

Notifikasi untuk success, error, warning, info dengan styling konsisten.

**Usage:**

```blade
<x-alert type="success" message="Data berhasil disimpan!" />
<x-alert type="error">Terjadi kesalahan!</x-alert>
```

**Props:**

- `type`: success, error, warning, info (default: success)
- `dismissible`: true/false (default: true)
- `message`: Pesan alert

### 2. Page Header (`<x-page-header>`)

Header halaman dengan gradient brand color.

**Usage:**

```blade
<x-page-header
    title="Master No Part"
    subtitle="Kelola daftar nomor part"
    icon="list-check">
    <x-slot:action>
        <x-button href="{{ route('part-settings.create') }}" icon="plus-circle">
            Tambah No Part
        </x-button>
    </x-slot:action>
</x-page-header>
```

**Props:**

- `title`: Judul halaman
- `subtitle`: Subtitle/deskripsi
- `icon`: Bootstrap icon name
- `action`: Slot untuk tombol/aksi

### 3. Card (`<x-card>`)

Container card dengan styling konsisten.

**Usage:**

```blade
<x-card title="Daftar Data" icon="table" borderColor="#0C7779">
    <!-- Content here -->
</x-card>
```

**Props:**

- `title`: Judul card
- `icon`: Bootstrap icon name
- `borderColor`: Warna border kiri (default: #0C7779)
- `headerColor`: Warna header (default: #0C7779)
- `noPadding`: true untuk table (default: false)

### 4. Button (`<x-button>`)

Tombol dengan styling konsisten dan hover effects.

**Usage:**

```blade
<x-button variant="primary" icon="save">Simpan</x-button>
<x-button variant="danger" size="sm" icon="trash">Hapus</x-button>
<x-button href="{{ route('index') }}" variant="light" icon="arrow-left">Kembali</x-button>
```

**Props:**

- `variant`: primary, secondary, success, danger, warning, light
- `size`: sm, md, lg
- `icon`: Bootstrap icon name
- `type`: button, submit (default: button)
- `href`: URL jika digunakan sebagai link

### 5. Pagination (`<x-pagination>`)

Pagination dengan styling modern dan informasi halaman.

**Usage:**

```blade
<x-pagination :paginator="$items" />
```

**Props:**

- `paginator`: Instance dari paginator Laravel

### 6. Modal Delete (`<x-modal-delete>`)

Modal konfirmasi hapus dengan styling konsisten.

**Usage:**

```blade
<x-modal-delete
    id="deletePartModal"
    title="Hapus No Part"
    message="Anda yakin ingin menghapus No Part berikut?"
    itemName="-" />
```

**Props:**

- `id`: ID modal (default: deleteModal)
- `title`: Judul modal
- `message`: Pesan konfirmasi
- `itemName`: Nama item yang akan dihapus

### 7. Empty State (`<x-empty-state>`)

Tampilan saat tidak ada data.

**Usage:**

```blade
<x-empty-state
    icon="inbox"
    title="Belum ada data"
    message="Mulai dengan menambahkan data baru">
    <x-slot:action>
        <x-button href="{{ route('create') }}" icon="plus">Tambah Data</x-button>
    </x-slot:action>
</x-empty-state>
```

**Props:**

- `icon`: Bootstrap icon name
- `title`: Judul pesan
- `message`: Deskripsi
- `action`: Slot untuk tombol aksi

## Struktur Views (Reorganisasi)

```
resources/views/
├── components/          # Reusable components
│   ├── alert.blade.php
│   ├── button.blade.php
│   ├── card.blade.php
│   ├── empty-state.blade.php
│   ├── modal-delete.blade.php
│   ├── page-header.blade.php
│   └── pagination.blade.php
│
├── shared/              # Shared layouts & components
│   ├── layouts/
│   │   └── app.blade.php
│   ├── dashboard.blade.php
│   └── stock-view/
│       └── index.blade.php
│
├── admin/               # Admin role pages
│   ├── locations/       # Master locations management
│   │   ├── index.blade.php
│   │   └── edit.blade.php
│   ├── boxes/           # Box management
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── show.blade.php
│   │   └── scanner.blade.php
│   └── users/           # User management
│       ├── index.blade.php
│       ├── create.blade.php
│       └── edit.blade.php
│
├── supervisor/          # Supervisor role pages
│   ├── approvals/       # Box not full approvals
│   │   └── index.blade.php
│   └── audit/           # Audit trails
│       └── index.blade.php
│
└── operator/            # Operator role pages (warehouse)
    ├── stock-input/
    │   └── index.blade.php
    ├── stock-withdrawal/
    │   ├── index.blade.php
    │   └── history.blade.php
    ├── part-settings/   # Master part settings
    │   ├── index.blade.php
    │   ├── create.blade.php
    │   └── edit.blade.php
    ├── box-not-full/    # Box not full requests
    │   └── create.blade.php
    ├── merge/           # Pallet merging
    │   └── index.blade.php
    ├── delivery/        # Delivery orders
    │   ├── index.blade.php
    │   ├── create.blade.php
    │   ├── edit.blade.php
    │   ├── scan.blade.php
    │   └── fulfill.blade.php
    └── reports/         # Operational reports
        ├── stock-input.blade.php
        ├── withdrawal.blade.php
        └── operational.blade.php
```

## Migration Plan

1. ✅ **Create reusable components** in `resources/views/components/`
2. **Reorganize view structure** berdasarkan role
3. **Update existing views** untuk menggunakan components baru
4. **Test all functionalities** setelah refactoring
5. **Update controllers** jika ada perubahan path

## Example: Before & After

### Before (Inline Styles)

```blade
<div class="alert alert-success">
    <i class="bi bi-check-circle"></i> Data berhasil disimpan!
</div>
```

### After (Component)

```blade
<x-alert type="success" message="Data berhasil disimpan!" />
```

## Consistency Guidelines

1. **Always use brand colors** (#0C7779, #249E94)
2. **Use components** instead of inline styles
3. **Consistent spacing**: 40px top/bottom for headers, 15px for table cells
4. **Consistent border-radius**: 12px for cards, 8px for buttons
5. **Icons**: Always use Bootstrap Icons (`bi-*`)
6. **Hover effects**: Smooth transitions (0.3s ease)
7. **Empty states**: Always provide helpful messages and actions
8. **Error handling**: Use appropriate alert types and messages
