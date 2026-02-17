-- ============================================
-- Version 3.63.0 - Half-day work option for holidays
-- ============================================
-- is_half_day=1: Reduced hours (e.g. Easter Wednesday 7am-12pm), counts as work day
-- is_half_day=0: Full day off, excluded from expected work days
-- start_time/end_time: Used when is_half_day=1 for on-time/early-exit calculations

ALTER TABLE `joomla_ordenproduccion_company_holidays`
ADD COLUMN `is_half_day` tinyint(1) NOT NULL DEFAULT 0 AFTER `name`,
ADD COLUMN `start_time` time DEFAULT NULL AFTER `is_half_day`,
ADD COLUMN `end_time` time DEFAULT NULL AFTER `start_time`;
