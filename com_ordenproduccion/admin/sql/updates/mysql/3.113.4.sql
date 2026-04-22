-- com_ordenproduccion 3.113.4-STABLE: Pre-cotización proveedor externo — adjunto (cotización del proveedor).

SET @dbname = DATABASE();

SET @tbl_hdr = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion%'
    AND TABLE_NAME NOT LIKE '%pre_cotizacion_line%'
    AND TABLE_NAME NOT LIKE '%pre_cotizacion_confirmation%'
    LIMIT 1
);

SET @col_att = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_hdr AND COLUMN_NAME = 'vendor_quote_attachment'
);
SET @sql_att = IF(@tbl_hdr IS NOT NULL AND IFNULL(@col_att, 0) = 0,
    CONCAT(
        'ALTER TABLE `', @tbl_hdr, '` ADD COLUMN `vendor_quote_attachment` VARCHAR(512) DEFAULT NULL ',
        'COMMENT ''Relative path: media/com_ordenproduccion/precot_vendor_quote/'' AFTER `document_mode`'
    ),
    'SELECT 1');
PREPARE stmt_att FROM @sql_att;
EXECUTE stmt_att;
DEALLOCATE PREPARE stmt_att;
