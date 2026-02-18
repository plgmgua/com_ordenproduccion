-- ============================================
-- Add half-day option columns to company_holidays
-- Run this in phpMyAdmin if "Medio día" is not saving.
-- Table prefix: joomla_ (adjust if your prefix is different)
-- ============================================
-- If columns already exist, you may see "Duplicate column name" - that's OK.

ALTER TABLE `joomla_ordenproduccion_company_holidays`
ADD COLUMN `is_half_day` tinyint(1) NOT NULL DEFAULT 0 AFTER `name`,
ADD COLUMN `start_time` time DEFAULT NULL AFTER `is_half_day`,
ADD COLUMN `end_time` time DEFAULT NULL AFTER `start_time`;
