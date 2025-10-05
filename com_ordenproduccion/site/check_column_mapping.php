<?php
/**
 * Check Column Mapping Script
 * Access via: /components/com_ordenproduccion/check_column_mapping.php
 * This script checks which columns exist and fixes the mapping
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Column Mapping Check and Fix</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

try {
    $db = Factory::getDbo();
    
    echo "<h2>🔍 Current Database Columns</h2>";
    
    // Get actual database columns
    $query = "DESCRIBE joomla_ordenproduccion_ordenes";
    $db->setQuery($query);
    $actualColumns = $db->loadObjectList();
    
    echo "<h3>Actual Database Columns (" . count($actualColumns) . " total):</h3>";
    $actualColumnNames = [];
    foreach ($actualColumns as $column) {
        $actualColumnNames[] = $column->Field;
        echo "<div class='info'>• " . $column->Field . " (" . $column->Type . ")</div>";
    }
    
    echo "<h2>🔧 WebhookModel Columns</h2>";
    
    // Define what WebhookModel is trying to insert
    $webhookColumns = [
        'order_number',
        'client_id', 
        'client_name',
        'nit',
        'invoice_value',
        'work_description',
        'print_color',
        'dimensions',
        'delivery_date',
        'material',
        'quotation_files',
        'art_files',
        'cutting',
        'cutting_details',
        'blocking',
        'blocking_details',
        'folding',
        'folding_details',
        'laminating',
        'laminating_details',
        'spine',
        'gluing',
        'numbering',
        'numbering_details',
        'sizing',
        'stapling',
        'die_cutting',
        'die_cutting_details',
        'varnish',
        'varnish_details',
        'white_print',
        'trimming',
        'trimming_details',
        'eyelets',
        'perforation',
        'perforation_details',
        'instructions',
        'sales_agent',
        'request_date',
        'status',
        'order_type',
        'state',
        'created',
        'created_by',
        'modified',
        'modified_by',
        'version'
    ];
    
    echo "<h3>WebhookModel Columns (" . count($webhookColumns) . " total):</h3>";
    foreach ($webhookColumns as $column) {
        echo "<div class='info'>• $column</div>";
    }
    
    echo "<h2>🔍 Column Comparison</h2>";
    
    // Find missing columns (in webhook but not in database)
    $missingColumns = array_diff($webhookColumns, $actualColumnNames);
    if (!empty($missingColumns)) {
        echo "<h3 class='error'>❌ Missing Columns (WebhookModel tries to use but don't exist in DB):</h3>";
        foreach ($missingColumns as $column) {
            echo "<div class='error'>• $column</div>";
        }
    } else {
        echo "<div class='success'>✅ All WebhookModel columns exist in database</div>";
    }
    
    // Find extra columns (in database but not used by webhook)
    $extraColumns = array_diff($actualColumnNames, $webhookColumns);
    if (!empty($extraColumns)) {
        echo "<h3 class='warning'>⚠️ Extra Columns (exist in DB but not used by WebhookModel):</h3>";
        foreach ($extraColumns as $column) {
            echo "<div class='warning'>• $column</div>";
        }
    }
    
    echo "<h2>🔧 Fixed Column Mapping</h2>";
    
    // Create the corrected column mapping
    $correctedColumns = array_intersect($webhookColumns, $actualColumnNames);
    
    echo "<h3>Corrected WebhookModel Columns (" . count($correctedColumns) . " total):</h3>";
    foreach ($correctedColumns as $column) {
        echo "<div class='success'>• $column</div>";
    }
    
    if (!empty($missingColumns)) {
        echo "<h3 class='error'>❌ Columns to REMOVE from WebhookModel:</h3>";
        foreach ($missingColumns as $column) {
            echo "<div class='error'>• $column</div>";
        }
    }
    
    // Test a simple insert with only existing columns
    echo "<h2>🧪 Test Insert with Corrected Columns</h2>";
    
    $testOrderNumber = 'TEST-' . date('Ymd-His');
    $testData = [
        'order_number' => $testOrderNumber,
        'client_id' => '7',
        'client_name' => 'Test Client',
        'nit' => '123456789',
        'invoice_value' => 1000.00,
        'work_description' => 'Test work order',
        'delivery_date' => '2025-10-05',
        'state' => 1,
        'created' => date('Y-m-d H:i:s'),
        'created_by' => 0,
        'modified' => date('Y-m-d H:i:s'),
        'modified_by' => 0,
        'version' => '1.0.0'
    ];
    
    // Only use columns that actually exist
    $testData = array_intersect_key($testData, array_flip($correctedColumns));
    
    echo "<h3>Test Data (only existing columns):</h3>";
    echo "<div class='code'>";
    foreach ($testData as $key => $value) {
        echo "$key => $value\n";
    }
    echo "</div>";
    
    // Test the insert
    $query = $db->getQuery(true)
        ->insert($db->quoteName('joomla_ordenproduccion_ordenes'))
        ->columns(array_keys($testData))
        ->values(array_map([$db, 'quote'], $testData));
    
    $db->setQuery($query);
    try {
        $db->execute();
        $insertId = $db->insertid();
        echo "<div class='success'>✅ Test insert successful! Insert ID: $insertId</div>";
        
        // Clean up test record
        $deleteQuery = "DELETE FROM joomla_ordenproduccion_ordenes WHERE id = $insertId";
        $db->setQuery($deleteQuery);
        $db->execute();
        echo "<div class='info'>✅ Test record cleaned up</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Test insert failed: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>📋 Summary</h2>";
    echo "<p><strong>Problem:</strong> WebhookModel is trying to insert columns that don't exist in the database.</p>";
    echo "<p><strong>Solution:</strong> Remove the missing columns from WebhookModel's createOrder() method.</p>";
    
    if (!empty($missingColumns)) {
        echo "<p><strong>Action Required:</strong> Update WebhookModel to remove these columns:</p>";
        echo "<div class='code'>";
        foreach ($missingColumns as $column) {
            echo "// Remove: '$column' => \$formData['...'],\n";
        }
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
