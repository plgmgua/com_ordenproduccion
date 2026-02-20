-- ============================================
-- Version 3.70.0 - Pre-Cotización (pre-quote) CRUD
-- ============================================
-- Pre-Cotización = container for multiple pliego quote lines.
-- Each user sees only their own Pre-Cotizaciones. Number format: PRE-00001 (global sequence).
-- Uses joomla_ prefix for direct execution. Replace as needed.

-- 1. Pre-Cotización header (one per document, owned by user)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_pre_cotizacion` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `number` varchar(20) NOT NULL COMMENT 'PRE-00001 format, global sequence',
    `created_by` int(11) NOT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_number` (`number`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_created` (`created`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pre-quote header; one number per document, user-scoped list';

-- 2. Pre-Cotización lines (one pliego calculation per line; stores inputs + result for replay)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_pre_cotizacion_line` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `pre_cotizacion_id` int(11) NOT NULL,
    `quantity` int(11) NOT NULL DEFAULT 1,
    `paper_type_id` int(11) NOT NULL,
    `size_id` int(11) NOT NULL,
    `tiro_retiro` varchar(20) NOT NULL DEFAULT 'tiro',
    `lamination_type_id` int(11) DEFAULT NULL,
    `lamination_tiro_retiro` varchar(20) DEFAULT 'tiro',
    `process_ids` text DEFAULT NULL COMMENT 'JSON array of process IDs',
    `price_per_sheet` decimal(10,4) NOT NULL DEFAULT 0.0000,
    `total` decimal(12,2) NOT NULL DEFAULT 0.00,
    `calculation_breakdown` text DEFAULT NULL COMMENT 'JSON: rows (label, detail, subtotal) for display',
    `ordering` int(11) NOT NULL DEFAULT 0,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pre_cotizacion_id` (`pre_cotizacion_id`),
    KEY `idx_ordering` (`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='One pliego quote per line; stores inputs and result for Pre-Cotización';
