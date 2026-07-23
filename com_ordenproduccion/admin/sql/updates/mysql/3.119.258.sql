-- Retenciones: add document type (PDF title, e.g. Constancia de Exención de IVA)
-- Version: 3.119.258

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_retenciones' LIMIT 1);

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'tipo_documento');
SET @sql = IF(
    @tbl IS NOT NULL AND (IFNULL(@col_exists, 0) = 0),
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `tipo_documento` varchar(128) NOT NULL DEFAULT '''' COMMENT ''Document title from PDF (e.g. Constancia de Exención de IVA)'' AFTER `id`'),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = 'idx_tipo_documento');
SET @col_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'tipo_documento');
SET @sql2 = IF(
    @tbl IS NOT NULL AND IFNULL(@col_exists2, 0) > 0 AND IFNULL(@idx_exists, 0) = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD KEY `idx_tipo_documento` (`tipo_documento`)'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
