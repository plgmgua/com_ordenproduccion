<?php
/**
 * Register Menu Item Types for com_ordenproduccion
 * Run this script to register the menu item types in Joomla
 */

// Bootstrap Joomla
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\MenuType;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    echo "Registering Menu Item Types for com_ordenproduccion...\n";
    
    // Check if menu item types already exist
    $query = $db->getQuery(true)
        ->select('menutype')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' IN (' . $db->quote('com-ordenproduccion-ordenes') . ', ' . $db->quote('com-ordenproduccion-orden') . ')');
    
    $db->setQuery($query);
    $existing = $db->loadColumn();
    
    // Register Ordenes List Menu Item Type
    if (!in_array('com-ordenproduccion-ordenes', $existing)) {
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__menu_types'))
            ->set($db->quoteName('menutype') . ' = ' . $db->quote('com-ordenproduccion-ordenes'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE'))
            ->set($db->quoteName('description') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_DESC'))
            ->set($db->quoteName('client_id') . ' = 0');
        
        $db->setQuery($query);
        $db->execute();
        echo "✓ Registered 'com-ordenproduccion-ordenes' menu item type\n";
    } else {
        echo "✓ 'com-ordenproduccion-ordenes' menu item type already exists\n";
    }
    
    // Register Orden Detail Menu Item Type
    if (!in_array('com-ordenproduccion-orden', $existing)) {
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__menu_types'))
            ->set($db->quoteName('menutype') . ' = ' . $db->quote('com-ordenproduccion-orden'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_TITLE'))
            ->set($db->quoteName('description') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_DESC'))
            ->set($db->quoteName('client_id') . ' = 0');
        
        $db->setQuery($query);
        $db->execute();
        echo "✓ Registered 'com-ordenproduccion-orden' menu item type\n";
    } else {
        echo "✓ 'com-ordenproduccion-orden' menu item type already exists\n";
    }
    
    echo "\nMenu item types registration completed successfully!\n";
    echo "You can now create menu items using these types in the Joomla admin.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
