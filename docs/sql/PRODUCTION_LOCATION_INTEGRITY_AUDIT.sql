/*
    Production location integrity audit (READ-ONLY)

    Tujuan:
    - memeriksa schema/index yang melindungi assignment lokasi;
    - memeriksa unique index dan duplicate nomor pallet;
    - mencatat nomor pallet terbesar sebelum allocator diaktifkan;
    - menemukan relasi stock_locations yang yatim atau tidak konsisten;
    - menemukan perbedaan antara master_locations dan stock_locations;
    - menyediakan bukti sebelum cleanup data production.

    Skrip ini tidak melakukan INSERT, UPDATE, DELETE, atau ALTER.
    Jalankan seluruh bagian pada database production yang sama dengan aplikasi.
    Simpan hasil setiap query sebelum melakukan perbaikan data.
*/

/* 1. Verifikasi unique index dan foreign key yang diharapkan. */
SELECT
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'stock_locations'
  AND INDEX_NAME IN (
      'stock_locations_pallet_id_unique',
      'stock_locations_master_location_id_unique'
  )
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'stock_locations'
  AND COLUMN_NAME IN ('pallet_id', 'master_location_id')
ORDER BY CONSTRAINT_NAME, COLUMN_NAME;

/* 2. Verifikasi unique index nomor pallet. */
SELECT
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pallets'
  AND INDEX_NAME = 'pallets_pallet_number_unique'
ORDER BY SEQ_IN_INDEX;

/* 3. Duplicate nomor pallet. Hasil normalnya kosong. */
SELECT
    pallet_number,
    COUNT(*) AS pallet_count,
    GROUP_CONCAT(id ORDER BY id) AS pallet_ids
FROM pallets
GROUP BY pallet_number
HAVING COUNT(*) > 1
ORDER BY pallet_number;

/* 4. Nomor pallet terbesar sebagai baseline sebelum migration allocator. */
SELECT
    MAX(CAST(SUBSTRING_INDEX(pallet_number, '-', -1) AS UNSIGNED)) AS max_pallet_number,
    COUNT(*) AS pallet_number_count
FROM pallets
WHERE pallet_number LIKE 'PLT-%';

SELECT
    id,
    pallet_number,
    deleted_at,
    created_at
FROM pallets
WHERE pallet_number LIKE 'PLT-%'
ORDER BY CAST(SUBSTRING_INDEX(pallet_number, '-', -1) AS UNSIGNED) DESC
LIMIT 10;

/* 5. Konflik struktural lokasi. Dengan unique index aktif, hasil normalnya kosong. */
SELECT
    pallet_id,
    COUNT(*) AS stock_location_count,
    GROUP_CONCAT(id ORDER BY id) AS stock_location_ids
FROM stock_locations
GROUP BY pallet_id
HAVING COUNT(*) > 1;

SELECT
    master_location_id,
    COUNT(*) AS stock_location_count,
    GROUP_CONCAT(id ORDER BY id) AS stock_location_ids
FROM stock_locations
WHERE master_location_id IS NOT NULL
GROUP BY master_location_id
HAVING COUNT(*) > 1;

/* 6. stock_locations yang menunjuk pallet atau master location yang tidak valid. */
SELECT
    sl.id AS stock_location_id,
    sl.pallet_id,
    sl.master_location_id,
    sl.warehouse_location,
    sl.stored_at,
    p.pallet_number,
    p.deleted_at AS pallet_deleted_at,
    ml.code AS master_location_code
FROM stock_locations sl
LEFT JOIN pallets p ON p.id = sl.pallet_id
LEFT JOIN master_locations ml ON ml.id = sl.master_location_id
WHERE p.id IS NULL
   OR p.deleted_at IS NOT NULL
   OR (sl.master_location_id IS NOT NULL AND ml.id IS NULL)
ORDER BY sl.id;

/* 7. stock_locations dengan kode lokasi yang tidak sesuai master_locations. */
SELECT
    sl.id AS stock_location_id,
    sl.pallet_id,
    sl.master_location_id,
    sl.warehouse_location,
    ml.code AS master_location_code
FROM stock_locations sl
LEFT JOIN master_locations ml ON ml.id = sl.master_location_id
WHERE sl.master_location_id IS NOT NULL
  AND (
      ml.id IS NULL
      OR UPPER(TRIM(sl.warehouse_location)) <> UPPER(TRIM(ml.code))
  )
ORDER BY sl.id;

/* 8. Perbandingan ownership: master location vs stock location. */
SELECT
    ml.id AS master_location_id,
    ml.code,
    ml.is_occupied,
    ml.current_pallet_id,
    sl.pallet_id AS stock_location_pallet_id,
    sl.id AS stock_location_id,
    p.pallet_number
FROM master_locations ml
LEFT JOIN stock_locations sl ON sl.master_location_id = ml.id
LEFT JOIN pallets p ON p.id = sl.pallet_id
WHERE ml.current_pallet_id IS NOT NULL
  AND (
      sl.id IS NULL
      OR sl.pallet_id <> ml.current_pallet_id
      OR ml.is_occupied = 0
  )
