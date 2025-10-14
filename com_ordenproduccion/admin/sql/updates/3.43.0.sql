-- --------------------------------------------------------
-- Database update script for com_ordenproduccion
-- Version: 3.43.0
-- Author: Grimpsa
-- Date: 2025-01-27
-- 
-- Add invoices table for invoice management
-- --------------------------------------------------------

-- Invoices table for storing invoice data
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_invoices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` varchar(50) NOT NULL,
    `orden_id` int(11) NOT NULL,
    `orden_de_trabajo` varchar(50) NOT NULL,
    `client_name` varchar(255) NOT NULL,
    `client_nit` varchar(50) DEFAULT NULL,
    `client_address` text DEFAULT NULL,
    `sales_agent` varchar(255) DEFAULT NULL,
    `request_date` date DEFAULT NULL,
    `delivery_date` date DEFAULT NULL,
    `invoice_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `invoice_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    `currency` varchar(3) DEFAULT 'Q',
    `work_description` text DEFAULT NULL,
    `material` varchar(255) DEFAULT NULL,
    `dimensions` varchar(255) DEFAULT NULL,
    `print_color` varchar(100) DEFAULT NULL,
    `line_items` longtext DEFAULT NULL,
    `quotation_file` varchar(500) DEFAULT NULL,
    `extraction_status` enum('manual','automatic','pending') DEFAULT 'manual',
    `extraction_date` datetime DEFAULT NULL,
    `status` enum('draft','created','sent','paid','cancelled') DEFAULT 'draft',
    `notes` text DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    `version` varchar(20) DEFAULT '3.43.0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_invoice_number` (`invoice_number`),
    KEY `idx_orden_id` (`orden_id`),
    KEY `idx_orden_de_trabajo` (`orden_de_trabajo`),
    KEY `idx_client_name` (`client_name`),
    KEY `idx_invoice_date` (`invoice_date`),
    KEY `idx_status` (`status`),
    KEY `idx_state` (`state`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add invoice_number column to ordenes table to link invoices
ALTER TABLE `#__ordenproduccion_ordenes` 
ADD COLUMN `invoice_number` varchar(50) DEFAULT NULL AFTER `valor_a_facturar`,
ADD KEY `idx_invoice_number` (`invoice_number`);

-- Update component version
UPDATE `#__ordenproduccion_config` SET `setting_value` = '3.43.0-STABLE' WHERE `setting_key` = 'component_version';
