<?php
/**
 * Comprehensive Troubleshooting for com_ordenproduccion
 * This file helps diagnose component and module issues
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
$app = \Joomla\CMS\Factory::getApplication('site');
$db = \Joomla\CMS\Factory::getDbo();

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

// 9. Module Database Validation
echo "<h3>9. Module Database Validation</h3>";

// Check if module exists in joomla_extensions table
$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
    ->where($db->quoteName('type') . ' = ' . $db->quote('module'));

$db->setQuery($query);
$extension = $db->loadObject();

if ($extension) {
    echo "✅ Module found in extensions table:<br>";
    echo "   - ID: {$extension->extension_id}<br>";
    echo "   - Name: {$extension->name}<br>";
    echo "   - Enabled: {$extension->enabled}<br>";
    echo "   - Manifest Cache: " . (empty($extension->manifest_cache) ? '❌ EMPTY' : '✅ EXISTS') . "<br>";
    echo "   - Params: " . (empty($extension->params) ? '❌ EMPTY' : '✅ EXISTS') . "<br>";
} else {
    echo "❌ Module NOT found in extensions table<br>";
}

// Check if module exists in joomla_modules table
$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__modules'))
    ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));

$db->setQuery($query);
$modules = $db->loadObjectList();

if ($modules) {
    echo "✅ Found " . count($modules) . " module instance(s):<br>";
    foreach ($modules as $module) {
        echo "   - ID: {$module->id}, Title: {$module->title}, Published: {$module->published}<br>";
    }
} else {
    echo "❌ No module instances found in modules table<br>";
}

// Check if module files exist
echo "<h3>10. Module Files Check</h3>";
$modulePath = JPATH_ROOT . '/modules/mod_acciones_produccion';
$xmlFile = $modulePath . '/mod_acciones_produccion.xml';
$phpFile = $modulePath . '/mod_acciones_produccion.php';

if (file_exists($xmlFile)) {
    echo "✅ XML file exists: $xmlFile<br>";
    echo "   Size: " . filesize($xmlFile) . " bytes<br>";
    echo "   Modified: " . date('Y-m-d H:i:s', filemtime($xmlFile)) . "<br>";
} else {
    echo "❌ XML file NOT found: $xmlFile<br>";
}

if (file_exists($phpFile)) {
    echo "✅ PHP file exists: $phpFile<br>";
    echo "   Size: " . filesize($phpFile) . " bytes<br>";
    echo "   Modified: " . date('Y-m-d H:i:s', filemtime($phpFile)) . "<br>";
} else {
    echo "❌ PHP file NOT found: $phpFile<br>";
}

if (is_dir($modulePath)) {
    echo "✅ Module directory exists: $modulePath<br>";
    echo "   Contents:<br>";
    $files = scandir($modulePath);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $modulePath . '/' . $file;
            $type = is_dir($filePath) ? 'DIR' : 'FILE';
            $size = is_file($filePath) ? filesize($filePath) : 'N/A';
            echo "     - $file ($type, $size bytes)<br>";
        }
    }
} else {
    echo "❌ Module directory NOT found: $modulePath<br>";
}

// 11. Check for duplicate module entries
echo "<h3>11. Duplicate Module Entries Check</h3>";
$query = $db->getQuery(true)
    ->select('element, COUNT(*) as count')
    ->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('element') . ' = ' . $db->quote('mod_acciones_produccion'))
    ->where($db->quoteName('type') . ' = ' . $db->quote('module'))
    ->group('element');

$db->setQuery($query);
$duplicates = $db->loadObject();

if ($duplicates && $duplicates->count > 1) {
    echo "❌ Found {$duplicates->count} duplicate entries - this can cause issues<br>";
} else {
    echo "✅ No duplicate entries found<br>";
}

// 12. Check manifest cache content
echo "<h3>12. Manifest Cache Analysis</h3>";
if ($extension && !empty($extension->manifest_cache)) {
    $manifest = json_decode($extension->manifest_cache, true);
    if ($manifest) {
        echo "✅ Manifest cache is valid JSON<br>";
        echo "   - Name: " . ($manifest['name'] ?? 'N/A') . "<br>";
        echo "   - Version: " . ($manifest['version'] ?? 'N/A') . "<br>";
        echo "   - Description: " . ($manifest['description'] ?? 'N/A') . "<br>";
    } else {
        echo "❌ Manifest cache is invalid JSON<br>";
    }
} else {
    echo "❌ No manifest cache found<br>";
}

echo "<h3>🔧 Quick Fix Commands</h3>";
echo "<p>If you see issues above, run these commands:</p>";
echo "<ol>";
echo "<li><strong>Validate module database:</strong><br>";
echo "<code>cd /var/www/grimpsa_webserver && php validate_module_database.php</code></li>";
echo "<li><strong>Fix module database issues:</strong><br>";
echo "<code>cd /var/www/grimpsa_webserver && php fix_module_database.php</code></li>";
echo "<li><strong>Clear Joomla cache:</strong><br>";
echo "<code>cd /var/www/grimpsa_webserver && php cli/joomla.php cache:clear</code></li>";
echo "</ol>";

echo "<h3>📋 Recommendations</h3>";
echo "<p>If all tests pass but routing still fails, the issue might be:</p>";
echo "<ul>";
echo "<li>Component not properly registered in Joomla</li>";
echo "<li>Menu item routing issue</li>";
echo "<li>URL rewriting issue</li>";
echo "<li>Component dispatcher not working</li>";
echo "<li>Module XML data not available (use fix scripts above)</li>";
echo "<li>Duplicate module entries in database</li>";
echo "<li>Missing or corrupted manifest cache</li>";
echo "</ul>";

echo "<h3>📊 Summary</h3>";
$totalTests = 12;
$passedTests = 0;

// Count passed tests (simplified logic)
if ($extension) $passedTests++;
if ($modules) $passedTests++;
if (file_exists($xmlFile)) $passedTests++;
if (file_exists($phpFile)) $passedTests++;
if (is_dir($modulePath)) $passedTests++;
if (!$duplicates || $duplicates->count <= 1) $passedTests++;

echo "<p><strong>Tests Passed:</strong> $passedTests / $totalTests</p>";
if ($passedTests == $totalTests) {
    echo "<p style='color: green;'>✅ All tests passed! System appears to be working correctly.</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Some tests failed. Please review the issues above and run the fix commands.</p>";
}
?>