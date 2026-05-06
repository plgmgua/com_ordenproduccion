-- ============================================
-- Version 3.118.0 - Bank accounts (cuentas bancarias)
-- ============================================

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_bank_accounts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL COMMENT 'Account display name',
    `state` tinyint(3) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_state` (`state`),
    KEY `idx_name` (`name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Company bank accounts for receipts / reference';
