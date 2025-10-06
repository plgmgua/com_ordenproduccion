<?php
/**
 * Simple script to check actual database structure
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
    
    echo "<h1>Database Structure Check</h1>\n";
    
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
    
    // Get actual table structure
    $query = "DESCRIBE $tableName";
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo "<h2>Actual Table Structure:</h2>\n";
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
    $query = "SELECT COUNT(*) FROM $tableName";
    $db->setQuery($query);
    $totalRecords = $db->loadResult();
    
    echo "<h2>Records Summary:</h2>\n";
    echo "<p>Total records: $totalRecords</p>\n";
    
    if ($totalRecords > 0) {
        // Get first record to see actual field names
        $query = "SELECT * FROM $tableName LIMIT 1";
        $db->setQuery($query);
        $sampleRecord = $db->loadObject();
        
        echo "<h2>Sample Record (First Record):</h2>\n";
        echo "<pre>" . print_r($sampleRecord, true) . "</pre>\n";
        
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
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
