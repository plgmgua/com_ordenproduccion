-- Retenciones: support SAT-2229 / SAT-1911 forms + monto_retencion (RETENCIÓN)
-- Version: 3.119.259

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_retenciones' LIMIT 1);

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'monto_retencion');
SET @sql = IF(
    @tbl IS NOT NULL AND (IFNULL(@col_exists, 0) = 0),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `monto_retencion` DECIMAL(14,2) NOT NULL DEFAULT 0.00 COMMENT ''Monto RETENCIÓN (Q) from SAT-2229/SAT-1911'' AFTER `fact_iva_exento`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
