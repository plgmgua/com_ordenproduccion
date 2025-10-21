-- Drop and recreate payment proof tables with clean structure
-- Version 3.1.6

-- Drop existing tables
DROP TABLE IF EXISTS `joomla_ordenproduccion_payment_orders`;
DROP TABLE IF EXISTS `joomla_ordenproduccion_payment_proofs`;

-- Recreate payment_proofs table with correct structure
CREATE TABLE `joomla_ordenproduccion_payment_proofs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL COMMENT 'Primary/first order in this payment',
    `payment_type` varchar(50) NOT NULL COMMENT 'efectivo, cheque, transferencia, deposito',
    `bank` varchar(100) DEFAULT NULL,
    `document_number` varchar(255) NOT NULL,
    `payment_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total payment amount',
    `file_path` varchar(500) DEFAULT NULL,
    `created_by` int(11) NOT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `version` varchar(20) DEFAULT '3.1.6',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_payment_type` (`payment_type`),
    KEY `idx_payment_amount` (`payment_amount`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_state` (`state`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment proof records for work orders';

-- Add payment fields to ordenes table if they don't exist
-- First check if columns exist and drop them if they do
SET @dbname = DATABASE();
SET @tablename = 'joomla_ordenproduccion_ordenes';
SET @columnname1 = 'payment_proof_id';
SET @columnname2 = 'payment_value';

-- Check and drop payment_proof_id if exists
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE (table_name = @tablename)
     AND (table_schema = @dbname)
     AND (column_name = @columnname1)) > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN `', @columnname1, '`;'),
    'SELECT 1;'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Check and drop payment_value if exists
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE (table_name = @tablename)
     AND (table_schema = @dbname)
     AND (column_name = @columnname2)) > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN `', @columnname2, '`;'),
    'SELECT 1;'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Now add the columns with correct structure
ALTER TABLE `joomla_ordenproduccion_ordenes` 
ADD COLUMN `payment_proof_id` int(11) DEFAULT NULL COMMENT 'FK to payment_proofs table' AFTER `invoice_number`,
ADD COLUMN `payment_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Amount paid for this order' AFTER `payment_proof_id`,
ADD INDEX `idx_payment_proof_id` (`payment_proof_id`),
ADD INDEX `idx_payment_value` (`payment_value`);

