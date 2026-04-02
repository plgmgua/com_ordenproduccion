-- Link invoices to quotations (Lista de Cotizaciones: estado Facturada)
-- Version: 3.101.47

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_invoices' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'quotation_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `quotation_id` int(11) NULL DEFAULT NULL COMMENT ''FK to #__ordenproduccion_quotations when invoice is tied to a quote'' AFTER `orden_id`, ADD KEY `idx_quotation_id` (`quotation_id`)'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
