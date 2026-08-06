-- com_ordenproduccion 3.119.317-STABLE: Configurable pliego sheet processes (Barniz + duplicate) with custom labels.

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_pliego_sheet_processes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `slug` varchar(32) NOT NULL,
    `name` varchar(100) NOT NULL DEFAULT '',
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_pliego_sheet_process_slug` (`slug`),
    KEY `idx_state_ordering` (`state`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-pliego sheet process definitions (label + slug for pricing)';

INSERT INTO `#__ordenproduccion_pliego_sheet_processes` (`slug`, `name`, `ordering`, `state`)
SELECT 'barniz', 'Barniz', 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_pliego_sheet_processes` WHERE `slug` = 'barniz');

INSERT INTO `#__ordenproduccion_pliego_sheet_processes` (`slug`, `name`, `ordering`, `state`)
SELECT 'sheet_process_2', 'Proceso 2', 2, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `#__ordenproduccion_pliego_sheet_processes` WHERE `slug` = 'sheet_process_2');

SET @dbname = DATABASE();
SET @barniz_tbl = (
    SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_barniz_prices' LIMIT 1
);

SET @preparedStatement = IF(
    @barniz_tbl IS NOT NULL AND (
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @barniz_tbl AND COLUMN_NAME = 'process_slug'
    ) = 0,
    CONCAT('ALTER TABLE `', @barniz_tbl, '` ADD COLUMN `process_slug` varchar(32) NOT NULL DEFAULT ''barniz'' AFTER `id`'),
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = IF(
    @barniz_tbl IS NOT NULL AND (
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @barniz_tbl AND INDEX_NAME = 'idx_barniz_size_tiro_range'
    ) > 0,
    CONCAT('ALTER TABLE `', @barniz_tbl, '` DROP INDEX `idx_barniz_size_tiro_range`'),
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = IF(
    @barniz_tbl IS NOT NULL AND (
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @barniz_tbl AND INDEX_NAME = 'idx_sheet_process_size_tiro_range'
    ) = 0,
    CONCAT('ALTER TABLE `', @barniz_tbl, '` ADD UNIQUE KEY `idx_sheet_process_size_tiro_range` (`process_slug`, `size_id`, `tiro_retiro`, `qty_min`)'),
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @line_tbl = (
    SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion_line' LIMIT 1
);

SET @preparedStatement = IF(
    @line_tbl IS NOT NULL AND (
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @line_tbl AND COLUMN_NAME = 'sheet_process_2_tiro_retiro'
    ) = 0,
    CONCAT('ALTER TABLE `', @line_tbl, '` ADD COLUMN `sheet_process_2_tiro_retiro` varchar(20) DEFAULT NULL AFTER `barniz_tiro_retiro`'),
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
