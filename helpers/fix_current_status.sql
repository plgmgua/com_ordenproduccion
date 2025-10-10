-- =====================================================
-- Fix Current Status Values
--
-- Based on current database state:
-- - New → Nueva
-- - terminada → Terminada
--
-- Run this in phpMyAdmin to fix the 2 non-standard status values
-- =====================================================

-- Fix 'New' to 'Nueva'
UPDATE `joomla_ordenproduccion_ordenes` 
SET `status` = 'Nueva' 
WHERE `status` = 'New';

-- Fix 'terminada' to 'Terminada'
UPDATE `joomla_ordenproduccion_ordenes` 
SET `status` = 'Terminada' 
WHERE `status` = 'terminada';

-- Verify the results
SELECT 
    `status`,
    COUNT(*) as `count`
FROM `joomla_ordenproduccion_ordenes`
GROUP BY `status`
ORDER BY `count` DESC;

