-- IMPLEMENTASI KASUS 2 (GLOBAL)
-- Masalah: Duplicate key pada pallet_items_pallet_id_part_number_unique saat proses merge.
-- Batasan: DILARANG mengubah stok/isi boxes. Skrip ini hanya menyentuh metadata/key pallet_items.
-- DB target: MySQL 8.x

-- =========================
-- 0) KONTEKS KEAMANAN
-- =========================
SET @case2_tag := 'CASE2_2026_04_24';
SET @run_ts := NOW();

-- PENTING:
-- 1) Jalankan dengan user berprivilege yang bisa CREATE TRIGGER dan ALTER TABLE.
-- 2) Eksekusi saat maintenance window jika traffic tinggi.
-- 3) Skrip ini TIDAK mengupdate tabel boxes dan TIDAK mengubah qty/isi boxes.

START TRANSACTION;

-- =========================
-- 1) PEMERIKSAAN AWAL KETAT
-- =========================
-- Pastikan pallet_items ada
SELECT COUNT(*) INTO @tbl_exists
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'pallet_items';

SET @sql := IF(@tbl_exists = 1,
    'SELECT ''OK: pallet_items exists'' AS precheck_msg',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''ABORT: table pallet_items not found''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Pastikan boxes dan pallet_boxes ada (hanya untuk cek silang integritas)
SELECT COUNT(*) INTO @boxes_exists
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'boxes';

SELECT COUNT(*) INTO @pivot_exists
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'pallet_boxes';

SET @sql := IF(@boxes_exists = 1 AND @pivot_exists = 1,
    'SELECT ''OK: boxes and pallet_boxes exist'' AS precheck_msg',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''ABORT: boxes or pallet_boxes table not found''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================
-- 2) BACKUP SNAPSHOT (NON-DESTRUKTIF)
-- =========================
CREATE TABLE IF NOT EXISTS backup_case2_pallet_items_20260424 LIKE pallet_items;
INSERT IGNORE INTO backup_case2_pallet_items_20260424
SELECT * FROM pallet_items;

-- =========================
-- 3) TABEL LAPORAN PEMERIKSAAN AWAL
-- =========================
CREATE TABLE IF NOT EXISTS case2_pallet_items_report (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_tag VARCHAR(64) NOT NULL,
    report_key VARCHAR(128) NOT NULL,
    report_value BIGINT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_case2_report_tag (run_tag),
    INDEX idx_case2_report_key (report_key)
);

CREATE TABLE IF NOT EXISTS case2_pallet_items_conflict_review (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_tag VARCHAR(64) NOT NULL,
    pallet_item_id BIGINT UNSIGNED NOT NULL,
    pallet_id BIGINT UNSIGNED NOT NULL,
    current_part_number VARCHAR(255) NOT NULL,
    normalized_part_number VARCHAR(255) NOT NULL,
    conflict_reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_case2_conflict_tag (run_tag),
    INDEX idx_case2_conflict_pallet (pallet_id)
);

-- Hapus baris lama dengan run tag yang sama (aman untuk rerun/idempotent)
DELETE FROM case2_pallet_items_report WHERE run_tag = @case2_tag;
DELETE FROM case2_pallet_items_conflict_review WHERE run_tag = @case2_tag;

-- Metrik: total baris
INSERT INTO case2_pallet_items_report (run_tag, report_key, report_value, created_at)
SELECT @case2_tag, 'total_pallet_items', COUNT(*), @run_ts
FROM pallet_items;

-- Metrik: baris yang perlu normalisasi (trim/upper/NBSP->spasi)
INSERT INTO case2_pallet_items_report (run_tag, report_key, report_value, created_at)
SELECT @case2_tag, 'rows_need_normalization', COUNT(*), @run_ts
FROM pallet_items
WHERE part_number <> UPPER(TRIM(REPLACE(part_number, CHAR(160), ' ')));

-- Metrik: potensi bentrok jika normalisasi diterapkan
INSERT INTO case2_pallet_items_report (run_tag, report_key, report_value, created_at)
SELECT @case2_tag, 'potential_normalization_collisions', COUNT(*), @run_ts
FROM (
    SELECT pallet_id,
           UPPER(TRIM(REPLACE(part_number, CHAR(160), ' '))) AS normalized_part,
           COUNT(*) AS cnt
    FROM pallet_items
    GROUP BY pallet_id, UPPER(TRIM(REPLACE(part_number, CHAR(160), ' ')))
    HAVING COUNT(*) > 1
) c;

-- Simpan detail baris bentrok normalisasi (hanya untuk review)
INSERT INTO case2_pallet_items_conflict_review
(run_tag, pallet_item_id, pallet_id, current_part_number, normalized_part_number, conflict_reason, created_at)
SELECT @case2_tag,
       p.id,
       p.pallet_id,
       p.part_number,
       UPPER(TRIM(REPLACE(p.part_number, CHAR(160), ' '))) AS normalized_part,
       'Normalization would collide with another row in same pallet',
       @run_ts
FROM pallet_items p
JOIN (
    SELECT pallet_id,
           UPPER(TRIM(REPLACE(part_number, CHAR(160), ' '))) AS normalized_part
    FROM pallet_items
    GROUP BY pallet_id, UPPER(TRIM(REPLACE(part_number, CHAR(160), ' ')))
    HAVING COUNT(*) > 1
) x
  ON x.pallet_id = p.pallet_id
 AND x.normalized_part = UPPER(TRIM(REPLACE(p.part_number, CHAR(160), ' ')));

