-- Invoice: FEL mock issuance queue, debug JSON, local artifacts, unique quotation
-- Version: 3.101.50

SET @dbname = DATABASE();

SET @tbl = (SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME LIKE '%ordenproduccion_invoices' LIMIT 1);

-- fel_issue_status: none = not using engine; pending/processing/completed/failed for cotización flow
SET @sql1 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'fel_issue_status') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `fel_issue_status` varchar(20) NOT NULL DEFAULT ''none'' COMMENT ''none|pending|processing|completed|failed'' AFTER `fel_extra`'),
    'SELECT 1');
PREPARE stmt1 FROM @sql1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

SET @sql2 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'fel_issue_error') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `fel_issue_error` text NULL DEFAULT NULL AFTER `fel_issue_status`'),
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @sql3 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'fel_request_json') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `fel_request_json` longtext NULL DEFAULT NULL COMMENT ''Last FELplex-style request (debug)'' AFTER `fel_issue_error`'),
    'SELECT 1');
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

SET @sql4 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'fel_response_json') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `fel_response_json` longtext NULL DEFAULT NULL COMMENT ''Last FELplex-style response (debug)'' AFTER `fel_request_json`'),
    'SELECT 1');
PREPARE stmt4 FROM @sql4;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;

SET @sql5 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'fel_local_pdf_path') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `fel_local_pdf_path` varchar(512) NULL DEFAULT NULL AFTER `fel_response_json`'),
    'SELECT 1');
PREPARE stmt5 FROM @sql5;
EXECUTE stmt5;
DEALLOCATE PREPARE stmt5;

SET @sql6 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'fel_local_xml_path') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `fel_local_xml_path` varchar(512) NULL DEFAULT NULL AFTER `fel_local_pdf_path`'),
    'SELECT 1');
PREPARE stmt6 FROM @sql6;
EXECUTE stmt6;
DEALLOCATE PREPARE stmt6;

SET @sql7 = IF(
    @tbl IS NOT NULL AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'felplex_uuid') = 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `felplex_uuid` varchar(64) NULL DEFAULT NULL COMMENT ''FELplex document UUID (mock or live)'' AFTER `fel_local_xml_path`'),
    'SELECT 1');
PREPARE stmt7 FROM @sql7;
EXECUTE stmt7;
DEALLOCATE PREPARE stmt7;

-- One invoice per cotización (MySQL allows multiple NULLs on UNIQUE)
SET @sql8 = IF(
    @tbl IS NOT NULL
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND INDEX_NAME = 'uq_ordenproduccion_invoices_quotation_id') = 0
    AND (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = 'quotation_id') > 0,
    CONCAT('ALTER TABLE `', @tbl, '` ADD UNIQUE KEY `uq_ordenproduccion_invoices_quotation_id` (`quotation_id`)'),
    'SELECT 1');
PREPARE stmt8 FROM @sql8;
EXECUTE stmt8;
DEALLOCATE PREPARE stmt8;
