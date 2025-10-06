<?php
/**
 * Deep database debugging script
 * Check actual database structure and data
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
    
    echo "<h1>🔍 Deep Database Debugging</h1>\n";
    echo "<h2>Component Version: 1.8.32-ALPHA</h2>\n";
    echo "<hr>\n";
    
    // 1. Check if the webhook table exists and its structure
    echo "<h3>📋 1. Webhook Table Structure Check</h3>\n";
    
    $webhookTable = '#__ordenproduccion_ordenes';
    $query = "SHOW TABLES LIKE '" . str_replace('#__', $db->getPrefix(), $webhookTable) . "'";
    $db->setQuery($query);
    $tables = $db->loadColumn();
    
    if (empty($tables)) {
        echo "<p>❌ <strong>Webhook table does not exist:</strong> $webhookTable</p>\n";
        
        // Check what tables do exist
        echo "<h4>Available tables with 'orden' in name:</h4>\n";
        $query = "SHOW TABLES LIKE '%orden%'";
        $db->setQuery($query);
        $allTables = $db->loadColumn();
        foreach ($allTables as $table) {
            echo "<p>📋 $table</p>\n";
        }
    } else {
        $actualTable = $tables[0];
        echo "<p>✅ <strong>Webhook table found:</strong> $actualTable</p>\n";
        
        // Get table structure
        $query = "DESCRIBE $actualTable";
        $db->setQuery($query);
        $columns = $db->loadObjectList();
        
        echo "<h4>Table Structure:</h4>\n";
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
            $query = "SELECT * FROM $actualTable LIMIT 5";
            $db->setQuery($query);
            $records = $db->loadObjectList();
            
            echo "<h4>Sample Records:</h4>\n";
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
    
    // 2. Check old table structure if it exists
    echo "<h3>📋 2. Old Table Structure Check</h3>\n";
    
    $oldTable = '#__ordenes_de_trabajo';
    $query = "SHOW TABLES LIKE '" . str_replace('#__', $db->getPrefix(), $oldTable) . "'";
    $db->setQuery($query);
    $oldTables = $db->loadColumn();
    
    if (!empty($oldTables)) {
        $actualOldTable = $oldTables[0];
        echo "<p>✅ <strong>Old table found:</strong> $actualOldTable</p>\n";
        
        $query = "SELECT COUNT(*) FROM $actualOldTable";
        $db->setQuery($query);
        $oldTotalRecords = $db->loadResult();
        echo "<p><strong>Total records in old table:</strong> $oldTotalRecords</p>\n";
        
        if ($oldTotalRecords > 0) {
            // Check for record with ID 15 in old table
            $query = "SELECT * FROM $actualOldTable WHERE id = 15";
            $db->setQuery($query);
            $oldRecord15 = $db->loadObject();
            
            if ($oldRecord15) {
                echo "<p>✅ <strong>Record with ID 15 found in old table:</strong></p>\n";
                echo "<table border='1'>\n";
                foreach (get_object_vars($oldRecord15) as $key => $value) {
                    echo "<tr><th>$key</th><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>\n";
                }
                echo "</table>\n";
            }
        }
    } else {
        echo "<p>❌ <strong>Old table does not exist:</strong> $oldTable</p>\n";
    }
    
    // 3. Check component configuration
    echo "<h3>⚙️ 3. Component Configuration</h3>\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "<p>✅ <strong>Component found:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Name: {$component->name}</li>\n";
        echo "<li>Enabled: " . ($component->enabled ? 'Yes' : 'No') . "</li>\n";
        echo "<li>Version: {$component->version}</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p>❌ <strong>Component not found in database</strong></p>\n";
    }
    
    // 4. Check menu items
    echo "<h3>🔗 4. Menu Items Check</h3>\n";
    
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%com_ordenproduccion%'));
    
    $db->setQuery($query);
    $menuItems = $db->loadObjectList();
    
    if (count($menuItems) > 0) {
        echo "<p>✅ <strong>Menu items found:</strong></p>\n";
        echo "<table border='1'>\n";
        echo "<tr><th>ID</th><th>Title</th><th>Link</th><th>Published</th></tr>\n";
        foreach ($menuItems as $item) {
            echo "<tr>";
            echo "<td>{$item->id}</td>";
            echo "<td>" . htmlspecialchars($item->title) . "</td>";
            echo "<td>" . htmlspecialchars($item->link) . "</td>";
            echo "<td>" . ($item->published ? 'Yes' : 'No') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p>❌ <strong>No menu items found for component</strong></p>\n";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>\n";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p><strong>Stack trace:</strong></p>\n";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "<hr>\n";
echo "<p><strong>Debug completed:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>
