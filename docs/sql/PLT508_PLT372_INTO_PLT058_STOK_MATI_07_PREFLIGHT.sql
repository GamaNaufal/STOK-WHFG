/*
  PREFLIGHT SAJA -- tidak melakukan INSERT/UPDATE/DELETE/DDL.
  TARGET TERBARU: seluruh box yang masih tersimpan pada PLT-508 dan PLT-372
                 -> PLT-058, yang sudah berada di STOK MATI 07.
  Isi existing PLT-058 harus dipertahankan; tujuan BUKAN PLT-508.
  Termasuk expired, tanpa mengubah status/nomor/PCS/tanggal masuk box.
  Box withdrawn/deleted tetap sebagai histori, tidak diaktifkan/dipindah kembali.
  Menggantikan rencana lama PLT-372 -> PLT-508; jangan memakai target lama.
  Jalankan pada database aplikasi produksi, lalu kirim hasil pemeriksaan.
  Query mutasi harus disesuaikan setelah hasil ini diperiksa.
  Server tetap beroperasi: hasil SELECT ini bukan snapshot terkunci dan bukan
  persetujuan eksekusi. Transaksi mutasi harus memeriksa ulang kondisi dengan lock,
  membatasi waktu menunggu, dan rollback bila data berubah/terkunci/tidak sesuai.
  Row lock hanya berlaku sampai transaksi berakhir; tidak memperbaiki scheduler
  kode produksi lama yang dapat menghapus lokasi expired pada aktivitas berikutnya.
  Jangan memakai skrip CASE1/CASE2/global cleanup untuk pekerjaan ini.
*/

/* 1. Konteks database dan versi, tanpa menampilkan kredensial. */
SELECT DATABASE() AS database_name, VERSION() AS database_version,
       @@session.sql_mode AS sql_mode, @@session.time_zone AS session_time_zone,
       NOW() AS database_time;

/* 2. Mesin tabel dan fitur yang benar-benar tersedia pada server lama. */
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'pallets', 'boxes', 'pallet_boxes', 'pallet_items', 'stock_locations',
    'master_locations', 'stock_inputs', 'stock_input_boxes', 'stock_withdrawals',
    'delivery_pick_items', 'delivery_pick_sessions', 'delivery_issues',
    'not_full_box_requests', 'audit_logs', 'operation_locks',
    'expired_box_handlings', 'expired_box_transactions',
    'export_stock_pallets', 'export_planned_pallets',
    'export_pallet_location_reservations'
  )
ORDER BY TABLE_NAME;

SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA,
       COLLATION_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'pallets', 'boxes', 'pallet_boxes', 'pallet_items', 'stock_locations',
    'master_locations', 'not_full_box_requests', 'audit_logs'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('pallets', 'pallet_boxes', 'pallet_items', 'stock_locations', 'master_locations')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

/* Trigger dapat menulis tabel lain: harus diketahui sebelum menjamin lingkup mutasi. */
SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING, EVENT_MANIPULATION,
       ACTION_STATEMENT
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE()
  AND EVENT_OBJECT_TABLE IN ('pallets', 'pallet_boxes', 'pallet_items', 'stock_locations', 'master_locations', 'audit_logs')
ORDER BY EVENT_OBJECT_TABLE, TRIGGER_NAME;

/* 3. Identitas target: termasuk soft-delete agar tidak memilih record yang salah. */
SELECT p.*
FROM pallets p
WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
ORDER BY p.pallet_number, p.id;

/* Ringkasan isi existing tujuan dan kedua sumber; tidak menjumlahkan histori keluar. */
SELECT p.id, p.pallet_number, p.deleted_at,
       CASE WHEN p.pallet_number = 'PLT-058' THEN 'TUJUAN: pertahankan isi existing'
            ELSE 'SUMBER: pindahkan box fisik ke PLT-058' END AS planned_role,
       COUNT(pb.id) AS total_link_rows,
       SUM(CASE WHEN b.id IS NOT NULL AND b.deleted_at IS NULL AND b.is_withdrawn = 0
                     AND (b.expired_status IS NULL OR b.expired_status <> 'handled')
                THEN 1 ELSE 0 END) AS stored_box_count,
       SUM(CASE WHEN b.id IS NOT NULL AND b.deleted_at IS NULL AND b.is_withdrawn = 0
                     AND (b.expired_status IS NULL OR b.expired_status <> 'handled')
                THEN b.pcs_quantity ELSE 0 END) AS stored_pcs,
       SUM(CASE WHEN b.deleted_at IS NOT NULL OR b.is_withdrawn = 1 THEN 1 ELSE 0 END) AS historical_box_count
