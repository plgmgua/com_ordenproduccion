-- =====================================================
-- Fix next order number after bulk import
-- Version: 2.4.0
-- Date: 2025-10-09
-- =====================================================
-- 
-- PROBLEM:
-- After bulk import, the next_order_number is lower than
-- the highest imported order number, causing duplicates.
--
-- SOLUTION:
-- This script finds the highest order number and sets
-- next_order_number to be one higher.
-- =====================================================

-- Step 1: Find the highest order number
SELECT 
    'Current highest order number:' AS info,
    MAX(CAST(REPLACE(orden_de_trabajo, 'ORD-', '') AS UNSIGNED)) AS highest_number
FROM joomla_ordenproduccion_ordenes;

-- Step 2: Check current settings
SELECT 
    'Current next_order_number in settings:' AS info,
    next_order_number
FROM joomla_ordenproduccion_settings
WHERE id = 1;

-- Step 3: Update next_order_number to be higher than the highest order
-- Set it to highest + 1
UPDATE joomla_ordenproduccion_settings
SET next_order_number = (
    SELECT MAX(CAST(REPLACE(orden_de_trabajo, 'ORD-', '') AS UNSIGNED)) + 1
    FROM joomla_ordenproduccion_ordenes
)
WHERE id = 1;

-- Step 4: Verify the update
SELECT 
    'Updated next_order_number:' AS info,
    next_order_number
FROM joomla_ordenproduccion_settings
WHERE id = 1;

-- Step 5: Show sample of highest orders
SELECT 
    id,
    orden_de_trabajo,
    client_name,
    created
FROM joomla_ordenproduccion_ordenes
ORDER BY CAST(REPLACE(orden_de_trabajo, 'ORD-', '') AS UNSIGNED) DESC
LIMIT 10;

