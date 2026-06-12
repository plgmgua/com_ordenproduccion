-- ============================================
-- Version 3.119.150 - MT-940 dedup keys for import log and transactions
-- ============================================

ALTER TABLE `#__ordenproduccion_mt940_import_log`
    ADD COLUMN `content_hash` varchar(64) DEFAULT NULL COMMENT 'SHA-256 of file body' AFTER `filename`;

ALTER TABLE `#__ordenproduccion_mt940_transactions`
    ADD COLUMN `tx_fingerprint` varchar(64) DEFAULT NULL COMMENT 'SHA-256 dedup key' AFTER `raw_line`;

ALTER TABLE `#__ordenproduccion_mt940_import_log`
    ADD UNIQUE KEY `uk_mt940_import_filename` (`filename`);

ALTER TABLE `#__ordenproduccion_mt940_import_log`
    ADD UNIQUE KEY `uk_mt940_import_content_hash` (`content_hash`);

ALTER TABLE `#__ordenproduccion_mt940_transactions`
    ADD UNIQUE KEY `uk_mt940_tx_fingerprint` (`tx_fingerprint`);
