# Ringkasan Sistem

## 1) System Architecture Overview

- **Frontend**: Blade views (Bootstrap CDN + Tailwind via Vite).
- **Backend**: Laravel Controllers + Services + Models.
- **Database**: MySQL (relasi utama: pallet, box, stock, delivery, audit).
- **Integrasi**: Export PDF (DomPDF) & Excel (Maatwebsite).

```mermaid
flowchart TB
  subgraph UI[Frontend UI]
    VIEWS[Blade Views]
  end

  subgraph APP[Laravel Application]
    ROUTES[Routes]
    CTRL[Controllers]
    SRV[Services]
    MDL[Models]
  end

  subgraph DB[(MySQL Database)]
    TABLES[Tables]
  end

  VIEWS --> ROUTES --> CTRL --> SRV --> MDL --> DB
```

---

## 2) Database Design (ERD)

```mermaid
erDiagram
  USERS {
    bigint id PK
    string name
    string email
    string role
  }

  PALLETS {
    bigint id PK
    string pallet_number
  }

  PALLET_ITEMS {
    bigint id PK
    bigint pallet_id FK
    string part_number
    int box_quantity
    int pcs_quantity
  }

  BOXES {
    bigint id PK
    string box_number
    string part_number
    int pcs_quantity
    bool is_withdrawn
  }

  PALLET_BOXES {
    bigint id PK
    bigint pallet_id FK
    bigint box_id FK
  }

  STOCK_LOCATIONS {
    bigint id PK
    bigint pallet_id FK
    string warehouse_location
    datetime stored_at
  }

  MASTER_LOCATIONS {
    bigint id PK
    string code
    bool is_occupied
    bigint current_pallet_id FK
  }

  STOCK_INPUTS {
    bigint id PK
    bigint pallet_id FK
    bigint pallet_item_id FK
    bigint user_id FK
    int pcs_quantity
    int box_quantity
    datetime stored_at
  }

  STOCK_WITHDRAWALS {
    bigint id PK
    uuid withdrawal_batch_id
    bigint user_id FK
    bigint pallet_item_id FK
    bigint box_id FK
    string part_number
    int pcs_quantity
    string status
    datetime withdrawn_at
  }

  DELIVERY_ORDERS {
    bigint id PK
    bigint sales_user_id FK
    string customer_name
    date delivery_date
    string status
  }

  DELIVERY_ORDER_ITEMS {
    bigint id PK
    bigint delivery_order_id FK
    string part_number
    int quantity
    int fulfilled_quantity
  }

  DELIVERY_PICK_SESSIONS {
    bigint id PK
    bigint delivery_order_id FK
    bigint created_by FK
    string status
    datetime started_at
    datetime completed_at
    datetime redo_until
    string completion_status
  }

  DELIVERY_PICK_ITEMS {
    bigint id PK
    bigint pick_session_id FK
    bigint box_id FK
    string part_number
    int pcs_quantity
    string status
    datetime scanned_at
    bigint scanned_by FK
  }

  DELIVERY_ISSUES {
    bigint id PK
    bigint pick_session_id FK
    bigint box_id FK
    string issue_type
    string status
    bigint resolved_by FK
  }

  AUDIT_LOGS {
    bigint id PK
    string type
    string action
    string model
    bigint model_id
    bigint user_id FK
    datetime created_at
  }

  USERS ||--o{ STOCK_INPUTS : creates
  USERS ||--o{ STOCK_WITHDRAWALS : withdraws
  USERS ||--o{ DELIVERY_ORDERS : creates
  USERS ||--o{ DELIVERY_PICK_SESSIONS : starts
  USERS ||--o{ DELIVERY_PICK_ITEMS : scans
  USERS ||--o{ AUDIT_LOGS : logs

  PALLETS ||--o{ PALLET_ITEMS : has
  PALLETS ||--o{ PALLET_BOXES : holds
  BOXES ||--o{ PALLET_BOXES : in
  PALLETS ||--o{ STOCK_LOCATIONS : stored_at
  MASTER_LOCATIONS ||--o{ STOCK_LOCATIONS : reference

  DELIVERY_ORDERS ||--o{ DELIVERY_ORDER_ITEMS : has
  DELIVERY_ORDERS ||--o{ DELIVERY_PICK_SESSIONS : pick
  DELIVERY_PICK_SESSIONS ||--o{ DELIVERY_PICK_ITEMS : contains
  DELIVERY_PICK_SESSIONS ||--o{ DELIVERY_ISSUES : issue
```

