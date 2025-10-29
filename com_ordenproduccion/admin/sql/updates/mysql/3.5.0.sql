-- Migration for Timesheet Approval System
-- Version 3.5.0

-- Add manager to employee groups
ALTER TABLE `joomla_ordenproduccion_employee_groups` 
ADD COLUMN `manager_user_id` INT(11) DEFAULT NULL AFTER `color`,
ADD KEY `idx_manager_user_id` (`manager_user_id`);

-- Add approval fields to summary table
ALTER TABLE `joomla_ordenproduccion_asistencia_summary` 
ADD COLUMN `approved_hours` DECIMAL(5,2) DEFAULT NULL AFTER `total_hours`,
ADD COLUMN `approval_status` VARCHAR(20) DEFAULT 'pending' AFTER `is_early_exit`,
ADD COLUMN `approved_by` INT(11) DEFAULT NULL AFTER `approval_status`,
ADD COLUMN `approved_date` DATETIME DEFAULT NULL AFTER `approved_by`,
ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `approved_date`,
ADD KEY `idx_approval_status` (`approval_status`),
ADD KEY `idx_approved_by` (`approved_by`);

-- Update existing records to pending status
UPDATE `joomla_ordenproduccion_asistencia_summary` 
SET `approval_status` = 'pending' 
WHERE `approval_status` IS NULL OR `approval_status` = '';

