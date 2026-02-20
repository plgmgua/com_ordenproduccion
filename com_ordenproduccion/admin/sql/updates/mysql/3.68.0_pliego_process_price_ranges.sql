-- ============================================
-- Version 3.68.0 - Procesos Adicionales: price by pliego range (1-1000 vs 1001+)
-- ============================================
-- Replaces single price_per_pliego with:
--   price_1_to_1000 = total price when quantity is 1-1000 pliegos
--   price_1001_plus  = total price when quantity is 1001+
-- Replace #__ with your DB prefix (e.g. joomla_) or use joomla_ below.

-- Add new columns if not present (idempotent)
SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pliego_processes' LIMIT 1);

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'price_1_to_1000') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `price_1_to_1000` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT ''Total price for 1-1000 pliegos'' AFTER `price_per_pliego`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'price_1001_plus') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `price_1001_plus` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT ''Total price for 1001+ pliegos'' AFTER `price_1_to_1000`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- No backfill: price_1_to_1000 and price_1001_plus are set per process in Productos (Procesos Adicionales).
