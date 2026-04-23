-- com_ordenproduccion 3.113.55-STABLE: Stored combined PDF path after orden de compra approval.

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_orden_compra' LIMIT 1
);

SET @sql = (
    SELECT IF(
        @tbl IS NULL,
        'SELECT 1',
        IF(
            (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'approved_pdf_path') > 0,
            'SELECT 1',
            CONCAT(
                'ALTER TABLE `', @tbl,
                '` ADD COLUMN `approved_pdf_path` VARCHAR(512) DEFAULT NULL ',
                'COMMENT ''Relative path: media/com_ordenproduccion/orden_compra_approved/…'' AFTER `modified_by`'
            )
        )
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
