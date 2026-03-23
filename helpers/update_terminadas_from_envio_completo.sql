-- =====================================================
-- Update Orders to "Terminadas" Status
-- 
-- This script updates all work orders (ordenes de trabajo)
-- that have an "envio completo impreso" event in their
-- historial to status "Terminada".
--
-- Criteria:
-- - historial.event_type = 'shipping_print'
-- - historial.event_title = 'Impresion de Envio'
-- - historial.event_description LIKE 'Envio completo impreso via%'
-- - historial.state = 1 (active)
--
-- @package     Joomla.Administrator
-- @subpackage  com_ordenproduccion
-- @copyright   (C) 2025 Grimpsa. All rights reserved.
-- @license     GNU General Public License version 2 or later; see LICENSE.txt
-- =====================================================

-- First, let's see how many orders will be affected (preview query)
SELECT 
    o.id,
    o.orden_de_trabajo,
    o.status AS current_status,
    h.event_description,
    h.created AS print_date
FROM `joomla_ordenproduccion_ordenes` o
INNER JOIN `joomla_ordenproduccion_historial` h ON h.order_id = o.id
WHERE h.event_type = 'shipping_print'
  AND h.event_title = 'Impresion de Envio'
  AND h.event_description LIKE 'Envio completo impreso via%'
  AND h.state = 1
  AND o.status != 'Terminada'
ORDER BY h.created DESC;

-- Now update the orders to "Terminada" status
UPDATE `joomla_ordenproduccion_ordenes` o
INNER JOIN `joomla_ordenproduccion_historial` h ON h.order_id = o.id
SET o.status = 'Terminada',
    o.modified = NOW()
WHERE h.event_type = 'shipping_print'
  AND h.event_title = 'Impresion de Envio'
  AND h.event_description LIKE 'Envio completo impreso via%'
  AND h.state = 1
  AND o.status != 'Terminada';

-- Verify the results
SELECT 
    o.id,
    o.orden_de_trabajo,
    o.status,
    h.event_description,
    h.created AS print_date
FROM `joomla_ordenproduccion_ordenes` o
INNER JOIN `joomla_ordenproduccion_historial` h ON h.order_id = o.id
WHERE h.event_type = 'shipping_print'
  AND h.event_title = 'Impresion de Envio'
  AND h.event_description LIKE 'Envio completo impreso via%'
  AND h.state = 1
  AND o.status = 'Terminada'
ORDER BY h.created DESC;

-- Summary: Count of updated orders
SELECT 
    COUNT(DISTINCT o.id) AS total_updated
FROM `joomla_ordenproduccion_ordenes` o
INNER JOIN `joomla_ordenproduccion_historial` h ON h.order_id = o.id
WHERE h.event_type = 'shipping_print'
  AND h.event_title = 'Impresion de Envio'
  AND h.event_description LIKE 'Envio completo impreso via%'
  AND h.state = 1
  AND o.status = 'Terminada';
