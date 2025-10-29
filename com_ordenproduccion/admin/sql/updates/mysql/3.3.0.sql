-- ============================================
-- Employee Management System - Version 3.3.0
-- Create employee groups and update employees table
-- ============================================

-- Create employee groups table
CREATE TABLE IF NOT EXISTS `#__ordenproduccion_employee_groups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text,
    `work_start_time` time NOT NULL DEFAULT '08:00:00',
    `work_end_time` time NOT NULL DEFAULT '17:00:00',
    `expected_hours` decimal(5,2) NOT NULL DEFAULT 8.00,
    `grace_period_minutes` int(11) NOT NULL DEFAULT 15,
    `color` varchar(7) DEFAULT '#3490dc',
    `ordering` int(11) NOT NULL DEFAULT 0,
    `state` tinyint(3) NOT NULL DEFAULT 1,
    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by` int(11) NOT NULL DEFAULT 0,
    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_state` (`state`),
    KEY `idx_ordering` (`ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default employee group
INSERT INTO `#__ordenproduccion_employee_groups` 
    (`name`, `description`, `work_start_time`, `work_end_time`, `expected_hours`, `grace_period_minutes`, `color`, `ordering`, `state`, `created_by`)
VALUES
    ('Horario Estándar', 'Horario de oficina estándar (8:00 AM - 5:00 PM)', '08:00:00', '17:00:00', 8.00, 15, '#3490dc', 1, 1, 0),
    ('Turno Matutino', 'Turno de la mañana (6:00 AM - 2:00 PM)', '06:00:00', '14:00:00', 8.00, 15, '#38c172', 2, 1, 0),
    ('Turno Vespertino', 'Turno de la tarde (2:00 PM - 10:00 PM)', '14:00:00', '22:00:00', 8.00, 15, '#f6993f', 3, 1, 0),
    ('Turno Nocturno', 'Turno de la noche (10:00 PM - 6:00 AM)', '22:00:00', '06:00:00', 8.00, 15, '#6574cd', 4, 1, 0);

-- Update existing employees table to add group relationship
ALTER TABLE `joomla_ordenproduccion_employees`
    ADD COLUMN `group_id` int(11) DEFAULT NULL AFTER `personname`,
    ADD COLUMN `employee_number` varchar(50) DEFAULT NULL AFTER `cardno`,
    ADD COLUMN `hire_date` date DEFAULT NULL AFTER `position`,
    ADD COLUMN `notes` text AFTER `expected_daily_hours`,
    ADD KEY `idx_group_id` (`group_id`);

-- Assign all existing employees to default group (id=1)
UPDATE `joomla_ordenproduccion_employees` 
SET `group_id` = 1 
WHERE `group_id` IS NULL;

-- Update component version
UPDATE `#__extensions` 
SET `manifest_cache` = JSON_SET(
    `manifest_cache`,
    '$.version',
    '3.3.0-STABLE'
)
WHERE `element` = 'com_ordenproduccion' 
AND `type` = 'component';

