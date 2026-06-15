-- ============================================
-- Version 3.119.160 - MT-940 import run log (cron / manual)
-- ============================================

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_mt940_run_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `trigger_type` varchar(32) NOT NULL DEFAULT 'cron' COMMENT 'cron|manual_mailbox|manual_file',
    `status` varchar(32) NOT NULL DEFAULT 'success' COMMENT 'success|fail|skipped',
    `emails_scanned` int(11) NOT NULL DEFAULT 0,
    `files_imported` int(11) NOT NULL DEFAULT 0,
    `files_skipped` int(11) NOT NULL DEFAULT 0,
    `transactions_imported` int(11) NOT NULL DEFAULT 0,
    `message` text,
    `details_json` mediumtext,
    `http_status` smallint NOT NULL DEFAULT 200,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `ran_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ran_at` (`ran_at`),
    KEY `idx_trigger_type` (`trigger_type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='MT-940 import execution log (cron and manual runs)';
