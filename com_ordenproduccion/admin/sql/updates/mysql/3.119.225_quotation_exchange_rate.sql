-- com_ordenproduccion 3.119.225-STABLE: BANGUAT USD exchange rate snapshot on quotation create (display-only USD toggle).

ALTER TABLE `#__ordenproduccion_quotations`
    ADD COLUMN IF NOT EXISTS `exchange_rate` decimal(12,6) DEFAULT NULL COMMENT 'GTQ per 1 USD (BANGUAT referencia) at quotation date',
    ADD COLUMN IF NOT EXISTS `exchange_rate_date` date DEFAULT NULL COMMENT 'Date used for exchange_rate lookup (quote_date or creation)';
