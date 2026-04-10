-- Telegram outbound queue (async processing via cron URL). Version: 3.108.0

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_queue` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `chat_id` varchar(32) NOT NULL,
    `body` mediumtext NOT NULL,
    `attempts` tinyint unsigned NOT NULL DEFAULT 0,
    `created` datetime NOT NULL,
    `last_try` datetime DEFAULT NULL,
    `last_error` varchar(1024) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
