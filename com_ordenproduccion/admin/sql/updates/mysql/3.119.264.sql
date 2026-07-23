-- Retenciones: SAT Excel validation fields (RetIVA.xlsx / RetISR.xlsx)
-- Version: 3.119.264

SET @dbname = DATABASE();
SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_retenciones' LIMIT 1);

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'sat_validated');
SET @sql = IF(
    @tbl IS NOT NULL AND (IFNULL(@col_exists, 0) = 0),
    CONCAT('ALTER TABLE `', @tbl, '` ',
      'ADD COLUMN `sat_validated` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = matched against SAT Excel report'' AFTER `source_filename`, ',
      'ADD COLUMN `sat_validated_at` datetime DEFAULT NULL AFTER `sat_validated`, ',
      'ADD COLUMN `sat_validated_by` int(11) DEFAULT NULL AFTER `sat_validated_at`, ',
      'ADD COLUMN `sat_estado_constancia` varchar(32) DEFAULT NULL COMMENT ''ESTADO CONSTANCIA from SAT Excel'' AFTER `sat_validated_by`, ',
      'ADD COLUMN `sat_excel_total` decimal(14,2) DEFAULT NULL COMMENT ''TOTAL RETENCIÓN from SAT Excel'' AFTER `sat_estado_constancia`, ',
      'ADD COLUMN `sat_validation_status` varchar(32) DEFAULT NULL COMMENT ''ok|amount_mismatch'' AFTER `sat_excel_total`, ',
      'ADD KEY `idx_sat_validated` (`sat_validated`)'
    ),
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
