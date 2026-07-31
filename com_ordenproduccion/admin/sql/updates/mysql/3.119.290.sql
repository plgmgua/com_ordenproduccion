-- com_ordenproduccion 3.119.290-STABLE: Bank account currency + USD payment proof lines.

ALTER TABLE `#__ordenproduccion_bank_accounts`
    ADD COLUMN IF NOT EXISTS `currency` varchar(3) NOT NULL DEFAULT 'GTQ' COMMENT 'GTQ or USD' AFTER `account_number`;

ALTER TABLE `#__ordenproduccion_payment_proof_lines`
    ADD COLUMN IF NOT EXISTS `currency` varchar(3) NOT NULL DEFAULT 'GTQ' COMMENT 'Line currency (from bank account)' AFTER `amount`,
    ADD COLUMN IF NOT EXISTS `exchange_rate` decimal(12,6) DEFAULT NULL COMMENT 'GTQ per 1 USD (BANGUAT referencia)',
    ADD COLUMN IF NOT EXISTS `amount_gtq` decimal(10,2) DEFAULT NULL COMMENT 'GTQ equivalent for saldo / totals';
