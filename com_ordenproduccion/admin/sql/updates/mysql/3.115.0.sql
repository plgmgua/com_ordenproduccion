-- com_ordenproduccion 3.115.0-STABLE: orden_source_json on OT for cotización/PRE linkage snapshot
-- Version: 3.115.0

SET @dbname = DATABASE();

SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_ordenes' LIMIT 1
);

SET @has_col = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tbl
    AND COLUMN_NAME = 'orden_source_json'
);

SET @has_pre = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname
    AND TABLE_NAME = @tbl
    AND COLUMN_NAME = 'pre_cotizacion_id'
);

SET @sql = IF(
    @tbl IS NOT NULL AND IFNULL(@has_col, 1) = 0,
    IF(
        IFNULL(@has_pre, 0) > 0,
        CONCAT(
            'ALTER TABLE `', @tbl,
            '` ADD COLUMN `orden_source_json` MEDIUMTEXT NULL',
            ' COMMENT ''JSON snapshot: quotation/PRE linkage, document_mode, valor refs, confirmation detalles''',
            ' AFTER `pre_cotizacion_id`'
        ),
        CONCAT(
            'ALTER TABLE `', @tbl,
            '` ADD COLUMN `orden_source_json` MEDIUMTEXT NULL',
            ' COMMENT ''JSON snapshot: quotation/PRE linkage, document_mode, valor refs, confirmation detalles'''
        )
    ),
    'SELECT 1'
);

PREPARE stmt_ord_src FROM @sql;
EXECUTE stmt_ord_src;
DEALLOCATE PREPARE stmt_ord_src;
