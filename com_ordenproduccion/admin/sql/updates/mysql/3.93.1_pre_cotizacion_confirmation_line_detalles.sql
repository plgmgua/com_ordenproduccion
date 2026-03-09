-- ============================================
-- Version 3.93.1 - Add line_detalles_json to pre_cotizacion_confirmation (Step 3: instrucciones para orden de trabajo)
-- ============================================
-- Replace joomla_ with your table prefix if different.

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_confirmation' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'line_detalles_json') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `line_detalles_json` LONGTEXT DEFAULT NULL COMMENT ''Step 3: JSON snapshot of line detalles (instrucciones para orden de trabajo)'' AFTER `instrucciones_facturacion`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
