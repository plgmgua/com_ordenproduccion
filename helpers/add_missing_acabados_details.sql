-- Add missing acabados details columns to ordenes table
-- These fields are shown in the PDF but missing from the database

-- Run this in phpMyAdmin to add the missing columns

ALTER TABLE `joomla_ordenproduccion_ordenes`
ADD COLUMN `spine_details` TEXT NULL DEFAULT NULL AFTER `spine`,
ADD COLUMN `gluing_details` TEXT NULL DEFAULT NULL AFTER `gluing`,
ADD COLUMN `sizing_details` TEXT NULL DEFAULT NULL AFTER `sizing`,
ADD COLUMN `stapling_details` TEXT NULL DEFAULT NULL AFTER `stapling`,
ADD COLUMN `white_print_details` TEXT NULL DEFAULT NULL AFTER `white_print`,
ADD COLUMN `eyelets_details` TEXT NULL DEFAULT NULL AFTER `eyelets`;

-- Verify the new columns
DESCRIBE `joomla_ordenproduccion_ordenes`;

SELECT 'Missing acabados details columns added successfully!' as status;

