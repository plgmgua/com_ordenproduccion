<?php
/**
 * Troubleshooting file for com_ordenproduccion
 * This file helps diagnose component and routing issues
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
$app = \Joomla\CMS\Factory::getApplication('site');

echo "<h2>🔍 Component Registration & Routing Test</h2>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// 1. Check if component is registered in Joomla
echo "<h3>1. Component Registration Check</h3>";
try {
    $db = \Joomla\CMS\Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__extensions')
        ->where('type = ' . $db->quote('component'))
        ->where('element = ' . $db->quote('com_ordenproduccion'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "✅ Component found in database<br>";
        echo "- Name: " . $component->name . "<br>";
        echo "- Enabled: " . ($component->enabled ? 'Yes' : 'No') . "<br>";
        echo "- State: " . $component->state . "<br>";
        echo "- Manifest: " . $component->manifest_cache . "<br>";
    } else {
        echo "❌ Component not found in database - may need to be installed<br>";
    }
} catch (Exception $e) {
    echo "❌ Database query failed: " . $e->getMessage() . "<br>";
}

// 2. Check component boot
echo "<h3>2. Component Boot Test</h3>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    echo "✅ Component booted successfully<br>";
} catch (Exception $e) {
    echo "❌ Component boot failed: " . $e->getMessage() . "<br>";
}

// 3. Check MVC Factory
echo "<h3>3. MVC Factory Test</h3>";
try {
    $mvcFactory = $component->getMVCFactory();
    echo "✅ MVC Factory available<br>";
} catch (Exception $e) {
    echo "❌ MVC Factory failed: " . $e->getMessage() . "<br>";
}

// 4. Test URL routing
echo "<h3>4. URL Routing Test</h3>";
echo "Current URL: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "Try these URLs:<br>";
echo "<ul>";
echo "<li><a href='/index.php?option=com_ordenproduccion&view=orden&id=15'>/index.php?option=com_ordenproduccion&view=orden&id=15</a></li>";
echo "<li><a href='/index.php/component/com_ordenproduccion/?view=orden&id=15'>/index.php/component/com_ordenproduccion/?view=orden&id=15</a></li>";
echo "<li><a href='/component/com_ordenproduccion/?view=orden&id=15'>/component/com_ordenproduccion/?view=orden&id=15</a></li>";
echo "</ul>";

// 5. Check component files
echo "<h3>5. Component Files Check</h3>";
$componentPath = JPATH_ROOT . '/components/com_ordenproduccion';
if (is_dir($componentPath)) {
    echo "✅ Component directory exists: " . $componentPath . "<br>";
    
    // Check site entry point
    $siteEntryPoint = $componentPath . '/site/ordenproduccion.php';
    if (file_exists($siteEntryPoint)) {
        echo "✅ Site entry point exists<br>";
    } else {
        echo "❌ Site entry point not found<br>";
    }
    
    // Check manifest
    $manifestPath = $componentPath . '/com_ordenproduccion.xml';
    if (file_exists($manifestPath)) {
        echo "✅ Manifest file exists<br>";
    } else {
        echo "❌ Manifest file not found<br>";
    }
} else {
    echo "❌ Component directory not found<br>";
}

// 6. Test direct model access
echo "<h3>6. Direct Model Access Test</h3>";
try {
    $mvcFactory = $component->getMVCFactory();
    $model = $mvcFactory->createModel('Orden', 'Site');
    $item = $model->getItem(15);
    if ($item) {
        echo "✅ Work order retrieved: " . $item->order_number . "<br>";
    } else {
        echo "❌ Work order not found<br>";
    }
} catch (Exception $e) {
    echo "❌ Direct model access failed: " . $e->getMessage() . "<br>";
}

// 7. Check menu items
echo "<h3>7. Menu Items Check</h3>";
try {
    $db = \Joomla\CMS\Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__menu')
        ->where('link LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $menuItems = $db->loadObjectList();
    
    if (empty($menuItems)) {
        echo "❌ No menu items found for component<br>";
    } else {
        echo "✅ Found " . count($menuItems) . " menu items:<br>";
        foreach ($menuItems as $menu) {
            echo "- " . $menu->title . " (" . $menu->link . ") - Published: " . ($menu->published ? 'Yes' : 'No') . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Menu query failed: " . $e->getMessage() . "<br>";
}

echo "<h3>Recommendations</h3>";
echo "<p>If component is registered but routing fails:</p>";
echo "<ul>";
echo "<li>Check if component is enabled in Joomla admin</li>";
echo "<li>Verify menu items are published</li>";
echo "<li>Check URL rewriting settings</li>";
echo "<li>Try different URL patterns</li>";
echo "</ul>";
?>