<?php
/**
 * Debug Menu Item Types
 * This script will help us understand why the menu item type is showing "Metadata"
 */

// Bootstrap Joomla
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    echo "=== DEBUGGING MENU ITEM TYPES ===\n\n";
    
    // 1. Check what's in the menu_types table
    echo "1. Checking menu_types table...\n";
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%ordenproduccion%'));
    
    $db->setQuery($query);
    $menuTypes = $db->loadObjectList();
    
    if (!empty($menuTypes)) {
        foreach ($menuTypes as $type) {
            echo "   - Menutype: {$type->menutype}\n";
            echo "     Title: {$type->title}\n";
            echo "     Description: {$type->description}\n";
            echo "     Client ID: {$type->client_id}\n\n";
        }
    } else {
        echo "   No menu item types found for ordenproduccion\n\n";
    }
    
    // 2. Check language files
    echo "2. Checking language files...\n";
    $lang = Factory::getLanguage();
    $currentLang = $lang->getTag();
    echo "   Current language: $currentLang\n";
    
    $langFile = JPATH_ROOT . '/components/com_ordenproduccion/site/language/' . $currentLang . '/com_ordenproduccion.sys.ini';
    if (file_exists($langFile)) {
        echo "   ✓ Language file exists: $langFile\n";
        $content = file_get_contents($langFile);
        if (strpos($content, 'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE') !== false) {
            echo "   ✓ Language key found in file\n";
        } else {
            echo "   ❌ Language key NOT found in file\n";
        }
    } else {
        echo "   ❌ Language file missing: $langFile\n";
    }
    
    // 3. Test language translation
    echo "\n3. Testing language translation...\n";
    $testKey = 'COM_ORDENPRODUCCION_ORDENES_VIEW_DEFAULT_TITLE';
    $translated = \Joomla\CMS\Language\Text::_($testKey);
    echo "   Key: $testKey\n";
    echo "   Translated: $translated\n";
    echo "   Is translated: " . ($translated !== $testKey ? 'YES' : 'NO') . "\n";
    
    // 4. Check metadata.xml files
    echo "\n4. Checking metadata.xml files...\n";
    $metadataFiles = [
        JPATH_ROOT . '/components/com_ordenproduccion/site/views/ordenes/metadata.xml',
        JPATH_ROOT . '/components/com_ordenproduccion/site/views/orden/metadata.xml'
    ];
    
    foreach ($metadataFiles as $file) {
        if (file_exists($file)) {
            echo "   ✓ " . basename(dirname($file)) . "/metadata.xml exists\n";
            $content = file_get_contents($file);
            echo "   Content preview:\n";
            echo "   " . str_replace("\n", "\n   ", $content) . "\n\n";
        } else {
            echo "   ❌ " . basename(dirname($file)) . "/metadata.xml missing\n";
        }
    }
    
    // 5. Check if views folder is properly declared
    echo "5. Checking component manifest...\n";
    $manifestFile = JPATH_ROOT . '/components/com_ordenproduccion/com_ordenproduccion.xml';
    if (file_exists($manifestFile)) {
        $manifest = file_get_contents($manifestFile);
        if (strpos($manifest, '<folder>views</folder>') !== false) {
            echo "   ✓ Views folder declared in manifest\n";
        } else {
            echo "   ❌ Views folder NOT declared in manifest\n";
            echo "   This might be the issue!\n";
        }
    }
    
    // 6. Check component discovery
    echo "\n6. Testing component discovery...\n";
    try {
        $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
        echo "   ✓ Component can be booted\n";
        
        // Try to get the MVC factory
        $mvcFactory = $component->getMVCFactory();
        echo "   ✓ MVC Factory available\n";
        
        // Try to get the view
        try {
            $view = $mvcFactory->createView('Ordenes', 'Site', ['name' => 'Ordenes']);
            echo "   ✓ Ordenes view can be created\n";
        } catch (Exception $e) {
            echo "   ❌ Ordenes view creation failed: " . $e->getMessage() . "\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Component boot failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== DEBUG COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
