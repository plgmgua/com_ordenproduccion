-- Telegram: log successfully sent queue messages for admin reporting. Version: 3.109.0

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_sent_log` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `chat_id` varchar(32) NOT NULL,
    `body` mediumtext NOT NULL,
    `queued_created` datetime NOT NULL,
    `sent_at` datetime NOT NULL,
    `source_queue_id` int unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_sent_at` (`sent_at`),
    KEY `idx_chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
