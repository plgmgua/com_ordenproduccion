-- Blink card payment links for cotizaciones (Pay Bi via Blink gateway).
-- Version: 3.119.129

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_blink_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quotation_id` int(11) NOT NULL,
  `reference_id` varchar(64) NOT NULL COMMENT 'Sent to Blink as referenceId',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `installments` varchar(64) NOT NULL DEFAULT 'VC00',
  `title` varchar(200) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `payment_url` varchar(2048) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending' COMMENT 'pending|created|failed',
  `blink_response_json` mediumtext DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int(11) NOT NULL DEFAULT 0,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blink_reference_id` (`reference_id`),
  KEY `idx_blink_quotation_id` (`quotation_id`),
  KEY `idx_blink_status` (`status`),
  KEY `idx_blink_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
