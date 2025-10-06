<?php
/**
 * Fix Menu Item Type for Ordenes List View
 * 
 * This script specifically fixes the menu item type to show "ordenes list"
 * instead of "Metadata" for the frontend ordenes view.
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    echo "🔧 Fixing Menu Item Type for Ordenes List View\n";
    echo "==============================================\n\n";
    
    // 1. Check existing menu item types for com_ordenproduccion
    echo "📋 1. Checking Existing Menu Item Types:\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $existingTypes = $db->loadObjectList();
    
    if (empty($existingTypes)) {
        echo "   No existing menu item types found\n";
    } else {
        foreach ($existingTypes as $type) {
            echo "   - {$type->menutype}: {$type->title}\n";
        }
    }
    
    // 2. Remove any existing incorrect menu item types
    echo "\n📋 2. Cleaning Up Incorrect Menu Item Types:\n";
    
    $incorrectTypes = [
        'com_ordenproduccion',
        'com_ordenproduccion.ordenes',
        'com_ordenproduccion.orden'
    ];
    
    foreach ($incorrectTypes as $type) {
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($type));
        
        $db->setQuery($query);
        $result = $db->execute();
        
        if ($result) {
            echo "   ✅ Removed incorrect menu item type: $type\n";
        } else {
            echo "   ℹ️  Menu item type not found: $type\n";
        }
    }
    
    // 3. Create proper menu item types
    echo "\n📋 3. Creating Proper Menu Item Types:\n";
    
    $menuTypes = [
        'com_ordenproduccion.ordenes' => [
            'title' => 'Lista de Órdenes',
            'description' => 'Mostrar lista de órdenes de trabajo'
        ],
        'com_ordenproduccion.orden' => [
            'title' => 'Detalle de Orden',
            'description' => 'Mostrar detalle de orden de trabajo'
        ]
    ];
    
    foreach ($menuTypes as $type => $info) {
        $menuType = new stdClass();
        $menuType->menutype = $type;
        $menuType->title = $info['title'];
        $menuType->description = $info['description'];
        
        try {
            $result = $db->insertObject('#__menu_types', $menuType);
            if ($result) {
                echo "   ✅ Created menu item type: $type - {$info['title']}\n";
            } else {
                echo "   ❌ Failed to create menu item type: $type\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Error creating menu item type $type: " . $e->getMessage() . "\n";
        }
    }
    
    // 4. Check and fix existing menu items
    echo "\n📋 4. Checking Existing Menu Items:\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $menuItems = $db->loadObjectList();
    
    if (empty($menuItems)) {
        echo "   No existing menu items found\n";
    } else {
        foreach ($menuItems as $item) {
            echo "   - ID: {$item->id}, Title: {$item->title}, Link: {$item->link}, Type: {$item->menutype}\n";
            
            // Update menu item type if it's incorrect
            if ($item->menutype === 'com_ordenproduccion' || $item->menutype === '') {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__menu'))
                    ->set($db->quoteName('menutype') . ' = ' . $db->quote('com_ordenproduccion.ordenes'))
                    ->where($db->quoteName('id') . ' = ' . (int) $item->id);
                
                $db->setQuery($query);
                $result = $db->execute();
                
                if ($result) {
                    echo "     ✅ Updated menu item type to: com_ordenproduccion.ordenes\n";
                } else {
                    echo "     ❌ Failed to update menu item type\n";
                }
            }
        }
    }
    
    // 5. Test language loading
    echo "\n📋 5. Testing Language Loading:\n";
    
    $lang = Factory::getLanguage();
    $lang->load('com_ordenproduccion', JPATH_SITE);
    
    $testStrings = [
        'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE',
        'COM_ORDENPRODUCCION_ORDENES_TITLE'
    ];
    
    foreach ($testStrings as $string) {
        $translated = Text::_($string);
        if ($translated === $string) {
            echo "   ❌ $string: Not translated (showing key)\n";
        } else {
            echo "   ✅ $string: $translated\n";
        }
    }
    
    // 6. Clear cache
    echo "\n📋 6. Clearing Cache:\n";
    
    $cache = Factory::getCache();
    $cache->clean('com_ordenproduccion');
    echo "   ✅ Cleared component cache\n";
    
    $cache->clean('_system');
    echo "   ✅ Cleared system cache\n";
    
    echo "\n✅ Menu Item Type Fix Complete\n";
    echo "\n💡 Next Steps:\n";
    echo "1. Clear browser cache\n";
    echo "2. Go to Menus > Add New Menu Item\n";
    echo "3. Select 'Lista de Órdenes' from the menu item type list\n";
    echo "4. The menu item type should now show 'Lista de Órdenes' instead of 'Metadata'\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
