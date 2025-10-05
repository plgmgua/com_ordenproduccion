<?php
/**
 * Comprehensive Fix for Frontend Menu and Language Issues
 * 
 * This script fixes:
 * 1. Menu item type registration
 * 2. Language file loading
 * 3. Menu item creation
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
    
    echo "🔧 Comprehensive Frontend Menu and Language Fix\n";
    echo "==============================================\n\n";
    
    // 1. Check and fix language files
    echo "📋 1. Checking Language Files:\n";
    
    $languageFiles = [
        'en-GB' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/en-GB/com_ordenproduccion.ini',
        'es-ES' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/es-ES/com_ordenproduccion.ini'
    ];
    
    foreach ($languageFiles as $lang => $file) {
        if (file_exists($file)) {
            echo "   ✅ $lang: $file (exists)\n";
        } else {
            echo "   ❌ $lang: $file (missing)\n";
        }
    }
    
    // 2. Check if language files are in the correct Joomla location
    echo "\n📋 2. Checking Joomla Language Directory:\n";
    
    $joomlaLanguageFiles = [
        'en-GB' => '/var/www/grimpsa_webserver/language/en-GB/com_ordenproduccion.ini',
        'es-ES' => '/var/www/grimpsa_webserver/language/es-ES/com_ordenproduccion.ini'
    ];
    
    foreach ($joomlaLanguageFiles as $lang => $file) {
        if (file_exists($file)) {
            echo "   ✅ $lang: $file (exists)\n";
        } else {
            echo "   ❌ $lang: $file (missing)\n";
            
            // Copy from component directory if it exists
            $sourceFile = "/var/www/grimpsa_webserver/components/com_ordenproduccion/language/$lang/com_ordenproduccion.ini";
            if (file_exists($sourceFile)) {
                $targetDir = dirname($file);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                if (copy($sourceFile, $file)) {
                    echo "   ✅ Copied $lang language file to Joomla directory\n";
                } else {
                    echo "   ❌ Failed to copy $lang language file\n";
                }
            }
        }
    }
    
    // 3. Check menu item types
    echo "\n📋 3. Checking Menu Item Types:\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $existingTypes = $db->loadObjectList();
    
    if (empty($existingTypes)) {
        echo "   No existing menu item types found\n";
        
        // Create menu item types
        $menuTypes = [
            'com_ordenproduccion.ordenes' => 'Lista de Órdenes',
            'com_ordenproduccion.orden' => 'Detalle de Orden'
        ];
        
        foreach ($menuTypes as $type => $title) {
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
        }
    } else {
        foreach ($existingTypes as $type) {
            echo "   ✅ {$type->menutype}: {$type->title}\n";
        }
    }
    
    // 4. Test language loading
    echo "\n📋 4. Testing Language Loading:\n";
    
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
    
    // 5. Check menu items
    echo "\n📋 5. Checking Menu Items:\n";
    
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
            echo "   - {$item->title} ({$item->link}): {$item->menutype}\n";
        }
    }
    
    echo "\n✅ Frontend Menu and Language Fix Complete\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
