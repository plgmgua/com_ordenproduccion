-- =====================================================
-- Weekly Schedule for Employee Groups (v3.4.0)
-- Database Prefix: #__
-- =====================================================

-- Add weekly_schedule column to store day-specific schedules as JSON
ALTER TABLE `#__ordenproduccion_employee_groups` 
ADD COLUMN `weekly_schedule` JSON DEFAULT NULL AFTER `expected_hours`;

-- Update existing groups with default weekly schedule (Monday-Friday same as current settings)
UPDATE `#__ordenproduccion_employee_groups`
SET `weekly_schedule` = JSON_OBJECT(
    'monday', JSON_OBJECT(
        'enabled', true,
        'start_time', work_start_time,
        'end_time', work_end_time,
        'expected_hours', expected_hours
    ),
    'tuesday', JSON_OBJECT(
        'enabled', true,
        'start_time', work_start_time,
        'end_time', work_end_time,
        'expected_hours', expected_hours
    ),
    'wednesday', JSON_OBJECT(
        'enabled', true,
        'start_time', work_start_time,
        'end_time', work_end_time,
        'expected_hours', expected_hours
    ),
    'thursday', JSON_OBJECT(
        'enabled', true,
        'start_time', work_start_time,
        'end_time', work_end_time,
        'expected_hours', expected_hours
    ),
    'friday', JSON_OBJECT(
        'enabled', true,
        'start_time', work_start_time,
        'end_time', work_end_time,
        'expected_hours', expected_hours
    ),
    'saturday', JSON_OBJECT(
        'enabled', false,
        'start_time', '08:00:00',
        'end_time', '13:00:00',
        'expected_hours', 5.00
    ),
    'sunday', JSON_OBJECT(
        'enabled', false,
        'start_time', '08:00:00',
        'end_time', '17:00:00',
        'expected_hours', 8.00
    )
)
WHERE `weekly_schedule` IS NULL;

