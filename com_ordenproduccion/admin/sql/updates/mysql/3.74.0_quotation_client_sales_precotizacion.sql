-- Quotation: client_id, sales_agent (Agente de Ventas); quotation_items: pre_cotizacion_id
-- Version: 3.74.0

SET @dbname = DATABASE();

-- quotations: client_id
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotations' LIMIT 1);
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'client_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `client_id` int(11) DEFAULT NULL COMMENT ''External client id from URL'' AFTER `client_nit`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'sales_agent') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `sales_agent` varchar(255) DEFAULT NULL COMMENT ''Agente de Ventas'' AFTER `contact_phone`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- quotation_items: pre_cotizacion_id
SET @tbl2 = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotation_items' LIMIT 1);
SET @sql = IF(
    @tbl2 IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl2 AND COLUMN_NAME = 'pre_cotizacion_id') = 0,
    CONCAT('ALTER TABLE `', @tbl2, '` ADD COLUMN `pre_cotizacion_id` int(11) DEFAULT NULL COMMENT ''Pre-Cotización id when line is from pre-cotización'' AFTER `quotation_id`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
