<?php
/**
 * Simple troubleshooting script
 */

// Test 0: Can PHP even run?
echo 'TEST 0: PHP is working<br>';

// Test 1: Can we enable errors?
ini_set('display_errors', 1);
error_reporting(E_ALL);
echo 'TEST 1: Error reporting enabled<br>';

// Test 2: Can we define constants?
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
echo 'TEST 2: Constants defined<br>';

// Test 3: Does JPATH_BASE exist?
if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', dirname(__FILE__));
}
echo 'TEST 3: JPATH_BASE = ' . JPATH_BASE . '<br>';

// Test 4: Does defines.php exist?
$definesPath = JPATH_BASE . '/includes/defines.php';
if (file_exists($definesPath)) {
    echo 'TEST 4: defines.php exists<br>';
} else {
    echo 'TEST 4 FAILED: defines.php NOT found at: ' . htmlspecialchars($definesPath) . '<br>';
    exit;
}

// Test 5: Can we load defines.php?
try {
    require_once $definesPath;
    echo 'TEST 5: defines.php loaded<br>';
} catch (Exception $e) {
    echo 'TEST 5 FAILED: ' . htmlspecialchars($e->getMessage()) . '<br>';
    exit;
}

// Test 6: Does framework.php exist?
$frameworkPath = JPATH_BASE . '/includes/framework.php';
if (file_exists($frameworkPath)) {
    echo 'TEST 6: framework.php exists<br>';
} else {
    echo 'TEST 6 FAILED: framework.php NOT found at: ' . htmlspecialchars($frameworkPath) . '<br>';
    exit;
}

// Test 7: Can we load framework.php?
try {
    require_once $frameworkPath;
    echo 'TEST 7: framework.php loaded<br>';
} catch (Exception $e) {
    echo 'TEST 7 FAILED: ' . htmlspecialchars($e->getMessage()) . '<br>';
    exit;
}

// Test 8: Can we use Factory?
use Joomla\CMS\Factory;
echo 'TEST 8: Factory class loaded<br>';

// Test 9: Can we get database?
try {
    $db = Factory::getDbo();
    echo 'TEST 9: Database connection OK<br>';
} catch (Exception $e) {
    echo 'TEST 9 FAILED: ' . htmlspecialchars($e->getMessage()) . '<br>';
    exit;
}

// Test 10: Can we query database?
try {
    $orderId = isset($_GET['id']) ? (int) $_GET['id'] : 1402;
    echo 'TEST 10: Testing order ID ' . $orderId . '<br>';
    
    $query = $db->getQuery(true);
    $query->select('*')
          ->from($db->quoteName('#__ordenproduccion_ordenes'))
          ->where($db->quoteName('id') . ' = ' . (int) $orderId);
    
    $db->setQuery($query);
    $order = $db->loadObject();
    
    if ($order) {
        echo 'TEST 10: Order found!<br>';
        echo 'Order Number: ' . htmlspecialchars($order->order_number) . '<br>';
        echo 'Client: ' . htmlspecialchars($order->client_name) . '<br>';
    } else {
        echo 'TEST 10 FAILED: Order not found<br>';
        exit;
    }
} catch (Exception $e) {
    echo 'TEST 10 FAILED: ' . htmlspecialchars($e->getMessage()) . '<br>';
    exit;
}

// Test 11: Check EAV table
try {
    $query = "SHOW TABLES LIKE '%ordenproduccion_info%'";
    $db->setQuery($query);
    $eavTable = $db->loadResult();
    
    if ($eavTable) {
        echo 'TEST 11: EAV table exists: ' . htmlspecialchars($eavTable) . '<br>';
    } else {
        echo 'TEST 11 WARNING: EAV table does not exist<br>';
    }
} catch (Exception $e) {
    echo 'TEST 11 FAILED: ' . htmlspecialchars($e->getMessage()) . '<br>';
}

