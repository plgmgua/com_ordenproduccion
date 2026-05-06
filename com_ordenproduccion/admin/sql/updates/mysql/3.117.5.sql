-- com_ordenproduccion 3.117.5-STABLE: pre_cotización cabecera cantidad_total (to the right of medidas in UI / OT)
-- Version: 3.117.5

SET @dbname = DATABASE();

SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion'
    AND TABLE_NAME NOT LIKE '%pre_cotizacion_line%'
    AND TABLE_NAME NOT LIKE '%pre_cotizacion_confirmation%'
    LIMIT 1
);

SET @has_medidas = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'medidas'
);

SET @has_ct = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'cantidad_total'
);

SET @sql_ct = IF(
    @tbl IS NOT NULL AND IFNULL(@has_ct, 1) = 0,
    IF(
        IFNULL(@has_medidas, 0) > 0,
        CONCAT(
            'ALTER TABLE `', @tbl,
            '` ADD COLUMN `cantidad_total` VARCHAR(128) NULL DEFAULT NULL',
            ' COMMENT ''Total quantity header (PRE)'' AFTER `medidas`'
        ),
        CONCAT(
            'ALTER TABLE `', @tbl,
            '` ADD COLUMN `cantidad_total` VARCHAR(128) NULL DEFAULT NULL',
            ' COMMENT ''Total quantity header (PRE)'' AFTER `descripcion`'
        )
    ),
    'SELECT 1'
);

PREPARE stmt_ct FROM @sql_ct;
EXECUTE stmt_ct;
DEALLOCATE PREPARE stmt_ct;
