-- Add invoice_number column to ordenes table
-- This allows manual assignment of invoice numbers from the work orders tab

ALTER TABLE `joomla_ordenproduccion_ordenes` 
ADD COLUMN `invoice_number` VARCHAR(50) DEFAULT NULL COMMENT 'Manually assigned invoice number' 
AFTER `order_number`;

-- Add index for better performance
ALTER TABLE `joomla_ordenproduccion_ordenes` 
ADD INDEX `idx_invoice_number` (`invoice_number`);

-- Add comment to table
ALTER TABLE `joomla_ordenproduccion_ordenes` 
COMMENT = 'Work orders table with manual invoice number assignment capability';
