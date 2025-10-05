-- SQL Script to Check and Clean Menu Items for com_ordenproduccion
-- Run this in phpMyAdmin to find and clean up menu items

-- 1. Check all menu items related to ordenproduccion
SELECT 
    id,
    menutype,
    title,
    alias,
    path,
    link,
    type,
    published,
    parent_id,
    level,
    component_id,
    language,
    client_id,
    created,
    created_by
FROM `joomla_menu` 
WHERE `link` LIKE '%com_ordenproduccion%' 
   OR `title` LIKE '%orden%' 
   OR `title` LIKE '%trabajo%'
   OR `alias` LIKE '%orden%'
ORDER BY id DESC;

-- 2. Check menu item types
SELECT 
    menutype,
    title,
    description,
    client_id
FROM `joomla_menu_types` 
WHERE `menutype` LIKE '%ordenproduccion%' 
   OR `title` LIKE '%orden%'
   OR `title` LIKE '%trabajo%';

-- 3. Check for duplicate or conflicting menu items
SELECT 
    title,
    alias,
    COUNT(*) as count,
    GROUP_CONCAT(id) as ids,
    GROUP_CONCAT(menutype) as menutypes
FROM `joomla_menu` 
WHERE `link` LIKE '%com_ordenproduccion%'
GROUP BY title, alias
HAVING COUNT(*) > 1;

-- 4. Check for menu items in trash (state = -2)
SELECT 
    id,
    menutype,
    title,
    alias,
    path,
    link,
    state,
    created,
    created_by
FROM `joomla_menu` 
WHERE `state` = -2 
  AND (`link` LIKE '%com_ordenproduccion%' 
       OR `title` LIKE '%orden%' 
       OR `title` LIKE '%trabajo%');

-- 5. Check for unpublished menu items (published = 0)
SELECT 
    id,
    menutype,
    title,
    alias,
    path,
    link,
    published,
    created,
    created_by
FROM `joomla_menu` 
WHERE `published` = 0 
  AND (`link` LIKE '%com_ordenproduccion%' 
       OR `title` LIKE '%orden%' 
       OR `title` LIKE '%trabajo%');

-- 6. DELETE COMMANDS (Use with caution - uncomment only if needed)
-- Delete menu items in trash
-- DELETE FROM `joomla_menu` WHERE `state` = -2 AND `link` LIKE '%com_ordenproduccion%';

-- Delete unpublished menu items (if you want to clean them up)
-- DELETE FROM `joomla_menu` WHERE `published` = 0 AND `link` LIKE '%com_ordenproduccion%';

-- Delete duplicate menu items (keep the newest one)
-- DELETE FROM `joomla_menu` WHERE id IN (SELECT id FROM (
--     SELECT id FROM `joomla_menu` 
--     WHERE `link` LIKE '%com_ordenproduccion%' 
--     AND id NOT IN (
--         SELECT MAX(id) FROM `joomla_menu` 
--         WHERE `link` LIKE '%com_ordenproduccion%' 
--         GROUP BY title, alias
--     )
-- ) AS temp);

-- 7. Check component registration
SELECT 
    extension_id,
    name,
    element,
    type,
    enabled,
    state
FROM `joomla_extensions` 
WHERE `element` = 'com_ordenproduccion';

-- 8. Check for any menu items with similar names that might conflict
SELECT 
    id,
    menutype,
    title,
    alias,
    path,
    link,
    published,
    state
FROM `joomla_menu` 
WHERE `title` LIKE '%orden%' 
   OR `title` LIKE '%trabajo%'
   OR `title` LIKE '%produccion%'
   OR `alias` LIKE '%orden%'
   OR `alias` LIKE '%trabajo%'
   OR `alias` LIKE '%produccion%'
ORDER BY title;
