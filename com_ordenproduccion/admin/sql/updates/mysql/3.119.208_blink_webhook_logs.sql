-- Blink gateway log.created webhook payloads (Pay Bi exchange trace).
-- Version: 3.119.208

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_blink_exchange_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `blink_log_id` varchar(64) DEFAULT NULL COMMENT 'Blink log entry id from payload.data when present',
  `request_id` varchar(64) DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `gateway_operation` varchar(64) DEFAULT NULL,
  `event_type` varchar(32) NOT NULL DEFAULT 'log.created',
  `log_data_json` mediumtext NOT NULL COMMENT 'Full payload.data object',
  `received` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blink_exlog_request_id` (`request_id`),
  KEY `idx_blink_exlog_reference_id` (`reference_id`),
  KEY `idx_blink_exlog_received` (`received`),
  KEY `idx_blink_exlog_blink_log_id` (`blink_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
