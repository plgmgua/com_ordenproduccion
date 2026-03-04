-- Payment proofs: verification status (Ingresado / Verificado). Only Verificado affects client balance.
-- Version: 3.84.0
--
-- Run in phpMyAdmin (replace joomla_ with your table prefix if different):
--
-- ALTER TABLE `joomla_ordenproduccion_payment_proofs`
--   ADD COLUMN `verification_status` VARCHAR(20) NOT NULL DEFAULT 'verificado'
--   COMMENT 'ingresado=not yet validated; verificado=counts toward client balance'
--   AFTER `state`;
--

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proofs' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'verification_status') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `verification_status` VARCHAR(20) NOT NULL DEFAULT ''verificado'' COMMENT ''ingresado=not yet validated; verificado=counts toward client balance'' AFTER `state`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
