-- ============================================
-- Version 3.9.0 - Lunch break hours for employee groups
-- ============================================

ALTER TABLE `#__ordenproduccion_employee_groups`
    ADD COLUMN `lunch_break_hours` DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER `expected_hours`;

UPDATE `#__ordenproduccion_employee_groups`
SET `lunch_break_hours` = 1.00
WHERE `lunch_break_hours` IS NULL;
