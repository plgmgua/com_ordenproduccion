-- ============================================
-- Version 3.77.0 - Envíos (shipment types: fixed price / custom price)
-- ============================================
-- Sub-view "Envíos" under Administración de Imprenta.
-- tipo: 'fixed' = name + valor; 'custom' = name only, amount requested when added to pre-cotización.
-- Manual run in phpMyAdmin: uses prefix joomla_. Change table name if your prefix is different.

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_envios` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL COMMENT 'Display name',
    `tipo` varchar(20) NOT NULL DEFAULT 'fixed' COMMENT 'fixed or custom',
    `valor` decimal(12,2) DEFAULT NULL COMMENT 'Fixed price; NULL for custom',
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_state` (`state`),
    KEY `idx_tipo` (`tipo`),
    KEY `idx_ordering` (`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shipment types: fixed price or custom (amount on pre-cotización)';
