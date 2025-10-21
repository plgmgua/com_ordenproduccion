-- Drop and recreate payment proof tables with clean structure
-- Version 3.1.6

-- Drop existing tables
DROP TABLE `joomla_ordenproduccion_payment_orders`;
DROP TABLE `joomla_ordenproduccion_payment_proofs`;

-- Create payment_proofs table with correct structure
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

-- Add payment fields to ordenes table (safe to run multiple times)
SET @dbname = DATABASE();
SET @tablename = 'joomla_ordenproduccion_ordenes';

-- Drop indexes if they exist
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE table_schema = @dbname
     AND table_name = @tablename
     AND index_name = 'idx_payment_proof_id') > 0,
    'ALTER TABLE `joomla_ordenproduccion_ordenes` DROP INDEX `idx_payment_proof_id`;',
    'SELECT 1;'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE table_schema = @dbname
     AND table_name = @tablename
     AND index_name = 'idx_payment_value') > 0,
    'ALTER TABLE `joomla_ordenproduccion_ordenes` DROP INDEX `idx_payment_value`;',
    'SELECT 1;'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

-- Drop columns if they exist
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = @dbname
     AND table_name = @tablename
     AND column_name = 'payment_proof_id') > 0,
    'ALTER TABLE `joomla_ordenproduccion_ordenes` DROP COLUMN `payment_proof_id`;',
    'SELECT 1;'
));
PREPARE alterIfExists FROM @preparedStatement;
EXECUTE alterIfExists;
DEALLOCATE PREPARE alterIfExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = @dbname
     AND table_name = @tablename
     AND column_name = 'payment_value') > 0,
    'ALTER TABLE `joomla_ordenproduccion_ordenes` DROP COLUMN `payment_value`;',
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

