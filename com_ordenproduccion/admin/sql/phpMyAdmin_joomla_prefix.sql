-- =============================================================================
-- SQL scripts for phpMyAdmin - table prefix: joomla_
-- com_ordenproduccion - Invoices & FEL import
-- =============================================================================
-- Run these in order. If you already have the invoices table (from 3.43.0),
-- run only "Script 2 - 3.97.0 FEL import support".
-- =============================================================================


-- -----------------------------------------------------------------------------
-- Script 1 - 3.43.0 (only if you don't have the invoices table yet)
-- -----------------------------------------------------------------------------

-- Invoices table for storing invoice data
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_invoices` (
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

-- Add invoice_number column to ordenes table to link invoices (skip if column already exists)
ALTER TABLE `joomla_ordenproduccion_ordenes`
ADD COLUMN `invoice_number` varchar(50) DEFAULT NULL AFTER `valor_a_facturar`,
ADD KEY `idx_invoice_number` (`invoice_number`);

-- Update component version (if you have this table)
-- UPDATE `joomla_ordenproduccion_config` SET `setting_value` = '3.43.0-STABLE' WHERE `setting_key` = 'component_version';


-- -----------------------------------------------------------------------------
-- Script 2 - 3.97.0 Invoices FEL import support (REQUIRED for XML import)
-- -----------------------------------------------------------------------------

-- Make orden_id nullable so invoices can be imported from XML without a work order
ALTER TABLE `joomla_ordenproduccion_invoices`
    MODIFY COLUMN `orden_id` int(11) NULL DEFAULT NULL;

-- Allow orden_de_trabajo empty for FEL imports (keep NOT NULL but default '')
ALTER TABLE `joomla_ordenproduccion_invoices`
    MODIFY COLUMN `orden_de_trabajo` varchar(50) NOT NULL DEFAULT '';

-- FEL authorization UUID (SAT Guatemala) - unique for imported DTE
ALTER TABLE `joomla_ordenproduccion_invoices`
    ADD COLUMN `fel_autorizacion_uuid` varchar(64) NULL DEFAULT NULL AFTER `version`,
    ADD UNIQUE KEY `idx_fel_autorizacion_uuid` (`fel_autorizacion_uuid`);

-- FEL document type (e.g. FCAM = Factura Cambiaria)
ALTER TABLE `joomla_ordenproduccion_invoices`
    ADD COLUMN `fel_tipo_dte` varchar(20) NULL DEFAULT NULL AFTER `fel_autorizacion_uuid`;

-- FEL emission datetime (from DatosGenerales FechaHoraEmision)
ALTER TABLE `joomla_ordenproduccion_invoices`
    ADD COLUMN `fel_fecha_emision` datetime NULL DEFAULT NULL AFTER `fel_tipo_dte`;

-- Emisor (issuer) from XML
ALTER TABLE `joomla_ordenproduccion_invoices`
    ADD COLUMN `fel_emisor_nit` varchar(20) NULL DEFAULT NULL AFTER `fel_fecha_emision`,
    ADD COLUMN `fel_emisor_nombre` varchar(255) NULL DEFAULT NULL AFTER `fel_emisor_nit`;

-- Receptor (receiver) from XML
ALTER TABLE `joomla_ordenproduccion_invoices`
    ADD COLUMN `fel_receptor_id` varchar(50) NULL DEFAULT NULL AFTER `fel_emisor_nombre`,
    ADD COLUMN `fel_receptor_nombre` varchar(255) NULL DEFAULT NULL AFTER `fel_receptor_id`,
    ADD COLUMN `fel_receptor_direccion` text NULL DEFAULT NULL AFTER `fel_receptor_nombre`;

-- Currency from XML (CodigoMoneda)
ALTER TABLE `joomla_ordenproduccion_invoices`
    ADD COLUMN `fel_moneda` varchar(5) NULL DEFAULT NULL AFTER `fel_receptor_direccion`;

-- Source: 'order' = from work order, 'fel_import' = from XML
ALTER TABLE `joomla_ordenproduccion_invoices`
    ADD COLUMN `invoice_source` varchar(20) NOT NULL DEFAULT 'order' AFTER `fel_moneda`;
