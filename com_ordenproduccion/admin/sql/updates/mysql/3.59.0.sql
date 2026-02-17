-- ============================================
-- Version 3.59.0 - Asistencia analysis and config
-- ============================================
-- Stores asistencia calculation config: work days of week, on-time threshold.
-- work_days: comma-separated 0-6 (Sun-Sat), e.g. '1,2,3,4,5' = Mon-Fri
-- on_time_threshold: minimum % on-time to pass (default 90)

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_asistencia_config` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `param_key` varchar(50) NOT NULL,
    `param_value` text NOT NULL,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_param_key` (`param_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default: Mon-Fri work days, 90% on-time threshold
INSERT INTO `joomla_ordenproduccion_asistencia_config` (`param_key`, `param_value`)
VALUES
    ('work_days', '1,2,3,4,5'),
    ('on_time_threshold', '90')
ON DUPLICATE KEY UPDATE param_value = VALUES(param_value);
