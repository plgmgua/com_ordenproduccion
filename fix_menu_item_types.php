<?php
/**
 * Fix Menu Item Types for com_ordenproduccion
 * 
 * This script fixes the menu item type registration for the frontend views
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    echo "🔧 Fixing Menu Item Types for com_ordenproduccion\n";
    echo "================================================\n\n";
    
    // Check existing menu item types
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $existingTypes = $db->loadObjectList();
    
    echo "📋 Existing Menu Item Types:\n";
    if (empty($existingTypes)) {
        echo "   No existing menu item types found\n";
    } else {
        foreach ($existingTypes as $type) {
            echo "   - {$type->menutype}: {$type->title}\n";
        }
    }
    echo "\n";
    
    // Check if menu item types are properly registered
    $requiredTypes = [
        'com_ordenproduccion.ordenes' => 'Lista de Órdenes',
        'com_ordenproduccion.orden' => 'Detalle de Orden'
    ];
    
    echo "🔍 Checking Required Menu Item Types:\n";
    foreach ($requiredTypes as $type => $title) {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($type));
        
        $db->setQuery($query);
        $exists = $db->loadObject();
        
        if ($exists) {
            echo "   ✅ $type: {$exists->title}\n";
        } else {
            echo "   ❌ $type: Missing\n";
            
            // Create the menu item type
            $menuType = new stdClass();
            $menuType->menutype = $type;
            $menuType->title = $title;
            $menuType->description = "Menu item type for $title";
            
            try {
                $result = $db->insertObject('#__menu_types', $menuType);
                if ($result) {
                    echo "   ✅ Created: $type\n";
                } else {
                    echo "   ❌ Failed to create: $type\n";
                }
            } catch (Exception $e) {
                echo "   ❌ Error creating $type: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n";
    
    // Check menu items
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $menuItems = $db->loadObjectList();
    
    echo "📋 Existing Menu Items:\n";
    if (empty($menuItems)) {
        echo "   No existing menu items found\n";
    } else {
        foreach ($menuItems as $item) {
            echo "   - {$item->title} ({$item->link}): {$item->menutype}\n";
        }
    }
    
    echo "\n✅ Menu Item Type Fix Complete\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
