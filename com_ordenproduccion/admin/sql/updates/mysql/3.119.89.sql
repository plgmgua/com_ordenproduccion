-- com_ordenproduccion 3.119.89-STABLE: Barniz per-pliego pricing and pre-cotización line field.

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_barniz_prices` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `size_id` int(11) NOT NULL,
    `tiro_retiro` varchar(20) NOT NULL DEFAULT 'tiro',
    `qty_min` int(11) NOT NULL DEFAULT 1,
    `qty_max` int(11) NOT NULL DEFAULT 999999,
    `price_per_sheet` decimal(10,4) NOT NULL DEFAULT 0.0000,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_barniz_size_tiro_range` (`size_id`, `tiro_retiro`, `qty_min`),
    KEY `idx_size_id` (`size_id`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Barniz price per sheet by size and tiro/retiro';

SET @dbname = DATABASE();
SET @tablename = (
    SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1
);
SET @preparedStatement = IF(
    @tablename IS NOT NULL AND (
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'barniz_tiro_retiro'
    ) = 0,
    CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `barniz_tiro_retiro` varchar(20) DEFAULT NULL AFTER `lamination_tiro_retiro`'),
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
