-- --------------------------------------------------------
-- Database update script for Asistencia (Attendance) feature
-- Version: 3.2.0
-- Date: 2025-10-28
-- --------------------------------------------------------

-- Enhanced attendance table structure with biometric integration
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_asistencia` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cardno` varchar(50) NOT NULL,
    `personname` varchar(255) NOT NULL,
    `authdate` date NOT NULL,
    `authtime` time NOT NULL,
    `authdatetime` datetime NOT NULL,
    `direction` varchar(50) DEFAULT 'Puerta',
    `devicename` varchar(255) DEFAULT NULL,
    `deviceserialno` varchar(255) DEFAULT NULL,
    `entry_type` enum('biometric','manual') DEFAULT 'biometric',
    `notes` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cardno` (`cardno`),
    KEY `idx_personname` (`personname`),
    KEY `idx_authdate` (`authdate`),
    KEY `idx_authdatetime` (`authdatetime`),
    KEY `idx_entry_type` (`entry_type`),
    KEY `idx_state` (`state`),
    KEY `idx_composite` (`cardno`, `authdate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily attendance summary table for quick reporting
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_asistencia_summary` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cardno` varchar(50) NOT NULL,
    `personname` varchar(255) NOT NULL,
    `work_date` date NOT NULL,
    `first_entry` time DEFAULT NULL,
    `last_exit` time DEFAULT NULL,
    `total_hours` decimal(5,2) DEFAULT NULL,
    `expected_hours` decimal(5,2) DEFAULT 8.00,
    `hours_difference` decimal(5,2) DEFAULT NULL,
    `total_entries` int(11) DEFAULT 0,
    `is_complete` tinyint(1) DEFAULT 0,
    `is_late` tinyint(1) DEFAULT 0,
    `is_early_exit` tinyint(1) DEFAULT 0,
    `notes` text,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_person_date` (`cardno`, `work_date`),
    KEY `idx_work_date` (`work_date`),
    KEY `idx_personname` (`personname`),
    KEY `idx_is_complete` (`is_complete`),
    KEY `idx_is_late` (`is_late`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Employee registry for attendance tracking
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_employees` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `cardno` varchar(50) NOT NULL,
    `personname` varchar(255) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `department` varchar(100) DEFAULT NULL,
    `position` varchar(100) DEFAULT NULL,
    `work_schedule_start` time DEFAULT '08:00:00',
    `work_schedule_end` time DEFAULT '17:00:00',
    `expected_daily_hours` decimal(5,2) DEFAULT 8.00,
    `active` tinyint(1) DEFAULT 1,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_cardno` (`cardno`),
    KEY `idx_personname` (`personname`),
    KEY `idx_active` (`active`),
    KEY `idx_department` (`department`),
    KEY `idx_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing attendance data if it exists
INSERT INTO `#__ordenproduccion_asistencia` 
    (`cardno`, `personname`, `authdate`, `authtime`, `authdatetime`, `direction`, 
     `devicename`, `deviceserialno`, `entry_type`, `created_by`)
SELECT 
    COALESCE(card_no, 'UNKNOWN'),
    person_name,
    auth_date,
    COALESCE(auth_time, '00:00:00'),
    COALESCE(auth_datetime, CONCAT(auth_date, ' ', COALESCE(auth_time, '00:00:00'))),
    COALESCE(direction, 'Puerta'),
    device_name,
    device_serial_no,
    'biometric',
    0
FROM `#__ordenproduccion_attendance`
WHERE NOT EXISTS (
    SELECT 1 FROM `#__ordenproduccion_asistencia` 
    WHERE cardno = COALESCE(card_no, 'UNKNOWN') 
    AND authdatetime = COALESCE(auth_datetime, CONCAT(auth_date, ' ', COALESCE(auth_time, '00:00:00')))
);

-- Insert default employees based on existing attendance data
INSERT INTO `#__ordenproduccion_employees` 
    (`cardno`, `personname`, `active`, `created_by`)
SELECT DISTINCT
    COALESCE(card_no, LPAD(ROW_NUMBER() OVER (ORDER BY person_name), 8, '0')),
    person_name,
    1,
    0
FROM `#__ordenproduccion_attendance`
WHERE person_name IS NOT NULL AND person_name != ''
ON DUPLICATE KEY UPDATE personname = VALUES(personname);

-- Update component configuration
INSERT INTO `#__ordenproduccion_config` (`setting_key`, `setting_value`, `description`, `created_by`) VALUES
('asistencia_enabled', '1', 'Enable attendance tracking', 0),
('asistencia_expected_hours', '8.00', 'Expected daily work hours', 0),
('asistencia_grace_period', '15', 'Late grace period in minutes', 0),
('asistencia_work_start', '08:00:00', 'Default work start time', 0),
('asistencia_work_end', '17:00:00', 'Default work end time', 0),
('asistencia_auto_calculate', '1', 'Auto-calculate daily summaries', 0)
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

