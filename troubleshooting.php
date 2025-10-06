<?php
/**
 * Component Troubleshooting and Validation Script
 * 
 * This script provides comprehensive troubleshooting and validation
 * for the com_ordenproduccion component.
 * 
 * Usage: Access via web browser or Sourcerer plugin
 * URL: https://your-domain.com/troubleshooting.php
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
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
    
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>Component Troubleshooting</title></head><body>\n";
    echo "<h1>🔧 Component Troubleshooting & Validation</h1>\n";
    echo "<h2>Component Version: <span style='color: blue;'>$componentVersion</span></h2>\n";
    echo "<hr>\n";
    
    // 1. Component Installation Check
    echo "<h3>📋 1. Component Installation Status</h3>\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "<p>✅ <strong>Component found in database</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Name: {$component->name}</li>\n";
        echo "<li>Enabled: " . ($component->enabled ? 'Yes' : 'No') . "</li>\n";
        echo "<li>Version: {$component->version}</li>\n";
        echo "<li>Manifest Cache: " . (strlen($component->manifest_cache) > 0 ? 'Present' : 'Missing') . "</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p>❌ <strong>Component not found in database</strong></p>\n";
    }
    
    // 2. File Structure Validation
    echo "<h3>📁 2. File Structure Validation</h3>\n";
    
    $criticalFiles = [
        'Admin Entry Point' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/ordenproduccion.php',
        'Site Entry Point' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/ordenproduccion.php',
        'Admin Manifest' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/com_ordenproduccion.xml',
        'Site Manifest' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/com_ordenproduccion.xml',
        'Service Provider' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/services/provider.php',
        'Admin Dispatcher' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php',
        'Site Dispatcher' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php'
    ];
    
    foreach ($criticalFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            $modified = date('Y-m-d H:i:s', filemtime($file));
            echo "<p>✅ <strong>$name</strong>: $file ($size bytes, modified: $modified)</p>\n";
        } else {
            echo "<p>❌ <strong>$name</strong>: $file (missing)</p>\n";
        }
    }
    
    // 3. Database Tables Check
    echo "<h3>🗄️ 3. Database Tables Check</h3>\n";
    
    $tables = [
        'ordenes' => 'joomla_ordenproduccion_ordenes',
        'settings' => 'joomla_ordenproduccion_settings'
    ];
    
    foreach ($tables as $name => $table) {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($table));
        
        try {
            $db->setQuery($query);
            $count = $db->loadResult();
            echo "<p>✅ <strong>$name table</strong>: $table ($count records)</p>\n";
        } catch (Exception $e) {
            echo "<p>❌ <strong>$name table</strong>: $table (error: " . $e->getMessage() . ")</p>\n";
        }
    }
    
    // 4. Menu Item Types Check
    echo "<h3>🔗 4. Menu Item Types Check</h3>\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $menuTypes = $db->loadObjectList();
    
    if (count($menuTypes) > 0) {
        echo "<p>✅ <strong>Menu item types found:</strong></p>\n";
        echo "<ul>\n";
        foreach ($menuTypes as $menuType) {
            echo "<li>{$menuType->menutype}: {$menuType->title}</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p>❌ <strong>No menu item types found</strong></p>\n";
    }
    
    // 5. Language Files Check
    echo "<h3>🌐 5. Language Files Check</h3>\n";
    
    $languageFiles = [
        'Site EN' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/en-GB/com_ordenproduccion.ini',
        'Site ES' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/language/es-ES/com_ordenproduccion.ini',
        'Admin EN' => '/var/www/grimpsa_webserver/administrator/language/en-GB/com_ordenproduccion.ini',
        'Admin ES' => '/var/www/grimpsa_webserver/administrator/language/es-ES/com_ordenproduccion.ini'
    ];
    
    foreach ($languageFiles as $name => $file) {
        if (file_exists($file)) {
            $size = filesize($file);
            echo "<p>✅ <strong>$name</strong>: $file ($size bytes)</p>\n";
        } else {
            echo "<p>❌ <strong>$name</strong>: $file (missing)</p>\n";
        }
    }
    
    // 6. Webhook Endpoints Check
    echo "<h3>🔗 6. Webhook Endpoints Check</h3>\n";
    
    $webhookEndpoints = [
        'Test Webhook' => 'https://grimpsa_webserver.grantsolutions.cc/components/com_ordenproduccion/site/webhook/test',
        'Production Webhook' => 'https://grimpsa_webserver.grantsolutions.cc/components/com_ordenproduccion/site/webhook/production'
    ];
    
    foreach ($webhookEndpoints as $name => $url) {
        echo "<p>🔗 <strong>$name</strong>: <a href='$url' target='_blank'>$url</a></p>\n";
    }
    
    // 7. Cache Status
    echo "<h3>🗑️ 7. Cache Status</h3>\n";
    
    $cacheDir = '/var/www/grimpsa_webserver/cache';
    $adminCacheDir = '/var/www/grimpsa_webserver/administrator/cache';
    
    if (is_dir($cacheDir)) {
        $cacheFiles = count(glob($cacheDir . '/*'));
        echo "<p>📁 <strong>Site Cache</strong>: $cacheDir ($cacheFiles files)</p>\n";
    }
    
    if (is_dir($adminCacheDir)) {
        $adminCacheFiles = count(glob($adminCacheDir . '/*'));
        echo "<p>📁 <strong>Admin Cache</strong>: $adminCacheDir ($adminCacheFiles files)</p>\n";
    }
    
    // 8. System Information
    echo "<h3>ℹ️ 8. System Information</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>PHP Version</strong>: " . phpversion() . "</li>\n";
    echo "<strong>Joomla Version</strong>: " . JVERSION . "</li>\n";
    echo "<li><strong>Server Time</strong>: " . date('Y-m-d H:i:s') . "</li>\n";
    echo "<li><strong>Document Root</strong>: " . $_SERVER['DOCUMENT_ROOT'] . "</li>\n";
    echo "</ul>\n";
    
    echo "<hr>\n";
    echo "<h3>💡 Troubleshooting Tips</h3>\n";
    echo "<ul>\n";
    echo "<li>If menu item types show 'Metadata', run the fix script</li>\n";
    echo "<li>If language strings don't appear, clear cache and check language files</li>\n";
    echo "<li>If webhook fails, check database connection and table structure</li>\n";
    echo "<li>If component doesn't load, check file permissions and dispatcher registration</li>\n";
    echo "</ul>\n";
    
    echo "<p><strong>Component Version: $componentVersion</strong></p>\n";
    echo "<p><em>Generated: " . date('Y-m-d H:i:s') . "</em></p>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Stack trace:</strong></p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "</body></html>\n";
?>
