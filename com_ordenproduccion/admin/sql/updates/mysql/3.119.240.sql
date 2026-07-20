-- ============================================
-- Version 3.119.240 - Payment type default bank / destination account
-- ============================================

ALTER TABLE `#__ordenproduccion_payment_types`
    ADD COLUMN `default_bank` varchar(100) DEFAULT NULL
        COMMENT 'Default origin bank code for payment lines'
        AFTER `super_user_only`,
    ADD COLUMN `default_bank_account_id` int(11) DEFAULT NULL
        COMMENT 'Default destination bank account id for payment lines'
        AFTER `default_bank`;
