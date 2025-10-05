<?php
/**
 * Fix Database Columns Script
 * Access via: /components/com_ordenproduccion/fix_database_columns.php
 * This script checks and fixes missing database columns
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Database Columns Fix Script</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

try {
    echo "<h2>🔍 Checking Database Table Structure</h2>";
    
    $db = Factory::getDbo();
    
    // Check if the main table exists
    echo "<h3>Step 1: Checking if table exists</h3>";
    $query = "SHOW TABLES LIKE '#__ordenproduccion_ordenes'";
    $db->setQuery($query);
    $tableExists = $db->loadResult();
    
    if (!$tableExists) {
        echo "<div class='error'>❌ Table #__ordenproduccion_ordenes does not exist!</div>";
        echo "<div class='info'>We need to create the table first.</div>";
        
        // Read the install script
        $installScript = JPATH_BASE . '/administrator/components/com_ordenproduccion/sql/install.mysql.utf8.sql';
        if (file_exists($installScript)) {
            echo "<div class='info'>Found install script: $installScript</div>";
            $sql = file_get_contents($installScript);
            echo "<div class='code'>" . htmlspecialchars($sql) . "</div>";
            
            echo "<h3>Creating table from install script...</h3>";
            $queries = explode(';', $sql);
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) && !preg_match('/^--/', $query)) {
                    $query = str_replace('#__', $db->getPrefix(), $query);
                    $db->setQuery($query);
                    try {
                        $db->execute();
                        echo "<div class='success'>✓ Executed: " . substr($query, 0, 50) . "...</div>";
                    } catch (Exception $e) {
                        echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
                    }
                }
            }
        } else {
            echo "<div class='error'>❌ Install script not found at: $installScript</div>";
        }
    } else {
        echo "<div class='success'>✓ Table #__ordenproduccion_ordenes exists</div>";
    }
    
    // Check table structure
    echo "<h3>Step 2: Checking table structure</h3>";
    $query = "DESCRIBE #__ordenproduccion_ordenes";
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    if (empty($columns)) {
        echo "<div class='error'>❌ Could not get table structure</div>";
    } else {
        echo "<div class='success'>✓ Found " . count($columns) . " columns in table</div>";
        echo "<h4>Current columns:</h4>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li><strong>" . $column->Field . "</strong> - " . $column->Type . " (" . $column->Null . ", " . $column->Key . ")</li>";
        }
        echo "</ul>";
        
        // Check for the missing column
        $hasOrdenDeTrabajo = false;
        foreach ($columns as $column) {
            if ($column->Field === 'orden_de_trabajo') {
                $hasOrdenDeTrabajo = true;
                break;
            }
        }
        
        if (!$hasOrdenDeTrabajo) {
            echo "<div class='error'>❌ Column 'orden_de_trabajo' is missing!</div>";
            echo "<h3>Step 3: Adding missing column</h3>";
            
            $alterQuery = "ALTER TABLE #__ordenproduccion_ordenes ADD COLUMN orden_de_trabajo VARCHAR(50) NOT NULL AFTER id";
            $db->setQuery($alterQuery);
            try {
                $db->execute();
                echo "<div class='success'>✓ Added column 'orden_de_trabajo'</div>";
            } catch (Exception $e) {
                echo "<div class='error'>✗ Error adding column: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='success'>✓ Column 'orden_de_trabajo' exists</div>";
        }
    }
    
    // Check EAV table
    echo "<h3>Step 4: Checking EAV table</h3>";
    $query = "SHOW TABLES LIKE '#__ordenproduccion_info'";
    $db->setQuery($query);
    $eavTableExists = $db->loadResult();
    
    if (!$eavTableExists) {
        echo "<div class='error'>❌ EAV table #__ordenproduccion_info does not exist!</div>";
        echo "<h4>Creating EAV table...</h4>";
        
        $createEavQuery = "CREATE TABLE IF NOT EXISTS #__ordenproduccion_info (
            id int(11) NOT NULL AUTO_INCREMENT,
            numero_de_orden varchar(50) NOT NULL,
            tipo_de_campo varchar(50) NOT NULL,
            valor text,
            usuario varchar(100) DEFAULT NULL,
            timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            state tinyint(3) NOT NULL DEFAULT 1,
            created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by int(11) NOT NULL DEFAULT 0,
            modified datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            modified_by int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_numero_orden (numero_de_orden),
            KEY idx_tipo_campo (tipo_de_campo),
            KEY idx_timestamp (timestamp),
            KEY idx_state (state),
            KEY idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->setQuery($createEavQuery);
        try {
            $db->execute();
            echo "<div class='success'>✓ Created EAV table #__ordenproduccion_info</div>";
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error creating EAV table: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='success'>✓ EAV table #__ordenproduccion_info exists</div>";
        
        // Check EAV table structure
        $query = "DESCRIBE #__ordenproduccion_info";
        $db->setQuery($query);
        $eavColumns = $db->loadObjectList();
        
        echo "<h4>EAV table columns:</h4>";
        echo "<ul>";
        foreach ($eavColumns as $column) {
            echo "<li><strong>" . $column->Field . "</strong> - " . $column->Type . "</li>";
        }
        echo "</ul>";
    }
    
    // Test a simple insert to verify the fix
    echo "<h3>Step 5: Testing database fix</h3>";
    
    $testOrderNumber = 'TEST-' . date('Ymd-His');
    $insertQuery = "INSERT INTO #__ordenproduccion_ordenes 
        (orden_de_trabajo, nombre_del_cliente, descripcion_de_trabajo, fecha_de_entrega, state, created, created_by, version) 
        VALUES ('$testOrderNumber', 'Test Client', 'Test Description', '2025-10-05', 1, NOW(), 0, '1.0.0')";
    
    $db->setQuery($insertQuery);
    try {
        $db->execute();
        $insertId = $db->insertid();
        echo "<div class='success'>✓ Test insert successful! Insert ID: $insertId</div>";
        
        // Clean up test record
        $deleteQuery = "DELETE FROM #__ordenproduccion_ordenes WHERE id = $insertId";
        $db->setQuery($deleteQuery);
        $db->execute();
        echo "<div class='info'>✓ Test record cleaned up</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>✗ Test insert failed: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>✅ Database Fix Complete</h2>";
    echo "<p>If all steps above show success, the production webhook should now work.</p>";
    echo "<p><strong>Next step:</strong> Test the production webhook again with your payload.</p>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Fatal error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace:</div>";
    echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
}
?>
