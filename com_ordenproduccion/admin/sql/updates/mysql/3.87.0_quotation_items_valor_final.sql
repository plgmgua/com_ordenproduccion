-- quotation_items: valor_final (optional override >= subtotal; recalculates unit price)
-- Version: 3.87.0

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotation_items' LIMIT 1);
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'valor_final');
SET @sql = IF(
    @tbl IS NOT NULL AND (IFNULL(@col_exists, 0) = 0),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `valor_final` decimal(12,2) NULL DEFAULT NULL COMMENT ''Override line total (must be >= pre-cot subtotal); unit price = valor_final/cantidad'' AFTER `subtotal`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
