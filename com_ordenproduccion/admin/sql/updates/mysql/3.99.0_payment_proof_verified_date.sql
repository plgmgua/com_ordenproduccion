-- Payment proofs: store when a proof was marked as Verificado (validated).
-- Version: 3.99.0
--
-- Run in phpMyAdmin (replace joomla_ with your table prefix if different):
--
-- ALTER TABLE `joomla_ordenproduccion_payment_proofs`
--   ADD COLUMN `verified_date` DATETIME NULL DEFAULT NULL
--   COMMENT 'When verification_status was set to verificado'
--   AFTER `verification_status`;
--

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proofs' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'verified_date') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `verified_date` DATETIME NULL DEFAULT NULL COMMENT ''When verification_status was set to verificado'' AFTER `verification_status`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
