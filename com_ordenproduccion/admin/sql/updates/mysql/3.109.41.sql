-- Telegram inbound webhook request log (3.109.41): diagnostics for Bot API POSTs to task=webhook

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_webhook_log` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `created` datetime NOT NULL,
    `ip` varchar(64) NOT NULL DEFAULT '',
    `user_agent` varchar(512) NOT NULL DEFAULT '',
    `http_method` varchar(8) NOT NULL DEFAULT '',
    `body_length` int unsigned NOT NULL DEFAULT 0,
    `secret_header_present` tinyint unsigned NOT NULL DEFAULT 0,
    `secret_valid` tinyint unsigned NOT NULL DEFAULT 0,
    `http_status` smallint NOT NULL DEFAULT 0,
    `outcome` varchar(80) NOT NULL DEFAULT '',
    `update_id` bigint DEFAULT NULL,
    `chat_id` varchar(32) DEFAULT NULL,
    `text_preview` varchar(512) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created`),
    KEY `idx_outcome` (`outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
