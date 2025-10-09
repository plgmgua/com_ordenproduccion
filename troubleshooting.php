<?php
/**
 * TROUBLESHOOTING SCRIPT: Order Detail View 500 Error
 * 
 * URL: https://grimpsa_webserver.grantsolutions.cc/troubleshooting.php?id=1402
 */

// Enable all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<div style="font-family: monospace; margin: 20px; max-width: 1200px;">';
echo '<h1 style="color: #d32f2f;">🔥 ORDER DETAIL VIEW 500 ERROR DEBUG</h1>';
echo '<p><strong>Purpose:</strong> Diagnose why /index.php/component/ordenproduccion/?view=orden&id=1402 shows 500 error</p>';
echo '<hr>';

// Test 1: Bootstrap Joomla
echo '<h2>Test 1: Bootstrap Joomla Framework</h2>';
try {
    define('_JEXEC', 1);
    define('JPATH_BASE', dirname(__FILE__));
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
echo '<p>Testing Order ID: <strong>' . $orderId . '</strong></p>';

// Test 3: Check if order exists in main table
echo '<h2>Test 3: Check Order Exists in Main Table</h2>';
try {
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = ' . (int) $orderId);
    
    $db->setQuery($query);
    $order = $db->loadObject();
    
    if ($order) {
        echo '<p style="color: green;">✅ Order found in joomla_ordenproduccion_ordenes</p>';
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
        echo '<tr><th>Field</th><th>Value</th></tr>';
        foreach ($order as $key => $value) {
            echo '<tr><td>' . htmlspecialchars($key) . '</td><td>' . htmlspecialchars(substr((string)$value, 0, 100)) . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color: red;">❌ Order ID ' . $orderId . ' not found in main table</p>';
        die('Cannot continue without valid order');
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Database Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    die();
}

// Test 4: Check EAV table structure
echo '<h2>Test 4: Check EAV Table Structure (joomla_ordenproduccion_info)</h2>';
try {
    $db = Factory::getDbo();
    $query = "DESCRIBE " . $db->quoteName('#__ordenproduccion_info');
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo '<p style="color: green;">✅ EAV table exists with ' . count($columns) . ' columns:</p>';
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    foreach ($columns as $col) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($col->Field) . '</strong></td>';
        echo '<td>' . htmlspecialchars($col->Type) . '</td>';
        echo '<td>' . htmlspecialchars($col->Null) . '</td>';
        echo '<td>' . htmlspecialchars($col->Key) . '</td>';
        echo '<td>' . htmlspecialchars($col->Default) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p style="color: orange;">⚠️ This might be OK if EAV table doesn\'t exist yet</p>';
}

// Test 5: Check if EAV records exist for this order
echo '<h2>Test 5: Check EAV Records for Order ' . htmlspecialchars($order->order_number ?? 'N/A') . '</h2>';
try {
    if (!empty($order->order_number)) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_info'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order->order_number));
        
        $db->setQuery($query);
        echo '<p><strong>SQL Query:</strong></p>';
        echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">' . htmlspecialchars((string)$query) . '</pre>';
        
        $eavRecords = $db->loadObjectList();
        
        if ($eavRecords) {
            echo '<p style="color: green;">✅ Found ' . count($eavRecords) . ' EAV records</p>';
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
            echo '<tr><th>ID</th><th>numero_de_orden</th><th>tipo_de_campo</th><th>valor</th><th>state</th></tr>';
            foreach ($eavRecords as $rec) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($rec->id ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($rec->numero_de_orden ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($rec->tipo_de_campo ?? '') . '</td>';
                echo '<td>' . htmlspecialchars(substr((string)($rec->valor ?? ''), 0, 50)) . '</td>';
                echo '<td>' . htmlspecialchars($rec->state ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color: orange;">⚠️ No EAV records found (this is OK, means no additional data)</p>';
        }
    } else {
        echo '<p style="color: orange;">⚠️ order_number is empty, cannot query EAV table</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error querying EAV table: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

// Test 6: Simulate getEAVData() method
echo '<h2>Test 6: Simulate OrdenModel::getEAVData() Method</h2>';
try {
    if (!empty($order->order_number)) {
        $db = Factory::getDbo();
        
        // Step 1: Get order number (we already have it, but simulating the method)
        echo '<p><strong>Step 1:</strong> Get order_number for ID ' . $orderId . '</p>';
        $query = $db->getQuery(true)
            ->select('order_number')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . (int) $orderId);
        
        $db->setQuery($query);
        $orderNumber = $db->loadResult();
        echo '<p style="color: green;">✅ order_number = ' . htmlspecialchars($orderNumber) . '</p>';
        
        if (!$orderNumber) {
            echo '<p style="color: orange;">⚠️ No order_number found, method would return []</p>';
        } else {
            // Step 2: Query EAV data using Spanish column names
            echo '<p><strong>Step 2:</strong> Query EAV data with Spanish column names</p>';
            $query = $db->getQuery(true)
                ->select($db->quoteName('tipo_de_campo') . ' AS attribute_name, ' . $db->quoteName('valor') . ' AS attribute_value')
                ->from($db->quoteName('#__ordenproduccion_info'))
                ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber))
                ->where($db->quoteName('state') . ' = 1');
            
            echo '<p><strong>SQL Query:</strong></p>';
            echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">' . htmlspecialchars((string)$query) . '</pre>';
            
            $db->setQuery($query);
            $results = $db->loadObjectList();
            
            if ($results) {
                echo '<p style="color: green;">✅ Query returned ' . count($results) . ' rows</p>';
                
                // Step 3: Convert to associative array
                echo '<p><strong>Step 3:</strong> Convert to associative array</p>';
                $eavData = [];
                foreach ($results as $row) {
                    $eavData[$row->attribute_name] = $row->attribute_value;
                }
                
                echo '<p style="color: green;">✅ EAV Data Array:</p>';
                echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">';
                print_r($eavData);
                echo '</pre>';
            } else {
                echo '<p style="color: orange;">⚠️ Query returned 0 rows (empty EAV data)</p>';
                echo '<p>Method would return: []</p>';
            }
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error simulating getEAVData(): ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '<p style="color: red;"><strong>THIS IS LIKELY THE ERROR CAUSING THE 500!</strong></p>';
}

// Test 7: Try to load OrdenModel class
echo '<h2>Test 7: Load OrdenModel Class</h2>';
try {
    require_once JPATH_BASE . '/components/com_ordenproduccion/src/Model/OrdenModel.php';
    echo '<p style="color: green;">✅ OrdenModel.php file loaded successfully</p>';
    
    // Check if class exists
    if (class_exists('Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel')) {
        echo '<p style="color: green;">✅ OrdenModel class exists</p>';
    } else {
        echo '<p style="color: red;">❌ OrdenModel class does not exist after loading file</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error loading OrdenModel: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '<p style="color: red;"><strong>MODEL FILE HAS SYNTAX ERROR!</strong></p>';
}

// Test 8: Check EAV table 'state' column type
echo '<h2>Test 8: Check EAV Table "state" Column</h2>';
try {
    $db = Factory::getDbo();
    $query = "SHOW COLUMNS FROM " . $db->quoteName('#__ordenproduccion_info') . " WHERE Field = 'state'";
    $db->setQuery($query);
    $stateColumn = $db->loadObject();
    
    if ($stateColumn) {
        echo '<p style="color: green;">✅ "state" column exists</p>';
        echo '<p><strong>Type:</strong> ' . htmlspecialchars($stateColumn->Type) . '</p>';
        echo '<p><strong>Null:</strong> ' . htmlspecialchars($stateColumn->Null) . '</p>';
        echo '<p><strong>Default:</strong> ' . htmlspecialchars($stateColumn->Default) . '</p>';
        
        if ($stateColumn->Type !== 'tinyint(3)' && $stateColumn->Type !== 'tinyint(4)') {
            echo '<p style="color: orange;">⚠️ Warning: "state" column type is ' . htmlspecialchars($stateColumn->Type) . ' instead of tinyint</p>';
        }
    } else {
        echo '<p style="color: red;">❌ "state" column does not exist in EAV table!</p>';
        echo '<p style="color: red;"><strong>THIS WILL CAUSE THE QUERY TO FAIL!</strong></p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<hr>';
echo '<h2 style="color: #1976d2;">🔍 DIAGNOSIS COMPLETE</h2>';
echo '<p>Review the results above. Look for:</p>';
echo '<ul>';
echo '<li>❌ Red errors indicate the problem</li>';
echo '<li>⚠️ Orange warnings might be related</li>';
echo '<li>✅ Green checkmarks mean that part is working</li>';
echo '</ul>';
echo '<p><strong>Most likely issues:</strong></p>';
echo '<ol>';
echo '<li>EAV table missing "state" column (Test 8)</li>';
echo '<li>SQL query error in getEAVData() (Test 6)</li>';
echo '<li>OrdenModel.php has syntax error (Test 7)</li>';
echo '</ol>';
echo '</div>';
