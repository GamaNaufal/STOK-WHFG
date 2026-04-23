-- IMPLEMENTASI KASUS 1
-- Masalah: Kolom `verification_box_ids` tidak ditemukan pada delivery_pick_sessions.
-- Tujuan: Sinkronkan skema agar update aplikasi ke delivery_pick_sessions tidak gagal lagi.
-- Batasan: Patch hanya skema (tanpa mutasi stok/qty).
-- DB target: MySQL 8.x

SET @case1_tag := 'CASE1_2026_04_24';
SET @run_ts := NOW();
SET @db_name := DATABASE();

-- =========================
-- 1) PEMERIKSAAN AWAL
-- =========================
SELECT COUNT(*) INTO @has_delivery_pick_sessions
FROM information_schema.tables
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions';

CREATE TABLE IF NOT EXISTS case1_delivery_pick_sessions_report (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_tag VARCHAR(64) NOT NULL,
    report_key VARCHAR(128) NOT NULL,
    report_value VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_case1_report_tag (run_tag),
    INDEX idx_case1_report_key (report_key)
);

DELETE FROM case1_delivery_pick_sessions_report WHERE run_tag = @case1_tag;

INSERT INTO case1_delivery_pick_sessions_report (run_tag, report_key, report_value, created_at)
VALUES (@case1_tag, 'table_delivery_pick_sessions_exists', IF(@has_delivery_pick_sessions = 1, 'YES', 'NO'), @run_ts);

-- Jika tabel tidak ada, hentikan eksekusi dengan error paksa.
SET @sql := IF(
    @has_delivery_pick_sessions = 1,
    'SELECT ''OK: delivery_pick_sessions exists'' AS precheck_msg',
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''ABORT: table delivery_pick_sessions not found'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================
-- 2) SINKRONISASI SKEMA (IDEMPOTENT)
-- =========================
-- 2.1 approved_by (boleh NULL)
SELECT COUNT(*) INTO @has_approved_by
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'approved_by';

