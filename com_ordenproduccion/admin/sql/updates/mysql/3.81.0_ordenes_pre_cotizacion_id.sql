-- Ordenes: link to pre-cotización when orden was created from a pre-cotización
-- Version: 3.81.0

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_ordenes' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pre_cotizacion_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pre_cotizacion_id` int(11) DEFAULT NULL COMMENT ''Pre-Cotización id when orden was created from this pre-cotización'' AFTER `state`, ADD KEY `idx_pre_cotizacion_id` (`pre_cotizacion_id`)'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
