<?php
/**
 * Troubleshooting file for com_ordenproduccion
 * This file helps diagnose component and routing issues
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
try {
    $app = \Joomla\CMS\Factory::getApplication('site');
} catch (Exception $e) {
    echo "❌ Failed to initialize Joomla application: " . $e->getMessage() . "<br>";
    exit;
}

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
    
    // Check entry point in component root (flat structure)
    $entryPoint = $componentPath . '/ordenproduccion.php';
    if (file_exists($entryPoint)) {
        echo "✅ Entry point exists: " . $entryPoint . "<br>";
        echo "File size: " . filesize($entryPoint) . " bytes<br>";
        echo "File permissions: " . substr(sprintf('%o', fileperms($entryPoint)), -4) . "<br>";
    } else {
        echo "❌ Entry point not found: " . $entryPoint . "<br>";
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

// 12. Debug Orden View 404 Issue
echo "<h3>12. Debug Orden View 404 Issue</h3>";
echo "<p>Testing the exact issue: 404 'Work order not found' when viewing order detail</p>";

// Test 1: Direct Model Access
echo "<h4>12.1 Direct Model Access Test</h4>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    $mvcFactory = $component->getMVCFactory();
    
    // Test with ID 15 (from troubleshooting)
    $model = $mvcFactory->createModel('Orden', 'Site');
    $model->setState('orden.id', 15);
    
    $item = $model->getItem(15);
    
    if ($item && is_object($item)) {
        echo "✅ Model can retrieve item with ID 15<br>";
        echo "- Order Number: " . ($item->order_number ?? 'N/A') . "<br>";
        echo "- Client: " . ($item->client_name ?? 'N/A') . "<br>";
        echo "- Status: " . ($item->status ?? 'N/A') . "<br>";
    } else {
        echo "❌ Model cannot retrieve item with ID 15<br>";
        if ($model) {
            $errors = $model->getErrors();
            if (!empty($errors)) {
                echo "Model errors: " . implode(', ', $errors) . "<br>";
            }
        }
    }
} catch (Exception $e) {
    echo "❌ Model access failed: " . $e->getMessage() . "<br>";
}

// Test 2: URL Parameters
echo "<h4>12.2 URL Parameters Test</h4>";
$input = $app->input;
echo "Current View: " . $input->get('view', 'none') . "<br>";
echo "Current ID: " . $input->get('id', 'none') . "<br>";
echo "Current Option: " . $input->get('option', 'none') . "<br>";

// Test 3: View Creation
echo "<h4>12.3 View Creation Test</h4>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    $mvcFactory = $component->getMVCFactory();
    
    // Set the input parameters for orden view
    $input->set('view', 'orden');
    $input->set('id', 15);
    
    $view = $mvcFactory->createView('Orden', 'Site');
    if ($view) {
        echo "✅ View created successfully<br>";
        echo "View class: " . get_class($view) . "<br>";
        
        // Try to get the model from the view
        $viewModel = $view->getModel();
        if ($viewModel && is_object($viewModel)) {
            echo "✅ View model retrieved<br>";
            echo "Model class: " . get_class($viewModel) . "<br>";
            
            // Try to get the item
            $item = $viewModel->getItem();
            if ($item && is_object($item)) {
                echo "✅ View model can retrieve item<br>";
            } else {
                echo "❌ View model cannot retrieve item<br>";
                $errors = $viewModel->getErrors();
                if (!empty($errors)) {
                    echo "Model errors: " . implode(', ', $errors) . "<br>";
                }
            }
        } else {
            echo "❌ View model not found<br>";
        }
    } else {
        echo "❌ View creation failed<br>";
    }
    
} catch (Exception $e) {
    echo "❌ View creation failed: " . $e->getMessage() . "<br>";
}

// Test 4: View Template Check
echo "<h4>12.4 View Template Check</h4>";
$templatePath = JPATH_ROOT . '/components/com_ordenproduccion/site/tmpl/orden/default.php';
if (file_exists($templatePath)) {
    echo "✅ View template exists: " . $templatePath . "<br>";
    echo "File size: " . filesize($templatePath) . " bytes<br>";
} else {
    echo "❌ View template not found: " . $templatePath . "<br>";
    echo "Checking alternative locations:<br>";
    
    // Check flat structure template
    $flatTemplatePath = JPATH_ROOT . '/components/com_ordenproduccion/tmpl/orden/default.php';
    if (file_exists($flatTemplatePath)) {
        echo "✅ Found template in flat structure: " . $flatTemplatePath . "<br>";
    } else {
        echo "❌ Template not found in flat structure either<br>";
    }
}

// Test 5: Error Simulation
echo "<h4>12.5 Error Simulation Test</h4>";
try {
    // Simulate the exact URL that's failing
    $app->input->set('option', 'com_ordenproduccion');
    $app->input->set('view', 'orden');
    $app->input->set('id', 15);
    
    $component = $app->bootComponent('com_ordenproduccion');
    $dispatcher = $component->getDispatcher($app);
    
    if ($dispatcher && is_object($dispatcher)) {
        echo "✅ Dispatcher created successfully<br>";
        echo "Dispatcher class: " . get_class($dispatcher) . "<br>";
        
        // Try to dispatch
        $dispatcher->dispatch();
        echo "✅ Dispatch completed successfully<br>";
    } else {
        echo "❌ Dispatcher creation failed<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Dispatch failed: " . $e->getMessage() . "<br>";
    echo "Error code: " . $e->getCode() . "<br>";
    echo "Error trace: " . $e->getTraceAsString() . "<br>";
}

echo "<h3>Recommendations</h3>";
echo "<p>Based on the debugging analysis:</p>";
echo "<ul>";
echo "<li>Check if the model is properly getting the ID from the state</li>";
echo "<li>Verify the view template exists and is accessible</li>";
echo "<li>Check if there are any access control issues</li>";
echo "<li>Verify the dispatcher is working correctly</li>";
echo "<li>Check if the issue is with flat vs site structure</li>";
echo "</ul>";
?>