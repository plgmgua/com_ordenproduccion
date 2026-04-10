-- Telegram bot: per-user chat_id for order-owner notifications
-- Version: 3.105.0

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_users` (
    `user_id` int(11) NOT NULL,
    `chat_id` varchar(32) NOT NULL COMMENT 'Telegram chat id for sendMessage',
    `created` datetime NOT NULL,
    `modified` datetime DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    KEY `idx_chat_id` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
