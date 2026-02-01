-- Add all menu items for com_ordenproduccion component to Joomla admin menu
-- This script adds all the menu items that should appear in the component's admin menu

-- First, get the component ID
SET @component_id = (SELECT extension_id FROM `#__extensions` WHERE `element` = 'com_ordenproduccion' AND `type` = 'component' LIMIT 1);

-- Get the parent menu ID (the main component menu item)
SET @parent_id = (SELECT id FROM `#__menu` WHERE `link` = 'index.php?option=com_ordenproduccion&view=dashboard' AND `menutype` = 'main' AND `client_id` = 1 LIMIT 1);

-- If parent doesn't exist, create it first
INSERT IGNORE INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`, `stale`
) VALUES (
    'main', 'COM_ORDENPRODUCCION', 'production-orders', '', 'Production Orders', 
    'index.php?option=com_ordenproduccion&view=dashboard', 'component', 1, 
    1, 1, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:ordenproduccion', 0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 0, 0, '*', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0
);

-- Update parent_id reference
SET @parent_id = (SELECT id FROM `#__menu` WHERE `link` = 'index.php?option=com_ordenproduccion&view=dashboard' AND `menutype` = 'main' AND `client_id` = 1 LIMIT 1);

-- Add Dashboard menu item (if not exists)
INSERT IGNORE INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`, `stale`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_DASHBOARD', 'dashboard', '', 'Production Orders/Dashboard', 
    'index.php?option=com_ordenproduccion&view=dashboard', 'component', 1, 
    @parent_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:ordenproduccion-dashboard', 0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 0, 0, '*', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0
);

-- Add Orders menu item (if not exists)
INSERT IGNORE INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`, `stale`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_ORDERS', 'orders', '', 'Production Orders/Orders', 
    'index.php?option=com_ordenproduccion&view=ordenes', 'component', 1, 
    @parent_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:ordenproduccion-orders', 0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 0, 0, '*', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0
);

-- Add Technicians menu item (if not exists)
INSERT IGNORE INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`, `stale`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_TECHNICIANS', 'technicians', '', 'Production Orders/Technicians', 
    'index.php?option=com_ordenproduccion&view=technicians', 'component', 1, 
    @parent_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:ordenproduccion-technicians', 0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 0, 0, '*', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0
);

-- Add Webhook Config menu item (if not exists)
INSERT IGNORE INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`, `stale`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_WEBHOOK', 'webhook-config', '', 'Production Orders/Webhook Config', 
    'index.php?option=com_ordenproduccion&view=webhook', 'component', 1, 
    @parent_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:ordenproduccion-webhook', 0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 0, 0, '*', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0
);

-- Add Debug Console menu item (if not exists)
INSERT IGNORE INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`, `stale`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_DEBUG', 'debug-console', '', 'Production Orders/Debug Console', 
    'index.php?option=com_ordenproduccion&view=debug', 'component', 1, 
    @parent_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:ordenproduccion-debug', 0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 0, 0, '*', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0
);

-- Add Settings menu item (if not exists)
INSERT IGNORE INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`, `publish_up`, `publish_down`, `stale`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_SETTINGS', 'settings', '', 'Production Orders/Settings', 
    'index.php?option=com_ordenproduccion&view=settings', 'component', 1, 
    @parent_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:ordenproduccion-settings', 0, 
    '{"menu-anchor_title":"","menu-anchor_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}', 
    0, 0, 0, '*', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0
);
