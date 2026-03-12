-- pre_cotizacion: oferta_expires (date when template offer expires; NULL = no expiration)
-- Version: 3.96.0

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion' LIMIT 1);
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'oferta_expires');
SET @sql = IF(
    @tbl IS NOT NULL AND (IFNULL(@col_exists, 0) = 0),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `oferta_expires` DATE NULL DEFAULT NULL COMMENT ''Expiration date for offer/template; NULL = no expiration'' AFTER `oferta`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
