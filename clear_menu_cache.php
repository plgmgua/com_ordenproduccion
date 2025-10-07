<?php
/**
 * Clear Menu Language Cache Script
 * This script forces Joomla to reload language files for proper menu display
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Clear Menu Language Cache</h2>\n";

// Define Joomla root path
$joomla_root = '/var/www/grimpsa_webserver';

// Check if Joomla exists
if (!file_exists($joomla_root . '/configuration.php')) {
    echo "<p style='color: red;'>❌ Joomla not found at: $joomla_root</p>\n";
    exit;
}

// Bootstrap Joomla
define('_JEXEC', 1);
define('JPATH_BASE', $joomla_root);
define('JPATH_ROOT', JPATH_BASE);
define('JPATH_SITE', JPATH_BASE);
define('JPATH_CONFIGURATION', JPATH_BASE);
define('JPATH_ADMINISTRATOR', JPATH_BASE . '/administrator');
define('JPATH_LIBRARIES', JPATH_BASE . '/libraries');
define('JPATH_PLUGINS', JPATH_BASE . '/plugins');
define('JPATH_INSTALLATION', JPATH_BASE . '/installation');
define('JPATH_THEMES', JPATH_BASE . '/templates');
define('JPATH_CACHE', JPATH_BASE . '/cache');
define('JPATH_MANIFESTS', JPATH_ADMINISTRATOR . '/manifests');

// Load Joomla framework
require_once JPATH_LIBRARIES . '/bootstrap.php';

try {
    // Initialize Joomla
    $app = \Joomla\CMS\Factory::getApplication('administrator');
    
    echo "<p style='color: green;'>✅ Joomla loaded successfully</p>\n";
    
    // Clear language cache
    echo "<h3>Clearing Language Cache...</h3>\n";
    
    // Method 1: Clear language cache directory
    $cache_path = JPATH_CACHE . '/com_ordenproduccion';
    if (is_dir($cache_path)) {
        $files = glob($cache_path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                echo "<p>🗑️ Deleted: " . basename($file) . "</p>\n";
            }
        }
        rmdir($cache_path);
        echo "<p style='color: green;'>✅ Component cache directory cleared</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ No component cache directory found</p>\n";
    }
    
    // Method 2: Clear general cache
    $general_cache_path = JPATH_CACHE . '/language';
    if (is_dir($general_cache_path)) {
        $files = glob($general_cache_path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "<p style='color: green;'>✅ General language cache cleared</p>\n";
    }
    
    // Method 3: Clear Joomla cache tables
    $db = \Joomla\CMS\Factory::getDbo();
    
    // Clear cache table if it exists
    $tables = $db->getTableList();
    if (in_array('joomla_cache', $tables)) {
        $query = "DELETE FROM `joomla_cache` WHERE `cache_group` LIKE '%language%' OR `cache_group` LIKE '%com_ordenproduccion%'";
        $db->setQuery($query);
        $result = $db->execute();
        echo "<p style='color: green;'>✅ Database cache cleared</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ Cache table not found</p>\n";
    }
    
    // Method 4: Force language reload
    $lang = \Joomla\CMS\Factory::getLanguage();
    $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', 'en-GB', true);
    $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', 'es-ES', true);
    $lang->load('com_ordenproduccion.sys', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', 'en-GB', true);
    $lang->load('com_ordenproduccion.sys', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion', 'es-ES', true);
    
    echo "<p style='color: green;'>✅ Language files reloaded</p>\n";
    
    // Method 5: Clear menu cache specifically
    $menu_cache_path = JPATH_CACHE . '/menu';
    if (is_dir($menu_cache_path)) {
        $files = glob($menu_cache_path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        echo "<p style='color: green;'>✅ Menu cache cleared</p>\n";
    }
    
    // Test language constants
    echo "<h3>Testing Language Constants...</h3>\n";
    
    $constants = [
        'COM_ORDENPRODUCCION',
        'COM_ORDENPRODUCCION_MENU_DASHBOARD',
        'COM_ORDENPRODUCCION_MENU_ORDERS',
        'COM_ORDENPRODUCCION_MENU_TECHNICIANS',
        'COM_ORDENPRODUCCION_MENU_WEBHOOK',
        'COM_ORDENPRODUCCION_MENU_DEBUG',
        'COM_ORDENPRODUCCION_MENU_SETTINGS'
    ];
    
    foreach ($constants as $constant) {
        $translated = \Joomla\CMS\Language\Text::_($constant);
        if ($translated !== $constant) {
            echo "<p style='color: green;'>✅ $constant = '$translated'</p>\n";
        } else {
            echo "<p style='color: red;'>❌ $constant = '$translated' (not translated)</p>\n";
        }
    }
    
    echo "<h3>Cache Clearing Complete!</h3>\n";
    echo "<p style='color: green; font-weight: bold;'>✅ All caches cleared successfully</p>\n";
    echo "<p><strong>Next steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Refresh your Joomla admin panel (F5)</li>\n";
    echo "<li>Menu labels should now display correctly</li>\n";
    echo "<li>If still showing constants, try logging out and back in</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Stack trace:</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
