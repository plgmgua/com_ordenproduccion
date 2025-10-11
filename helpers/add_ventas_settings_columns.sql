-- ======================================================================
-- Add Ventas Settings Columns
-- Component: com_ordenproduccion
-- Version: 2.5.2
-- Date: 2025-10-11
-- ======================================================================

-- Purpose: Add duplicate_request_endpoint and duplicate_request_api_key
--          columns to the ordenproduccion_settings table for the new
--          "Duplicar Solicitud" functionality in the Ventas section

-- HOW TO RUN IN PHPMYADMIN:
-- 1. Select your database (grimpsa_prod)
-- 2. Click "SQL" tab
-- 3. Copy and paste this entire file
-- 4. Click "Go"

-- Backup current settings
SELECT * FROM joomla_ordenproduccion_settings;

-- Add duplicate_request_endpoint column
ALTER TABLE joomla_ordenproduccion_settings
ADD COLUMN IF NOT EXISTS duplicate_request_endpoint VARCHAR(500) NULL DEFAULT NULL
COMMENT 'HTTP endpoint URL for duplicate order requests from Ventas section'
AFTER default_order_status;

-- Add duplicate_request_api_key column
ALTER TABLE joomla_ordenproduccion_settings
ADD COLUMN IF NOT EXISTS duplicate_request_api_key VARCHAR(200) NULL DEFAULT NULL
COMMENT 'Optional API key for duplicate order endpoint authentication'
AFTER duplicate_request_endpoint;

-- Verify new columns
SHOW COLUMNS FROM joomla_ordenproduccion_settings LIKE 'duplicate%';

-- Show updated table structure
DESCRIBE joomla_ordenproduccion_settings;

-- ======================================================================
-- VERIFICATION
-- ======================================================================
SELECT 
    'Column Check' AS check_type,
    COUNT(*) AS found_columns
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = 'grimpsa_prod'
    AND TABLE_NAME = 'joomla_ordenproduccion_settings'
    AND COLUMN_NAME IN ('duplicate_request_endpoint', 'duplicate_request_api_key');
-- Expected result: found_columns = 2

-- ======================================================================
-- SUCCESS MESSAGE
-- ======================================================================
SELECT 
    '✅ Ventas settings columns added successfully' AS status,
    NOW() AS executed_at;

