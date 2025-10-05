<?php
/**
 * Comprehensive Joomla Component Debug Script
 * This will help us understand the exact component loading issue
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Joomla Component Debug Analysis</h1>";

// Test 1: Basic Joomla Environment
echo "<h2>1. Joomla Environment Check</h2>";
if (defined('_JEXEC')) {
    echo "✅ Joomla _JEXEC constant defined<br>";
} else {
    echo "❌ Joomla _JEXEC constant NOT defined<br>";
}

// Test 2: Factory Class
echo "<h2>2. Factory Class Test</h2>";
try {
    $app = \Joomla\CMS\Factory::getApplication();
    echo "✅ Factory::getApplication() works<br>";
    echo "Application type: " . $app->getName() . "<br>";
} catch (Exception $e) {
    echo "❌ Factory::getApplication() failed: " . $e->getMessage() . "<br>";
}

// Test 3: Component Registration Check
echo "<h2>3. Component Registration Check</h2>";
try {
    $db = \Joomla\CMS\Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'));
    
    $db->setQuery($query);
    $extension = $db->loadObject();
    
    if ($extension) {
        echo "✅ Component registered in database<br>";
        echo "State: " . ($extension->enabled ? 'Enabled' : 'Disabled') . "<br>";
        echo "Type: " . $extension->type . "<br>";
        echo "Manifest cache: " . (strlen($extension->manifest_cache) > 0 ? 'Present' : 'Missing') . "<br>";
    } else {
        echo "❌ Component NOT registered in database<br>";
    }
} catch (Exception $e) {
    echo "❌ Database check failed: " . $e->getMessage() . "<br>";
}

// Test 4: File Structure Check
echo "<h2>4. File Structure Check</h2>";
$files = [
    'Site Entry Point' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/ordenproduccion.php',
    'Site Dispatcher' => '/var/www/grimpsa_webserver/components/com_ordenproduccion/site/src/Dispatcher/Dispatcher.php',
    'Admin Entry Point' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/ordenproduccion.php',
    'Service Provider' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/services/provider.php',
    'Component Extension' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/src/Extension/OrdenproduccionComponent.php',
    'Admin Dispatcher' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/src/Dispatcher/Dispatcher.php',
    'Manifest File' => '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/com_ordenproduccion.xml'
];

foreach ($files as $name => $file) {
    if (file_exists($file)) {
        echo "✅ $name: EXISTS<br>";
        $content = file_get_contents($file);
        echo "&nbsp;&nbsp;&nbsp;Size: " . strlen($content) . " bytes<br>";
        
        // Check for specific content
        if ($name === 'Site Entry Point' && strpos($content, 'dispatch()') !== false) {
            echo "&nbsp;&nbsp;&nbsp;✅ Uses dispatch() method<br>";
        } elseif ($name === 'Site Entry Point') {
            echo "&nbsp;&nbsp;&nbsp;❌ Does NOT use dispatch() method<br>";
        }
    } else {
        echo "❌ $name: MISSING<br>";
    }
}

// Test 5: Autoloader Check
echo "<h2>5. Autoloader Check</h2>";
$autoloaderFile = '/var/www/grimpsa_webserver/administrator/cache/autoload_psr4.php';
if (file_exists($autoloaderFile)) {
    echo "✅ Autoloader file exists<br>";
    $content = file_get_contents($autoloaderFile);
    if (strpos($content, 'Grimpsa\\\\Component\\\\Ordenproduccion') !== false) {
        echo "✅ Component namespace found in autoloader<br>";
        
        // Extract the namespace mapping
        preg_match('/\'Grimpsa\\\\Component\\\\Ordenproduccion\'\s*=>\s*\'([^\']+)\'/', $content, $matches);
        if (isset($matches[1])) {
            echo "Namespace path: " . $matches[1] . "<br>";
        }
    } else {
        echo "❌ Component namespace NOT found in autoloader<br>";
        echo "Autoloader content preview:<br>";
        echo "<pre>" . htmlspecialchars(substr($content, 0, 1000)) . "</pre>";
    }
} else {
    echo "❌ Autoloader file missing<br>";
}

// Test 6: Component Boot Test
echo "<h2>6. Component Boot Test</h2>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    echo "✅ Component booted successfully<br>";
    echo "Component class: " . get_class($component) . "<br>";
    
    // Check if component has dispatch method
    if (method_exists($component, 'dispatch')) {
        echo "✅ Component has dispatch() method<br>";
    } else {
        echo "❌ Component does NOT have dispatch() method<br>";
    }
    
    // Check available methods
    $methods = get_class_methods($component);
    echo "Available methods: " . implode(', ', $methods) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Component booting failed: " . $e->getMessage() . "<br>";
    echo "Error trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

// Test 7: MVC Factory Check
echo "<h2>7. MVC Factory Check</h2>";
try {
    $container = $app->getContainer();
    $mvcFactory = $container->get(\Joomla\CMS\MVC\Factory\MVCFactoryInterface::class);
    echo "✅ MVC Factory available<br>";
    echo "MVC Factory class: " . get_class($mvcFactory) . "<br>";
} catch (Exception $e) {
    echo "❌ MVC Factory not available: " . $e->getMessage() . "<br>";
}

// Test 8: Dispatcher Factory Check
echo "<h2>8. Dispatcher Factory Check</h2>";
try {
    $dispatcherFactory = $container->get(\Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface::class);
    echo "✅ Dispatcher Factory available<br>";
    echo "Dispatcher Factory class: " . get_class($dispatcherFactory) . "<br>";
} catch (Exception $e) {
    echo "❌ Dispatcher Factory not available: " . $e->getMessage() . "<br>";
}

// Test 9: Menu Item Types Check
echo "<h2>9. Menu Item Types Check</h2>";
try {
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu_types'))
        ->where($db->quoteName('menutype') . ' LIKE ' . $db->quote('%ordenproduccion%'));
    
    $db->setQuery($query);
    $menuTypes = $db->loadObjectList();
    
    if (count($menuTypes) > 0) {
        echo "✅ Found " . count($menuTypes) . " menu item types:<br>";
        foreach ($menuTypes as $type) {
            echo "&nbsp;&nbsp;&nbsp;- {$type->menutype}: {$type->title}<br>";
        }
    } else {
        echo "❌ No menu item types found<br>";
    }
} catch (Exception $e) {
    echo "❌ Menu item types check failed: " . $e->getMessage() . "<br>";
}

echo "<h2>🔍 Debug Complete</h2>";
echo "<p>This analysis should help identify the exact issue with the component loading.</p>";
echo "<p>Check the log files for detailed debugging information:</p>";
echo "<ul>";
echo "<li>/var/www/grimpsa_webserver/component_debug.log</li>";
echo "<li>/var/www/grimpsa_webserver/dispatcher_debug.log</li>";
echo "<li>/var/www/grimpsa_webserver/debug_component_loading.log</li>";
echo "</ul>";
?>
