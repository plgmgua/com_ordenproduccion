-- =====================================================
-- Add shipping_type column to ordenes table
-- Version: 2.3.8
-- Date: 2025-10-09
-- =====================================================

-- Add shipping_type column
ALTER TABLE `joomla_ordenproduccion_ordenes`
ADD COLUMN `shipping_type` VARCHAR(50) DEFAULT 'Entrega a domicilio' AFTER `shipping_address`;

-- Add index for better performance
ALTER TABLE `joomla_ordenproduccion_ordenes`
ADD INDEX `idx_shipping_type` (`shipping_type`);

-- Verify the change
SELECT 
    'Column added successfully' AS status,
    COUNT(*) AS total_records,
    SUM(CASE WHEN shipping_type = 'Entrega a domicilio' THEN 1 ELSE 0 END) AS entrega_domicilio,
    SUM(CASE WHEN shipping_type = 'Recoge en oficina' THEN 1 ELSE 0 END) AS recoge_oficina,
    SUM(CASE WHEN shipping_type IS NULL THEN 1 ELSE 0 END) AS null_values
FROM `joomla_ordenproduccion_ordenes`;

