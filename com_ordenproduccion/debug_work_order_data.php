<?php
/**
 * Debug script to check work order data retrieval
 * This will help identify why work_description is not showing
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Initialize Joomla
$app = Factory::getApplication('site');

echo "<h1>Work Order Data Debug</h1>\n";

// Get the work order ID from URL parameter
$orderId = $_GET['id'] ?? 1; // Default to ID 1 for testing
echo "<p><strong>Checking Work Order ID:</strong> $orderId</p>\n";

try {
    $db = Factory::getDbo();
    
    // Get main work order data
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = ' . (int)$orderId)
        ->where($db->quoteName('state') . ' = 1');

    $db->setQuery($query);
    $workOrder = $db->loadObject();

    if (!$workOrder) {
        echo "<p style='color: red;'><strong>ERROR:</strong> Work order not found!</p>\n";
        exit;
    }

    echo "<h2>Main Table Data (#__ordenproduccion_ordenes)</h2>\n";
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Field</th><th>Value</th></tr>\n";
    
    foreach ($workOrder as $field => $value) {
        $highlight = '';
        if (strpos($field, 'work') !== false || strpos($field, 'description') !== false || strpos($field, 'trabajo') !== false) {
            $highlight = ' style="background-color: yellow;"';
        }
        echo "<tr$highlight><td>$field</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>\n";
    }
    echo "</table>\n";

    // Get EAV data
    $eavQuery = $db->getQuery(true)
        ->select('attribute_name, attribute_value')
        ->from($db->quoteName('#__ordenproduccion_info'))
        ->where($db->quoteName('order_id') . ' = ' . (int)$orderId)
        ->where($db->quoteName('state') . ' = 1');

    $db->setQuery($eavQuery);
    $eavData = $db->loadObjectList();

    echo "<h2>EAV Data (#__ordenproduccion_info)</h2>\n";
    if (empty($eavData)) {
        echo "<p style='color: orange;'><strong>WARNING:</strong> No EAV data found!</p>\n";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
        echo "<tr><th>Attribute Name</th><th>Value</th></tr>\n";
        
        foreach ($eavData as $item) {
            $highlight = '';
            if (strpos($item->attribute_name, 'work') !== false || strpos($item->attribute_name, 'description') !== false || strpos($item->attribute_name, 'trabajo') !== false) {
                $highlight = ' style="background-color: yellow;"';
            }
            echo "<tr$highlight><td>" . htmlspecialchars($item->attribute_name) . "</td><td>" . htmlspecialchars($item->attribute_value ?? 'NULL') . "</td></tr>\n";
        }
        echo "</table>\n";
    }

    // Test the PDF data retrieval logic
    echo "<h2>PDF Data Retrieval Test</h2>\n";
    
    // Simulate the PDF logic
    $jobDesc = 'N/A';
    $debugInfo = [];
    
    if (isset($workOrder->work_description)) {
        $jobDesc = $workOrder->work_description;
        $debugInfo[] = 'work_description: ' . $workOrder->work_description;
    } elseif (isset($workOrder->description)) {
        $jobDesc = $workOrder->description;
        $debugInfo[] = 'description: ' . $workOrder->description;
    }
    
    // Try EAV data
    if ($jobDesc === 'N/A' && !empty($eavData)) {
        $eavDataArray = [];
        foreach ($eavData as $item) {
            $eavDataArray[$item->attribute_name] = $item;
        }
        
        if (isset($eavDataArray['work_description'])) {
            $jobDesc = $eavDataArray['work_description']->attribute_value;
            $debugInfo[] = 'EAV work_description: ' . $jobDesc;
        } elseif (isset($eavDataArray['descripcion_de_trabajo'])) {
            $jobDesc = $eavDataArray['descripcion_de_trabajo']->attribute_value;
            $debugInfo[] = 'EAV descripcion_de_trabajo: ' . $jobDesc;
        }
    }
    
    echo "<p><strong>Final Job Description:</strong> " . htmlspecialchars($jobDesc) . "</p>\n";
    echo "<p><strong>Debug Info:</strong> " . implode(', ', $debugInfo) . "</p>\n";

} catch (Exception $e) {
    echo "<p style='color: red;'><strong>ERROR:</strong> " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Usage:</strong> Add ?id=WORK_ORDER_ID to the URL to check specific work order</p>\n";
echo "<p><strong>Example:</strong> debug_work_order_data.php?id=1</p>\n";
?>
