-- ============================================
-- Version 3.119.151 - MT-940 statement metadata + transaction code
-- ============================================

ALTER TABLE `#__ordenproduccion_mt940_import_log`
    ADD COLUMN `statement_reference` varchar(64) DEFAULT NULL COMMENT 'MT-940 :20:' AFTER `account_number`,
    ADD COLUMN `statement_date` date DEFAULT NULL COMMENT 'Parsed from :20:' AFTER `statement_reference`,
    ADD COLUMN `statement_sequence` varchar(32) DEFAULT NULL COMMENT 'MT-940 :28C:' AFTER `statement_date`,
    ADD COLUMN `currency` varchar(3) NOT NULL DEFAULT 'GTQ' AFTER `statement_sequence`,
    ADD COLUMN `opening_balance` decimal(15,2) DEFAULT NULL COMMENT 'MT-940 :60F:' AFTER `currency`,
    ADD COLUMN `closing_balance` decimal(15,2) DEFAULT NULL COMMENT 'MT-940 :62F:' AFTER `opening_balance`,
    ADD COLUMN `closing_available_balance` decimal(15,2) DEFAULT NULL COMMENT 'MT-940 :64:' AFTER `closing_balance`;

ALTER TABLE `#__ordenproduccion_mt940_transactions`
    ADD COLUMN `transaction_code` varchar(8) DEFAULT NULL COMMENT 'MT-940 :61: code e.g. NMSC' AFTER `reference`;
