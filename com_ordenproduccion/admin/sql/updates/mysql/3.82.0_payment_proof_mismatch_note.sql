-- Payment proofs: store user note when saving with a totals mismatch (Diferencia)
-- Version: 3.82.0

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proofs' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mismatch_note') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mismatch_note` TEXT NULL DEFAULT NULL COMMENT ''User reason when totals differ'' AFTER `state`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql2 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mismatch_difference') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mismatch_difference` VARCHAR(30) NULL DEFAULT NULL COMMENT ''Difference amount at save time'' AFTER `mismatch_note`'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
