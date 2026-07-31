-- com_ordenproduccion 3.119.290 — USD payment proof (phpMyAdmin, prefix joomla_)
-- Run each block once. Skip any statement that errors with "Duplicate column name".

ALTER TABLE `joomla_ordenproduccion_bank_accounts`
    ADD COLUMN `currency` varchar(3) NOT NULL DEFAULT 'GTQ' COMMENT 'GTQ or USD' AFTER `account_number`;

ALTER TABLE `joomla_ordenproduccion_payment_proof_lines`
    ADD COLUMN `currency` varchar(3) NOT NULL DEFAULT 'GTQ' COMMENT 'Line currency (from bank account)' AFTER `amount`;

ALTER TABLE `joomla_ordenproduccion_payment_proof_lines`
    ADD COLUMN `exchange_rate` decimal(12,6) DEFAULT NULL COMMENT 'GTQ per 1 USD (BANGUAT referencia)' AFTER `currency`;

ALTER TABLE `joomla_ordenproduccion_payment_proof_lines`
    ADD COLUMN `amount_gtq` decimal(10,2) DEFAULT NULL COMMENT 'GTQ equivalent for saldo / totals' AFTER `exchange_rate`;
