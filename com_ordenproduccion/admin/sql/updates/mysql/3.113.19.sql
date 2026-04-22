-- com_ordenproduccion 3.113.19-STABLE: Condiciones de entrega per vendor-quote registro row.

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_precot_vendor_quote_event' LIMIT 1
);

SET @sql = (
    SELECT IF(
        @tbl IS NULL,
        'SELECT 1',
        IF(
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'condiciones_entrega') > 0,
            'SELECT 1',
            CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `condiciones_entrega` VARCHAR(512) DEFAULT NULL COMMENT ''Delivery conditions for this request (user-editable)'' AFTER `vendor_quote_attachment`')
        )
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
