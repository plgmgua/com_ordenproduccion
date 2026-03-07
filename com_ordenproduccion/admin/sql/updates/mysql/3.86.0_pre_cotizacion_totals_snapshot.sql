-- ============================================
-- Version 3.86.0 - Pre-Cotización totals snapshot (historical)
-- Run in phpMyAdmin. Safe to run multiple times.
-- ============================================
-- Saves calculated subtotal, margen, IVA, ISR, comisión, total and total_final
-- so totals do not change if folio/element prices change later.
-- total_final defaults to total and can be overridden from cotización view.

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion' LIMIT 1);

-- lines_subtotal
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'lines_subtotal') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `lines_subtotal` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Sum of line totals (excl. envio) at save time'' AFTER `state`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- margen_amount
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'margen_amount') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `margen_amount` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Margen de ganancia amount at save time'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- iva_amount
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'iva_amount') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `iva_amount` DECIMAL(12,2) NULL DEFAULT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- isr_amount
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'isr_amount') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `isr_amount` DECIMAL(12,2) NULL DEFAULT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- comision_amount
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'comision_amount') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `comision_amount` DECIMAL(12,2) NULL DEFAULT NULL'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- total (calculated total)
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'total') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `total` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Calculated total (subtotal+margen+iva+isr+comision) at save time'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- total_final (overridable from cotización; defaults to total)
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'total_final') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `total_final` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Final total; default=total, overridable from cotización'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
