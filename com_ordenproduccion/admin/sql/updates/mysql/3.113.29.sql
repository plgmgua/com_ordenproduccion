-- com_ordenproduccion 3.113.29-STABLE: Per-line vendor unit price for future purchase orders (proveedor externo).

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1
);

SET @sql = (
    SELECT IF(
        @tbl IS NULL,
        'SELECT 1',
        IF(
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'vendor_precio_unit_proveedor') > 0,
            'SELECT 1',
            CONCAT(
                'ALTER TABLE `', @tbl,
                '` ADD COLUMN `vendor_precio_unit_proveedor` DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                ' COMMENT ''External vendor line: unit price for future PO (not used in line total)''',
                ' AFTER `price_per_sheet`'
            )
        )
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
