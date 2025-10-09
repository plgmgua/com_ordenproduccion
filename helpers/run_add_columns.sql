-- Add the missing columns to joomla_ordenproduccion_ordenes
-- Run this in phpMyAdmin NOW

ALTER TABLE `joomla_ordenproduccion_ordenes`
ADD COLUMN IF NOT EXISTS `tiro_retiro` VARCHAR(50) NULL DEFAULT NULL AFTER `perforation_details`,
ADD COLUMN IF NOT EXISTS `instrucciones_entrega` TEXT NULL DEFAULT NULL AFTER `shipping_phone`;

-- Verify columns were added
DESCRIBE `joomla_ordenproduccion_ordenes`;

SELECT 'Columns added successfully!' as status;

