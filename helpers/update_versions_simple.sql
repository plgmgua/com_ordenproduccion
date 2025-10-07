-- Simple Version Update Script for Joomla Extensions
-- Run this in phpMyAdmin to update version numbers in the database

-- Step 1: Check current versions first
SELECT 
    'BEFORE UPDATE' AS status,
    `element`,
    `type`,
    `name`,
    JSON_UNQUOTE(JSON_EXTRACT(`manifest_cache`, '$.version')) AS version
FROM `joomla_extensions` 
WHERE `element` IN ('com_ordenproduccion', 'mod_acciones_produccion')
ORDER BY `type`, `element`;

-- Step 2: Update com_ordenproduccion component to version 2.0.2-STABLE
UPDATE `joomla_extensions` 
SET `manifest_cache` = JSON_SET(`manifest_cache`, '$.version', '2.0.2-STABLE')
WHERE `element` = 'com_ordenproduccion' AND `type` = 'component';

-- Step 3: Update mod_acciones_produccion module to version 2.0.0-STABLE  
UPDATE `joomla_extensions` 
SET `manifest_cache` = JSON_SET(`manifest_cache`, '$.version', '2.0.0-STABLE')
WHERE `element` = 'mod_acciones_produccion' AND `type` = 'module';

-- Step 4: Verify the updates
SELECT 
    'AFTER UPDATE' AS status,
    `element`,
    `type`, 
    `name`,
    JSON_UNQUOTE(JSON_EXTRACT(`manifest_cache`, '$.version')) AS version
FROM `joomla_extensions` 
WHERE `element` IN ('com_ordenproduccion', 'mod_acciones_produccion')
ORDER BY `type`, `element`;

-- If JSON functions don't work, use this alternative approach:
-- (Uncomment and run these instead if the above fails)

/*
UPDATE `joomla_extensions` 
SET `manifest_cache` = REPLACE(`manifest_cache`, '"version":"1.8.220-STABLE"', '"version":"2.0.2-STABLE"')
WHERE `element` = 'com_ordenproduccion' AND `type` = 'component';

UPDATE `joomla_extensions` 
SET `manifest_cache` = REPLACE(`manifest_cache`, '"version":"1.0.0"', '"version":"2.0.0-STABLE"')
WHERE `element` = 'mod_acciones_produccion' AND `type` = 'module';
*/
