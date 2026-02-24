-- ============================================
-- Version 3.78.0 - Pre-Cotización lines: support Envío (shipping) lines
-- ============================================
-- line_type: add 'envio'; envio_id = FK to #__ordenproduccion_envios; envio_valor = amount for custom tipo.
-- Manual run in phpMyAdmin: uses prefix joomla_. Change table name if your prefix is different.

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'envio_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `envio_id` INT(11) DEFAULT NULL COMMENT ''FK to envios; for line_type=envio'' AFTER `elemento_id`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'envio_valor') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `envio_valor` DECIMAL(12,2) DEFAULT NULL COMMENT ''Amount when envio tipo=custom'' AFTER `envio_id`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Extend line_type to allow 'envio' (existing column; no ALTER needed if already VARCHAR(20))
