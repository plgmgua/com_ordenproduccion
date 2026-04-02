-- FEL: scheduled issuance (8:00 local on chosen date)
-- Version: 3.101.51

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_invoices' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'fel_scheduled_at') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `fel_scheduled_at` datetime NULL DEFAULT NULL COMMENT ''Process FEL at or after this UTC time (8:00 local on billing date)'' AFTER `felplex_uuid`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
