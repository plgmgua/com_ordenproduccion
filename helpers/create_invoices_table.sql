-- Create invoices table for Com Orden Produccion
-- This table stores invoice records linked to work orders

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_invoices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `invoice_number` VARCHAR(50) NOT NULL,
    `orden_id` INT(11) NOT NULL COMMENT 'Foreign key to ordenes table',
    `orden_de_trabajo` VARCHAR(50) NOT NULL COMMENT 'Work order number for reference',
    `client_name` VARCHAR(255) NOT NULL,
    `client_nit` VARCHAR(100) DEFAULT NULL,
    `sales_agent` VARCHAR(255) DEFAULT NULL,
    `request_date` DATE DEFAULT NULL,
    `delivery_date` DATE DEFAULT NULL,
    `invoice_date` DATE NOT NULL COMMENT 'Date invoice was created',
    `invoice_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(10) DEFAULT 'Q' COMMENT 'Currency symbol (Q, $, etc.)',
    
    -- Work information from order
    `work_description` TEXT DEFAULT NULL,
    `material` VARCHAR(255) DEFAULT NULL,
    `dimensions` VARCHAR(255) DEFAULT NULL,
    `print_color` VARCHAR(255) DEFAULT NULL,
    
    -- Invoice line items (JSON format for flexibility)
    `line_items` TEXT DEFAULT NULL COMMENT 'JSON array of invoice line items',
    
    -- PDF extraction metadata
    `quotation_file` TEXT DEFAULT NULL COMMENT 'Path to original quotation PDF',
    `extraction_status` VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, extracted, failed, manual',
    `extraction_date` DATETIME DEFAULT NULL,
    
    -- Invoice status
    `status` VARCHAR(50) DEFAULT 'draft' COMMENT 'draft, sent, paid, cancelled',
    `notes` TEXT DEFAULT NULL,
    
    -- Metadata
    `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT(11) NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    `modified_by` INT(11) DEFAULT NULL,
    `state` TINYINT(3) NOT NULL DEFAULT 1,
    `version` VARCHAR(20) DEFAULT '3.2.0',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_invoice_number` (`invoice_number`),
    KEY `idx_orden_id` (`orden_id`),
    KEY `idx_orden_de_trabajo` (`orden_de_trabajo`),
    KEY `idx_status` (`status`),
    KEY `idx_invoice_date` (`invoice_date`),
    KEY `idx_sales_agent` (`sales_agent`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample line_items JSON structure:
-- [
--   {
--     "quantity": 2,
--     "description": "Rótulos en PVC de 3mm con impresión medidas 50x50 cms con type doble cara, con plastico mate. corte",
--     "unit_price": 225.00,
--     "total": 450.00
--   }
-- ]

