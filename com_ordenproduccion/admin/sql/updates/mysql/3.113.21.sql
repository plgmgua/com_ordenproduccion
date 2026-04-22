-- com_ordenproduccion 3.113.21-STABLE: condiciones_entrega as long text (TEXT).

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
             WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'condiciones_entrega') = 0,
            'SELECT 1',
            IF(
                LOWER((SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'condiciones_entrega' LIMIT 1)) = 'text',
                'SELECT 1',
                CONCAT('ALTER TABLE `', @tbl, '` MODIFY COLUMN `condiciones_entrega` TEXT NULL COMMENT ''Delivery conditions for this request (user-editable)''')
            )
        )
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
