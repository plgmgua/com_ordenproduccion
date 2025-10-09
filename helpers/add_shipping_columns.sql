-- Add shipping and tiro_retiro columns to main orders table
-- This ELIMINATES the need for complex EAV queries
-- Run this in phpMyAdmin

-- Add tiro_retiro column
ALTER TABLE `joomla_ordenproduccion_ordenes`
ADD COLUMN `tiro_retiro` VARCHAR(50) NULL DEFAULT NULL AFTER `perforation_details`,
ADD COLUMN `instrucciones_entrega` TEXT NULL DEFAULT NULL AFTER `shipping_address`;

-- Add index for faster queries
ALTER TABLE `joomla_ordenproduccion_ordenes`
ADD INDEX `idx_tiro_retiro` (`tiro_retiro`);

-- Show the updated structure
DESCRIBE `joomla_ordenproduccion_ordenes`;

-- Count existing records
SELECT COUNT(*) as total_ordenes FROM `joomla_ordenproduccion_ordenes`;

SELECT 'Columns added successfully!' as status;

