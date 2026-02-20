-- ============================================
-- Version 3.72.0 - Elementos: price by quantity range (like Procesos Adicionales)
-- ============================================
-- Each element can define:
--   range_1_ceiling = upper bound of first range (e.g. 1000 units)
--   price_1_to_1000 = unit price for quantity 1 to range_1_ceiling
--   price_1001_plus  = unit price for quantity > range_1_ceiling
-- Existing column "price" remains as default/legacy (e.g. used when range prices are zero).

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_elementos' LIMIT 1);

-- Add range_1_ceiling if table exists and column not present
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'range_1_ceiling') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `range_1_ceiling` INT(11) NOT NULL DEFAULT 1000 COMMENT ''Upper bound of first range (e.g. 1000 = range 1 is 1-1000 units)'' AFTER `price`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'price_1_to_1000') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `price_1_to_1000` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Unit price for 1 to range_1_ceiling'' AFTER `range_1_ceiling`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'price_1001_plus') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `price_1001_plus` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Unit price for quantity > range_1_ceiling'' AFTER `price_1_to_1000`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill: copy existing price into price_1_to_1000 so current behavior is preserved
SET @sql = IF(@tbl IS NOT NULL, CONCAT('UPDATE `', @tbl, '` SET price_1_to_1000 = price WHERE price_1_to_1000 = 0 AND price > 0'), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
