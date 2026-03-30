-- Mismatch ticket: workflow status + threaded comments (helpdesk-style)
-- Version: 3.101.15
-- Requires: 3.82.0 (mismatch_note / mismatch_difference on payment_proofs)

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_proofs' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mismatch_ticket_status') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mismatch_ticket_status` VARCHAR(32) NULL DEFAULT NULL COMMENT ''nuevo|esperando_respuesta|resuelto'' AFTER `mismatch_difference`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql2 = IF(
    @tbl IS NOT NULL
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mismatch_ticket_status') > 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mismatch_note') > 0,
    CONCAT('UPDATE `', @tbl, '` SET `mismatch_ticket_status` = ''nuevo'' WHERE `mismatch_ticket_status` IS NULL AND `state` = 1 AND ((`mismatch_note` IS NOT NULL AND LENGTH(TRIM(`mismatch_note`)) > 0) OR (`mismatch_difference` IS NOT NULL AND LENGTH(TRIM(`mismatch_difference`)) > 0))'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_payment_mismatch_ticket_comments` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `payment_proof_id` int(11) unsigned NOT NULL,
    `body` text NOT NULL,
    `created` datetime NOT NULL,
    `created_by` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_payment_proof_id` (`payment_proof_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
