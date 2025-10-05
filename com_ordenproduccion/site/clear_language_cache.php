<?php
/**
 * Aggressive Language Cache Clearing Script
 * Specifically designed to fix language translation issues
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
    
    echo "=== AGGRESSIVE LANGUAGE CACHE CLEARING ===\n";
    echo "This script will clear all caches that might affect language translations.\n\n";
    
    // 1. Clear all Joomla cache groups
    echo "1. Clearing all Joomla cache groups...\n";
    $cache = Factory::getCache();
    $cache->clean();
    echo "   ✓ Main cache cleared\n";
    
    // 2. Clear specific cache groups that affect language
    $cacheGroups = [
        '_system' => 'System cache',
        'com_modules' => 'Modules cache', 
        'com_plugins' => 'Plugins cache',
        'com_languages' => 'Languages cache',
        'language' => 'Language cache',
        'component' => 'Component cache'
    ];
    
    foreach ($cacheGroups as $group => $description) {
        try {
            $cache->clean($group);
            echo "   ✓ $description cleared\n";
        } catch (Exception $e) {
            echo "   ⚠ $description: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. Clear language-specific cache
    echo "\n2. Clearing language-specific cache...\n";
    try {
        $languageCache = Cache::getInstance('language', array('defaultgroup' => 'language'));
        $languageCache->clean();
        echo "   ✓ Language cache cleared\n";
    } catch (Exception $e) {
        echo "   ⚠ Language cache: " . $e->getMessage() . "\n";
    }
    
    // 4. Clear component cache
    echo "\n3. Clearing component cache...\n";
    try {
        $componentCache = Cache::getInstance('component', array('defaultgroup' => 'com_ordenproduccion'));
        $componentCache->clean();
        echo "   ✓ Component cache cleared\n";
    } catch (Exception $e) {
        echo "   ⚠ Component cache: " . $e->getMessage() . "\n";
    }
    
    // 5. Force reload language files
    echo "\n4. Force reloading language files...\n";
    $lang = Factory::getLanguage();
    $currentLang = $lang->getTag();
    echo "   Current language: $currentLang\n";
    
    // Clear language object cache
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/components/com_ordenproduccion/site', null, true, true);
    echo "   ✓ Site language files reloaded\n";
    
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/administrator/components/com_ordenproduccion', null, true, true);
    echo "   ✓ Admin language files reloaded\n";
    
    // 6. Clear file system cache directories
    echo "\n5. Clearing file system cache directories...\n";
    $cacheDirs = [
        JPATH_ROOT . '/cache',
        JPATH_ROOT . '/tmp',
        JPATH_ROOT . '/administrator/cache'
    ];
    
    foreach ($cacheDirs as $dir) {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            $count = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $count++;
                }
            }
            echo "   ✓ Cleared $count files from " . basename($dir) . "\n";
        }
    }
    
    // 7. Test language translations
    echo "\n6. Testing language translations...\n";
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
    
    echo "\n=== CACHE CLEARING COMPLETE ===\n";
    if ($allWorking) {
        echo "✅ SUCCESS: All language translations are now working!\n";
        echo "The status labels should now display correctly in the frontend.\n";
    } else {
        echo "❌ WARNING: Some translations are still not working.\n";
        echo "You may need to:\n";
        echo "1. Check if language files are properly deployed\n";
        echo "2. Verify language file permissions\n";
        echo "3. Restart web server if needed\n";
    }
    
    echo "\nNext steps:\n";
    echo "1. Test the frontend work orders view\n";
    echo "2. Check if status labels now show in Spanish\n";
    echo "3. Run validate_deployment.php to verify\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
