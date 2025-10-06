<?php
/**
 * Complete Production Component Fix
 * 
 * This script comprehensively fixes all production component issues including:
 * - Menu item type registration
 * - Language file loading
 * - Component structure
 * - Database integrity
 * - Cache clearing
 */
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
    
    // Get component version
    $versionFile = JPATH_BASE . '/components/com_ordenproduccion/VERSION';
    $componentVersion = 'Unknown';
    if (file_exists($versionFile)) {
        $componentVersion = trim(file_get_contents($versionFile));
    }
    
    echo "🔧 Complete Production Component Fix\n";
    echo "====================================\n";
    echo "Component Version: $componentVersion\n\n";
    
    // 1. Check component installation
    echo "📋 1. Checking Component Installation:\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "   ✅ Component found in database\n";
        echo "   - Name: {$component->name}\n";
        echo "   - Enabled: " . ($component->enabled ? 'Yes' : 'No') . "\n";
        
        // Ensure component is enabled
        if (!$component->enabled) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'));
            
            $db->setQuery($query);
            $db->execute();
            echo "   ✅ Enabled component\n";
        }
    } else {
        echo "   ❌ Component not found in database\n";
    }
    
    // 2. Check menu item type files
    echo "\n📋 2. Checking Menu Item Type Files:\n";
    
    $menuFiles = [
        'Admin Ordenes' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/menu/ordenes.xml',
        'Admin Orden' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/menu/orden.xml'
    ];
    
    foreach ($menuFiles as $name => $file) {
        if (file_exists($file)) {
            echo "   ✅ $name: $file (exists)\n";
        } else {
            echo "   ❌ $name: $file (missing)\n";
        }
    }
    
    // 3. Clean up existing menu item types
    echo "\n📋 3. Cleaning Up Menu Item Types:\n";
    
    // Remove all existing com_ordenproduccion menu item types
    $query = $db->getQuery(true)
        ->delete($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $result = $db->execute();
    echo "   ✅ Removed all existing com_ordenproduccion menu item types\n";
    
    // 4. Create proper menu item types
    echo "\n📋 4. Creating Menu Item Types:\n";
    
    $menuTypes = [
        'ordenes' => [
            'title' => 'Lista de Órdenes',
            'description' => 'Mostrar lista de órdenes de trabajo'
        ],
        'orden' => [
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
                echo "   ✅ Created: $type - {$info['title']}\n";
            } else {
                echo "   ❌ Failed to create: $type\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Error creating $type: " . $e->getMessage() . "\n";
        }
    }
    
    // 5. Check language files
    echo "\n📋 5. Checking Language Files:\n";
    
    $languageFiles = [
        'Site EN' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/en-GB/com_ordenproduccion.ini',
        'Site ES' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/es-ES/com_ordenproduccion.ini',
        'Joomla EN' => '/var/www/grimpsa_webserver/language/en-GB/com_ordenproduccion.ini',
        'Joomla ES' => '/var/www/grimpsa_webserver/language/es-ES/com_ordenproduccion.ini'
    ];
    
    foreach ($languageFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "   ✅ $name: $file ($size bytes)\n";
        } else {
            echo "   ❌ $name: $file (missing)\n";
            
            // Try to copy from component directory
            if (strpos($file, '/language/') !== false) {
                $sourceFile = str_replace('/language/', '/components/com_ordenproduccion/language/', $file);
                if (file_exists($sourceFile)) {
                    $targetDir = dirname($file);
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    if (copy($sourceFile, $file)) {
                        echo "     ✅ Copied from component directory\n";
                    }
                }
            }
        }
    }
    
    // 6. Test language loading
    echo "\n📋 6. Testing Language Loading:\n";
    
    $lang = Factory::getLanguage();
    $lang->load('com_ordenproduccion', JPATH_SITE);
    
    $testStrings = [
        'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE',
        'COM_ORDENPRODUCCION_ORDENES_TITLE',
        'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_DESC'
    ];
    
    foreach ($testStrings as $string) {
        $translated = Text::_($string);
        if ($translated === $string) {
            echo "   ❌ $string: Not translated\n";
        } else {
            echo "   ✅ $string: $translated\n";
        }
    }
    
    // 7. Clear all caches
    echo "\n📋 7. Clearing All Caches:\n";
    
    $cache = Factory::getCache();
    $cache->clean('com_ordenproduccion');
    echo "   ✅ Cleared component cache\n";
    
    $cache->clean('_system');
    echo "   ✅ Cleared system cache\n";
    
    $cache->clean('_system', 'page');
    echo "   ✅ Cleared page cache\n";
    
    echo "\n✅ Complete Production Component Fix Finished\n";
    echo "\n💡 What Should Happen Now:\n";
    echo "1. Go to Menus > Add New Menu Item\n";
    echo "2. In 'Menu Item Type' dropdown, you should see:\n";
    echo "   - 'Lista de Órdenes' (not 'Metadata')\n";
    echo "   - 'Detalle de Orden'\n";
    echo "3. Select 'Lista de Órdenes' to create the frontend view\n";
    echo "\n🔧 If it still shows 'Metadata':\n";
    echo "1. Clear browser cache completely\n";
    echo "2. Log out and log back into Joomla admin\n";
    echo "3. Try again\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
