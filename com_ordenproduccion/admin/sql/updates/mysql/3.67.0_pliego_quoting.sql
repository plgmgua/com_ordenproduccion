-- ============================================
-- Version 3.67.0 - Pliego quoting / product system
-- ============================================
-- Run this script manually in phpMyAdmin.
-- Table prefix: joomla_
-- Tables: sizes (shared), paper types, print prices, lamination types/prices,
-- additional processes (cut, bend, etc.), and cotizaciones (quotes).

-- 1. Pliego sizes (shared by paper and lamination)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_pliego_sizes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL COMMENT 'Display name e.g. 60x90',
    `code` varchar(50) DEFAULT NULL,
    `width_cm` decimal(8,2) DEFAULT NULL,
    `height_cm` decimal(8,2) DEFAULT NULL,
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pliego sizes (shared by paper and lamination)';

-- 2. Paper types
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_paper_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `code` varchar(50) DEFAULT NULL,
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paper types for pliegos';

-- 3. Paper type – size availability (not all papers in all sizes)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_paper_type_sizes` (
    `paper_type_id` int(11) NOT NULL,
    `size_id` int(11) NOT NULL,
    PRIMARY KEY (`paper_type_id`, `size_id`),
    KEY `idx_size_id` (`size_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Which sizes are available for each paper type';

-- 4. Print price per pliego: paper_type + size + tiro/retiro + quantity range (1–500, 501+)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_pliego_print_prices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `paper_type_id` int(11) NOT NULL,
    `size_id` int(11) NOT NULL,
    `tiro_retiro` varchar(20) NOT NULL DEFAULT 'tiro' COMMENT 'tiro=one side, retiro=both sides',
    `qty_min` int(11) NOT NULL DEFAULT 1,
    `qty_max` int(11) NOT NULL DEFAULT 999999,
    `price_per_sheet` decimal(10,4) NOT NULL DEFAULT 0.0000,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_paper_size_tiro_range` (`paper_type_id`, `size_id`, `tiro_retiro`, `qty_min`),
    KEY `idx_paper_type_id` (`paper_type_id`),
    KEY `idx_size_id` (`size_id`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Print price per sheet by paper, size, tiro/retiro, quantity range';

-- 5. Lamination types (all have all sizes)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_lamination_types` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `code` varchar(50) DEFAULT NULL,
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lamination material types';

-- 6. Lamination price per pliego: lamination_type + size + tiro/retiro + qty ranges (1–9, 10–500, 501+)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_lamination_prices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `lamination_type_id` int(11) NOT NULL,
    `size_id` int(11) NOT NULL,
    `tiro_retiro` varchar(20) NOT NULL DEFAULT 'tiro',
    `qty_min` int(11) NOT NULL DEFAULT 1,
    `qty_max` int(11) NOT NULL DEFAULT 999999,
    `price_per_sheet` decimal(10,4) NOT NULL DEFAULT 0.0000,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_lam_size_tiro_range` (`lamination_type_id`, `size_id`, `tiro_retiro`, `qty_min`),
    KEY `idx_lamination_type_id` (`lamination_type_id`),
    KEY `idx_size_id` (`size_id`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lamination price per sheet by type, size, tiro/retiro, quantity range';

-- 7. Additional processes (cut, bend, perforado, pegado, engrapado, etc.) – fixed price per pliego
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_pliego_processes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `code` varchar(50) DEFAULT NULL,
    `price_per_pliego` decimal(10,4) NOT NULL DEFAULT 0.0000,
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ordering` (`ordering`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional processes: cut, bend, perforado, pegado, engrapado, etc.';

-- 8. Cotizaciones (quotes)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_cotizaciones_pliego` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `quote_number` varchar(50) DEFAULT NULL,
    `client_name` varchar(255) DEFAULT NULL,
    `quantity` int(11) NOT NULL DEFAULT 1,
    `paper_type_id` int(11) NOT NULL,
    `size_id` int(11) NOT NULL,
    `tiro_retiro` varchar(20) NOT NULL DEFAULT 'tiro',
    `needs_lamination` tinyint(1) NOT NULL DEFAULT 0,
    `lamination_type_id` int(11) DEFAULT NULL,
    `print_price_per_sheet` decimal(10,4) DEFAULT NULL,
    `lamination_price_per_sheet` decimal(10,4) DEFAULT NULL,
    `processes_total_per_sheet` decimal(10,4) DEFAULT 0.0000,
    `total_price` decimal(12,2) DEFAULT NULL,
    `notes` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_quote_number` (`quote_number`),
    KEY `idx_created` (`created`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pliego quotes';

-- 9. Quote – additional processes (many-to-many)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_cotizacion_pliego_processes` (
    `cotizacion_id` int(11) NOT NULL,
    `process_id` int(11) NOT NULL,
    PRIMARY KEY (`cotizacion_id`, `process_id`),
    KEY `idx_process_id` (`process_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Additional processes applied to a quote';
