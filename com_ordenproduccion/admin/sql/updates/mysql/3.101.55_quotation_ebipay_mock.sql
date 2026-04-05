-- Quotations: ebi pay mock link engine (debug JSON + internal code)
-- Version: 3.101.55

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_quotations' LIMIT 1);

SET @sql1 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'ebipay_codigo_interno') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `ebipay_codigo_interno` varchar(100) NULL DEFAULT NULL COMMENT ''ebi pay link SKU (mock)'''),
    'SELECT 1');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql2 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'ebipay_mock_json') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `ebipay_mock_json` mediumtext NULL DEFAULT NULL COMMENT ''Last mock login/network/link debug JSON'''),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @sql3 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'ebipay_mock_at') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `ebipay_mock_at` datetime NULL DEFAULT NULL COMMENT ''When mock link was generated'''),
    'SELECT 1');
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;
