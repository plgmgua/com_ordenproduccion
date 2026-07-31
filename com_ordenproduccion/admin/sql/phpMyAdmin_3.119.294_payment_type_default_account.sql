-- Manual phpMyAdmin: payment_types default bank / destination account columns
-- Prefix: joomla_  — run each ALTER; skip if column already exists.

ALTER TABLE `joomla_ordenproduccion_payment_types`
    ADD COLUMN `default_bank` varchar(100) DEFAULT NULL
        COMMENT 'Default origin bank code for payment lines';

ALTER TABLE `joomla_ordenproduccion_payment_types`
    ADD COLUMN `default_bank_account_id` int(11) DEFAULT NULL
        COMMENT 'Default destination bank account id for payment lines';

-- Example: set default USD cuenta destino for tax types (adjust account id as needed)
-- UPDATE `joomla_ordenproduccion_payment_types`
-- SET `default_bank_account_id` = 3
-- WHERE `code` IN ('retencion_de_isr', 'retencion_sat', 'exencion_iva');
