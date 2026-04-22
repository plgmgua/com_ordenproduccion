-- com_ordenproduccion 3.113.16-STABLE: Vendor quote file per registro row (precot_vendor_quote_event.vendor_quote_attachment).

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
             WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'vendor_quote_attachment') > 0,
            'SELECT 1',
            CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `vendor_quote_attachment` VARCHAR(512) DEFAULT NULL COMMENT ''Relative path under site root (precot_vendor_quote)'' AFTER `meta`')
        )
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
