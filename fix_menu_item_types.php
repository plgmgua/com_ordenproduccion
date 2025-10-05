<?php
/**
 * Fix Menu Item Types - Comprehensive Solution
 * This script addresses the "Metadata" display issue by using multiple approaches
 */

// Bootstrap Joomla
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Cache\Cache;
use Joomla\CMS\Language\Language;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    echo "=== COMPREHENSIVE MENU ITEM TYPE FIX ===\n\n";
    
    // 1. Clear all existing menu item types for this component
    echo "1. Clearing existing menu item types...\n";
    $query = $db->getQuery(true)
        ->delete($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%ordenproduccion%'));
    
    $db->setQuery($query);
    $deleted = $db->execute();
    echo "   ✓ Cleared existing menu item types\n";
    
    // 2. Clear all caches
    echo "\n2. Clearing all caches...\n";
    $cache = Factory::getCache();
    $cache->clean();
    echo "   ✓ Main cache cleared\n";
    
    $cacheGroups = ['_system', 'com_modules', 'com_plugins', 'com_languages', 'language', 'component'];
    foreach ($cacheGroups as $group) {
        try {
            $cache->clean($group);
            echo "   ✓ $group cache cleared\n";
        } catch (Exception $e) {
            echo "   ⚠ $group cache: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Force reload language files
    echo "\n3. Reloading language files...\n";
    $lang = Factory::getLanguage();
    $currentLang = $lang->getTag();
    echo "   Current language: $currentLang\n";
    
    // Clear and reload language files
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/components/com_ordenproduccion/site', null, true, true);
    echo "   ✓ Site language files reloaded\n";
    
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/administrator/components/com_ordenproduccion', null, true, true);
    echo "   ✓ Admin language files reloaded\n";
    
    // 4. Test language translations
    echo "\n4. Testing language translations...\n";
    $testKey = 'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE';
    $translated = \Joomla\CMS\Language\Text::_($testKey);
    echo "   Key: $testKey\n";
    echo "   Translated: $translated\n";
    echo "   Is translated: " . ($translated !== $testKey ? 'YES' : 'NO') . "\n";
    
    // 5. Create menu item types with explicit titles
    echo "\n5. Creating menu item types with explicit titles...\n";
    
    // Create Ordenes menu item type
    $query = $db->getQuery(true)
        ->insert($db->quoteName('#__menu_types'))
        ->set($db->quoteName('menutype') . ' = ' . $db->quote('com-ordenproduccion-ordenes'))
        ->set($db->quoteName('title') . ' = ' . $db->quote('Listado de Órdenes'))
        ->set($db->quoteName('description') . ' = ' . $db->quote('Muestra una lista de órdenes de trabajo con filtros y paginación'))
        ->set($db->quoteName('client_id') . ' = 0');
    
    $db->setQuery($query);
    $db->execute();
    echo "   ✓ Created 'com-ordenproduccion-ordenes' with title 'Listado de Órdenes'\n";
    
    // Create Orden menu item type
    $query = $db->getQuery(true)
        ->insert($db->quoteName('#__menu_types'))
        ->set($db->quoteName('menutype') . ' = ' . $db->quote('com-ordenproduccion-orden'))
        ->set($db->quoteName('title') . ' = ' . $db->quote('Detalle de Orden de Trabajo'))
        ->set($db->quoteName('description') . ' = ' . $db->quote('Muestra el detalle completo de una orden de trabajo específica'))
        ->set($db->quoteName('client_id') . ' = 0');
    
    $db->setQuery($query);
    $db->execute();
    echo "   ✓ Created 'com-ordenproduccion-orden' with title 'Detalle de Orden de Trabajo'\n";
    
    // 6. Verify menu item types were created
    echo "\n6. Verifying menu item types...\n";
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%ordenproduccion%'));
    
    $db->setQuery($query);
    $menuTypes = $db->loadObjectList();
    
    foreach ($menuTypes as $type) {
        echo "   - Menutype: {$type->menutype}\n";
        echo "     Title: {$type->title}\n";
        echo "     Description: {$type->description}\n\n";
    }
    
    // 7. Clear caches again
    echo "7. Final cache clear...\n";
    $cache->clean();
    echo "   ✓ Final cache clear completed\n";
    
    echo "\n=== FIX COMPLETE ===\n";
    echo "The menu item types should now show:\n";
    echo "- 'Listado de Órdenes' instead of 'Metadata'\n";
    echo "- 'Detalle de Orden de Trabajo' for the detail view\n";
    echo "\nNext steps:\n";
    echo "1. Go to Joomla Admin → Menus → [Any Menu] → New\n";
    echo "2. Check if the menu item types now show the correct titles\n";
    echo "3. If still showing 'Metadata', try clearing browser cache\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
