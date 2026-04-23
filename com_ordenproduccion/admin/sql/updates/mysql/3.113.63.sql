-- com_ordenproduccion 3.113.63-STABLE: Orden de compra — opción CC proveedor al aprobar (correo al solicitante).

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_orden_compra' LIMIT 1
);

SET @sql = IF(
    @tbl IS NOT NULL
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'approve_email_cc_vendor') = 0,
    CONCAT(
        'ALTER TABLE `', @tbl, '` ADD COLUMN `approve_email_cc_vendor` tinyint(1) NOT NULL DEFAULT 0 ',
        'COMMENT ''1 = CC vendor contact_email when sending approved PO to requester'' AFTER `approved_pdf_path`'
    ),
    'SELECT ''skip approve_email_cc_vendor'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
