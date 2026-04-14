-- Telegram mismatch ticket: anchor message registry for reply-to-comment (3.109.22)

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_telegram_mismatch_anchor` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `chat_id` varchar(32) NOT NULL COMMENT 'Telegram chat id (private)',
    `message_id` bigint NOT NULL COMMENT 'Telegram message_id of anchor DM',
    `payment_proof_id` int(11) unsigned NOT NULL,
    `joomla_user_id` int(11) NOT NULL COMMENT 'Recipient Joomla user (who received anchor)',
    `created` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_chat_message` (`chat_id`, `message_id`),
    KEY `idx_payment_proof` (`payment_proof_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
