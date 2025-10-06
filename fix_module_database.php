<?php
/**
 * Fix Module Database Issues
 * Cleans up and fixes module database entries
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

echo "=== FIXING MODULE DATABASE ISSUES ===\n\n";

// Step 1: Check for duplicate entries
echo "1. Checking for duplicate module entries...\n";
$query = $db->getQuery(true)
    ->select('element, COUNT(*) as count')
    ->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
    ->where($db->quoteName('type') . ' = ' . $db->quote('module'))
    ->group('element');

$db->setQuery($query);
$duplicates = $db->loadObject();

if ($duplicates && $duplicates->count > 1) {
    echo "❌ Found {$duplicates->count} duplicate entries\n";
    
    // Keep only the first entry, delete the rest
    $query = $db->getQuery(true)
        ->select('extension_id')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('module'))
        ->order('extension_id ASC');

    $db->setQuery($query);
    $ids = $db->loadColumn();
    
    if (count($ids) > 1) {
        $keepId = array_shift($ids); // Keep the first one
        echo "🔧 Keeping extension ID: $keepId\n";
        echo "🔧 Removing duplicate IDs: " . implode(', ', $ids) . "\n";
        
        foreach ($ids as $id) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__extensions'))
                ->where($db->quoteName('extension_id') . ' = ' . (int)$id);
            $db->setQuery($query);
            $db->execute();
        }
        echo "✅ Duplicate entries removed\n";
    }
} else {
    echo "✅ No duplicate entries found\n";
}

// Step 2: Check and fix manifest cache
echo "\n2. Checking manifest cache...\n";
$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
    ->where($db->quoteName('type') . ' = ' . $db->quote('module'));

$db->setQuery($query);
$extension = $db->loadObject();

if ($extension) {
    if (empty($extension->manifest_cache)) {
        echo "❌ Manifest cache is empty\n";
        echo "🔧 Attempting to rebuild manifest cache...\n";
        
        // Try to read the XML file and rebuild manifest cache
        $xmlFile = '/var/www/grimpsa_webserver/modules/mod_acciones_produccion/mod_acciones_produccion.xml';
        if (file_exists($xmlFile)) {
            $xml = simplexml_load_file($xmlFile);
            if ($xml) {
                $manifest = [
                    'name' => (string)$xml->name,
                    'version' => (string)$xml->version,
                    'description' => (string)$xml->description,
                    'author' => (string)$xml->author,
                    'creationDate' => (string)$xml->creationDate,
                    'copyright' => (string)$xml->copyright,
                    'license' => (string)$xml->license,
                    'authorEmail' => (string)$xml->authorEmail,
                    'authorUrl' => (string)$xml->authorUrl
                ];
                
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('manifest_cache') . ' = ' . $db->quote(json_encode($manifest)))
                    ->where($db->quoteName('extension_id') . ' = ' . (int)$extension->extension_id);
                
                $db->setQuery($query);
                if ($db->execute()) {
                    echo "✅ Manifest cache rebuilt successfully\n";
                } else {
                    echo "❌ Failed to rebuild manifest cache\n";
                }
            } else {
                echo "❌ Cannot parse XML file\n";
            }
        } else {
            echo "❌ XML file not found\n";
        }
    } else {
        echo "✅ Manifest cache exists\n";
    }
} else {
    echo "❌ Module not found in extensions table\n";
}

// Step 3: Check module instances
echo "\n3. Checking module instances...\n";
$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__modules'))
    ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));

$db->setQuery($query);
$modules = $db->loadObjectList();

if ($modules) {
    echo "✅ Found " . count($modules) . " module instance(s)\n";
    foreach ($modules as $module) {
        echo "   - ID: {$module->id}, Title: {$module->title}, Published: {$module->published}\n";
    }
} else {
    echo "❌ No module instances found\n";
    echo "🔧 Creating default module instance...\n";
    
    // Create a default module instance
    $query = $db->getQuery(true)
        ->insert($db->quoteName('#__modules'))
        ->set($db->quoteName('title') . ' = ' . $db->quote('Acciones Produccion'))
        ->set($db->quoteName('note') . ' = ' . $db->quote(''))
        ->set($db->quoteName('content') . ' = ' . $db->quote(''))
        ->set($db->quoteName('showtitle') . ' = 1')
        ->set($db->quoteName('control') . ' = ' . $db->quote(''))
        ->set($db->quoteName('params') . ' = ' . $db->quote('{}'))
        ->set($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'))
        ->set($db->quoteName('access') . ' = 1')
        ->set($db->quoteName('showtitle') . ' = 1')
        ->set($db->quoteName('client_id') . ' = 0')
        ->set($db->quoteName('language') . ' = ' . $db->quote('*'))
        ->set($db->quoteName('publish_up') . ' = ' . $db->quote('0000-00-00 00:00:00'))
        ->set($db->quoteName('publish_down') . ' = ' . $db->quote('0000-00-00 00:00:00'))
        ->set($db->quoteName('published') . ' = 1')
        ->set($db->quoteName('ordering') . ' = 0');
    
    $db->setQuery($query);
    if ($db->execute()) {
        $moduleId = $db->insertid();
        echo "✅ Created module instance with ID: $moduleId\n";
    } else {
        echo "❌ Failed to create module instance\n";
    }
}

// Step 4: Clear Joomla cache
echo "\n4. Clearing Joomla cache...\n";
try {
    $cache = Factory::getCache();
    $cache->clean('_system');
    $cache->clean('com_modules');
    echo "✅ Cache cleared successfully\n";
} catch (Exception $e) {
    echo "❌ Failed to clear cache: " . $e->getMessage() . "\n";
}

echo "\n=== FIX COMPLETE ===\n";
echo "Please check the module in Joomla admin now.\n";
?>
