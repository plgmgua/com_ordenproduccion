-- Invoice: optional manually attached PDF (regular invoices; not used by mock FEL queue).
-- Version: 3.103.3

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_invoices' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'manual_pdf_path') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `manual_pdf_path` varchar(512) NULL DEFAULT NULL COMMENT ''User-uploaded PDF (factura adjunta)'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
