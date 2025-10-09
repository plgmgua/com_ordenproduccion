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

// Test 13: Test the EAV query
try {
    echo 'TEST 13: Testing EAV query...<br>';
    
    $query = $db->getQuery(true);
    $query->select($db->quoteName('tipo_de_campo') . ' AS attribute_name')
          ->select($db->quoteName('valor') . ' AS attribute_value')
          ->from($db->quoteName('#__ordenproduccion_info'))
          ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order->order_number))
          ->where($db->quoteName('state') . ' = 1');
    
    echo 'SQL: ' . htmlspecialchars((string)$query) . '<br>';
    
    $db->setQuery($query);
    $results = $db->loadObjectList();
    
    if ($results) {
        echo 'TEST 13: Query returned ' . count($results) . ' rows<br>';
    } else {
        echo 'TEST 13: Query returned 0 rows (no EAV data)<br>';
    }
} catch (Exception $e) {
    echo 'TEST 13 FAILED: <strong style="color: red;">' . htmlspecialchars($e->getMessage()) . '</strong><br>';
    echo 'THIS IS LIKELY THE 500 ERROR CAUSE!<br>';
}

echo '<hr>';
echo '<h2>ALL TESTS COMPLETE</h2>';
echo '<p>If you see this message, troubleshooting.php is working!</p>';