ORDER BY ml.id;

SELECT
    ml.id AS master_location_id,
    ml.code,
    ml.is_occupied,
    ml.current_pallet_id,
    sl.pallet_id AS stock_location_pallet_id,
    sl.id AS stock_location_id,
    p.pallet_number
FROM master_locations ml
JOIN stock_locations sl ON sl.master_location_id = ml.id
LEFT JOIN pallets p ON p.id = sl.pallet_id
WHERE ml.is_occupied = 0
   OR ml.current_pallet_id IS NULL
   OR ml.current_pallet_id <> sl.pallet_id
ORDER BY ml.id;

/* 9. Master location occupied tetapi tidak memiliki pallet yang valid. */
SELECT
    ml.id AS master_location_id,
    ml.code,
    ml.is_occupied,
    ml.current_pallet_id,
    p.pallet_number,
    p.deleted_at AS pallet_deleted_at
FROM master_locations ml
LEFT JOIN pallets p ON p.id = ml.current_pallet_id
LEFT JOIN stock_locations sl
    ON sl.master_location_id = ml.id
   AND sl.pallet_id = ml.current_pallet_id
WHERE ml.is_occupied = 1
  AND (
      ml.current_pallet_id IS NULL
      OR p.id IS NULL
      OR p.deleted_at IS NOT NULL
      OR sl.id IS NULL
  )
ORDER BY ml.id;

/* 10. Pallet dengan lokasi tetapi tidak memiliki inventory aktif.
      Ini kandidat review, bukan otomatis data yang boleh dihapus. */
SELECT
    sl.id AS stock_location_id,
    sl.pallet_id,
    p.pallet_number,
    sl.master_location_id,
    sl.warehouse_location,
    sl.stored_at,
    COUNT(DISTINCT CASE
        WHEN b.deleted_at IS NULL
         AND b.is_withdrawn = 0
         AND (b.expired_status IS NULL OR b.expired_status NOT IN ('handled', 'expired'))
        THEN b.id
    END) AS active_box_count,
    COUNT(DISTINCT b.id) AS all_box_history_count
FROM stock_locations sl
JOIN pallets p ON p.id = sl.pallet_id
LEFT JOIN pallet_boxes pb ON pb.pallet_id = p.id
LEFT JOIN boxes b ON b.id = pb.box_id
GROUP BY
    sl.id,
    sl.pallet_id,
    p.pallet_number,
    sl.master_location_id,
    sl.warehouse_location,
    sl.stored_at
HAVING active_box_count = 0
ORDER BY sl.id;

/* 11. Pallet aktif yang tidak memiliki stock_locations.
      Pallet tanpa lokasi tidak selalu salah; hasil ini perlu direview
      bersama stock_inputs/pallet_items dan status operasionalnya. */
SELECT
    p.id AS pallet_id,
    p.pallet_number,
    p.deleted_at,
    COUNT(DISTINCT CASE
        WHEN b.deleted_at IS NULL
         AND b.is_withdrawn = 0
         AND (b.expired_status IS NULL OR b.expired_status NOT IN ('handled', 'expired'))
        THEN b.id
    END) AS active_box_count,
    COUNT(DISTINCT pi.id) AS pallet_item_count,
    COUNT(DISTINCT si.id) AS stock_input_count
FROM pallets p
LEFT JOIN stock_locations sl ON sl.pallet_id = p.id
LEFT JOIN pallet_boxes pb ON pb.pallet_id = p.id
LEFT JOIN boxes b ON b.id = pb.box_id
LEFT JOIN pallet_items pi ON pi.pallet_id = p.id
LEFT JOIN stock_inputs si ON si.pallet_id = p.id
WHERE p.deleted_at IS NULL
  AND sl.id IS NULL
GROUP BY p.id, p.pallet_number, p.deleted_at
HAVING active_box_count > 0
    OR pallet_item_count > 0
    OR stock_input_count > 0
ORDER BY p.id;

/* 12. Status tabel allocator nomor pallet.
       Sebelum migration, hasilnya harus menunjukkan 0 baris.
       Setelah migration, hasilnya harus menunjukkan 1 baris id=1. */
SELECT
    TABLE_NAME,
    TABLE_TYPE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'pallet_number_sequences';

/* Jalankan query berikut setelah migration allocator berhasil:
   SELECT id, next_number, created_at, updated_at
   FROM pallet_number_sequences
   ORDER BY id;
*/

/* 13. Detail khusus untuk lokasi yang pernah dilaporkan.
      Ganti 201 dengan master_location_id dari error production. */
SELECT
    ml.id AS master_location_id,
    ml.code,
    ml.is_occupied,
    ml.current_pallet_id,
    sl.id AS stock_location_id,
    sl.pallet_id,
    sl.warehouse_location,
    sl.stored_at,
    p.pallet_number,
    p.deleted_at AS pallet_deleted_at
FROM master_locations ml
LEFT JOIN stock_locations sl ON sl.master_location_id = ml.id
LEFT JOIN pallets p ON p.id = sl.pallet_id
WHERE ml.id = 201
ORDER BY ml.id, sl.id;
