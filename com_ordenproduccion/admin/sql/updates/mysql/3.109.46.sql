-- Mismatch ticket comments: origin for UI (web vs Telegram). Version: 3.109.46

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_mismatch_ticket_comments' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'source') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `source` VARCHAR(16) NOT NULL DEFAULT ''site'' COMMENT ''site|telegram'' AFTER `created_by`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
