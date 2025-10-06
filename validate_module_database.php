<?php
/**
 * Module Database Validation Script
 * Checks and fixes module database entries
 */

// Joomla bootstrap
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;

$app = Factory::getApplication('administrator');
$db = Factory::getDbo();

echo "=== MODULE DATABASE VALIDATION ===\n\n";

// Check if module exists in joomla_extensions table
echo "1. Checking joomla_extensions table...\n";
$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
    ->where($db->quoteName('type') . ' = ' . $db->quote('module'));

$db->setQuery($query);
$extension = $db->loadObject();

if ($extension) {
    echo "✅ Module found in extensions table:\n";
    echo "   - ID: {$extension->extension_id}\n";
    echo "   - Name: {$extension->name}\n";
    echo "   - Enabled: {$extension->enabled}\n";
    echo "   - Manifest Cache: " . (empty($extension->manifest_cache) ? 'EMPTY' : 'EXISTS') . "\n";
    echo "   - Params: " . (empty($extension->params) ? 'EMPTY' : 'EXISTS') . "\n";
} else {
    echo "❌ Module NOT found in extensions table\n";
}

// Check if module exists in joomla_modules table
echo "\n2. Checking joomla_modules table...\n";
$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__modules'))
    ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));

$db->setQuery($query);
$modules = $db->loadObjectList();

if ($modules) {
    echo "✅ Found " . count($modules) . " module instance(s):\n";
    foreach ($modules as $module) {
        echo "   - ID: {$module->id}\n";
        echo "   - Title: {$module->title}\n";
        echo "   - Position: {$module->position}\n";
        echo "   - Published: {$module->published}\n";
        echo "   - Client ID: {$module->client_id}\n";
    }
} else {
    echo "❌ No module instances found in modules table\n";
}

// Check if module files exist
echo "\n3. Checking module files...\n";
$modulePath = '/var/www/grimpsa_webserver/modules/mod_acciones_produccion';
$xmlFile = $modulePath . '/mod_acciones_produccion.xml';
$phpFile = $modulePath . '/mod_acciones_produccion.php';

if (file_exists($xmlFile)) {
    echo "✅ XML file exists: $xmlFile\n";
    echo "   Size: " . filesize($xmlFile) . " bytes\n";
    echo "   Modified: " . date('Y-m-d H:i:s', filemtime($xmlFile)) . "\n";
} else {
    echo "❌ XML file NOT found: $xmlFile\n";
}

if (file_exists($phpFile)) {
    echo "✅ PHP file exists: $phpFile\n";
    echo "   Size: " . filesize($phpFile) . " bytes\n";
    echo "   Modified: " . date('Y-m-d H:i:s', filemtime($phpFile)) . "\n";
} else {
    echo "❌ PHP file NOT found: $phpFile\n";
}

// Check module directory
if (is_dir($modulePath)) {
    echo "✅ Module directory exists: $modulePath\n";
    echo "   Contents:\n";
    $files = scandir($modulePath);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $modulePath . '/' . $file;
            $type = is_dir($filePath) ? 'DIR' : 'FILE';
            $size = is_file($filePath) ? filesize($filePath) : 'N/A';
            echo "     - $file ($type, $size bytes)\n";
        }
    }
} else {
    echo "❌ Module directory NOT found: $modulePath\n";
}

// Try to fix the module if there are issues
echo "\n4. Attempting to fix module registration...\n";

if (!$extension || empty($extension->manifest_cache)) {
    echo "🔧 Fixing module registration...\n";
    
    // Try to reinstall the module
    $installer = new Installer();
    $result = $installer->install($modulePath);
    
    if ($result) {
        echo "✅ Module reinstallation successful\n";
    } else {
        echo "❌ Module reinstallation failed\n";
        echo "   Error: " . $installer->getError() . "\n";
    }
} else {
    echo "✅ Module registration appears to be correct\n";
}

// Check manifest cache content
echo "\n5. Checking manifest cache...\n";
if ($extension && !empty($extension->manifest_cache)) {
    $manifest = json_decode($extension->manifest_cache, true);
    if ($manifest) {
        echo "✅ Manifest cache is valid JSON\n";
        echo "   - Name: " . ($manifest['name'] ?? 'N/A') . "\n";
        echo "   - Version: " . ($manifest['version'] ?? 'N/A') . "\n";
        echo "   - Description: " . ($manifest['description'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Manifest cache is invalid JSON\n";
    }
} else {
    echo "❌ No manifest cache found\n";
}

echo "\n=== VALIDATION COMPLETE ===\n";
echo "If issues persist, try:\n";
echo "1. Clear Joomla cache\n";
echo "2. Reinstall the module\n";
echo "3. Check file permissions\n";
echo "4. Verify XML file syntax\n";
?>