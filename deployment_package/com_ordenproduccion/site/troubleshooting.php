<?php
/**
 * Debug routing for com_ordenproduccion
 * This file helps diagnose routing issues
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
$app = \Joomla\CMS\Factory::getApplication('site');

echo "<h2>🔍 Routing Debug Test</h2>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// 1. Check if component is accessible
echo "<h3>1. Component Access Test</h3>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    echo "✅ Component booted successfully<br>";
} catch (Exception $e) {
    echo "❌ Component boot failed: " . $e->getMessage() . "<br>";
}

// 2. Check MVC Factory
echo "<h3>2. MVC Factory Test</h3>";
try {
    $mvcFactory = $component->getMVCFactory();
    echo "✅ MVC Factory available<br>";
} catch (Exception $e) {
    echo "❌ MVC Factory failed: " . $e->getMessage() . "<br>";
}

// 3. Check if OrdenController exists
echo "<h3>3. Controller Test</h3>";
try {
    $controller = $mvcFactory->createController('Orden', 'Site');
    echo "✅ OrdenController created successfully<br>";
} catch (Exception $e) {
    echo "❌ OrdenController failed: " . $e->getMessage() . "<br>";
}

// 4. Check if OrdenModel exists
echo "<h3>4. Model Test</h3>";
try {
    $model = $mvcFactory->createModel('Orden', 'Site');
    echo "✅ OrdenModel created successfully<br>";
} catch (Exception $e) {
    echo "❌ OrdenModel failed: " . $e->getMessage() . "<br>";
}

// 5. Check if Orden View exists
echo "<h3>5. View Test</h3>";
try {
    $view = $mvcFactory->createView('Orden', 'Site');
    echo "✅ OrdenView created successfully<br>";
} catch (Exception $e) {
    echo "❌ OrdenView failed: " . $e->getMessage() . "<br>";
}

// 6. Test direct model access
echo "<h3>6. Direct Model Access Test</h3>";
try {
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

// 7. Check URL parameters
echo "<h3>7. URL Parameters</h3>";
$input = $app->input;
echo "View: " . $input->get('view', 'none') . "<br>";
echo "ID: " . $input->get('id', 'none') . "<br>";
echo "Task: " . $input->get('task', 'none') . "<br>";

// 8. Check component manifest
echo "<h3>8. Component Manifest Check</h3>";
$manifestPath = JPATH_ROOT . '/components/com_ordenproduccion/com_ordenproduccion.xml';
if (file_exists($manifestPath)) {
    echo "✅ Manifest file exists<br>";
    $manifest = simplexml_load_file($manifestPath);
    echo "Component name: " . $manifest->name . "<br>";
    echo "Component version: " . $manifest->version . "<br>";
} else {
    echo "❌ Manifest file not found<br>";
}

echo "<h3>Recommendations</h3>";
echo "<p>If all tests pass but routing still fails, the issue might be:</p>";
echo "<ul>";
echo "<li>Component not properly registered in Joomla</li>";
echo "<li>Menu item routing issue</li>";
echo "<li>URL rewriting issue</li>";
echo "<li>Component dispatcher not working</li>";
echo "</ul>";
?>