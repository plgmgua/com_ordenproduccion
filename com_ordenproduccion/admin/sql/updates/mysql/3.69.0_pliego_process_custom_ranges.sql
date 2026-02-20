-- ============================================
-- Version 3.69.0 - Procesos Adicionales: custom range ceiling per process
-- ============================================
-- Each process can define its own first-range upper bound (e.g. 500 or 1000).
-- Range 1 = 1 to range_1_ceiling pliegos (price_1_to_1000)
-- Range 2 = (range_1_ceiling + 1)+ pliegos (price_1001_plus)

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pliego_processes' LIMIT 1);

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'range_1_ceiling') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `range_1_ceiling` INT(11) NOT NULL DEFAULT 1000 COMMENT ''Upper bound of first range (e.g. 500 = range 1 is 1-500)'' AFTER `price_1001_plus`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
