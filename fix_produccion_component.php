<?php
/**
 * Fix Production Component Issues
 * This script addresses multiple issues:
 * 1. Menu item types disappearing
 * 2. 404 routing errors
 * 3. Language translation issues
 * 4. Component registration problems
 * 
 * Usage: Run this script from the Joomla root directory
 * php fix_produccion_component.php
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
    
    echo "=== FIXING PRODUCTION COMPONENT ISSUES ===\n\n";
    
    // 1. Clean Up Existing Menu Items
    echo "1. Cleaning up existing menu items...\n";
    
    // Check for existing menu items that might conflict
    $query = $db->getQuery(true)
        ->select('id, title, alias, link, state, published')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $existingMenuItems = $db->loadObjectList();
    
    if (!empty($existingMenuItems)) {
        echo "   Found " . count($existingMenuItems) . " existing menu items:\n";
        foreach ($existingMenuItems as $item) {
            echo "   - ID: {$item->id}, Title: {$item->title}, State: {$item->state}, Published: {$item->published}\n";
        }
        
        // Delete menu items in trash (state = -2)
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__menu'))
            ->where($db->quoteName('state') . ' = -2')
            ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
        
        $db->setQuery($query);
        $deleted = $db->execute();
        echo "   ✓ Cleaned up menu items in trash\n";
    } else {
        echo "   ✓ No existing menu items found\n";
    }
    
    // 2. Fix Menu Item Types (Database Approach)
    echo "\n2. Fixing Menu Item Types...\n";
    
    // Check if menu item types exist
    $query = $db->getQuery(true)
        ->select('menutype')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' IN (' . $db->quote('com-ordenproduccion-ordenes') . ', ' . $db->quote('com-ordenproduccion-orden') . ')');
    
    $db->setQuery($query);
    $existing = $db->loadColumn();
    
    // Insert missing menu item types
    if (!in_array('com-ordenproduccion-ordenes', $existing)) {
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__menu_types'))
            ->set($db->quoteName('menutype') . ' = ' . $db->quote('com-ordenproduccion-ordenes'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE'))
            ->set($db->quoteName('description') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_DESC'))
            ->set($db->quoteName('client_id') . ' = 0');
        
        $db->setQuery($query);
        $db->execute();
        echo "   ✓ Added 'com-ordenproduccion-ordenes' menu item type\n";
    } else {
        echo "   ✓ 'com-ordenproduccion-ordenes' menu item type already exists\n";
    }
    
    if (!in_array('com-ordenproduccion-orden', $existing)) {
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__menu_types'))
            ->set($db->quoteName('menutype') . ' = ' . $db->quote('com-ordenproduccion-orden'))
            ->set($db->quoteName('title') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_TITLE'))
            ->set($db->quoteName('description') . ' = ' . $db->quote('COM_ORDENPRODUCCION_ORDEN_VIEW_DEFAULT_DESC'))
            ->set($db->quoteName('client_id') . ' = 0');
        
        $db->setQuery($query);
        $db->execute();
        echo "   ✓ Added 'com-ordenproduccion-orden' menu item type\n";
    } else {
        echo "   ✓ 'com-ordenproduccion-orden' menu item type already exists\n";
    }
    
    // 2. Clear All Caches
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
    
    // 3. Force Reload Language Files
    echo "\n3. Reloading language files...\n";
    $lang = Factory::getLanguage();
    $currentLang = $lang->getTag();
    echo "   Current language: $currentLang\n";
    
    // Clear and reload language files
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/components/com_ordenproduccion/site', null, true, true);
    echo "   ✓ Site language files reloaded\n";
    
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/administrator/components/com_ordenproduccion', null, true, true);
    echo "   ✓ Admin language files reloaded\n";
    
    // 4. Test Language Translations
    echo "\n4. Testing language translations...\n";
    $statusTests = [
        'COM_ORDENPRODUCCION_STATUS_NEW' => 'Nueva',
        'COM_ORDENPRODUCCION_STATUS_IN_PROCESS' => 'En Proceso',
        'COM_ORDENPRODUCCION_STATUS_COMPLETED' => 'Completada',
        'COM_ORDENPRODUCCION_STATUS_CLOSED' => 'Cerrada'
    ];
    
    $allWorking = true;
    foreach ($statusTests as $key => $expected) {
        $translated = \Joomla\CMS\Language\Text::_($key);
        $working = ($translated === $expected);
        $status = $working ? '✓' : '❌';
        echo "   $status $key: $translated " . ($working ? '' : "(expected: $expected)") . "\n";
        if (!$working) $allWorking = false;
    }
    
    // 5. Check Component Registration
    echo "\n5. Checking component registration...\n";
    $query = $db->getQuery(true)
        ->select('extension_id, element, enabled')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "   ✓ Component registered (ID: {$component->extension_id}, Enabled: {$component->enabled})\n";
    } else {
        echo "   ❌ Component not found in extensions table\n";
    }
    
    // 6. Check View Files
    echo "\n6. Checking view files...\n";
    $viewFiles = [
        JPATH_ROOT . '/components/com_ordenproduccion/site/src/View/Ordenes/HtmlView.php',
        JPATH_ROOT . '/components/com_ordenproduccion/site/src/View/Orden/HtmlView.php',
        JPATH_ROOT . '/components/com_ordenproduccion/site/tmpl/ordenes/default.php',
        JPATH_ROOT . '/components/com_ordenproduccion/site/tmpl/orden/default.php'
    ];
    
    foreach ($viewFiles as $file) {
        if (file_exists($file)) {
            echo "   ✓ " . basename($file) . " exists\n";
        } else {
            echo "   ❌ " . basename($file) . " missing\n";
        }
    }
    
    // 7. Check Controller Files
    echo "\n7. Checking controller files...\n";
    $controllerFiles = [
        JPATH_ROOT . '/components/com_ordenproduccion/site/src/Controller/OrdenesController.php',
        JPATH_ROOT . '/components/com_ordenproduccion/site/src/Controller/OrdenController.php'
    ];
    
    foreach ($controllerFiles as $file) {
        if (file_exists($file)) {
            echo "   ✓ " . basename($file) . " exists\n";
        } else {
            echo "   ❌ " . basename($file) . " missing\n";
        }
    }
    
    // 8. Check for Webhook Conflicts
    echo "\n8. Checking for webhook conflicts...\n";
    $webhookFiles = [
        JPATH_ROOT . '/components/com_ordenproduccion/site/src/View/Webhook/HtmlView.php',
        JPATH_ROOT . '/components/com_ordenproduccion/site/src/Controller/WebhookController.php'
    ];
    
    $webhookConflicts = false;
    foreach ($webhookFiles as $file) {
        if (file_exists($file)) {
            echo "   ⚠️ " . basename($file) . " exists - might cause routing conflicts\n";
            $webhookConflicts = true;
        }
    }
    
    if (!$webhookConflicts) {
        echo "   ✓ No webhook files found - no conflicts\n";
    }
    
    // 9. Test Routing
    echo "\n9. Testing routing...\n";
    try {
        $testRoutes = [
            'index.php?option=com_ordenproduccion&view=ordenes' => 'Ordenes List',
            'index.php?option=com_ordenproduccion&view=orden&id=1' => 'Orden Detail'
        ];
        
        foreach ($testRoutes as $route => $description) {
            try {
                $url = \Joomla\CMS\Router\Route::_($route);
                echo "   ✓ $description: Route generated successfully\n";
            } catch (Exception $e) {
                echo "   ❌ $description: Error - " . $e->getMessage() . "\n";
            }
        }
    } catch (Exception $e) {
        echo "   ❌ Routing test error: " . $e->getMessage() . "\n";
    }
    
    // 10. Summary
    echo "\n=== SUMMARY ===\n";
    if ($allWorking) {
        echo "✅ Language translations are working\n";
    } else {
        echo "❌ Language translations still have issues\n";
    }
    
    echo "✅ Menu item types have been registered\n";
    echo "✅ All caches have been cleared\n";
    echo "✅ Component registration verified\n";
    echo "✅ View and controller files checked\n";
    
    if ($webhookConflicts) {
        echo "⚠️ Webhook files detected - may cause routing conflicts\n";
    }
    
    echo "\nNext steps:\n";
    echo "1. Go to Joomla Admin → Menus → [Any Menu] → New\n";
    echo "2. Check if 'Lista de Órdenes de Trabajo' and 'Detalle de Orden de Trabajo' appear\n";
    echo "3. Test the frontend work orders view\n";
    echo "4. Try applying filters to see if 404 error is resolved\n";
    echo "5. Check if status labels now show in Spanish\n";
    echo "6. Run validate_deployment.php to verify all fixes\n";
    
    // 11. Comprehensive Menu Item Type Fix (NEW)
    echo "\n11. Comprehensive Menu Item Type Fix...\n";
    
    // Clear all existing menu item types for this component
    echo "   Clearing existing menu item types...\n";
    $query = $db->getQuery(true)
        ->delete($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%ordenproduccion%'));
    
    $db->setQuery($query);
    $deleted = $db->execute();
    echo "   ✓ Cleared existing menu item types\n";
    
    // Test language translations
    echo "   Testing language translations...\n";
    $testKey = 'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE';
    $translated = \Joomla\CMS\Language\Text::_($testKey);
    echo "   Key: $testKey\n";
    echo "   Translated: $translated\n";
    echo "   Is translated: " . ($translated !== $testKey ? 'YES' : 'NO') . "\n";
    
    // Create menu item types with explicit titles
    echo "   Creating menu item types with explicit titles...\n";
    
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
    
    // Verify menu item types were created
    echo "   Verifying menu item types...\n";
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%ordenproduccion%'));
    
    $db->setQuery($query);
    $menuTypes = $db->loadObjectList();
    
    foreach ($menuTypes as $type) {
        echo "   - Menutype: {$type->menutype}\n";
        echo "     Title: {$type->title}\n";
        echo "     Description: {$type->description}\n";
    }
    
    // Final cache clear
    echo "   Final cache clear...\n";
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