---

## 3) Master Data Tables

- `users` (role user)
- `master_locations` (kode lokasi)
- `part_settings` (qty_box default)

---

## 4) Inventory Tables

- `pallets`
- `pallet_items`
- `boxes`
- `pallet_boxes`
- `stock_locations`

---

## 5) Transaction Tables

- `stock_inputs`
- `stock_withdrawals`
- `delivery_orders`
- `delivery_order_items`
- `delivery_pick_sessions`
- `delivery_pick_items`
- `delivery_issues`

---

## 6) Audit & Logging Tables

- `audit_logs`

---

## 7) Business Flow Diagrams

### Inbound Flow (Stock Input)

```mermaid
flowchart TD
  A[Scan Box / Part] --> B[Session Pallet]
  B --> C[Create/Update Pallet]
  C --> D[Attach Box to Pallet]
  D --> E[Save Stock Location]
  E --> F[Insert Stock Input]
  F --> G[Audit Log]
```

### Outbound Flow (Withdrawal / Delivery)

```mermaid
flowchart TD
  A[Request Withdrawal/Delivery] --> B[Check Stock]
  B --> C[Generate Withdrawal Batch]
  C --> D[Update Pallet Items / Boxes]
  D --> E[Stock Withdrawal]
  E --> F[Audit Log]
```

---

## 8) Sales & PPC Approval Flow

```mermaid
flowchart TD
  A[Sales Create Order] --> B[PPC Review]
  B -->|Approve| C[Status: approved]
  B -->|Reject| D[Status: rejected]
  B -->|Correction| E[Status: correction]
  E --> A
  C --> F[Warehouse Fulfillment]
```

---

## 9) RBAC (Role Based Access Control)

- **admin**: akses penuh.
- **admin_warehouse**: lokasi, part settings, scan issues, stock view.
- **warehouse_operator**: stock input, delivery fulfillment, merge pallet, stock view.
- **sales**: create & edit delivery order.
- **ppc**: approval delivery order.
- **supervisi**: reports & stock view.

Role enforcement utama ada di [routes/web.php](routes/web.php) dan controller terkait.

---

## 10) Backend API Structure

**Routing utama**: [routes/web.php](routes/web.php)

- **Auth**: /login, /logout
- **Stock Input**: /stock-input/\*
- **Delivery**: /delivery-stock/\*
- **Reports**: /reports/\*
- **Stock View API**:
    - /api/stock/by-part
    - /api/stock/part-detail/{partNumber}
    - /api/stock/pallet-detail/{palletId}
    - /api/locations/search

---

## 11) Frontend UI Structure

- Layout: resources/views/shared/layouts/app.blade.php
- Dashboard: resources/views/shared/dashboard.blade.php
- Stock Input: resources/views/warehouse/stock-input/\*
- Stock View: resources/views/shared/stock-view/\*
- Reports: resources/views/warehouse/reports/\*
- Delivery: resources/views/delivery/\*
- Master data: resources/views/admin/_, resources/views/users/_

---

## 12) Tech Stack

- **Backend**: Laravel 12, PHP 8.2
- **Database**: MySQL
- **Frontend**: Blade, Bootstrap CDN, Tailwind (Vite)
- **Export**: DomPDF, Maatwebsite Excel
- **Build tools**: Vite
