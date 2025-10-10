-- =====================================================
-- Fix Status Values to Match Filter
--
-- This script updates old status values to the new
-- standardized format that matches the filter dropdown.
--
-- OLD VALUES (inconsistent):
-- - nueva, en_proceso, terminada, cerrada (lowercase with underscores)
-- - New, Terminada (mixed)
--
-- NEW VALUES (standardized):
-- - Nueva, En Proceso, Terminada, Entregada, Cerrada
--
-- @package     Joomla.Administrator
-- @subpackage  com_ordenproduccion
-- @copyright   (C) 2025 Grimpsa. All rights reserved.
-- @license     GNU General Public License version 2 or later; see LICENSE.txt
-- =====================================================

-- Update lowercase 'nueva' to 'Nueva'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Nueva'
WHERE `status` = 'nueva';

-- Update 'en_proceso' to 'En Proceso'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'En Proceso'
WHERE `status` = 'en_proceso';

-- Update lowercase 'terminada' to 'Terminada'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Terminada'
WHERE `status` = 'terminada';

-- Update 'entregada' to 'Entregada'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Entregada'
WHERE `status` = 'entregada';

-- Update lowercase 'cerrada' to 'Cerrada'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Cerrada'
WHERE `status` = 'cerrada';

-- Update 'New' to 'Nueva'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Nueva'
WHERE `status` = 'New';

-- Update 'In Process' or 'In Progress' to 'En Proceso'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'En Proceso'
WHERE `status` IN ('In Process', 'In Progress', 'en proceso');

-- Update 'Delivered' to 'Entregada'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Entregada'
WHERE `status` = 'Delivered';

-- Update 'Completed' to 'Terminada'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Terminada'
WHERE `status` = 'Completed';

-- Update 'Closed' to 'Cerrada'
UPDATE `#__ordenproduccion_ordenes`
SET `status` = 'Cerrada'
WHERE `status` = 'Closed';

-- Display summary of current status values
SELECT 
    `status`,
    COUNT(*) as `count`
FROM `#__ordenproduccion_ordenes`
GROUP BY `status`
ORDER BY `count` DESC;

-- Show total records updated
SELECT 
    'Status values have been standardized' as `Message`,
    COUNT(*) as `Total_Records`
FROM `#__ordenproduccion_ordenes`;

