-- ============================================
-- Version 3.64.0 - Multiple payment method lines per proof
-- Table prefix: joomla_
-- Run this in phpMyAdmin BEFORE using the updated payment form
-- ============================================
-- Supports: cheque + nota crédito fiscal, etc. in one payment registration

-- 1. Create payment_proof_lines table
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_payment_proof_lines` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `payment_proof_id` int(11) NOT NULL,
    `payment_type` varchar(50) NOT NULL COMMENT 'efectivo, cheque, transferencia, deposito, nota_credito_fiscal',
    `bank` varchar(100) DEFAULT NULL,
    `document_number` varchar(255) NOT NULL DEFAULT '',
    `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
    `ordering` int(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_payment_proof_id` (`payment_proof_id`),
    KEY `idx_ordering` (`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment method lines per proof (cheque, NCF, etc.)';

-- 2. Migrate existing payment_proofs into one line each
INSERT INTO `joomla_ordenproduccion_payment_proof_lines` 
    (`payment_proof_id`, `payment_type`, `bank`, `document_number`, `amount`, `ordering`)
SELECT 
    `id`,
    COALESCE(`payment_type`, 'efectivo'),
    `bank`,
    COALESCE(`document_number`, ''),
    COALESCE(`payment_amount`, 0),
    0
FROM `joomla_ordenproduccion_payment_proofs`
WHERE NOT EXISTS (
    SELECT 1 FROM `joomla_ordenproduccion_payment_proof_lines` ppl 
    WHERE ppl.payment_proof_id = joomla_ordenproduccion_payment_proofs.id
);
