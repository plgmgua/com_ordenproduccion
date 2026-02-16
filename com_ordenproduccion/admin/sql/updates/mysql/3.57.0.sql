-- ============================================
-- Version 3.57.0 - Client balance (Saldo) table for reuse
-- ============================================
-- Stores current Saldo per client for use by other views/modules.
-- Note: Uses joomla_ prefix for direct phpMyAdmin execution.

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_client_balance` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `client_name` varchar(255) NOT NULL,
    `nit` varchar(100) DEFAULT NULL,
    `saldo` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Current balance (Total invoiced - paid)',
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_client_nit` (`client_name`(191), `nit`(50)),
    KEY `idx_client_name` (`client_name`(191)),
    KEY `idx_nit` (`nit`(50)),
    KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Client Saldo (balance) - reusable by views/modules';
