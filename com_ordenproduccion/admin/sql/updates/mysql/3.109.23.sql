-- Telegram queue: optional metadata for mismatch anchor rows (register message_id after send). Version: 3.109.23

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_telegram_queue' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mismatch_anchor_payment_proof_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mismatch_anchor_payment_proof_id` INT UNSIGNED NULL DEFAULT NULL COMMENT ''When set, cron registers Telegram message_id in mismatch anchor table after send'' AFTER `last_error`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql2 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'mismatch_anchor_joomla_user_id') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `mismatch_anchor_joomla_user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT ''Joomla user who should receive anchor (order owner)'' AFTER `mismatch_anchor_payment_proof_id`'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
