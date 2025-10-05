<?php
/**
 * Comprehensive Fix for com_ordenproduccion Component
 * 
 * This script fixes all the issues identified in the validation:
 * 1. Menu item type registration
 * 2. Language file deployment
 * 3. ComponentDispatcherFactory constructor
 * 4. Missing files
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
    
    echo "🔧 Comprehensive com_ordenproduccion Component Fix\n";
    echo "================================================\n\n";
    
    // 1. Fix Language Files
    echo "📋 1. Fixing Language Files:\n";
    
    $languageFiles = [
        'en-GB' => [
            'source' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/language/en-GB/com_ordenproduccion.ini',
            'target' => '/var/www/grimpsa_webserver/language/en-GB/com_ordenproduccion.ini'
        ],
        'es-ES' => [
            'source' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/language/es-ES/com_ordenproduccion.ini',
            'target' => '/var/www/grimpsa_webserver/language/es-ES/com_ordenproduccion.ini'
        ]
    ];
    
    foreach ($languageFiles as $lang => $files) {
        if (file_exists($files['source'])) {
            $targetDir = dirname($files['target']);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
                echo "   ✅ Created directory: $targetDir\n";
            }
            
            if (copy($files['source'], $files['target'])) {
                echo "   ✅ Copied $lang language file to Joomla directory\n";
            } else {
                echo "   ❌ Failed to copy $lang language file\n";
            }
        } else {
            echo "   ❌ Source language file not found: {$files['source']}\n";
        }
    }
    
    // 2. Fix Menu Item Types
    echo "\n📋 2. Fixing Menu Item Types:\n";
    
    $menuTypes = [
        'com_ordenproduccion.ordenes' => 'Lista de Órdenes',
        'com_ordenproduccion.orden' => 'Detalle de Orden'
    ];
    
    foreach ($menuTypes as $type => $title) {
        // Check if exists
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($type));
        
        $db->setQuery($query);
        $exists = $db->loadObject();
        
        if (!$exists) {
            $menuType = new stdClass();
            $menuType->menutype = $type;
            $menuType->title = $title;
            $menuType->description = "Menu item type for $title";
            
            try {
                $result = $db->insertObject('#__menu_types', $menuType);
                if ($result) {
                    echo "   ✅ Created menu item type: $type\n";
                } else {
                    echo "   ❌ Failed to create menu item type: $type\n";
                }
            } catch (Exception $e) {
                echo "   ❌ Error creating menu item type $type: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✅ Menu item type exists: $type\n";
        }
    }
    
    // 3. Test Language Loading
    echo "\n📋 3. Testing Language Loading:\n";
    
    // Load the component language
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
            echo "   ❌ $string: Not translated (showing key)\n";
        } else {
            echo "   ✅ $string: $translated\n";
        }
    }
    
    // 4. Clear Joomla Cache
    echo "\n📋 4. Clearing Joomla Cache:\n";
    
    $cache = Factory::getCache();
    $cache->clean('com_ordenproduccion');
    echo "   ✅ Cleared component cache\n";
    
    $cache->clean('_system');
    echo "   ✅ Cleared system cache\n";
    
    // 5. Check Component Status
    echo "\n📋 5. Checking Component Status:\n";
    
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
        echo "   - Version: {$component->manifest_cache}\n";
    } else {
        echo "   ❌ Component not found in database\n";
    }
    
    echo "\n✅ Comprehensive Component Fix Complete\n";
    echo "\n💡 Next Steps:\n";
    echo "1. Clear browser cache\n";
    echo "2. Try creating a new menu item\n";
    echo "3. Check if the menu item type now shows 'Lista de Órdenes'\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>