-- Add payment fields to ordenes table
ALTER TABLE `joomla_ordenproduccion_ordenes` 
ADD COLUMN `payment_proof_id` int(11) DEFAULT NULL COMMENT 'FK to payment_proofs table' AFTER `invoice_number`,
ADD COLUMN `payment_value` DECIMAL(10,2) DEFAULT NULL COMMENT 'Amount paid for this order' AFTER `payment_proof_id`,
ADD INDEX `idx_payment_proof_id` (`payment_proof_id`),
ADD INDEX `idx_payment_value` (`payment_value`);

-- Drop the junction table since we're using direct FK now
DROP TABLE IF EXISTS `joomla_ordenproduccion_payment_orders`;

