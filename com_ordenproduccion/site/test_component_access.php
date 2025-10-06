<?php
/**
 * Test component access for com_ordenproduccion
 * This file tests if the component is accessible via different URLs
 */

// Prevent direct access
defined('_JEXEC') or die;

// Get Joomla application
$app = \Joomla\CMS\Factory::getApplication();

echo "<h2>🔍 Component Access Test</h2>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// 1. Check current URL
echo "<h3>1. Current URL Analysis</h3>";
$currentUrl = $_SERVER['REQUEST_URI'];
echo "Current URL: " . $currentUrl . "<br>";

// 2. Check if component directory exists
echo "<h3>2. Component Directory Check</h3>";
$componentPath = JPATH_ROOT . '/components/com_ordenproduccion';
if (is_dir($componentPath)) {
    echo "✅ Component directory exists: " . $componentPath . "<br>";
} else {
    echo "❌ Component directory not found: " . $componentPath . "<br>";
}

// 3. Check if site entry point exists
echo "<h3>3. Site Entry Point Check</h3>";
$siteEntryPoint = $componentPath . '/site/ordenproduccion.php';
if (file_exists($siteEntryPoint)) {
    echo "✅ Site entry point exists: " . $siteEntryPoint . "<br>";
} else {
    echo "❌ Site entry point not found: " . $siteEntryPoint . "<br>";
}

// 4. Check if component is registered in Joomla
echo "<h3>4. Component Registration Check</h3>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    echo "✅ Component registered in Joomla<br>";
} catch (Exception $e) {
    echo "❌ Component not registered: " . $e->getMessage() . "<br>";
}

// 5. Test different URL patterns
echo "<h3>5. URL Pattern Tests</h3>";
echo "Try these URLs:<br>";
echo "<ul>";
echo "<li><a href='/index.php/component/com_ordenproduccion/?view=orden&id=15'>/index.php/component/com_ordenproduccion/?view=orden&id=15</a></li>";
echo "<li><a href='/index.php?option=com_ordenproduccion&view=orden&id=15'>/index.php?option=com_ordenproduccion&view=orden&id=15</a></li>";
echo "<li><a href='/component/com_ordenproduccion/?view=orden&id=15'>/component/com_ordenproduccion/?view=orden&id=15</a></li>";
echo "</ul>";

// 6. Check Joomla component list
echo "<h3>6. Joomla Component List</h3>";
try {
    $db = \Joomla\CMS\Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('element, name, enabled')
        ->from('#__extensions')
        ->where('type = ' . $db->quote('component'))
        ->where('element LIKE ' . $db->quote('%ordenproduccion%'));
    
    $db->setQuery($query);
    $components = $db->loadObjectList();
    
    if (empty($components)) {
        echo "❌ No ordenproduccion components found in database<br>";
    } else {
        echo "✅ Found components:<br>";
        foreach ($components as $comp) {
            echo "- " . $comp->element . " (" . $comp->name . ") - Enabled: " . ($comp->enabled ? 'Yes' : 'No') . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database query failed: " . $e->getMessage() . "<br>";
}

// 7. Check if component is installed
echo "<h3>7. Component Installation Check</h3>";
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
        echo "- Version: " . $component->manifest_cache . "<br>";
    } else {
        echo "❌ Component not found in database - may need to be installed<br>";
    }
} catch (Exception $e) {
    echo "❌ Database query failed: " . $e->getMessage() . "<br>";
}

echo "<h3>Recommendations</h3>";
echo "<p>If component is not registered, you may need to:</p>";
echo "<ul>";
echo "<li>Install the component via Joomla admin</li>";
echo "<li>Check if component is enabled</li>";
echo "<li>Verify component files are in correct location</li>";
echo "</ul>";
?>