SET @sql := IF(
    @has_approved_by = 0,
    'ALTER TABLE delivery_pick_sessions ADD COLUMN approved_by BIGINT UNSIGNED NULL',
    'SELECT ''SKIP: approved_by exists'' AS schema_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.2 approved_at (boleh NULL)
SELECT COUNT(*) INTO @has_approved_at
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'approved_at';

SET @sql := IF(
    @has_approved_at = 0,
    'ALTER TABLE delivery_pick_sessions ADD COLUMN approved_at DATETIME NULL',
    'SELECT ''SKIP: approved_at exists'' AS schema_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.3 approval_notes (boleh NULL)
SELECT COUNT(*) INTO @has_approval_notes
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'approval_notes';

SET @sql := IF(
    @has_approval_notes = 0,
    'ALTER TABLE delivery_pick_sessions ADD COLUMN approval_notes TEXT NULL',
    'SELECT ''SKIP: approval_notes exists'' AS schema_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.4 redo_until (boleh NULL)
SELECT COUNT(*) INTO @has_redo_until
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'redo_until';

SET @sql := IF(
    @has_redo_until = 0,
    'ALTER TABLE delivery_pick_sessions ADD COLUMN redo_until DATETIME NULL',
    'SELECT ''SKIP: redo_until exists'' AS schema_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.5 completion_status (default pending)
SELECT COUNT(*) INTO @has_completion_status
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'completion_status';

SET @sql := IF(
    @has_completion_status = 0,
    'ALTER TABLE delivery_pick_sessions ADD COLUMN completion_status ENUM(''pending'',''completed'',''redone'') NOT NULL DEFAULT ''pending''',
    'SELECT ''SKIP: completion_status exists'' AS schema_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2.6 verification_box_ids (kolom utama yang memicu error)
SELECT COUNT(*) INTO @has_verification_box_ids
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'verification_box_ids';

SET @sql := IF(
    @has_verification_box_ids = 0,
    'ALTER TABLE delivery_pick_sessions ADD COLUMN verification_box_ids JSON NULL',
    'SELECT ''SKIP: verification_box_ids exists'' AS schema_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================
-- 3) SINKRONISASI INDEKS (IDEMPOTENT)
-- =========================
-- indeks status
SELECT COUNT(*) INTO @idx_status
FROM information_schema.statistics
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND index_name = 'delivery_pick_sessions_status_index';

SET @sql := IF(
    @idx_status = 0,
    'ALTER TABLE delivery_pick_sessions ADD INDEX delivery_pick_sessions_status_index (status)',
    'SELECT ''SKIP: status index exists'' AS index_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- indeks delivery_order_id
SELECT COUNT(*) INTO @idx_delivery_order
FROM information_schema.statistics
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND index_name = 'delivery_pick_sessions_delivery_order_id_index';

SET @sql := IF(
    @idx_delivery_order = 0,
    'ALTER TABLE delivery_pick_sessions ADD INDEX delivery_pick_sessions_delivery_order_id_index (delivery_order_id)',
    'SELECT ''SKIP: delivery_order_id index exists'' AS index_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- indeks created_by
SELECT COUNT(*) INTO @idx_created_by
FROM information_schema.statistics
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND index_name = 'delivery_pick_sessions_created_by_index';

SET @sql := IF(
    @idx_created_by = 0,
    'ALTER TABLE delivery_pick_sessions ADD INDEX delivery_pick_sessions_created_by_index (created_by)',
    'SELECT ''SKIP: created_by index exists'' AS index_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- indeks started_at
SELECT COUNT(*) INTO @idx_started_at
FROM information_schema.statistics
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND index_name = 'delivery_pick_sessions_started_at_index';

SET @sql := IF(
    @idx_started_at = 0,
    'ALTER TABLE delivery_pick_sessions ADD INDEX delivery_pick_sessions_started_at_index (started_at)',
    'SELECT ''SKIP: started_at index exists'' AS index_msg'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =========================
-- 4) PEMERIKSAAN AKHIR
-- =========================
INSERT INTO case1_delivery_pick_sessions_report (run_tag, report_key, report_value, created_at)
SELECT @case1_tag, 'has_approved_by', IF(COUNT(*)=1, 'YES', 'NO'), NOW()
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'approved_by';

INSERT INTO case1_delivery_pick_sessions_report (run_tag, report_key, report_value, created_at)
SELECT @case1_tag, 'has_approved_at', IF(COUNT(*)=1, 'YES', 'NO'), NOW()
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'approved_at';

INSERT INTO case1_delivery_pick_sessions_report (run_tag, report_key, report_value, created_at)
SELECT @case1_tag, 'has_approval_notes', IF(COUNT(*)=1, 'YES', 'NO'), NOW()
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'approval_notes';

INSERT INTO case1_delivery_pick_sessions_report (run_tag, report_key, report_value, created_at)
SELECT @case1_tag, 'has_redo_until', IF(COUNT(*)=1, 'YES', 'NO'), NOW()
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'redo_until';

INSERT INTO case1_delivery_pick_sessions_report (run_tag, report_key, report_value, created_at)
SELECT @case1_tag, 'has_completion_status', IF(COUNT(*)=1, 'YES', 'NO'), NOW()
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'completion_status';

INSERT INTO case1_delivery_pick_sessions_report (run_tag, report_key, report_value, created_at)
SELECT @case1_tag, 'has_verification_box_ids', IF(COUNT(*)=1, 'YES', 'NO'), NOW()
FROM information_schema.columns
WHERE table_schema = @db_name
  AND table_name = 'delivery_pick_sessions'
  AND column_name = 'verification_box_ids';

-- Validasi level parser untuk jalur update yang sebelumnya gagal (tanpa mutasi data)
UPDATE delivery_pick_sessions
SET verification_box_ids = verification_box_ids
WHERE 1 = 0;

-- Tampilkan laporan
SELECT *
FROM case1_delivery_pick_sessions_report
WHERE run_tag = @case1_tag
ORDER BY id;

-- =========================
-- 5) PANDUAN ROLLBACK MANUAL (JIKA DIPERLUKAN)
-- =========================
-- Rollback ini hanya menghapus kolom yang ditambahkan oleh patch ini.
-- Jalankan hanya jika kolom tersebut memang ditambahkan oleh skrip ini dan aplikasi sudah tidak membutuhkannya.
-- 1) ALTER TABLE delivery_pick_sessions DROP COLUMN verification_box_ids;
-- 2) ALTER TABLE delivery_pick_sessions DROP COLUMN completion_status;
-- 3) ALTER TABLE delivery_pick_sessions DROP COLUMN redo_until;
-- 4) ALTER TABLE delivery_pick_sessions DROP COLUMN approval_notes;
-- 5) ALTER TABLE delivery_pick_sessions DROP COLUMN approved_at;
-- 6) ALTER TABLE delivery_pick_sessions DROP COLUMN approved_by;
