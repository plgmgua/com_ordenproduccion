-- com_ordenproduccion 3.113.39-STABLE: Outbound email log for Control de ventas (vendor quote + payment proof mismatch notices).

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_outbound_email_log` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `context_type` varchar(64) NOT NULL COMMENT 'vendor_quote_request|paymentproof_mismatch|…',
    `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '1=sent OK, 0=failed',
    `user_id` int(11) NOT NULL DEFAULT 0 COMMENT 'Actor who triggered the send',
    `to_email` varchar(512) NOT NULL DEFAULT '' COMMENT 'Recipient(s); comma-separated if several',
    `subject` varchar(512) NOT NULL DEFAULT '',
    `error_message` text COMMENT 'Transport / exception detail when status=0',
    `meta` mediumtext COMMENT 'JSON: precot_id, proveedor_id, payment_proof_id, recipients[], etc.',
    `created` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_created` (`user_id`, `created`),
    KEY `idx_created` (`created`),
    KEY `idx_context` (`context_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Outbound email audit log (com_ordenproduccion)';
