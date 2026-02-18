-- ============================================
-- Version 3.65.0 - Payment types management
-- ============================================
-- Manage payment types (efectivo, cheque, etc.) like banks.
-- Uses joomla_ prefix for direct execution. Replace as needed.

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_payment_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `code` varchar(100) NOT NULL COMMENT 'Unique code (e.g., efectivo, cheque)',
    `name` varchar(255) NOT NULL COMMENT 'Display name',
    `name_en` varchar(255) DEFAULT NULL,
    `name_es` varchar(255) DEFAULT NULL,
    `ordering` int(11) NOT NULL DEFAULT 0,
    `requires_bank` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show bank field, 0=hide (e.g. efectivo)',
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_code` (`code`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment types (efectivo, cheque, transferencia, etc.)';

-- Insert default payment types
INSERT INTO `joomla_ordenproduccion_payment_types` (`code`, `name`, `name_en`, `name_es`, `ordering`, `requires_bank`, `state`, `created_by`) VALUES
('efectivo', 'Efectivo', 'Cash', 'Efectivo', 1, 0, 1, 0),
('cheque', 'Cheque', 'Check', 'Cheque', 2, 1, 1, 0),
('transferencia', 'Transferencia Bancaria', 'Bank Transfer', 'Transferencia Bancaria', 3, 1, 1, 0),
('deposito', 'Depósito Bancario', 'Bank Deposit', 'Depósito Bancario', 4, 1, 1, 0),
('nota_credito_fiscal', 'Nota Crédito Fiscal', 'Tax Credit Note', 'Nota Crédito Fiscal', 5, 1, 1, 0);
