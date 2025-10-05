<?php
/**
 * Clear Joomla Cache Script
 * Run this to clear Joomla cache and force reload of language strings
 */

// Bootstrap Joomla
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Cache\Cache;

try {
    $app = Factory::getApplication('site');
    
    echo "Clearing Joomla cache...\n";
    
    // Clear all cache groups
    $cache = Factory::getCache();
    $cache->clean();
    
    // Clear specific cache groups
    $cacheGroups = ['_system', 'com_modules', 'com_plugins', 'com_languages'];
    foreach ($cacheGroups as $group) {
        $cache->clean($group);
        echo "✓ Cleared cache group: $group\n";
    }
    
    // Clear language cache specifically
    $languageCache = Cache::getInstance('language', array('defaultgroup' => 'language'));
    $languageCache->clean();
    echo "✓ Cleared language cache\n";
    
    // Clear component cache
    $componentCache = Cache::getInstance('component', array('defaultgroup' => 'com_ordenproduccion'));
    $componentCache->clean();
    echo "✓ Cleared component cache\n";
    
    echo "\nCache cleared successfully!\n";
    echo "Menu item types should now show proper Spanish titles.\n";
    echo "Check: Admin → Menus → [Any Menu] → New → Menu Item Type\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
