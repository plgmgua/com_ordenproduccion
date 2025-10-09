<?php
/**
 * DEBUG SCRIPT: Test Order View Loading
 * 
 * This script tests the order detail view to identify 500 errors
 */

// Enable all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<div style="font-family: monospace; margin: 20px;">';
echo '<h1>🔍 Order View Debugging</h1>';
echo '<hr>';

// Test 1: Bootstrap Joomla
echo '<h2>Test 1: Bootstrap Joomla Framework</h2>';
try {
    define('_JEXEC', 1);
    define('JPATH_BASE', '/var/www/grimpsa_webserver');
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
    
    use Joomla\CMS\Factory;
    
    $app = Factory::getApplication('site');
    echo '<p style="color: green;">✅ Joomla framework loaded</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    die();
}

// Test 2: Get Order ID
echo '<h2>Test 2: Get Order ID</h2>';
$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 1402;
echo '<p>Order ID: ' . $orderId . '</p>';

// Test 3: Check if order exists in main table
echo '<h2>Test 3: Check Order in Main Table</h2>';
try {
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = ' . (int) $orderId);
    
    $db->setQuery($query);
    $order = $db->loadObject();
    
    if ($order) {
        echo '<p style="color: green;">✅ Order found in main table</p>';
        echo '<pre>' . print_r($order, true) . '</pre>';
    } else {
        echo '<p style="color: red;">❌ Order not found in main table</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Database Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

// Test 4: Check EAV table columns
echo '<h2>Test 4: Check EAV Table Structure</h2>';
try {
    $db = Factory::getDbo();
    $query = "DESCRIBE " . $db->quoteName('#__ordenproduccion_info');
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo '<p style="color: green;">✅ EAV table columns:</p>';
    echo '<ul>';
    foreach ($columns as $col) {
        echo '<li>' . htmlspecialchars($col->Field) . ' (' . htmlspecialchars($col->Type) . ')</li>';
    }
    echo '</ul>';
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// Test 5: Check if EAV records exist
echo '<h2>Test 5: Check EAV Records for This Order</h2>';
try {
    if ($order && !empty($order->order_number)) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_info'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order->order_number));
        
        $db->setQuery($query);
        $eavRecords = $db->loadObjectList();
        
        if ($eavRecords) {
            echo '<p style="color: green;">✅ Found ' . count($eavRecords) . ' EAV records</p>';
            echo '<pre>' . print_r($eavRecords, true) . '</pre>';
        } else {
            echo '<p style="color: orange;">⚠️ No EAV records found for order ' . htmlspecialchars($order->order_number) . '</p>';
        }
    } else {
        echo '<p style="color: orange;">⚠️ Cannot check EAV records - order_number not found</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

// Test 6: Test Model Loading
echo '<h2>Test 6: Test OrdenModel::getEAVData()</h2>';
try {
    // Manually test the EAV query
    if ($order && !empty($order->order_number)) {
        $db = Factory::getDbo();
        
        echo '<p>Testing SQL query with order_number: ' . htmlspecialchars($order->order_number) . '</p>';
        
        $query = $db->getQuery(true)
            ->select($db->quoteName('tipo_de_campo') . ' AS attribute_name, ' . $db->quoteName('valor') . ' AS attribute_value')
            ->from($db->quoteName('#__ordenproduccion_info'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order->order_number))
            ->where($db->quoteName('state') . ' = 1');
        
        echo '<p>SQL Query:</p>';
        echo '<pre>' . htmlspecialchars((string) $query) . '</pre>';
        
        $db->setQuery($query);
        $results = $db->loadObjectList();
        
        if ($results) {
            echo '<p style="color: green;">✅ EAV query executed successfully</p>';
            $eavData = [];
            foreach ($results as $row) {
                $eavData[$row->attribute_name] = $row->attribute_value;
            }
            echo '<p>EAV Data Array:</p>';
            echo '<pre>' . print_r($eavData, true) . '</pre>';
        } else {
            echo '<p style="color: orange;">⚠️ EAV query returned no results</p>';
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Model Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

// Test 7: Try to load the actual Model
echo '<h2>Test 7: Load OrdenModel</h2>';
try {
    use Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel;
    
    // This will fail if the model has syntax errors
    echo '<p style="color: green;">✅ OrdenModel class loaded</p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error loading OrdenModel: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo '<hr>';
echo '<p><strong>Debug complete. Check results above.</strong></p>';
echo '</div>';
