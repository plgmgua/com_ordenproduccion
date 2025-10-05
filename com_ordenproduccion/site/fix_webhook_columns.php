<?php
/**
 * Fix Webhook Columns Script
 * Access via: /components/com_ordenproduccion/fix_webhook_columns.php
 * This script automatically fixes the WebhookModel column mapping
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Fix Webhook Columns</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

try {
    $db = Factory::getDbo();
    
    echo "<h2>🔍 Getting Database Schema</h2>";
    
    // Get actual database columns
    $query = "DESCRIBE joomla_ordenproduccion_ordenes";
    $db->setQuery($query);
    $actualColumns = $db->loadObjectList();
    
    $actualColumnNames = [];
    foreach ($actualColumns as $column) {
        $actualColumnNames[] = $column->Field;
    }
    
    echo "<div class='success'>✅ Found " . count($actualColumnNames) . " columns in database</div>";
    
    echo "<h2>🔧 Creating Fixed WebhookModel Code</h2>";
    
    // Create the corrected orderData array
    $orderDataFields = [
        // Core fields that should always exist
        'order_number' => '$orderNumber',
        'client_id' => '$formData[\'client_id\'] ?? \'0\'',
        'client_name' => '$formData[\'cliente\']',
        'nit' => '$formData[\'nit\'] ?? \'\'',
        'invoice_value' => '$formData[\'valor_factura\'] ?? 0',
        'work_description' => '$formData[\'descripcion_trabajo\']',
        'print_color' => '$formData[\'color_impresion\'] ?? \'\'',
        'dimensions' => '$formData[\'medidas\'] ?? \'\'',
        'delivery_date' => '$this->formatDate($formData[\'fecha_entrega\'])',
        'material' => '$formData[\'material\'] ?? \'\'',
        'quotation_files' => 'isset($formData[\'cotizacion\']) ? json_encode($formData[\'cotizacion\']) : \'\'',
        'art_files' => 'isset($formData[\'arte\']) ? json_encode($formData[\'arte\']) : \'\'',
        'cutting' => '$formData[\'corte\'] ?? \'NO\'',
        'cutting_details' => '$formData[\'detalles_corte\'] ?? \'\'',
        'blocking' => '$formData[\'blocado\'] ?? \'NO\'',
        'blocking_details' => '$formData[\'detalles_blocado\'] ?? \'\'',
        'folding' => '$formData[\'doblado\'] ?? \'NO\'',
        'folding_details' => '$formData[\'detalles_doblado\'] ?? \'\'',
        'laminating' => '$formData[\'laminado\'] ?? \'NO\'',
        'laminating_details' => '$formData[\'detalles_laminado\'] ?? \'\'',
        'spine' => '$formData[\'lomo\'] ?? \'NO\'',
        'gluing' => '$formData[\'pegado\'] ?? \'NO\'',
        'numbering' => '$formData[\'numerado\'] ?? \'NO\'',
        'numbering_details' => '$formData[\'detalles_numerado\'] ?? \'\'',
        'sizing' => '$formData[\'sizado\'] ?? \'NO\'',
        'stapling' => '$formData[\'engrapado\'] ?? \'NO\'',
        'die_cutting' => '$formData[\'troquel\'] ?? \'NO\'',
        'die_cutting_details' => '$formData[\'detalles_troquel\'] ?? \'\'',
        'varnish' => '$formData[\'barniz\'] ?? \'NO\'',
        'varnish_details' => '$formData[\'detalles_barniz\'] ?? \'\'',
        'white_print' => '$formData[\'impresion_blanco\'] ?? \'NO\'',
        'trimming' => '$formData[\'despuntado\'] ?? \'NO\'',
        'trimming_details' => '$formData[\'detalles_despuntado\'] ?? \'\'',
        'eyelets' => '$formData[\'ojetes\'] ?? \'NO\'',
        'perforation' => '$formData[\'perforado\'] ?? \'NO\'',
        'perforation_details' => '$formData[\'detalles_perforado\'] ?? \'\'',
        'instructions' => '$formData[\'instrucciones\'] ?? \'\'',
        'sales_agent' => '$formData[\'agente_de_ventas\'] ?? \'\'',
        'request_date' => '$formData[\'fecha_de_solicitud\'] ?? $now',
        'status' => '\'New\'',
        'order_type' => '\'External\'',
        'state' => '1',
        'created' => '$now',
        'created_by' => '0',
        'modified' => '$now',
        'modified_by' => '0',
        'version' => '\'1.0.0\''
    ];
    
    // Filter to only include columns that actually exist in the database
    $filteredOrderData = [];
    foreach ($orderDataFields as $column => $value) {
        if (in_array($column, $actualColumnNames)) {
            $filteredOrderData[$column] = $value;
        }
    }
    
    echo "<div class='success'>✅ Filtered to " . count($filteredOrderData) . " existing columns</div>";
    
    // Generate the corrected PHP code
    $phpCode = "            // Prepare order data - only use columns that exist in the database\n";
    $phpCode .= "            \$orderData = [\n";
    foreach ($filteredOrderData as $column => $value) {
        $phpCode .= "                '$column' => $value,\n";
    }
    $phpCode .= "            ];";
    
    echo "<h3>Corrected PHP Code:</h3>";
    echo "<div class='code'>" . htmlspecialchars($phpCode) . "</div>";
    
    echo "<h2>🧪 Test with Corrected Data</h2>";
    
    // Test with a sample payload
    $testPayload = [
        'client_id' => '7',
        'cliente' => 'Grupo Impre S.A.',
        'nit' => '114441782',
        'valor_factura' => '2500',
        'descripcion_trabajo' => '1000 Flyers Full Color con acabados especiales',
        'color_impresion' => 'Full Color',
        'medidas' => '8.5 x 11',
        'fecha_entrega' => '15/10/2025',
        'material' => 'Husky 250 grms',
        'corte' => 'SI',
        'detalles_corte' => 'Corte recto en guillotina'
    ];
    
    // Create test data with only existing columns
    $testOrderNumber = 'TEST-' . date('Ymd-His');
    $testData = [];
    
    foreach ($filteredOrderData as $column => $valueExpression) {
        // Replace variables with test values
        $value = str_replace([
            '$orderNumber',
            '$formData[\'client_id\'] ?? \'0\'',
            '$formData[\'cliente\']',
            '$formData[\'nit\'] ?? \'\'',
            '$formData[\'valor_factura\'] ?? 0',
            '$formData[\'descripcion_trabajo\']',
            '$formData[\'color_impresion\'] ?? \'\'',
            '$formData[\'medidas\'] ?? \'\'',
            '$formData[\'material\'] ?? \'\'',
            '$formData[\'corte\'] ?? \'NO\'',
            '$formData[\'detalles_corte\'] ?? \'\'',
            '$now',
            '\'New\'',
            '\'External\'',
            '1',
            '0',
            '\'1.0.0\''
        ], [
            $testOrderNumber,
            $testPayload['client_id'],
            $testPayload['cliente'],
            $testPayload['nit'],
            $testPayload['valor_factura'],
            $testPayload['descripcion_trabajo'],
            $testPayload['color_impresion'],
            $testPayload['medidas'],
            $testPayload['material'],
            $testPayload['corte'],
            $testPayload['detalles_corte'],
            date('Y-m-d H:i:s'),
            'New',
            'External',
            1,
            0,
            '1.0.0'
        ], $valueExpression);
        
        // Handle date formatting
        if (strpos($value, '$this->formatDate') !== false) {
            $value = '2025-10-15'; // Format the test date
        }
        
        // Handle JSON encoding
        if (strpos($value, 'json_encode') !== false) {
            $value = '""'; // Empty for test
        }
        
        $testData[$column] = $value;
    }
    
    echo "<h3>Test Data for Insert:</h3>";
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
        
        echo "<h2>✅ Solution Ready</h2>";
        echo "<p>The corrected code above should fix the 'Column count doesn't match value count' error.</p>";
        echo "<p><strong>Next step:</strong> Update the WebhookModel.php file with the corrected orderData array.</p>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Test insert failed: " . $e->getMessage() . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
