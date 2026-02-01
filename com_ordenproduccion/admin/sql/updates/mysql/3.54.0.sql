-- ============================================
-- Version 3.54.0 - Many-to-many payment documents and work orders
-- ============================================
-- Allows: multiple payment documents per work order, multiple work orders per payment document
-- Migrates existing payment_proof_id/payment_value from ordenes to new junction table

-- 1. Create junction table for payment-proof-to-order relationships
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_payment_orders` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Junction table linking payment proofs to work orders (many-to-many)';

-- 2. Migrate existing data from ordenes to payment_orders
INSERT INTO `#__ordenproduccion_payment_orders` (`payment_proof_id`, `order_id`, `amount_applied`, `created`, `created_by`)
SELECT 
    o.`payment_proof_id`,
    o.`id`,
    COALESCE(o.`payment_value`, 0.00),
    COALESCE(pp.`created`, NOW()),
    COALESCE(pp.`created_by`, 1)
FROM `#__ordenproduccion_ordenes` o
INNER JOIN `#__ordenproduccion_payment_proofs` pp ON pp.`id` = o.`payment_proof_id`
WHERE o.`payment_proof_id` IS NOT NULL
  AND o.`payment_proof_id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `#__ordenproduccion_payment_orders` po 
    WHERE po.`payment_proof_id` = o.`payment_proof_id` AND po.`order_id` = o.`id`
  );

-- 3. Make payment_proofs.order_id nullable (for standalone payments, first order is in junction)
ALTER TABLE `#__ordenproduccion_payment_proofs`
MODIFY COLUMN `order_id` int(11) DEFAULT NULL COMMENT 'Primary/first order (legacy), use payment_orders for full list';

-- 4. Remove payment_proof_id and payment_value from ordenes (safe, conditional)
SET @dbname = DATABASE();
SET @tablename = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_ordenes' LIMIT 1);

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_payment_proof_id') > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP INDEX `idx_payment_proof_id`'),
    'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE table_schema = @dbname AND table_name = @tablename AND index_name = 'idx_payment_value') > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP INDEX `idx_payment_value`'),
    'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'payment_proof_id') > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN `payment_proof_id`'),
    'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'payment_value') > 0,
    CONCAT('ALTER TABLE `', @tablename, '` DROP COLUMN `payment_value`'),
    'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
