-- com_ordenproduccion 3.112.0-STABLE: Pre-cotización "Proveedor externo" (solicitud a proveedores).
-- Header: document_mode. Line: vendor_descripcion, vendor_tiempo_entrega (unit price = price_per_sheet, line total = total).

SET @dbname = DATABASE();

SET @tbl_hdr = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion%'
    AND TABLE_NAME NOT LIKE '%pre_cotizacion_line%'
    LIMIT 1
);

SET @col_dm = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_hdr AND COLUMN_NAME = 'document_mode'
);
SET @sql_dm = IF(@tbl_hdr IS NOT NULL AND IFNULL(@col_dm, 0) = 0,
    CONCAT('ALTER TABLE `', @tbl_hdr, '` ADD COLUMN `document_mode` VARCHAR(32) NOT NULL DEFAULT ''pliego'' COMMENT ''pliego|proveedor_externo'' AFTER `state`'),
    'SELECT 1');
PREPARE stmt_dm FROM @sql_dm;
EXECUTE stmt_dm;
DEALLOCATE PREPARE stmt_dm;

SET @tbl_ln = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line%'
    LIMIT 1
);

SET @col_vd = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_ln AND COLUMN_NAME = 'vendor_descripcion'
);
SET @sql_vd = IF(@tbl_ln IS NOT NULL AND IFNULL(@col_vd, 0) = 0,
    CONCAT('ALTER TABLE `', @tbl_ln, '` ADD COLUMN `vendor_descripcion` MEDIUMTEXT NULL COMMENT ''External vendor line description'' AFTER `tipo_elemento`'),
    'SELECT 1');
PREPARE stmt_vd FROM @sql_vd;
EXECUTE stmt_vd;
DEALLOCATE PREPARE stmt_vd;

SET @col_vt = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_ln AND COLUMN_NAME = 'vendor_tiempo_entrega'
);
SET @sql_vt = IF(@tbl_ln IS NOT NULL AND IFNULL(@col_vt, 0) = 0,
    CONCAT('ALTER TABLE `', @tbl_ln, '` ADD COLUMN `vendor_tiempo_entrega` VARCHAR(512) NULL COMMENT ''Lead time after confirmation'' AFTER `vendor_descripcion`'),
    'SELECT 1');
PREPARE stmt_vt FROM @sql_vt;
EXECUTE stmt_vt;
DEALLOCATE PREPARE stmt_vt;
