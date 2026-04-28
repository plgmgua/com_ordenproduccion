-- com_ordenproduccion 3.115.2-STABLE: OT wizard step 3 extras on pre_cotización (delivery date + general instructions)
-- Version: 3.115.2

SET @dbname = DATABASE();

SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion'
    AND TABLE_NAME NOT LIKE '%pre_cotizacion_line%'
    AND TABLE_NAME NOT LIKE '%pre_cotizacion_confirmation%'
    LIMIT 1
);

SET @has_fe = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'ot_fecha_entrega'
);

SET @sql_fe = IF(
    @tbl IS NOT NULL AND IFNULL(@has_fe, 1) = 0,
    CONCAT(
        'ALTER TABLE `', @tbl,
        '` ADD COLUMN `ot_fecha_entrega` DATE NULL',
        ' COMMENT ''Target delivery date from OT wizard step 3 (cotización confirmada)'' AFTER `modified`'
    ),
    'SELECT 1'
);

PREPARE stmt_fe FROM @sql_fe;
EXECUTE stmt_fe;
DEALLOCATE PREPARE stmt_fe;

SET @has_in = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'ot_instrucciones_generales'
);

SET @sql_in = IF(
    @tbl IS NOT NULL AND IFNULL(@has_in, 1) = 0,
    CONCAT(
        'ALTER TABLE `', @tbl,
        '` ADD COLUMN `ot_instrucciones_generales` MEDIUMTEXT NULL',
        ' COMMENT ''General OT instructions from wizard step 3 (below per-process detalles)'' AFTER `ot_fecha_entrega`'
    ),
    'SELECT 1'
);

PREPARE stmt_in FROM @sql_in;
EXECUTE stmt_in;
DEALLOCATE PREPARE stmt_in;
