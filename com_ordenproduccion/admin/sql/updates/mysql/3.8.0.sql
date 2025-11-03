-- Migration for Work Order History Logging Table
-- Version 3.8.0
-- Creates historial table to log all events related to work orders
-- Supports: shipping descriptions, notes, printing events, cancellations, payment proofs, etc.

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_historial` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL COMMENT 'Foreign key to #__ordenproduccion_ordenes',
    `event_type` varchar(50) NOT NULL COMMENT 'Type of event: shipping_description, note, print, cancellation, payment_proof, etc.',
    `event_title` varchar(255) DEFAULT NULL COMMENT 'Title of the event (e.g., "Descripcion de Envio", "Nota", "Impresion de PDF")',
    `event_description` text COMMENT 'Main content/description of the event',
    `metadata` text COMMENT 'JSON data for additional structured information',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL COMMENT 'User ID who created the event',
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1 COMMENT '1 = active, 0 = deleted',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_event_type` (`event_type`),
    KEY `idx_created` (`created`),
    KEY `idx_state` (`state`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='History log for all work order events';

