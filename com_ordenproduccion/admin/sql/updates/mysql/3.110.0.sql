-- Vendors (Proveedores) master data — 3.110.0
-- Safe to run multiple times: create tables only if missing

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_proveedores` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `nit` varchar(64) NOT NULL DEFAULT '',
    `address` text,
    `phone` varchar(64) NOT NULL DEFAULT '',
    `contact_name` varchar(255) NOT NULL DEFAULT '',
    `contact_cellphone` varchar(64) NOT NULL DEFAULT '',
    `contact_email` varchar(255) NOT NULL DEFAULT '',
    `state` tinyint(3) NOT NULL DEFAULT 1 COMMENT '1=Activo 0=Inactivo',
    `created` datetime NOT NULL,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_state` (`state`),
    KEY `idx_name` (`name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_proveedor_productos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `proveedor_id` int(11) NOT NULL,
    `product_value` varchar(500) NOT NULL DEFAULT '' COMMENT 'Product or service line (metadata-style)',
    `ordering` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_proveedor_id` (`proveedor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
