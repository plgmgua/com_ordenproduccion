-- Payment proof files table: supports multiple attachments per payment proof
CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_payment_proof_files` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `payment_proof_id` int(11) NOT NULL COMMENT 'FK to payment_proofs.id',
    `file_path` varchar(500) NOT NULL COMMENT 'Relative path from JPATH_ROOT',
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_payment_proof_id` (`payment_proof_id`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
