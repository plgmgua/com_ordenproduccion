-- Cotización: optional "Cotización aprobada" / "Orden de compra" files + confirmation flag
-- Version: 3.101.21

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotations' LIMIT 1);

SET @sql = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'cotizacion_aprobada_path') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `cotizacion_aprobada_path` varchar(500) DEFAULT NULL COMMENT ''Optional approved quotation file'''),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql2 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'orden_compra_path') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `orden_compra_path` varchar(500) DEFAULT NULL COMMENT ''Optional purchase order file'''),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @sql3 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'cotizacion_confirmada') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `cotizacion_confirmada` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 after Finalizar confirmación'''),
    'SELECT 1');
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;
