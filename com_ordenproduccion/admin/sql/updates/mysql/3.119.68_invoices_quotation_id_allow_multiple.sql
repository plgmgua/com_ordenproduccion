-- Allow multiple FEL invoices per cotización (manual split billing)
-- Version: 3.119.68

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_invoices' LIMIT 1);

SET @sql_drop_uq = IF(
    @tbl IS NOT NULL
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = 'uq_ordenproduccion_invoices_quotation_id') > 0,
    CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `uq_ordenproduccion_invoices_quotation_id`'),
    'SELECT 1');
PREPARE stmt_drop FROM @sql_drop_uq;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET @sql_idx = IF(
    @tbl IS NOT NULL
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = 'idx_ordenproduccion_invoices_quotation_id') = 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'quotation_id') > 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD KEY `idx_ordenproduccion_invoices_quotation_id` (`quotation_id`)'),
    'SELECT 1');
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
