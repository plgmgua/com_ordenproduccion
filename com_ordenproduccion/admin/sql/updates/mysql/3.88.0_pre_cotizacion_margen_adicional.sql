-- pre_cotizacion: margen_adicional (difference when valor final > subtotal on quotation line)
-- Version: 3.88.0

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion' LIMIT 1);
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'margen_adicional');
SET @sql = IF(
    @tbl IS NOT NULL AND (IFNULL(@col_exists, 0) = 0),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `margen_adicional` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Margen Adicional: valor_final - subtotal when set on quotation line'' AFTER `total_final`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
