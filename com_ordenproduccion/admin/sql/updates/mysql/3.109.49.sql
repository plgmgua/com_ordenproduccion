-- Link payment proof file attachments to a specific payment line (nullable = legacy proof-level files). Version: 3.109.49

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proof_files' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'payment_proof_line_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `payment_proof_line_id` INT(11) NULL DEFAULT NULL COMMENT ''FK to payment_proof_lines.id'' AFTER `payment_proof_id`, ADD KEY `idx_payment_proof_line_id` (`payment_proof_line_id`)'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
