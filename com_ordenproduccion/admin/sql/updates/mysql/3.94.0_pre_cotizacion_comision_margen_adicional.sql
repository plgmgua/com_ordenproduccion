-- pre_cotizacion: comision_margen_adicional (amount = margen_adicional * param %; for reporting, not added to total)
-- Version: 3.94.0

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion' LIMIT 1);
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'comision_margen_adicional');
SET @sql = IF(
    @tbl IS NOT NULL AND (IFNULL(@col_exists, 0) = 0),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `comision_margen_adicional` DECIMAL(12,2) NULL DEFAULT NULL COMMENT ''Commission on additional margin (for report); not part of total'' AFTER `margen_adicional`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
