-- com_ordenproduccion 3.113.64-STABLE: Orden de compra — plantillas de correo al aprobar (workflow) + PDF sin franjas CMYK.

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_approval_workflows' LIMIT 1
);

SET @sql = IF(
    @tbl IS NOT NULL
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'email_ordencompra_approved_subject') = 0,
    CONCAT(
        'ALTER TABLE `', @tbl, '` ',
        'ADD COLUMN `email_ordencompra_approved_subject` varchar(255) DEFAULT NULL COMMENT ''PO approved email subject template'' AFTER `email_body_decided`, ',
        'ADD COLUMN `email_ordencompra_approved_body` mediumtext COMMENT ''PO approved email HTML body template'' AFTER `email_ordencompra_approved_subject`'
    ),
    'SELECT ''skip email_ordencompra_approved_*'' AS msg'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
