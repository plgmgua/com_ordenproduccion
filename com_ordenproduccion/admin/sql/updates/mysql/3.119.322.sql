-- com_ordenproduccion 3.119.322-STABLE: Track PRE copies from oferta templates (qty scaling on cotización).

SET @dbname = DATABASE();
SET @tbl = (
    SELECT TABLE_NAME FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_pre_cotizacion' LIMIT 1
);

SET @preparedStatement = IF(
    @tbl IS NOT NULL AND (
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'oferta_template_id'
    ) = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `oferta_template_id` INT(11) DEFAULT NULL COMMENT ''Source oferta PRE id when created from template'' AFTER `oferta_expires`, ADD KEY `idx_oferta_template_id` (`oferta_template_id`)'),
    'SELECT 1'
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
