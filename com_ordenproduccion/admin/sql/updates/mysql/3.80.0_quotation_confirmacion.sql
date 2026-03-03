-- Quotation confirmation: signed document path and billing instructions
-- Version: 3.80.0

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotations' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'signed_document_path') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `signed_document_path` varchar(500) DEFAULT NULL COMMENT ''Path to signed cotización file (proof of acceptance)'' AFTER `notes`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'instrucciones_facturacion') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `instrucciones_facturacion` text DEFAULT NULL COMMENT ''Billing instructions (Confirmar Cotización step 2)'' AFTER `signed_document_path`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
