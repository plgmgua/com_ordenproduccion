-- ============================================
-- Version 3.119.228 - MT-940 payment proof matching (Verificar pago)
-- Revert: admin/sql/rollback/3.119.228_mt940_payment_match_rollback.sql
-- Toggle: component param payment_proof_mt940_verification = 0 restores legacy flow
-- ============================================

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proof_lines' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mt940_transaction_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mt940_transaction_id` INT(11) NULL DEFAULT NULL COMMENT ''FK to mt940_transactions when matched'' AFTER `bank_account_id`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mt940_match_status') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mt940_match_status` VARCHAR(32) NULL DEFAULT NULL COMMENT ''pending|matched|ambiguous|no_match|manual|approved'' AFTER `mt940_transaction_id`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mt940_match_checked_at') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mt940_match_checked_at` DATETIME NULL DEFAULT NULL AFTER `mt940_match_status`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_payment_mt940_match_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `payment_proof_id` int(11) NOT NULL DEFAULT 0,
    `payment_proof_line_id` int(11) NULL DEFAULT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'info' COMMENT 'matched|ambiguous|no_match|skipped|error|info',
    `message` text,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_payment_proof_id` (`payment_proof_id`),
    KEY `idx_line_id` (`payment_proof_line_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='MT-940 payment match cron diagnostics';
