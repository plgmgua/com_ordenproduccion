-- quotation_items: valor_unitario 4 decimal places (precio individual / unit price)
-- Version: 3.76.0

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotation_items' LIMIT 1);
SET @sql = IF(
    @tbl IS NOT NULL,
    CONCAT('ALTER TABLE `', @tbl, '` MODIFY COLUMN `valor_unitario` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT ''Precio unidad / unit price per line'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
