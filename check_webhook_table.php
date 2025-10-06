<?php
/**
 * Check webhook table structure and data
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
    
    echo "<h1>🔍 Webhook Table Check</h1>\n";
    
    // Check if webhook table exists
    $webhookTable = '#__ordenproduccion_ordenes';
    $actualTable = str_replace('#__', $db->getPrefix(), $webhookTable);
    
    $query = "SHOW TABLES LIKE '$actualTable'";
    $db->setQuery($query);
    $tables = $db->loadColumn();
    
    if (empty($tables)) {
        echo "<p>❌ <strong>Webhook table does not exist:</strong> $actualTable</p>\n";
        
        // Check what tables do exist
        echo "<h3>Available tables with 'orden' in name:</h3>\n";
        $query = "SHOW TABLES LIKE '%orden%'";
        $db->setQuery($query);
        $allTables = $db->loadColumn();
        foreach ($allTables as $table) {
            echo "<p>📋 $table</p>\n";
        }
    } else {
        echo "<p>✅ <strong>Webhook table found:</strong> $actualTable</p>\n";
        
        // Get table structure
        $query = "DESCRIBE $actualTable";
        $db->setQuery($query);
        $columns = $db->loadObjectList();
        
        echo "<h3>Table Structure:</h3>\n";
        echo "<table border='1'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td><strong>{$column->Field}</strong></td>";
            echo "<td>{$column->Type}</td>";
            echo "<td>{$column->Null}</td>";
            echo "<td>{$column->Key}</td>";
            echo "<td>{$column->Default}</td>";
            echo "<td>{$column->Extra}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        // Check if there are any records
        $query = "SELECT COUNT(*) FROM $actualTable";
        $db->setQuery($query);
        $totalRecords = $db->loadResult();
        echo "<p><strong>Total records:</strong> $totalRecords</p>\n";
        
        if ($totalRecords > 0) {
            // Get first few records
            $query = "SELECT * FROM $actualTable LIMIT 3";
            $db->setQuery($query);
            $records = $db->loadObjectList();
            
            echo "<h3>Sample Records:</h3>\n";
            echo "<table border='1'>\n";
            if (!empty($records)) {
                // Get column headers from first record
                $firstRecord = $records[0];
                echo "<tr>";
                foreach (get_object_vars($firstRecord) as $key => $value) {
                    echo "<th>$key</th>";
                }
                echo "</tr>\n";
                
                foreach ($records as $record) {
                    echo "<tr>";
                    foreach (get_object_vars($record) as $key => $value) {
                        echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                    }
                    echo "</tr>\n";
                }
            }
            echo "</table>\n";
            
            // Check specifically for record with ID 15
            $query = "SELECT * FROM $actualTable WHERE id = 15";
            $db->setQuery($query);
            $record15 = $db->loadObject();
            
            echo "<h3>Record with ID 15:</h3>\n";
            if ($record15) {
                echo "<p>✅ <strong>Record found:</strong></p>\n";
                echo "<table border='1'>\n";
                foreach (get_object_vars($record15) as $key => $value) {
                    echo "<tr><th>$key</th><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>\n";
                }
                echo "</table>\n";
            } else {
                echo "<p>❌ <strong>No record found with ID 15</strong></p>\n";
                
                // Show available IDs
                $query = "SELECT id, order_number, client_name, state FROM $actualTable ORDER BY id LIMIT 10";
                $db->setQuery($query);
                $availableIds = $db->loadObjectList();
                
                echo "<h4>Available Records (first 10):</h4>\n";
                echo "<table border='1'>\n";
                echo "<tr><th>ID</th><th>Order Number</th><th>Client Name</th><th>State</th></tr>\n";
                foreach ($availableIds as $record) {
                    echo "<tr>";
                    echo "<td>{$record->id}</td>";
                    echo "<td>" . htmlspecialchars($record->order_number ?? 'NULL') . "</td>";
                    echo "<td>" . htmlspecialchars($record->client_name ?? 'NULL') . "</td>";
                    echo "<td>{$record->state}</td>";
                    echo "</tr>\n";
                }
                echo "</table>\n";
            }
        }
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
