-- ============================================
-- Version 3.119.149 - MT-940: bank account numbers + import metadata
-- ============================================

ALTER TABLE `#__ordenproduccion_bank_accounts`
    ADD COLUMN `account_number` varchar(32) DEFAULT NULL COMMENT 'Bank account number as in MT-940 :25: field' AFTER `name`,
    ADD KEY `idx_account_number` (`account_number`);

ALTER TABLE `#__ordenproduccion_mt940_import_log`
    ADD COLUMN `bank_account_id` int(11) NOT NULL DEFAULT 0 AFTER `filename`,
    ADD COLUMN `account_number` varchar(32) DEFAULT NULL COMMENT 'Account from MT-940 :25:' AFTER `bank_account_id`,
    ADD KEY `idx_bank_account_id` (`bank_account_id`),
    ADD KEY `idx_account_number` (`account_number`);

ALTER TABLE `#__ordenproduccion_mt940_transactions`
    ADD COLUMN `account_number` varchar(32) DEFAULT NULL COMMENT 'Account from MT-940 :25:' AFTER `bank_account_id`,
    ADD COLUMN `import_log_id` int(11) NOT NULL DEFAULT 0 AFTER `source_filename`,
    ADD KEY `idx_import_log_id` (`import_log_id`),
    ADD KEY `idx_account_number` (`account_number`);
