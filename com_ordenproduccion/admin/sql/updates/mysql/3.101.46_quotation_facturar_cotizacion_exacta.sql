-- Quotation: Facturar cotización exacta (checkbox); 1 = no custom billing instructions text
-- Version: 3.101.46

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotations' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'facturar_cotizacion_exacta') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `facturar_cotizacion_exacta` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''1=bill exact quote amount; 0=custom instrucciones'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
