-- Cotización línea: número de orden de trabajo cuando se crea la OT desde esa línea (aprobación o asistente).
-- Version: 3.119.22

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotation_items' LIMIT 1);
SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'orden_de_trabajo') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `orden_de_trabajo` varchar(80) DEFAULT NULL COMMENT ''Número OT al crear orden desde esta línea'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
