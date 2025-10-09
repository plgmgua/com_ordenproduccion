-- Add order_id and orden_de_trabajo columns to webhook logs table
-- This will show which order was created from each webhook request
-- Run this in phpMyAdmin

-- Add the new columns
ALTER TABLE `joomla_ordenproduccion_webhook_logs`
ADD COLUMN `order_id` INT NULL DEFAULT NULL AFTER `webhook_id`,
ADD COLUMN `orden_de_trabajo` VARCHAR(50) NULL DEFAULT NULL AFTER `order_id`,
ADD INDEX `idx_order_id` (`order_id`),
ADD INDEX `idx_orden_de_trabajo` (`orden_de_trabajo`);

-- Verify the new structure
DESCRIBE `joomla_ordenproduccion_webhook_logs`;

SELECT 'Columns added successfully! Now webhook logs will show order_id and orden_de_trabajo.' as status;

