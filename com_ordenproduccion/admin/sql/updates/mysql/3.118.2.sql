-- ============================================
-- Version 3.118.2 - Payment proof lines: company bank account
-- ============================================

ALTER TABLE `#__ordenproduccion_payment_proof_lines`
    ADD COLUMN `bank_account_id` int(11) DEFAULT NULL COMMENT 'FK to #__ordenproduccion_bank_accounts.id' AFTER `bank`;
