-- Fix Menu Items for com_ordenproduccion
-- This script ensures all menu items are properly registered in Joomla's menu system

-- First, remove any existing menu items for this component to avoid duplicates
DELETE FROM `#__menu` WHERE `component_id` = (SELECT `extension_id` FROM `#__extensions` WHERE `element` = 'com_ordenproduccion' AND `type` = 'component');

-- Get the component ID
SET @component_id = (SELECT `extension_id` FROM `#__extensions` WHERE `element` = 'com_ordenproduccion' AND `type` = 'component');

-- Insert main component menu item
INSERT INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`
) VALUES (
    'main', 'COM_ORDENPRODUCCION', 'com-ordenproduccion', '', 'com-ordenproduccion', 
    'index.php?option=com_ordenproduccion', 'component', 1, 1, 1, @component_id, 
    0, '0000-00-00 00:00:00', 0, 1, 'class:cog', 0, '{}', 0, 0, 0, '*', 1
);

-- Get the main menu item ID
SET @main_menu_id = LAST_INSERT_ID();

-- Insert Dashboard submenu item
INSERT INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_DASHBOARD', 'com-ordenproduccion-dashboard', '', 
    'com-ordenproduccion/dashboard', 'index.php?option=com_ordenproduccion&view=dashboard', 
    'component', 1, @main_menu_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:home', 0, '{}', 0, 0, 0, '*', 1
);

-- Insert Orders submenu item
INSERT INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_ORDERS', 'com-ordenproduccion-orders', '', 
    'com-ordenproduccion/orders', 'index.php?option=com_ordenproduccion&view=ordenes', 
    'component', 1, @main_menu_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:list', 0, '{}', 0, 0, 0, '*', 1
);

-- Insert Technicians submenu item
INSERT INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_TECHNICIANS', 'com-ordenproduccion-technicians', '', 
    'com-ordenproduccion/technicians', 'index.php?option=com_ordenproduccion&view=technicians', 
    'component', 1, @main_menu_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:users', 0, '{}', 0, 0, 0, '*', 1
);

-- Insert Webhook submenu item
INSERT INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_WEBHOOK', 'com-ordenproduccion-webhook', '', 
    'com-ordenproduccion/webhook', 'index.php?option=com_ordenproduccion&view=webhook', 
    'component', 1, @main_menu_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:link', 0, '{}', 0, 0, 0, '*', 1
);

-- Insert Debug submenu item
INSERT INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_DEBUG', 'com-ordenproduccion-debug', '', 
    'com-ordenproduccion/debug', 'index.php?option=com_ordenproduccion&view=debug', 
    'component', 1, @main_menu_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:bug', 0, '{}', 0, 0, 0, '*', 1
);

-- Insert Settings submenu item
INSERT INTO `#__menu` (
    `menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, 
    `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, 
    `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, 
    `home`, `language`, `client_id`
) VALUES (
    'main', 'COM_ORDENPRODUCCION_MENU_SETTINGS', 'com-ordenproduccion-settings', '', 
    'com-ordenproduccion/settings', 'index.php?option=com_ordenproduccion&view=settings', 
    'component', 1, @main_menu_id, 2, @component_id, 0, '0000-00-00 00:00:00', 
    0, 1, 'class:cog', 0, '{}', 0, 0, 0, '*', 1
);

-- Update the lft and rgt values for proper menu hierarchy
UPDATE `#__menu` SET `lft` = 1, `rgt` = 14 WHERE `id` = @main_menu_id;
UPDATE `#__menu` SET `lft` = 2, `rgt` = 3 WHERE `parent_id` = @main_menu_id AND `alias` = 'com-ordenproduccion-dashboard';
UPDATE `#__menu` SET `lft` = 4, `rgt` = 5 WHERE `parent_id` = @main_menu_id AND `alias` = 'com-ordenproduccion-orders';
UPDATE `#__menu` SET `lft` = 6, `rgt` = 7 WHERE `parent_id` = @main_menu_id AND `alias` = 'com-ordenproduccion-technicians';
UPDATE `#__menu` SET `lft` = 8, `rgt` = 9 WHERE `parent_id` = @main_menu_id AND `alias` = 'com-ordenproduccion-webhook';
UPDATE `#__menu` SET `lft` = 10, `rgt` = 11 WHERE `parent_id` = @main_menu_id AND `alias` = 'com-ordenproduccion-debug';
UPDATE `#__menu` SET `lft` = 12, `rgt` = 13 WHERE `parent_id` = @main_menu_id AND `alias` = 'com-ordenproduccion-settings';
