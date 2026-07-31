-- com_ordenproduccion 3.119.290-STABLE: Bank account currency + USD payment proof lines.
-- Compatible with MySQL 5.7 / MariaDB (no ADD COLUMN IF NOT EXISTS).

SET @dbname = DATABASE();

-- bank_accounts.currency
SET @tbl_ba = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_bank_accounts' LIMIT 1);

SET @sql_ba = IF(
    @tbl_ba IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_ba AND COLUMN_NAME = 'currency') = 0,
    CONCAT('ALTER TABLE `', @tbl_ba, '` ADD COLUMN `currency` varchar(3) NOT NULL DEFAULT ''GTQ'' COMMENT ''GTQ or USD'' AFTER `account_number`'),
    'SELECT 1');
PREPARE stmt_ba FROM @sql_ba;
EXECUTE stmt_ba;
DEALLOCATE PREPARE stmt_ba;

-- payment_proof_lines.currency, exchange_rate, amount_gtq
SET @tbl_ppl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proof_lines' LIMIT 1);

SET @sql_ppl_cur = IF(
    @tbl_ppl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_ppl AND COLUMN_NAME = 'currency') = 0,
    CONCAT('ALTER TABLE `', @tbl_ppl, '` ADD COLUMN `currency` varchar(3) NOT NULL DEFAULT ''GTQ'' COMMENT ''Line currency (from bank account)'' AFTER `amount`'),
    'SELECT 1');
PREPARE stmt_ppl_cur FROM @sql_ppl_cur;
EXECUTE stmt_ppl_cur;
DEALLOCATE PREPARE stmt_ppl_cur;

SET @sql_ppl_rate = IF(
    @tbl_ppl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_ppl AND COLUMN_NAME = 'exchange_rate') = 0,
    CONCAT('ALTER TABLE `', @tbl_ppl, '` ADD COLUMN `exchange_rate` decimal(12,6) DEFAULT NULL COMMENT ''GTQ per 1 USD (BANGUAT referencia)'' AFTER `currency`'),
    'SELECT 1');
PREPARE stmt_ppl_rate FROM @sql_ppl_rate;
EXECUTE stmt_ppl_rate;
DEALLOCATE PREPARE stmt_ppl_rate;

SET @sql_ppl_gtq = IF(
    @tbl_ppl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl_ppl AND COLUMN_NAME = 'amount_gtq') = 0,
    CONCAT('ALTER TABLE `', @tbl_ppl, '` ADD COLUMN `amount_gtq` decimal(10,2) DEFAULT NULL COMMENT ''GTQ equivalent for saldo / totals'' AFTER `exchange_rate`'),
    'SELECT 1');
PREPARE stmt_ppl_gtq FROM @sql_ppl_gtq;
EXECUTE stmt_ppl_gtq;
DEALLOCATE PREPARE stmt_ppl_gtq;
