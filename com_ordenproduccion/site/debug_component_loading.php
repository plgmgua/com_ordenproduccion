<?php
/**
 * Component Loading Debug Script
 * This script will help us understand exactly what's happening during component loading
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up logging
$logFile = '/var/www/grimpsa_webserver/debug_component_loading.log';
$log = function($message) use ($logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
};

$log("=== COMPONENT LOADING DEBUG START ===");

// Test 1: Check if Joomla is loaded
$log("Test 1: Checking Joomla environment");
if (defined('_JEXEC')) {
    $log("✓ Joomla _JEXEC constant defined");
} else {
    $log("❌ Joomla _JEXEC constant NOT defined");
}

// Test 2: Check Factory class
$log("Test 2: Checking Joomla Factory class");
try {
    $app = \Joomla\CMS\Factory::getApplication();
    $log("✓ Factory::getApplication() works");
    $log("Application type: " . $app->getName());
} catch (Exception $e) {
    $log("❌ Factory::getApplication() failed: " . $e->getMessage());
}

// Test 3: Check component booting
$log("Test 3: Testing component booting");
try {
    $component = $app->bootComponent('com_ordenproduccion');
    $log("✓ Component booted successfully");
    $log("Component class: " . get_class($component));
} catch (Exception $e) {
    $log("❌ Component booting failed: " . $e->getMessage());
    $log("Error trace: " . $e->getTraceAsString());
}

// Test 4: Check dispatcher
$log("Test 4: Testing dispatcher");
try {
    if (isset($component)) {
        $log("Attempting to call dispatch() method");
        $component->dispatch();
        $log("✓ dispatch() method executed successfully");
    } else {
        $log("❌ Cannot test dispatcher - component not booted");
    }
} catch (Exception $e) {
    $log("❌ dispatch() method failed: " . $e->getMessage());
    $log("Error trace: " . $e->getTraceAsString());
}

// Test 5: Check file structure
$log("Test 5: Checking file structure");
$files = [
    '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/ordenproduccion.php',
    '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/src/Dispatcher/Dispatcher.php',
    '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/services/provider.php',
    '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/src/Extension/OrdenproduccionComponent.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $log("✓ File exists: $file");
        $content = file_get_contents($file);
        $log("File size: " . strlen($content) . " bytes");
    } else {
        $log("❌ File missing: $file");
    }
}

// Test 6: Check autoloader
$log("Test 6: Checking autoloader");
$autoloaderFile = '/var/www/grimpsa_webserver/administrator/cache/autoload_psr4.php';
if (file_exists($autoloaderFile)) {
    $log("✓ Autoloader file exists");
    $content = file_get_contents($autoloaderFile);
    if (strpos($content, 'Grimpsa\\Component\\Ordenproduccion') !== false) {
        $log("✓ Component namespace found in autoloader");
    } else {
        $log("❌ Component namespace NOT found in autoloader");
    }
} else {
    $log("❌ Autoloader file missing");
}

// Test 7: Check component registration
$log("Test 7: Checking component registration in database");
try {
    $db = \Joomla\CMS\Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'));
    
    $db->setQuery($query);
    $extension = $db->loadObject();
    
    if ($extension) {
        $log("✓ Component registered in database");
        $log("Component state: " . $extension->enabled);
        $log("Component type: " . $extension->type);
    } else {
        $log("❌ Component NOT registered in database");
    }
} catch (Exception $e) {
    $log("❌ Database check failed: " . $e->getMessage());
}

$log("=== COMPONENT LOADING DEBUG END ===");
$log("Debug log saved to: $logFile");

echo "\n=== DEBUG COMPLETE ===\n";
echo "Check the log file: $logFile\n";
echo "This will help us identify exactly where the component loading is failing.\n";
?>
