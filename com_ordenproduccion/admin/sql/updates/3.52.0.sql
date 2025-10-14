-- Quotations System Tables
-- Version: 3.52.0
-- Date: 2025-10-14

-- Create quotations header table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_quotations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `quotation_number` varchar(50) NOT NULL,
    `client_name` varchar(255) NOT NULL,
    `client_nit` varchar(100) DEFAULT NULL,
    `client_address` text DEFAULT NULL,
    `contact_name` varchar(255) DEFAULT NULL,
    `contact_phone` varchar(100) DEFAULT NULL,
    `creation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `quote_date` date NOT NULL,
    `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
    `currency` varchar(10) DEFAULT 'Q',
    `status` varchar(50) DEFAULT 'draft',
    `notes` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    `version` varchar(20) DEFAULT '3.52.0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_quotation_number` (`quotation_number`),
    KEY `idx_client_name` (`client_name`),
    KEY `idx_client_nit` (`client_nit`),
    KEY `idx_quote_date` (`quote_date`),
    KEY `idx_status` (`status`),
    KEY `idx_state` (`state`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create quotation items/details table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_quotation_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `quotation_id` int(11) NOT NULL,
    `cantidad` decimal(10,2) NOT NULL DEFAULT '1.00',
    `descripcion` text NOT NULL,
    `valor_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
    `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
    `line_order` int(11) NOT NULL DEFAULT 0,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_quotation_id` (`quotation_id`),
    KEY `idx_line_order` (`line_order`),
    CONSTRAINT `fk_quotation_items_quotation` 
        FOREIGN KEY (`quotation_id`) 
        REFERENCES `#__ordenproduccion_quotations` (`id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


