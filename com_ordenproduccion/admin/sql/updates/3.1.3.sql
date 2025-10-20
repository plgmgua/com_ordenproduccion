-- Create payment proofs table for com_ordenproduccion
-- Version: 3.1.3

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_payment_proofs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `order_id` int(11) NOT NULL,
    `payment_type` varchar(50) NOT NULL COMMENT 'efectivo, cheque, transferencia, deposito',
    `bank` varchar(100) DEFAULT NULL,
    `document_number` varchar(255) NOT NULL,
    `file_path` varchar(500) DEFAULT NULL,
    `created_by` int(11) NOT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `version` varchar(20) DEFAULT '3.1.3',
    PRIMARY KEY (`id`),
    KEY `idx_order_id` (`order_id`),
    KEY `idx_payment_type` (`payment_type`),
    KEY `idx_created_by` (`created_by`),
    KEY `idx_state` (`state`),
    KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment proof records for work orders';