-- =========================
-- 4) NORMALISASI AMAN (TANPA PERUBAHAN QTY)
-- =========================
-- Normalisasi part_number hanya jika tidak bentrok dengan baris lain.
UPDATE pallet_items p
JOIN (
    SELECT id,
           pallet_id,
           part_number,
           UPPER(TRIM(REPLACE(part_number, CHAR(160), ' '))) AS normalized_part
    FROM pallet_items
) n ON n.id = p.id
LEFT JOIN pallet_items conflict
       ON conflict.id <> p.id
      AND conflict.pallet_id = n.pallet_id
      AND conflict.part_number = n.normalized_part
SET p.part_number = n.normalized_part,
    p.updated_at = NOW()
WHERE n.part_number <> n.normalized_part
  AND conflict.id IS NULL;

-- Simpan jumlah setelah normalisasi
INSERT INTO case2_pallet_items_report (run_tag, report_key, report_value, created_at)
SELECT @case2_tag, 'rows_need_normalization_after_patch', COUNT(*), NOW()
FROM pallet_items
WHERE part_number <> UPPER(TRIM(REPLACE(part_number, CHAR(160), ' ')));

-- =========================
-- 5) PENEGAKAN GUARDRAIL KEY
-- =========================
-- Pastikan unique index ada pada (pallet_id, part_number)
SELECT COUNT(*) INTO @has_unique_idx
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'pallet_items'
  AND index_name = 'pallet_items_pallet_id_part_number_unique'
  AND non_unique = 0;

SET @sql := IF(@has_unique_idx = 0,
    'ALTER TABLE pallet_items ADD UNIQUE KEY pallet_items_pallet_id_part_number_unique (pallet_id, part_number)',
    'SELECT ''OK: unique index already exists'' AS idx_msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Pastikan helper index ada (idempotent)
SELECT COUNT(*) INTO @has_idx_pallet
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'pallet_items'
  AND index_name = 'pallet_items_pallet_id_index';

SET @sql := IF(@has_idx_pallet = 0,
    'ALTER TABLE pallet_items ADD INDEX pallet_items_pallet_id_index (pallet_id)',
    'SELECT ''OK: pallet_id index already exists'' AS idx_msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @has_idx_part
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'pallet_items'
  AND index_name = 'pallet_items_part_number_index';

SET @sql := IF(@has_idx_part = 0,
    'ALTER TABLE pallet_items ADD INDEX pallet_items_part_number_index (part_number)',
    'SELECT ''OK: part_number index already exists'' AS idx_msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================
-- 6) TRIGGER NORMALISASI (UNTUK INSERT/UPDATE BERIKUTNYA)
-- =========================
DROP TRIGGER IF EXISTS trg_bi_pallet_items_normalize_part_number;
DROP TRIGGER IF EXISTS trg_bu_pallet_items_normalize_part_number;

DELIMITER $$
CREATE TRIGGER trg_bi_pallet_items_normalize_part_number
BEFORE INSERT ON pallet_items
FOR EACH ROW
BEGIN
    IF NEW.part_number IS NOT NULL THEN
        SET NEW.part_number = UPPER(TRIM(REPLACE(NEW.part_number, CHAR(160), ' ')));
    END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER trg_bu_pallet_items_normalize_part_number
BEFORE UPDATE ON pallet_items
FOR EACH ROW
BEGIN
    IF NEW.part_number IS NOT NULL THEN
        SET NEW.part_number = UPPER(TRIM(REPLACE(NEW.part_number, CHAR(160), ' ')));
    END IF;
END$$
DELIMITER ;

-- =========================
-- 7) PEMERIKSAAN AKHIR (TANPA MUTASI BOX)
-- =========================
-- A. Konfirmasi boxes tidak terdampak (hanya hitung jumlah baris)
INSERT INTO case2_pallet_items_report (run_tag, report_key, report_value, created_at)
SELECT @case2_tag, 'boxes_row_count_postcheck', COUNT(*), NOW()
FROM boxes;

-- B. Konfirmasi tidak ada duplicate key persis di pallet_items
INSERT INTO case2_pallet_items_report (run_tag, report_key, report_value, created_at)
SELECT @case2_tag, 'exact_duplicate_keys_after_patch', COUNT(*), NOW()
FROM (
    SELECT pallet_id, part_number, COUNT(*) AS cnt
    FROM pallet_items
    GROUP BY pallet_id, part_number
    HAVING COUNT(*) > 1
) d;

-- C. Konfirmasi bentrok normalisasi yang belum selesai tetap tercatat
INSERT INTO case2_pallet_items_report (run_tag, report_key, report_value, created_at)
SELECT @case2_tag, 'conflict_review_rows', COUNT(*), NOW()
FROM case2_pallet_items_conflict_review
WHERE run_tag = @case2_tag;

COMMIT;

-- =========================
-- 8) RINGKASAN OUTPUT
-- =========================
SELECT *
FROM case2_pallet_items_report
WHERE run_tag = @case2_tag
ORDER BY id;

SELECT *
FROM case2_pallet_items_conflict_review
WHERE run_tag = @case2_tag
ORDER BY pallet_id, normalized_part_number, pallet_item_id;

-- =========================
-- 9) PANDUAN ROLLBACK (MANUAL, JIKA DIPERLUKAN)
-- =========================
-- Jika perlu rollback patch ini:
-- 1) START TRANSACTION;
-- 2) DROP TRIGGER IF EXISTS trg_bi_pallet_items_normalize_part_number;
-- 3) DROP TRIGGER IF EXISTS trg_bu_pallet_items_normalize_part_number;
-- 4) TRUNCATE TABLE pallet_items;
-- 5) INSERT INTO pallet_items SELECT * FROM backup_case2_pallet_items_20260424;
-- 6) COMMIT;
-- Catatan: rollback hanya mengembalikan snapshot pallet_items; tabel boxes tidak disentuh oleh skrip ini.
