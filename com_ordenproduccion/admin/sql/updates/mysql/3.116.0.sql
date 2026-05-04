-- com_ordenproduccion 3.116.0-STABLE: Pre-cotización line type «tercerizado» (producto tercerizado) + column tercerizado_producto
-- Version: 3.116.0

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1
);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'tercerizado_producto') = 0,
    CONCAT(
        'ALTER TABLE `', @tbl,
        '` ADD COLUMN `tercerizado_producto` VARCHAR(512) DEFAULT NULL',
        ' COMMENT ''Free-text product name for line_type=tercerizado'' AFTER `tipo_elemento`'
    ),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
