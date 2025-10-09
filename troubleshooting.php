<?php
/**
 * TROUBLESHOOTING: Order Detail View 500 Error
 * URL: https://grimpsa_webserver.grantsolutions.cc/troubleshooting.php?id=1402
 */

// Enable all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<html><head><title>Order View Debug</title></head><body>';
echo '<div style="font-family: Arial; margin: 20px; max-width: 1200px;">';
echo '<h1 style="color: #d32f2f;">🔥 ORDER DETAIL VIEW DEBUG</h1>';
echo '<hr>';

// Step 1: Bootstrap Joomla
echo '<h2>Step 1: Bootstrap Joomla</h2>';
try {
    if (!defined('_JEXEC')) {
        define('_JEXEC', 1);
    }
    if (!defined('JPATH_BASE')) {
        define('JPATH_BASE', dirname(__FILE__));
    }
    
    if (!file_exists(JPATH_BASE . '/includes/defines.php')) {
        throw new Exception('defines.php not found at: ' . JPATH_BASE . '/includes/defines.php');
    }
    
    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';
    
    echo '<p style="color: green;">✅ Joomla framework loaded</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>File: ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p>Line: ' . $e->getLine() . '</p>';
    echo '</div></body></html>';
    exit;
}

// Step 2: Create application
echo '<h2>Step 2: Create Application</h2>';
try {
    use Joomla\CMS\Factory;
    $app = Factory::getApplication('site');
    echo '<p style="color: green;">✅ Application created</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></body></html>';
    exit;
}

// Step 3: Get database connection
echo '<h2>Step 3: Database Connection</h2>';
try {
    $db = Factory::getDbo();
    echo '<p style="color: green;">✅ Database connected</p>';
} catch (Exception $e) {
    echo '<p style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></body></html>';
    exit;
}

// Step 4: Get order ID
echo '<h2>Step 4: Get Order ID</h2>';
$orderId = isset($_GET['id']) ? (int) $_GET['id'] : 1402;
echo '<p>Testing Order ID: <strong>' . $orderId . '</strong></p>';

// Step 5: Query main table
echo '<h2>Step 5: Query Main Table</h2>';
try {
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = ' . (int) $orderId);
    
    $db->setQuery($query);
    $order = $db->loadObject();
    
    if ($order) {
        echo '<p style="color: green;">✅ Order found</p>';
        echo '<p><strong>order_number:</strong> ' . htmlspecialchars($order->order_number) . '</p>';
        echo '<p><strong>client_name:</strong> ' . htmlspecialchars($order->client_name) . '</p>';
        echo '<p><strong>status:</strong> ' . htmlspecialchars($order->status) . '</p>';
    } else {
        echo '<p style="color: red;">❌ Order not found</p>';
        echo '</div></body></html>';
        exit;
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></body></html>';
    exit;
}

// Step 6: Check if EAV table exists
echo '<h2>Step 6: Check EAV Table Exists</h2>';
try {
    $tables = $db->getTableList();
    $eavTableName = $db->getPrefix() . 'ordenproduccion_info';
    
    if (in_array($eavTableName, $tables)) {
        echo '<p style="color: green;">✅ EAV table exists: ' . htmlspecialchars($eavTableName) . '</p>';
    } else {
        echo '<p style="color: red;">❌ EAV table does NOT exist!</p>';
        echo '<p>Expected: ' . htmlspecialchars($eavTableName) . '</p>';
        echo '<p>Available tables:</p><ul>';
        foreach ($tables as $table) {
            if (strpos($table, 'ordenproduccion') !== false) {
                echo '<li>' . htmlspecialchars($table) . '</li>';
            }
        }
        echo '</ul>';
        echo '</div></body></html>';
        exit;
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div></body></html>';
    exit;
}

// Step 7: Show EAV table structure
echo '<h2>Step 7: EAV Table Structure</h2>';
try {
    $query = "DESCRIBE " . $db->quoteName($eavTableName);
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo '<p style="color: green;">✅ Table has ' . count($columns) . ' columns:</p>';
    echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
    echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>';
    foreach ($columns as $col) {
        $highlight = ($col->Field === 'state') ? 'background: yellow;' : '';
        echo '<tr style="' . $highlight . '">';
        echo '<td><strong>' . htmlspecialchars($col->Field) . '</strong></td>';
        echo '<td>' . htmlspecialchars($col->Type) . '</td>';
        echo '<td>' . htmlspecialchars($col->Null) . '</td>';
        echo '<td>' . htmlspecialchars($col->Default) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
} catch (Exception $e) {
    echo '<p style="color: red;">❌ ERROR: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// Step 8: Test the exact query from getEAVData()
echo '<h2>Step 8: Test getEAVData() Query</h2>';
try {
    echo '<p><strong>Query 1:</strong> Get order_number for ID ' . $orderId . '</p>';
    $query = $db->getQuery(true)
        ->select('order_number')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = ' . (int) $orderId);
    
    $db->setQuery($query);
    $orderNumber = $db->loadResult();
    echo '<p style="color: green;">✅ order_number = ' . htmlspecialchars($orderNumber) . '</p>';
    
    if ($orderNumber) {
        echo '<p><strong>Query 2:</strong> Get EAV data for order_number</p>';
        $query = $db->getQuery(true)
            ->select($db->quoteName('tipo_de_campo') . ' AS attribute_name, ' . $db->quoteName('valor') . ' AS attribute_value')
            ->from($db->quoteName('#__ordenproduccion_info'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber))
            ->where($db->quoteName('state') . ' = 1');
        
        echo '<p>SQL: <code>' . htmlspecialchars((string)$query) . '</code></p>';
        
        $db->setQuery($query);
        $results = $db->loadObjectList();
        
        if ($results) {
            echo '<p style="color: green;">✅ Query returned ' . count($results) . ' rows</p>';
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
            echo '<tr><th>attribute_name</th><th>attribute_value</th></tr>';
            foreach ($results as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row->attribute_name) . '</td>';
                echo '<td>' . htmlspecialchars(substr($row->attribute_value, 0, 100)) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color: orange;">⚠️ Query returned 0 rows (no EAV data)</p>';
        }
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ ERROR IN QUERY: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>This is likely the 500 error cause!</strong></p>';
    echo '<p>File: ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p>Line: ' . $e->getLine() . '</p>';
}

echo '<hr>';
echo '<h2 style="color: #1976d2;">🔍 DIAGNOSIS SUMMARY</h2>';
echo '<p>If all steps passed, the error is in the View or Template.</p>';
echo '<p>If Step 8 failed, the error is in the SQL query (likely missing "state" column).</p>';
echo '</div></body></html>';
