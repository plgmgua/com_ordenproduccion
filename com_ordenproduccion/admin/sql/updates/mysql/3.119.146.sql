-- ============================================
-- Version 3.119.146 - MT-940 bank statement import (settings + storage)
-- ============================================

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_mt940_transactions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `bank_account_id` int(11) NOT NULL DEFAULT 0 COMMENT 'FK to #__ordenproduccion_bank_accounts.id',
    `source_email_uid` varchar(64) DEFAULT NULL COMMENT 'IMAP UID of source email',
    `source_filename` varchar(255) DEFAULT NULL COMMENT 'Attached MT-940 .txt filename',
    `statement_date` date DEFAULT NULL,
    `transaction_date` date DEFAULT NULL,
    `value_date` date DEFAULT NULL,
    `reference` varchar(64) DEFAULT NULL,
    `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
    `currency` varchar(3) NOT NULL DEFAULT 'GTQ',
    `debit_credit` char(1) DEFAULT NULL COMMENT 'C=credit, D=debit',
    `description` text,
    `raw_line` text,
    `imported_at` datetime NOT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_bank_account` (`bank_account_id`),
    KEY `idx_statement_date` (`statement_date`),
    KEY `idx_transaction_date` (`transaction_date`),
    KEY `idx_reference` (`reference`),
    KEY `idx_source_email_uid` (`source_email_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parsed MT-940 bank transactions';

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_mt940_import_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email_uid` varchar(64) DEFAULT NULL,
    `sender` varchar(255) DEFAULT NULL,
    `subject` varchar(500) DEFAULT NULL,
    `filename` varchar(255) DEFAULT NULL,
    `status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT 'pending|imported|skipped|error',
    `transactions_count` int(11) NOT NULL DEFAULT 0,
    `message` text,
    `imported_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_email_uid` (`email_uid`),
    KEY `idx_imported_at` (`imported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MT-940 email import run log';
