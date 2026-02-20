-- ============================================
-- Version 3.73.0 - Pre-Cotización lines: support "Otros Elementos" (element-based lines)
-- ============================================
-- line_type: 'pliego' = pliego quote line (default), 'elementos' = element + quantity line
-- elemento_id: for line_type='elementos', FK to #__ordenproduccion_elementos

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'line_type') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `line_type` VARCHAR(20) NOT NULL DEFAULT ''pliego'' COMMENT ''pliego|elementos'' AFTER `pre_cotizacion_id`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'elemento_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `elemento_id` INT(11) DEFAULT NULL COMMENT ''For line_type=elementos'' AFTER `line_type`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
