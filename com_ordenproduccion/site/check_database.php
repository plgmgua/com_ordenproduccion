<?php
/**
 * Quick Database Check Script
 * Access via: /components/com_ordenproduccion/check_database.php
 * This script quickly checks what's wrong with the database
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Quick Database Check</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

try {
    $db = Factory::getDbo();
    
    echo "<h2>🔍 Database Status Check</h2>";
    
    // Check what tables exist
    echo "<h3>Tables with 'ordenproduccion' in name:</h3>";
    $query = "SHOW TABLES LIKE '%ordenproduccion%'";
    $db->setQuery($query);
    $tables = $db->loadColumn();
    
    if (empty($tables)) {
        echo "<div class='error'>❌ No ordenproduccion tables found!</div>";
        echo "<div class='info'>The component tables were never created.</div>";
    } else {
        echo "<div class='success'>✓ Found " . count($tables) . " tables:</div>";
        foreach ($tables as $table) {
            echo "<div class='info'>• $table</div>";
        }
    }
    
    // Check the main table structure
    $mainTable = $db->getPrefix() . 'ordenproduccion_ordenes';
    echo "<h3>Structure of $mainTable:</h3>";
    
    $query = "DESCRIBE $mainTable";
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    if (empty($columns)) {
        echo "<div class='error'>❌ Table $mainTable does not exist or has no columns</div>";
    } else {
        echo "<div class='success'>✓ Table has " . count($columns) . " columns:</div>";
        echo "<div class='code'>";
        foreach ($columns as $column) {
            echo $column->Field . " - " . $column->Type . "\n";
        }
        echo "</div>";
        
        // Check for the specific missing column
        $hasOrdenDeTrabajo = false;
        foreach ($columns as $column) {
            if ($column->Field === 'orden_de_trabajo') {
                $hasOrdenDeTrabajo = true;
                break;
            }
        }
        
        if ($hasOrdenDeTrabajo) {
            echo "<div class='success'>✅ Column 'orden_de_trabajo' exists!</div>";
        } else {
            echo "<div class='error'>❌ Column 'orden_de_trabajo' is missing!</div>";
            echo "<div class='info'>This is why the webhook is failing.</div>";
        }
    }
    
    // Check EAV table
    $eavTable = $db->getPrefix() . 'ordenproduccion_info';
    echo "<h3>Structure of $eavTable:</h3>";
    
    $query = "DESCRIBE $eavTable";
    $db->setQuery($query);
    $eavColumns = $db->loadObjectList();
    
    if (empty($eavColumns)) {
        echo "<div class='error'>❌ EAV table $eavTable does not exist</div>";
    } else {
        echo "<div class='success'>✓ EAV table has " . count($eavColumns) . " columns</div>";
        echo "<div class='code'>";
        foreach ($eavColumns as $column) {
            echo $column->Field . " - " . $column->Type . "\n";
        }
        echo "</div>";
    }
    
    echo "<h2>🎯 Summary</h2>";
    if (empty($tables)) {
        echo "<div class='error'>❌ PROBLEM: No component tables exist. Need to run installation.</div>";
    } elseif (!$hasOrdenDeTrabajo) {
        echo "<div class='error'>❌ PROBLEM: Missing 'orden_de_trabajo' column. Need to fix table structure.</div>";
    } else {
        echo "<div class='success'>✅ Database structure looks correct.</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
}
?>
