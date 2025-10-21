-- Add payment_amount column to payment_proofs table
ALTER TABLE `joomla_ordenproduccion_payment_proofs` 
ADD COLUMN `payment_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount of this payment' AFTER `document_number`;

-- Create junction table for payment proofs to work orders (many-to-many relationship)
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_payment_orders` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `payment_proof_id` int(11) NOT NULL COMMENT 'FK to payment_proofs table',
    `order_id` int(11) NOT NULL COMMENT 'FK to ordenes table',
    `amount_applied` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount applied to this specific order',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_payment_proof_id` (`payment_proof_id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_created` (`created`),
    UNIQUE KEY `idx_payment_order` (`payment_proof_id`, `order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Junction table linking payment proofs to multiple work orders';

-- Add index for better query performance
ALTER TABLE `joomla_ordenproduccion_payment_proofs`
ADD INDEX `idx_payment_amount` (`payment_amount`);

