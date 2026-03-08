-- ============================================
-- Version 3.90.0 - Pre-Cotización lines: custom name "Tipo de Elemento" per line
-- ============================================
-- User-assigned label for each line (calculo de folios, otros elementos, envío).
-- Safe to run multiple times (adds column only if missing).

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'tipo_elemento') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `tipo_elemento` VARCHAR(255) DEFAULT NULL COMMENT ''Custom name for this line (Tipo de Elemento)'' AFTER `ordering`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
