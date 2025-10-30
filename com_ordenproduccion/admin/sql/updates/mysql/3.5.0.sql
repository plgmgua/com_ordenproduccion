-- Migration for Timesheet Approval System
-- Version 3.5.0

-- Use Joomla table prefix and guard against duplicates
ALTER TABLE `#__ordenproduccion_employee_groups`
ADD COLUMN IF NOT EXISTS `manager_user_id` INT(11) DEFAULT NULL AFTER `color`;

-- Create index if not exists (MySQL 8.0+); otherwise ignore error if already exists
ALTER TABLE `#__ordenproduccion_employee_groups`
ADD KEY `idx_manager_user_id` (`manager_user_id`);

ALTER TABLE `#__ordenproduccion_asistencia_summary`
ADD COLUMN IF NOT EXISTS `approved_hours` DECIMAL(5,2) DEFAULT NULL AFTER `total_hours`,
ADD COLUMN IF NOT EXISTS `approval_status` VARCHAR(20) DEFAULT 'pending' AFTER `is_early_exit`,
ADD COLUMN IF NOT EXISTS `approved_by` INT(11) DEFAULT NULL AFTER `approval_status`,
ADD COLUMN IF NOT EXISTS `approved_date` DATETIME DEFAULT NULL AFTER `approved_by`;

-- Create indexes for approval fields (ignore if they already exist)
ALTER TABLE `#__ordenproduccion_asistencia_summary`
ADD KEY `idx_approval_status` (`approval_status`),
ADD KEY `idx_approved_by` (`approved_by`);

UPDATE `#__ordenproduccion_asistencia_summary` 
SET `approval_status` = 'pending' 
WHERE `approval_status` IS NULL OR `approval_status` = '';

