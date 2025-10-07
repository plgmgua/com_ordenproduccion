-- Update Joomla Component and Module Versions in Database
-- This script updates the version numbers in the extensions table to match the code

-- Update com_ordenproduccion component version from 1.8.220-STABLE to 2.0.2-STABLE
UPDATE `joomla_extensions` 
SET `manifest_cache` = JSON_SET(
    `manifest_cache`, 
    '$.version', 
    '2.0.2-STABLE'
)
WHERE `element` = 'com_ordenproduccion' 
AND `type` = 'component';

-- Update mod_acciones_produccion module version from 1.0.0 to 2.0.0-STABLE
UPDATE `joomla_extensions` 
SET `manifest_cache` = JSON_SET(
    `manifest_cache`, 
    '$.version', 
    '2.0.0-STABLE'
)
WHERE `element` = 'mod_acciones_produccion' 
AND `type` = 'module';

-- Verify the updates by displaying the current versions
SELECT 
    `element`,
    `type`,
    `name`,
    JSON_UNQUOTE(JSON_EXTRACT(`manifest_cache`, '$.version')) AS current_version,
    `creationDate`,
    `author`
FROM `joomla_extensions` 
WHERE `element` IN ('com_ordenproduccion', 'mod_acciones_produccion')
ORDER BY `type`, `element`;

-- Alternative method: Update using simple string replacement (if JSON functions don't work)
-- Uncomment these lines if the above JSON method fails:

-- UPDATE `joomla_extensions` 
-- SET `manifest_cache` = REPLACE(
--     `manifest_cache`, 
--     '"version":"1.8.220-STABLE"', 
--     '"version":"2.0.2-STABLE"'
-- )
-- WHERE `element` = 'com_ordenproduccion' 
-- AND `type` = 'component';

-- UPDATE `joomla_extensions` 
-- SET `manifest_cache` = REPLACE(
--     `manifest_cache`, 
--     '"version":"1.0.0"', 
--     '"version":"2.0.0-STABLE"'
-- )
-- WHERE `element` = 'mod_acciones_produccion' 
-- AND `type` = 'module';

-- Additional verification query to check all our extensions
SELECT 
    'EXTENSION SUMMARY' AS info,
    '' AS element,
    '' AS type,
    '' AS name,
    '' AS version,
    '' AS creationDate,
    '' AS author
UNION ALL
SELECT 
    '',
    `element`,
    `type`,
    `name`,
    JSON_UNQUOTE(JSON_EXTRACT(`manifest_cache`, '$.version')) AS version,
    `creationDate`,
    `author`
FROM `joomla_extensions` 
WHERE `element` IN ('com_ordenproduccion', 'mod_acciones_produccion')
ORDER BY `type`, `element`;
