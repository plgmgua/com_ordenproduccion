-- ============================================
-- Version 3.71.0 - Elementos (products: name, size, price)
-- ============================================
-- Sub-page "Elementos" under Productos. Simple products with name, size, price.
-- Uses joomla_ prefix for direct execution. Replace as needed.

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_elementos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `size` varchar(100) DEFAULT NULL COMMENT 'Size description e.g. 60x90',
    `price` decimal(12,2) NOT NULL DEFAULT 0.00,
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_state` (`state`),
    KEY `idx_ordering` (`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Elementos: products with name, size, price (Productos sub-page)';
