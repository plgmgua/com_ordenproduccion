-- ============================================
-- Version 3.55.0 - Client merge audit table
-- ============================================
-- Audit log for client merge operations (super user only)

CREATE TABLE IF NOT EXISTS `#__ordenproduccion_client_merges` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `source_client_name` varchar(255) NOT NULL,
    `source_nit` varchar(100) DEFAULT NULL,
    `target_client_name` varchar(255) NOT NULL,
    `target_nit` varchar(100) DEFAULT NULL,
    `orders_updated` int(11) NOT NULL DEFAULT 0,
    `merged_by` int(11) NOT NULL,
    `merged_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_merged_at` (`merged_at`),
    KEY `idx_merged_by` (`merged_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for client merge operations';
