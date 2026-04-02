-- Quotation: facturación mode (con envío / fecha específica) + optional date (Confirmar cotización)
-- Version: 3.101.45

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotations' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'facturacion_modo') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `facturacion_modo` varchar(32) DEFAULT NULL COMMENT ''con_envio|fecha_especifica'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql2 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'facturacion_fecha') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `facturacion_fecha` date DEFAULT NULL COMMENT ''When facturacion_modo=fecha_especifica'' AFTER `facturacion_modo`'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
