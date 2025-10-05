-- Install Menu Item Types for com_ordenproduccion
-- Run this script in phpMyAdmin to register the menu item types

-- Insert Ordenes List Menu Item Type
INSERT INTO `joomla_menu_types` (`menutype`, `title`, `description`, `client_id`) 
VALUES ('com-ordenproduccion-ordenes', 'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE', 'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_DESC', 0);

-- Insert Orden Detail Menu Item Type  
INSERT INTO `joomla_menu_types` (`menutype`, `title`, `description`, `client_id`) 
VALUES ('com-ordenproduccion-orden', 'COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_TITLE', 'COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_DESC', 0);
