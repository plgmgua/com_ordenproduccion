<?php
/**
 * Debug script for order detail view
 * Access: https://yoursite.com/helpers/debug_order_view.php?id=1402
 */

// Define Joomla environment
define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__));

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Start the application
$app = Factory::getApplication('site');

// Get order ID from URL
$orderId = (int) ($_GET['id'] ?? 0);

if ($orderId === 0) {
    die('<h1>Error: No order ID provided</h1><p>Usage: debug_order_view.php?id=1402</p>');
}

echo '<h1>Order Detail View Debug</h1>';
echo '<p><strong>Order ID:</strong> ' . $orderId . '</p>';
echo '<hr>';

try {
    $db = Factory::getDbo();
    
    // 1. Check if order exists
    echo '<h2>1. Main Order Record</h2>';
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = ' . $orderId);
    
    $db->setQuery($query);
    $order = $db->loadObject();
    
    if ($order) {
        echo '<p style="color: green;">✅ Order found</p>';
        echo '<pre>';
        echo 'Order Number: ' . htmlspecialchars($order->order_number ?? 'N/A') . "\n";
        echo 'Client: ' . htmlspecialchars($order->client_name ?? 'N/A') . "\n";
        echo 'Work Description: ' . htmlspecialchars(substr($order->work_description ?? 'N/A', 0, 100)) . "\n";
        echo '</pre>';
    } else {
        echo '<p style="color: red;">❌ Order not found</p>';
        die();
    }
    
    // 2. Check EAV table structure
    echo '<h2>2. EAV Table Structure</h2>';
    $query = "SHOW COLUMNS FROM `joomla_ordenproduccion_info`";
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>';
    foreach ($columns as $col) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($col->Field) . '</td>';
        echo '<td>' . htmlspecialchars($col->Type) . '</td>';
        echo '<td>' . htmlspecialchars($col->Null) . '</td>';
        echo '<td>' . htmlspecialchars($col->Key) . '</td>';
        echo '<td>' . htmlspecialchars($col->Default ?? 'NULL') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // 3. Check EAV data for this order
    echo '<h2>3. EAV Data for Order</h2>';
    
    $orderNumber = $order->order_number;
    echo '<p><strong>Looking for:</strong> numero_de_orden = ' . htmlspecialchars($orderNumber) . '</p>';
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_info'))
        ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));
    
    $db->setQuery($query);
    $eavRecords = $db->loadObjectList();
    
    if ($eavRecords) {
        echo '<p style="color: green;">✅ Found ' . count($eavRecords) . ' EAV records</p>';
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>ID</th><th>Numero de Orden</th><th>Tipo de Campo</th><th>Valor</th><th>State</th></tr>';
        foreach ($eavRecords as $rec) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($rec->id) . '</td>';
            echo '<td>' . htmlspecialchars($rec->numero_de_orden ?? 'NULL') . '</td>';
            echo '<td>' . htmlspecialchars($rec->tipo_de_campo ?? 'NULL') . '</td>';
            echo '<td>' . htmlspecialchars(substr($rec->valor ?? 'NULL', 0, 100)) . '</td>';
            echo '<td>' . htmlspecialchars($rec->state) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color: orange;">⚠️ No EAV records found</p>';
    }
    
    // 4. Test the exact query from OrdenModel
    echo '<h2>4. Test OrdenModel Query</h2>';
    
    $query = $db->getQuery(true)
        ->select($db->quoteName('tipo_de_campo') . ' AS attribute_name, ' . $db->quoteName('valor') . ' AS attribute_value')
        ->from($db->quoteName('#__ordenproduccion_info'))
        ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber))
        ->where($db->quoteName('state') . ' = 1');
    
    echo '<p><strong>SQL Query:</strong></p>';
    echo '<pre>' . htmlspecialchars($query->__toString()) . '</pre>';
    
    $db->setQuery($query);
    $results = $db->loadObjectList();
    
    if ($results) {
        echo '<p style="color: green;">✅ Query successful - ' . count($results) . ' records</p>';
        
        // Convert to associative array (same as model)
        $eavData = [];
        foreach ($results as $row) {
            $eavData[$row->attribute_name] = $row->attribute_value;
        }
        
        echo '<p><strong>EAV Data Array:</strong></p>';
        echo '<pre>';
        print_r($eavData);
        echo '</pre>';
        
        // Check for tiro_retiro specifically
        if (isset($eavData['tiro_retiro'])) {
            echo '<p style="color: green;">✅ Found tiro_retiro: ' . htmlspecialchars($eavData['tiro_retiro']) . '</p>';
        } else {
            echo '<p style="color: orange;">⚠️ tiro_retiro not found in EAV data</p>';
        }
        
        // Check for instrucciones_entrega
        if (isset($eavData['instrucciones_entrega'])) {
            echo '<p style="color: green;">✅ Found instrucciones_entrega: ' . htmlspecialchars($eavData['instrucciones_entrega']) . '</p>';
        } else {
            echo '<p style="color: orange;">⚠️ instrucciones_entrega not found in EAV data</p>';
        }
    } else {
        echo '<p style="color: red;">❌ Query returned no results</p>';
    }
    
    // 5. Try to load via model
    echo '<h2>5. Test via OrdenModel</h2>';
    
    try {
        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Orden', 'Site', ['ignore_request' => true]);
        $item = $model->getItem($orderId);
        
        if ($item) {
            echo '<p style="color: green;">✅ Model loaded order successfully</p>';
            echo '<p><strong>EAV Data property:</strong></p>';
            if (isset($item->eav_data)) {
                echo '<pre>';
                print_r($item->eav_data);
                echo '</pre>';
            } else {
                echo '<p style="color: red;">❌ eav_data property not set</p>';
            }
        } else {
            echo '<p style="color: red;">❌ Model failed to load order</p>';
        }
    } catch (\Exception $e) {
        echo '<p style="color: red;">❌ Model error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
    
} catch (\Exception $e) {
    echo '<h2 style="color: red;">Fatal Error</h2>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

echo '<hr>';
echo '<p><small>Debug completed at ' . date('Y-m-d H:i:s') . '</small></p>';

