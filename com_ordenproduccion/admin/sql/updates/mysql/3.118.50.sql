--
-- Certificador Digifact: full HTTP audit log (test + prod).
--
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_certificador_digifact_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created` datetime NOT NULL,
  `created_by` int NOT NULL DEFAULT 0,
  `environment` varchar(8) NOT NULL DEFAULT 'test',
  `operation` varchar(48) NOT NULL DEFAULT '',
  `request_method` varchar(16) NOT NULL DEFAULT 'GET',
  `request_url` mediumtext NOT NULL,
  `request_headers_json` mediumtext,
  `request_body` longtext,
  `response_http_code` int NOT NULL DEFAULT 0,
  `response_body` longtext,
  `client_error` text,
  `duration_ms` int NOT NULL DEFAULT 0,
  `invoice_id` int unsigned DEFAULT NULL,
  `quotation_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created`),
  KEY `idx_environment` (`environment`),
  KEY `idx_operation` (`operation`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_quotation` (`quotation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
