<?php
/**
 * Check webhook data and logs
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

try {
    $app = Factory::getApplication('site');
    $db = Factory::getDbo();
    
    echo "<h1>🔍 Webhook Data Check</h1>\n";
    
    // 1. Check webhook logs table
    echo "<h3>📋 1. Webhook Logs Table</h3>\n";
    
    $logsTable = '#__ordenproduccion_webhook_logs';
    $actualLogsTable = str_replace('#__', $db->getPrefix(), $logsTable);
    
    $query = "SHOW TABLES LIKE '$actualLogsTable'";
    $db->setQuery($query);
    $logTables = $db->loadColumn();
    
    if (!empty($logTables)) {
        $query = "SELECT COUNT(*) FROM $actualLogsTable";
        $db->setQuery($query);
        $logCount = $db->loadResult();
        echo "<p>✅ <strong>Webhook logs table found:</strong> $actualLogsTable ($logCount records)</p>\n";
        
        if ($logCount > 0) {
            $query = "SELECT * FROM $actualLogsTable ORDER BY created DESC LIMIT 5";
            $db->setQuery($query);
            $logs = $db->loadObjectList();
            
            echo "<h4>Recent Webhook Logs:</h4>\n";
            echo "<table border='1'>\n";
            echo "<tr><th>ID</th><th>Type</th><th>IP</th><th>Created</th><th>Data (first 100 chars)</th></tr>\n";
            foreach ($logs as $log) {
                $dataPreview = substr($log->data ?? '', 0, 100);
                echo "<tr>";
                echo "<td>{$log->id}</td>";
                echo "<td>" . htmlspecialchars($log->type ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($log->ip_address ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($log->created ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($dataPreview) . "...</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        echo "<p>❌ <strong>Webhook logs table does not exist:</strong> $actualLogsTable</p>\n";
    }
    
    // 2. Check orders table
    echo "<h3>📋 2. Orders Table</h3>\n";
    
    $ordersTable = '#__ordenproduccion_ordenes';
    $actualOrdersTable = str_replace('#__', $db->getPrefix(), $ordersTable);
    
    $query = "SHOW TABLES LIKE '$actualOrdersTable'";
    $db->setQuery($query);
    $orderTables = $db->loadColumn();
    
    if (!empty($orderTables)) {
        $query = "SELECT COUNT(*) FROM $actualOrdersTable";
        $db->setQuery($query);
        $orderCount = $db->loadResult();
        echo "<p>✅ <strong>Orders table found:</strong> $actualOrdersTable ($orderCount records)</p>\n";
        
        if ($orderCount > 0) {
            $query = "SELECT id, order_number, client_name, state, created FROM $actualOrdersTable ORDER BY id DESC LIMIT 5";
            $db->setQuery($query);
            $orders = $db->loadObjectList();
            
            echo "<h4>Recent Orders:</h4>\n";
            echo "<table border='1'>\n";
            echo "<tr><th>ID</th><th>Order Number</th><th>Client Name</th><th>State</th><th>Created</th></tr>\n";
            foreach ($orders as $order) {
                echo "<tr>";
                echo "<td>{$order->id}</td>";
                echo "<td>" . htmlspecialchars($order->order_number ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($order->client_name ?? 'NULL') . "</td>";
                echo "<td>{$order->state}</td>";
                echo "<td>" . htmlspecialchars($order->created ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
            
            // Check specifically for record with ID 15
            $query = "SELECT * FROM $actualOrdersTable WHERE id = 15";
            $db->setQuery($query);
            $record15 = $db->loadObject();
            
            echo "<h4>Record with ID 15:</h4>\n";
            if ($record15) {
                echo "<p>✅ <strong>Record found:</strong></p>\n";
                echo "<table border='1'>\n";
                foreach (get_object_vars($record15) as $key => $value) {
                    echo "<tr><th>$key</th><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>\n";
                }
                echo "</table>\n";
            } else {
                echo "<p>❌ <strong>No record found with ID 15</strong></p>\n";
            }
        }
    } else {
        echo "<p>❌ <strong>Orders table does not exist:</strong> $actualOrdersTable</p>\n";
    }
    
    // 3. Check EAV table
    echo "<h3>📋 3. EAV Info Table</h3>\n";
    
    $eavTable = '#__ordenproduccion_info';
    $actualEavTable = str_replace('#__', $db->getPrefix(), $eavTable);
    
    $query = "SHOW TABLES LIKE '$actualEavTable'";
    $db->setQuery($query);
    $eavTables = $db->loadColumn();
    
    if (!empty($eavTables)) {
        $query = "SELECT COUNT(*) FROM $actualEavTable";
        $db->setQuery($query);
        $eavCount = $db->loadResult();
        echo "<p>✅ <strong>EAV info table found:</strong> $actualEavTable ($eavCount records)</p>\n";
        
        if ($eavCount > 0) {
            $query = "SELECT * FROM $actualEavTable ORDER BY created DESC LIMIT 5";
            $db->setQuery($query);
            $eavRecords = $db->loadObjectList();
            
            echo "<h4>Recent EAV Records:</h4>\n";
            echo "<table border='1'>\n";
            echo "<tr><th>ID</th><th>Order ID</th><th>Attribute</th><th>Value (first 50 chars)</th><th>Created</th></tr>\n";
            foreach ($eavRecords as $eav) {
                $valuePreview = substr($eav->attribute_value ?? '', 0, 50);
                echo "<tr>";
                echo "<td>{$eav->id}</td>";
                echo "<td>{$eav->order_id}</td>";
                echo "<td>" . htmlspecialchars($eav->attribute_name ?? 'NULL') . "</td>";
                echo "<td>" . htmlspecialchars($valuePreview) . "...</td>";
                echo "<td>" . htmlspecialchars($eav->created ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } else {
        echo "<p>❌ <strong>EAV info table does not exist:</strong> $actualEavTable</p>\n";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Stack trace:</strong></p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "<hr>\n";
echo "<p><strong>Check completed:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>
