-- ============================================
-- Version 3.62.0 - Company holidays and justified absences
-- ============================================
-- Company holidays: apply to everyone, reduce expected work days
-- Employee justified absences: per-employee excused days off (vacation, medical, etc.)

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_company_holidays` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `holiday_date` date NOT NULL,
    `name` varchar(255) NOT NULL DEFAULT '',
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_holiday_date` (`holiday_date`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_employee_justified_absence` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `personname` varchar(255) NOT NULL,
    `absence_date` date NOT NULL,
    `reason` varchar(255) DEFAULT NULL,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_person_date` (`personname`(100), `absence_date`),
    KEY `idx_personname` (`personname`(100)),
    KEY `idx_absence_date` (`absence_date`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