FROM pallets p
LEFT JOIN pallet_boxes pb ON pb.pallet_id = p.id
LEFT JOIN boxes b ON b.id = pb.box_id
WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
GROUP BY p.id, p.pallet_number, p.deleted_at
ORDER BY p.pallet_number, p.id;

/* Scope (bila tersedia) terlihat pada m.* tanpa mengasumsikan versi skema baru. */
SELECT m.*
FROM master_locations m
WHERE m.code = 'STOK MATI 07'
   OR m.current_pallet_id IN (
       SELECT id FROM pallets WHERE pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
   )
   OR m.id IN (
       SELECT sl.master_location_id FROM stock_locations sl
       JOIN pallets p ON p.id = sl.pallet_id
       WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
   )
ORDER BY m.id;

SELECT sl.*, p.pallet_number, p.deleted_at AS pallet_deleted_at
FROM stock_locations sl
LEFT JOIN pallets p ON p.id = sl.pallet_id
WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
   OR sl.warehouse_location = 'STOK MATI 07'
   OR sl.master_location_id IN (SELECT id FROM master_locations WHERE code = 'STOK MATI 07')
ORDER BY sl.id;

/* 4. Box fisik dan histori yang terhubung: tidak mengaktifkan expired. */
SELECT p.id AS pallet_id, p.pallet_number, pb.id AS pivot_id,
       b.id AS box_id, b.box_number, b.part_number, b.pcs_quantity,
       b.expired_status, b.reactivated_until, b.is_withdrawn,
       b.deleted_at AS box_deleted_at, b.assigned_delivery_order_id,
       CASE
         WHEN b.id IS NULL THEN 'STOP: pivot tanpa box'
         WHEN b.deleted_at IS NOT NULL OR b.is_withdrawn = 1 THEN 'HISTORI: tidak dipindah'
         WHEN b.expired_status = 'handled' THEN 'STOP: periksa penanganan QA/legacy'
         ELSE 'FISIK: masuk tinjauan merge'
       END AS classification
FROM pallets p
JOIN pallet_boxes pb ON pb.pallet_id = p.id
LEFT JOIN boxes b ON b.id = pb.box_id
WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
ORDER BY p.pallet_number, pb.id;

/* Semua relasi box yang sama, termasuk ke pallet ketiga/soft-deleted. */
SELECT pb.box_id, b.box_number, pb.id AS pivot_id,
       p.id AS linked_pallet_id, p.pallet_number,
       p.deleted_at AS linked_pallet_deleted_at
FROM pallet_boxes pb
JOIN pallets p ON p.id = pb.pallet_id
LEFT JOIN boxes b ON b.id = pb.box_id
WHERE pb.box_id IN (
  SELECT scoped.box_id FROM pallet_boxes scoped
  JOIN pallets target ON target.id = scoped.pallet_id
  WHERE target.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
)
ORDER BY pb.box_id, pb.id;

SELECT pi.*
FROM pallet_items pi
JOIN pallets p ON p.id = pi.pallet_id
WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
ORDER BY pi.pallet_id, pi.id;

/* 5. Picking/delivery dan Not Full: tidak mengubah atau membatalkan proses lain. */
SELECT p.pallet_number, i.id AS pick_item_id, i.box_id,
       s.id AS session_id, s.delivery_order_id, s.status,
       s.completion_status, s.redo_until
FROM pallets p
JOIN pallet_boxes pb ON pb.pallet_id = p.id
JOIN delivery_pick_items i ON i.box_id = pb.box_id
JOIN delivery_pick_sessions s ON s.id = i.pick_session_id
WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
ORDER BY s.id, i.id;

SELECT r.*
FROM not_full_box_requests r
WHERE r.target_pallet_id IN (SELECT id FROM pallets WHERE pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372'))
   OR r.target_location_id IN (SELECT id FROM master_locations WHERE code = 'STOK MATI 07')
   OR r.box_id IN (
     SELECT pb.box_id FROM pallet_boxes pb
     JOIN pallets p ON p.id = pb.pallet_id
     WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
   )
ORDER BY r.id;

/* Histori header dan referensi ringkasan; sumber tidak akan dihapus permanen. */
SELECT si.*
FROM stock_inputs si
JOIN pallets p ON p.id = si.pallet_id
WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
ORDER BY si.id;

SELECT w.id, w.pallet_item_id, w.box_id, w.part_number, w.pcs_quantity,
       w.status, w.withdrawn_at, w.deleted_at
FROM stock_withdrawals w
WHERE w.pallet_item_id IN (
  SELECT pi.id FROM pallet_items pi
  JOIN pallets p ON p.id = pi.pallet_id
  WHERE p.pallet_number IN ('PLT-058', 'PLT-508', 'PLT-372')
)
ORDER BY w.id;