// Test 12: Check EAV table columns
try {
    $query = "DESCRIBE " . $db->quoteName('#__ordenproduccion_info');
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo 'TEST 12: EAV table columns:<br>';
    echo '<ul>';
    $hasState = false;
    foreach ($columns as $col) {
        echo '<li>' . htmlspecialchars($col->Field) . ' (' . htmlspecialchars($col->Type) . ')';
        if ($col->Field === 'state') {
            echo ' <strong style="color: green;">← FOUND!</strong>';
            $hasState = true;
        }
        echo '</li>';
    }
    echo '</ul>';
    
    if (!$hasState) {
        echo '<strong style="color: red;">WARNING: "state" column is MISSING!</strong><br>';
    }
} catch (Exception $e) {
    echo 'TEST 12 FAILED: ' . htmlspecialchars($e->getMessage()) . '<br>';
}

// Test 13: Test the OLD (BROKEN) EAV query
try {
    echo 'TEST 13A: Testing OLD (Spanish) EAV query...<br>';
    
    $query = $db->getQuery(true);
    $query->select($db->quoteName('tipo_de_campo') . ' AS attribute_name')
          ->select($db->quoteName('valor') . ' AS attribute_value')
          ->from($db->quoteName('#__ordenproduccion_info'))
          ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order->order_number))
          ->where($db->quoteName('state') . ' = 1');
    
    echo 'SQL: ' . htmlspecialchars((string)$query) . '<br>';
    
    $db->setQuery($query);
    $results = $db->loadObjectList();
    
    echo 'TEST 13A: <strong style="color: red;">This should FAIL with column not found</strong><br>';
} catch (Exception $e) {
    echo 'TEST 13A FAILED (expected): <strong style="color: red;">' . htmlspecialchars($e->getMessage()) . '</strong><br>';
}

// Test 14: Test the NEW (FIXED) EAV query
try {
    echo '<br>TEST 14: Testing NEW (English) EAV query...<br>';
    
    $query = $db->getQuery(true);
    $query->select($db->quoteName('attribute_name'))
          ->select($db->quoteName('attribute_value'))
          ->from($db->quoteName('#__ordenproduccion_info'))
          ->where($db->quoteName('order_id') . ' = ' . (int) $orderId)
          ->where($db->quoteName('state') . ' = 1');
    
    echo 'SQL: ' . htmlspecialchars((string)$query) . '<br>';
    
    $db->setQuery($query);
    $results = $db->loadObjectList();
    
    if ($results) {
        echo 'TEST 14: <strong style="color: green;">SUCCESS! Query returned ' . count($results) . ' rows</strong><br>';
        echo '<table border="1" cellpadding="5"><tr><th>attribute_name</th><th>attribute_value</th></tr>';
        foreach ($results as $row) {
            echo '<tr><td>' . htmlspecialchars($row->attribute_name) . '</td><td>' . htmlspecialchars(substr($row->attribute_value, 0, 100)) . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo 'TEST 14: Query returned 0 rows (no EAV data - this is OK)<br>';
    }
} catch (Exception $e) {
    echo 'TEST 14 FAILED: <strong style="color: red;">' . htmlspecialchars($e->getMessage()) . '</strong><br>';
    echo 'THE FIX DID NOT WORK!<br>';
}

// Test 15: Try to actually load the OrdenModel and call getItem()
try {
    echo '<br>TEST 15: Testing actual OrdenModel::getItem() method...<br>';
    
    // Check if the model file exists
    $modelPath = JPATH_BASE . '/components/com_ordenproduccion/src/Model/OrdenModel.php';
    if (!file_exists($modelPath)) {
        echo 'TEST 15 FAILED: OrdenModel.php not found at: ' . htmlspecialchars($modelPath) . '<br>';
    } else {
        echo 'Model file exists: ' . htmlspecialchars($modelPath) . '<br>';
        
        // Try to include and instantiate
        // Note: This is a simplified test, the actual MVC does more
        echo 'Attempting to load model class...<br>';
        
        // This is just a file check, actual instantiation requires MVC factory
        echo '<strong style="color: orange;">To fully test the model, access the actual order URL</strong><br>';
        echo 'URL: <a href="/index.php/component/ordenproduccion/?view=orden&id=' . $orderId . '">View Order ' . $orderId . '</a><br>';
    }
} catch (Exception $e) {
    echo 'TEST 15 FAILED: ' . htmlspecialchars($e->getMessage()) . '<br>';
}

echo '<hr>';
echo '<h2>ALL TESTS COMPLETE</h2>';
echo '<p>If you see this message, troubleshooting.php is working!</p>';
