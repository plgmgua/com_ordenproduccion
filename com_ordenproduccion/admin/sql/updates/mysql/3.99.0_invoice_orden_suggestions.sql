-- Invoice ↔ Orden de trabajo suggested/approved links (Facturas > Conciliar)
-- Version: 3.99.0

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_invoice_orden_suggestions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `invoice_id` int(11) NOT NULL,
    `orden_id` int(11) NOT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    `score` decimal(5,2) NOT NULL DEFAULT 0.00,
    `reasons` text DEFAULT NULL COMMENT 'JSON array of match reason codes',
    `created` datetime NOT NULL,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_invoice_orden` (`invoice_id`,`orden_id`),
    KEY `idx_invoice_status` (`invoice_id`,`status`),
    KEY `idx_status` (`status`),
    KEY `idx_orden_id` (`orden_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
