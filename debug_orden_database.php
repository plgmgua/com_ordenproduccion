<?php
/**
 * Debug script to check orden database records
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
    
    echo "<h1>Debug Orden Database</h1>\n";
    
    // Check if table exists
    $query = "SHOW TABLES LIKE '%ordenproduccion_ordenes%'";
    $db->setQuery($query);
    $tables = $db->loadColumn();
    
    if (empty($tables)) {
        echo "<p>❌ Table #__ordenproduccion_ordenes does not exist</p>\n";
        exit;
    }
    
    $tableName = $tables[0];
    echo "<p>✅ Table found: $tableName</p>\n";
    
    // Check table structure
    $query = "DESCRIBE $tableName";
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo "<h2>Table Structure:</h2>\n";
    echo "<table border='1'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "<td>{$column->Extra}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Check all records
    $query = "SELECT * FROM $tableName ORDER BY id";
    $db->setQuery($query);
    $records = $db->loadObjectList();
    
    echo "<h2>All Records:</h2>\n";
    echo "<p>Total records: " . count($records) . "</p>\n";
    
    if (!empty($records)) {
        echo "<table border='1'>\n";
        echo "<tr><th>ID</th><th>State</th><th>Order Number</th><th>Client</th><th>Sales Agent</th><th>Created</th></tr>\n";
        foreach ($records as $record) {
            echo "<tr>";
            echo "<td>{$record->id}</td>";
            echo "<td>{$record->state}</td>";
            echo "<td>" . ($record->orden_de_trabajo ?? 'N/A') . "</td>";
            echo "<td>" . ($record->nombre_del_cliente ?? 'N/A') . "</td>";
            echo "<td>" . ($record->agente_de_ventas ?? 'N/A') . "</td>";
            echo "<td>" . ($record->created ?? 'N/A') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    // Check specific record with ID 15
    $query = "SELECT * FROM $tableName WHERE id = 15";
    $db->setQuery($query);
    $record15 = $db->loadObject();
    
    echo "<h2>Record with ID 15:</h2>\n";
    if ($record15) {
        echo "<p>✅ Record found:</p>\n";
        echo "<pre>" . print_r($record15, true) . "</pre>\n";
    } else {
        echo "<p>❌ No record found with ID 15</p>\n";
    }
    
    // Check if there are any records with state = 1
    $query = "SELECT COUNT(*) FROM $tableName WHERE state = 1";
    $db->setQuery($query);
    $publishedCount = $db->loadResult();
    
    echo "<h2>Published Records (state = 1):</h2>\n";
    echo "<p>Count: $publishedCount</p>\n";
    
    if ($publishedCount > 0) {
        $query = "SELECT id, state, orden_de_trabajo, nombre_del_cliente FROM $tableName WHERE state = 1 ORDER BY id";
        $db->setQuery($query);
        $publishedRecords = $db->loadObjectList();
        
        echo "<table border='1'>\n";
        echo "<tr><th>ID</th><th>State</th><th>Order Number</th><th>Client</th></tr>\n";
        foreach ($publishedRecords as $record) {
            echo "<tr>";
            echo "<td>{$record->id}</td>";
            echo "<td>{$record->state}</td>";
            echo "<td>" . ($record->orden_de_trabajo ?? 'N/A') . "</td>";
            echo "<td>" . ($record->nombre_del_cliente ?? 'N/A') . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
