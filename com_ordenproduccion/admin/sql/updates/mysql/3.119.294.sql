-- com_ordenproduccion 3.119.294-STABLE: payment_types default bank columns (MySQL 5.7 / MariaDB safe)

SET @dbname = DATABASE();

SET @tbl = (
    SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_payment_types'
    LIMIT 1
);

SET @sql = IF(
    @tbl IS NOT NULL AND (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'default_bank'
    ) = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `default_bank` varchar(100) DEFAULT NULL COMMENT ''Default origin bank code for payment lines'''),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    @tbl IS NOT NULL AND (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'default_bank_account_id'
    ) = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `default_bank_account_id` int(11) DEFAULT NULL COMMENT ''Default destination bank account id for payment lines'''),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
