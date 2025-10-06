<?php
/**
 * Debug script for orden view
 * This script helps diagnose the 404 "Work order not found" issue
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
$app = \Joomla\CMS\Factory::getApplication('site');

echo "<h2>🔍 Orden View Debug Test</h2>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Check if we can access the model directly
echo "<h3>1. Direct Model Access Test</h3>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    $mvcFactory = $component->getMVCFactory();
    
    // Test with ID 15 (from troubleshooting)
    $model = $mvcFactory->createModel('Orden', 'Site');
    $model->setState('orden.id', 15);
    
    $item = $model->getItem(15);
    
    if ($item) {
        echo "✅ Model can retrieve item with ID 15<br>";
        echo "- Order Number: " . ($item->order_number ?? 'N/A') . "<br>";
        echo "- Client: " . ($item->client_name ?? 'N/A') . "<br>";
        echo "- Status: " . ($item->status ?? 'N/A') . "<br>";
    } else {
        echo "❌ Model cannot retrieve item with ID 15<br>";
        echo "Model errors: " . implode(', ', $model->getErrors()) . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Model access failed: " . $e->getMessage() . "<br>";
}

// Test 2: Check URL parameters
echo "<h3>2. URL Parameters Test</h3>";
$input = $app->input;
echo "View: " . $input->get('view', 'none') . "<br>";
echo "ID: " . $input->get('id', 'none') . "<br>";
echo "Option: " . $input->get('option', 'none') . "<br>";

// Test 3: Check if we can create the view
echo "<h3>3. View Creation Test</h3>";
try {
    $component = $app->bootComponent('com_ordenproduccion');
    $mvcFactory = $component->getMVCFactory();
    
    // Set the input parameters
    $input->set('view', 'orden');
    $input->set('id', 15);
    
    $view = $mvcFactory->createView('Orden', 'Site');
    echo "✅ View created successfully<br>";
    echo "View class: " . get_class($view) . "<br>";
    
    // Try to get the model from the view
    $viewModel = $view->getModel();
    if ($viewModel) {
        echo "✅ View model retrieved<br>";
        echo "Model class: " . get_class($viewModel) . "<br>";
        
        // Try to get the item
        $item = $viewModel->getItem();
        if ($item) {
            echo "✅ View model can retrieve item<br>";
        } else {
            echo "❌ View model cannot retrieve item<br>";
            echo "Model errors: " . implode(', ', $viewModel->getErrors()) . "<br>";
        }
    } else {
        echo "❌ View model not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ View creation failed: " . $e->getMessage() . "<br>";
}

// Test 4: Check database directly
echo "<h3>4. Direct Database Test</h3>";
try {
    $db = \Joomla\CMS\Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__ordenproduccion_ordenes')
        ->where('id = 15')
        ->where('state = 1');
    
    $db->setQuery($query);
    $result = $db->loadObject();
    
    if ($result) {
        echo "✅ Database record found for ID 15<br>";
        echo "- Order Number: " . ($result->order_number ?? 'N/A') . "<br>";
        echo "- Client: " . ($result->client_name ?? 'N/A') . "<br>";
        echo "- Status: " . ($result->status ?? 'N/A') . "<br>";
    } else {
        echo "❌ No database record found for ID 15<br>";
    }
} catch (Exception $e) {
    echo "❌ Database query failed: " . $e->getMessage() . "<br>";
}

// Test 5: Check if the issue is with the view template
echo "<h3>5. View Template Test</h3>";
$templatePath = JPATH_ROOT . '/components/com_ordenproduccion/site/tmpl/orden/default.php';
if (file_exists($templatePath)) {
    echo "✅ View template exists: " . $templatePath . "<br>";
    echo "File size: " . filesize($templatePath) . " bytes<br>";
} else {
    echo "❌ View template not found: " . $templatePath . "<br>";
}

// Test 6: Check the exact error
echo "<h3>6. Error Simulation Test</h3>";
try {
    // Simulate the exact URL that's failing
    $app->input->set('option', 'com_ordenproduccion');
    $app->input->set('view', 'orden');
    $app->input->set('id', 15);
    
    $component = $app->bootComponent('com_ordenproduccion');
    $dispatcher = $component->getDispatcher($app);
    
    echo "✅ Dispatcher created successfully<br>";
    echo "Dispatcher class: " . get_class($dispatcher) . "<br>";
    
    // Try to dispatch
    $dispatcher->dispatch();
    echo "✅ Dispatch completed successfully<br>";
    
} catch (Exception $e) {
    echo "❌ Dispatch failed: " . $e->getMessage() . "<br>";
    echo "Error code: " . $e->getCode() . "<br>";
    echo "Error trace: " . $e->getTraceAsString() . "<br>";
}

echo "<h3>Recommendations</h3>";
echo "<ul>";
echo "<li>Check if the model is properly getting the ID from the state</li>";
echo "<li>Verify the view template exists and is accessible</li>";
echo "<li>Check if there are any access control issues</li>";
echo "<li>Verify the dispatcher is working correctly</li>";
echo "</ul>";
