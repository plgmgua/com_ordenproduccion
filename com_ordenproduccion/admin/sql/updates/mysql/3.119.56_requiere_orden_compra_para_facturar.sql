-- Quotation: flag "requires purchase order to invoice" (Confirmar Cotización — no file upload)
-- Version: 3.119.56

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotations' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'requiere_orden_compra_para_facturar') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `requiere_orden_compra_para_facturar` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1=Requires PO to invoice (Confirmar Cotización)'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
