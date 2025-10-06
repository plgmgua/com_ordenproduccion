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

// 8. Analyze working component structure
echo "<h3>8. Working Component Analysis (com_approvalworkflow)</h3>";
$workingComponentPath = JPATH_ROOT . '/components/com_approvalworkflow';
if (is_dir($workingComponentPath)) {
    echo "✅ Working component found: " . $workingComponentPath . "<br>";
    
    // List files in working component
    $workingFiles = scandir($workingComponentPath);
    echo "Files in working component:<br>";
    foreach ($workingFiles as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $workingComponentPath . '/' . $file;
            if (is_dir($filePath)) {
                echo "- [DIR] " . $file . "<br>";
            } else {
                echo "- [FILE] " . $file . " (" . filesize($filePath) . " bytes)<br>";
            }
        }
    }
    
    // Check if working component has entry point
    $workingEntryPoint = $workingComponentPath . '/approvalworkflow.php';
    if (file_exists($workingEntryPoint)) {
        echo "<br>✅ Working component entry point exists: " . $workingEntryPoint . "<br>";
        echo "File size: " . filesize($workingEntryPoint) . " bytes<br>";
        echo "File permissions: " . substr(sprintf('%o', fileperms($workingEntryPoint)), -4) . "<br>";
    } else {
        echo "<br>❌ Working component entry point not found<br>";
    }
} else {
    echo "❌ Working component not found: " . $workingComponentPath . "<br>";
}

// 9. Compare our component structure
echo "<h3>9. Our Component Structure Analysis</h3>";
$ourComponentPath = JPATH_ROOT . '/components/com_ordenproduccion';
if (is_dir($ourComponentPath)) {
    echo "✅ Our component directory exists: " . $ourComponentPath . "<br>";
    
    // List files in our component
    $ourFiles = scandir($ourComponentPath);
    echo "Files in our component:<br>";
    foreach ($ourFiles as $file) {
        if ($file != '.' && $file != '..') {
            $filePath = $ourComponentPath . '/' . $file;
            if (is_dir($filePath)) {
                echo "- [DIR] " . $file . "<br>";
            } else {
                echo "- [FILE] " . $file . " (" . filesize($filePath) . " bytes)<br>";
            }
        }
    }
    
    // Check if our component has entry point
    $ourEntryPoint = $ourComponentPath . '/ordenproduccion.php';
    if (file_exists($ourEntryPoint)) {
        echo "<br>✅ Our component entry point exists: " . $ourEntryPoint . "<br>";
        echo "File size: " . filesize($ourEntryPoint) . " bytes<br>";
        echo "File permissions: " . substr(sprintf('%o', fileperms($ourEntryPoint)), -4) . "<br>";
    } else {
        echo "<br>❌ Our component entry point not found<br>";
    }
} else {
    echo "❌ Our component directory not found: " . $ourComponentPath . "<br>";
}

// 10. Check if our entry point is in wrong location
echo "<h3>10. Entry Point Location Analysis</h3>";
$possibleEntryPoints = [
    JPATH_ROOT . '/components/com_ordenproduccion/ordenproduccion.php',
    JPATH_ROOT . '/components/com_ordenproduccion/site/ordenproduccion.php',
    JPATH_ROOT . '/components/com_ordenproduccion/index.php'
];

foreach ($possibleEntryPoints as $entryPoint) {
    if (file_exists($entryPoint)) {
        echo "✅ Entry point found: " . $entryPoint . "<br>";
        echo "File size: " . filesize($entryPoint) . " bytes<br>";
        echo "File permissions: " . substr(sprintf('%o', fileperms($entryPoint)), -4) . "<br>";
    } else {
        echo "❌ Entry point not found: " . $entryPoint . "<br>";
    }
}

// 11. Test our entry point directly
echo "<h3>11. Direct Entry Point Test</h3>";
$ourEntryPoint = JPATH_ROOT . '/components/com_ordenproduccion/ordenproduccion.php';
if (file_exists($ourEntryPoint)) {
    echo "✅ Our entry point exists: " . $ourEntryPoint . "<br>";
    
    // Check if entry point has correct content
    $content = file_get_contents($ourEntryPoint);
    if (strpos($content, 'defined(\'_JEXEC\')') !== false) {
        echo "✅ Entry point has Joomla security check<br>";
    } else {
        echo "❌ Entry point missing Joomla security check<br>";
    }
    
    if (strpos($content, 'Factory::getApplication') !== false) {
        echo "✅ Entry point has Joomla application call<br>";
    } else {
        echo "❌ Entry point missing Joomla application call<br>";
    }
    
    if (strpos($content, 'bootComponent') !== false) {
        echo "✅ Entry point has component boot call<br>";
    } else {
        echo "❌ Entry point missing component boot call<br>";
    }
} else {
    echo "❌ Our entry point not found: " . $ourEntryPoint . "<br>";
}

echo "<h3>Recommendations</h3>";
echo "<p>Based on the working component analysis:</p>";
echo "<ul>";
echo "<li>Check if our entry point is in the correct location (component root, not site/ subdirectory)</li>";
echo "<li>Verify entry point has proper Joomla security and application calls</li>";
echo "<li>Compare our entry point structure with working component</li>";
echo "<li>Ensure entry point can be accessed directly</li>";
echo "</ul>";
?>