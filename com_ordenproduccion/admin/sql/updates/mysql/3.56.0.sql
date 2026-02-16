-- ============================================
-- Version 3.56.0 - Client opening balance (Jan 1 2026 cutover)
-- ============================================
-- Stores initial amount paid per client up to Dec 31 2025 for accounting cutover.
-- Saldo (balance) = Total invoiced - initial_paid_to_dec31_2025 - payments from Jan 1 2026
-- Note: Uses joomla_ prefix for direct phpMyAdmin execution. For other prefixes, replace joomla_ accordingly.

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_client_opening_balance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `client_name` varchar(255) NOT NULL,
    `nit` varchar(100) DEFAULT NULL,
    `amount_paid_to_dec31_2025` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount paid by client up to 2025-12-31',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_client_nit` (`client_name`(191), `nit`(50)),
    KEY `idx_client_name` (`client_name`(191)),
    KEY `idx_nit` (`nit`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Opening balance: amount paid per client up to Dec 31 2025';
