-- ============================================
-- Version 3.85.0 - Document date per payment line
-- Run in phpMyAdmin. Safe to run multiple times.
-- ============================================
-- Adds document_date to record the date of the check, transfer, or other document per line.

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proof_lines' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'document_date') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `document_date` DATE NULL DEFAULT NULL COMMENT ''Date of the document (check, transfer, etc.)'' AFTER `document_number`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
