-- Add Settings menu item to com_ordenproduccion component
-- This script adds the Settings menu item to the Joomla admin menu

INSERT INTO `#__menu` (
    `menutype`, 
    `title`, 
    `alias`, 
    `note`, 
    `path`, 
    `link`, 
    `type`, 
    `published`, 
    `parent_id`, 
    `level`, 
    `component_id`, 
    `checked_out`, 
    `checked_out_time`, 
    `browserNav`, 
    `access`, 
    `img`, 
    `template_style_id`, 
    `params`, 
    `lft`, 
    `rgt`, 
    `home`, 
    `language`, 
    `client_id`, 
    `publish_up`, 
    `publish_down`, 
    `stale`
) 
SELECT 
    'main', 
    'COM_ORDENPRODUCCION_MENU_SETTINGS', 
    'production-orders-settings', 
    '', 
    'Production Orders/Settings', 
    'index.php?option=com_ordenproduccion&view=settings', 
    'component', 
    1, 
    (SELECT id FROM `#__menu` WHERE `link` = 'index.php?option=com_ordenproduccion&view=ordenes' AND `menutype` = 'main' LIMIT 1), 
    2, 
    (SELECT extension_id FROM `#__extensions` WHERE `element` = 'com_ordenproduccion' AND `type` = 'component' LIMIT 1), 
    0, 
    '0000-00-00 00:00:00', 
    0, 
    1, 
    'class:ordenproduccion-settings', 
    0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 
    0, 
    0, 
    '*', 
    1, 
    '0000-00-00 00:00:00', 
    '0000-00-00 00:00:00', 
    0
WHERE NOT EXISTS (
    SELECT 1 FROM `#__menu` 
    WHERE `link` = 'index.php?option=com_ordenproduccion&view=settings' 
    AND `menutype` = 'main'
);
