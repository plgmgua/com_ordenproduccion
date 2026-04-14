-- 3.109.19: Pre-cotización pliego line — Impresión subtotal base (60% floor) and optional override (Aprobaciones Ventas).

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1);

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'impresion_subtotal_base');
SET @sql = IF(@tbl IS NOT NULL AND IFNULL(@col_exists, 0) = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `impresion_subtotal_base` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Print row subtotal for 60pct floor'' AFTER `calculation_breakdown`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'impresion_subtotal_override');
SET @sql2 = IF(@tbl IS NOT NULL AND IFNULL(@col_exists2, 0) = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `impresion_subtotal_override` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Approved print row subtotal override'' AFTER `impresion_subtotal_base`'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
