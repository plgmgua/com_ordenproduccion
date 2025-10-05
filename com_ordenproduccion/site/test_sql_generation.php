<?php
/**
 * Test SQL Generation Script
 * Access via: /components/com_ordenproduccion/test_sql_generation.php
 * This script tests SQL generation without instantiating WebhookModel
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Test SQL Generation</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

try {
    echo "<h2>🔍 Testing SQL Generation Without WebhookModel</h2>";
    
    // Test payload
    $testPayload = [
        'request_title' => 'Solicitud Ventas a Produccion',
        'form_data' => [
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
        ]
    ];
    
    echo "<h3>Test Payload:</h3>";
    echo "<div class='code'>" . json_encode($testPayload, JSON_PRETTY_PRINT) . "</div>";
    
    // Step 1: Generate Order Number (simulate WebhookModel logic)
    echo "<h3>Step 1: Generate Order Number</h3>";
    
    $formData = $testPayload['form_data'];
    $clientName = $formData['cliente'] ?? 'CLIENT';
    $clientCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $clientName), 0, 4));
    $date = date('Ymd');
    $time = date('His');
    $orderNumber = $clientCode . '-' . $date . '-' . $time;
    
    echo "<div class='success'>✓ Generated Order Number: <strong>$orderNumber</strong></div>";
    
    // Step 2: Format Date (simulate WebhookModel logic)
    echo "<h3>Step 2: Format Date</h3>";
    
    $dateInput = $formData['fecha_entrega'];
    $formattedDate = null;
    
    if (!empty($dateInput)) {
        // Handle DD/MM/YYYY format (from webhook payload)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateInput, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $formattedDate = $year . '-' . $month . '-' . $day;
        } else {
            $formattedDate = $dateInput;
        }
    }
    
    echo "<div class='success'>✓ Formatted Date: <strong>$formattedDate</strong></div>";
    
    // Step 3: Create Order Data Array (exact copy from WebhookModel)
    echo "<h3>Step 3: Create Order Data Array</h3>";
    
    $now = date('Y-m-d H:i:s');
    
    $orderData = [
        'order_number' => $orderNumber,
        'client_id' => $formData['client_id'] ?? '0',
        'client_name' => $formData['cliente'],
        'nit' => $formData['nit'] ?? '',
        'invoice_value' => $formData['valor_factura'] ?? 0,
        'work_description' => $formData['descripcion_trabajo'],
        'print_color' => $formData['color_impresion'] ?? '',
        'dimensions' => $formData['medidas'] ?? '',
        'delivery_date' => $formattedDate,
        'material' => $formData['material'] ?? '',
        'quotation_files' => isset($formData['cotizacion']) ? json_encode($formData['cotizacion']) : '',
        'art_files' => isset($formData['arte']) ? json_encode($formData['arte']) : '',
        'cutting' => $formData['corte'] ?? 'NO',
        'cutting_details' => $formData['detalles_corte'] ?? '',
        'blocking' => $formData['blocado'] ?? 'NO',
        'blocking_details' => $formData['detalles_blocado'] ?? '',
        'folding' => $formData['doblado'] ?? 'NO',
        'folding_details' => $formData['detalles_doblado'] ?? '',
        'laminating' => $formData['laminado'] ?? 'NO',
        'laminating_details' => $formData['detalles_laminado'] ?? '',
        'spine' => $formData['lomo'] ?? 'NO',
        'gluing' => $formData['pegado'] ?? 'NO',
        'numbering' => $formData['numerado'] ?? 'NO',
        'numbering_details' => $formData['detalles_numerado'] ?? '',
        'sizing' => $formData['sizado'] ?? 'NO',
        'stapling' => $formData['engrapado'] ?? 'NO',
        'die_cutting' => $formData['troquel'] ?? 'NO',
        'die_cutting_details' => $formData['detalles_troquel'] ?? '',
        'varnish' => $formData['barniz'] ?? 'NO',
        'varnish_details' => $formData['detalles_barniz'] ?? '',
        'white_print' => $formData['impresion_blanco'] ?? 'NO',
        'trimming' => $formData['despuntado'] ?? 'NO',
        'trimming_details' => $formData['detalles_despuntado'] ?? '',
        'eyelets' => $formData['ojetes'] ?? 'NO',
        'perforation' => $formData['perforado'] ?? 'NO',
        'perforation_details' => $formData['detalles_perforado'] ?? '',
        'instructions' => $formData['instrucciones'] ?? '',
        'sales_agent' => $formData['agente_de_ventas'] ?? '',
        'request_date' => $formData['fecha_de_solicitud'] ?? $now,
        'status' => 'New',
        'order_type' => 'External',
        'state' => 1,
        'created' => $now,
        'created_by' => 0,
        'modified' => $now,
        'modified_by' => 0,
        'version' => '1.0.0'
    ];
    
    echo "<div class='success'>✓ Order Data Array Created (" . count($orderData) . " fields)</div>";
    echo "<div class='code'>";
    foreach ($orderData as $key => $value) {
        echo "$key => " . (is_string($value) ? "'$value'" : $value) . "\n";
    }
    echo "</div>";
    
    // Step 4: Generate SQL Query (exact copy from WebhookModel)
    echo "<h3>Step 4: Generate SQL Query</h3>";
    
    $db = Factory::getDbo();
    
    // Create the query exactly like WebhookModel does (FIXED VERSION)
    $query = $db->getQuery(true)
        ->insert($db->quoteName('#__ordenproduccion_ordenes'))
        ->columns(array_keys($orderData));
    
    // Add values one by one to avoid array_map issues
    $values = [];
    foreach ($orderData as $value) {
        $values[] = $db->quote($value);
    }
    $query->values(implode(',', $values));
    
    $sql = (string) $query;
    
    echo "<div class='success'>✓ SQL Query Generated</div>";
    echo "<div class='code'>" . htmlspecialchars($sql) . "</div>";
    
    // Step 5: Count Columns and Values
    echo "<h3>Step 5: Count Columns and Values</h3>";
    
    // Extract columns from SQL
    preg_match('/INSERT INTO.*?\((.*?)\).*?VALUES.*?\((.*?)\)/s', $sql, $matches);
    if (isset($matches[1]) && isset($matches[2])) {
        $columns = array_map('trim', explode(',', $matches[1]));
        $values = array_map('trim', explode(',', $matches[2]));
        
        echo "<div class='info'>Columns (" . count($columns) . "):</div>";
        echo "<div class='code'>";
        foreach ($columns as $i => $column) {
            echo ($i + 1) . ". " . $column . "\n";
        }
        echo "</div>";
        
        echo "<div class='info'>Values (" . count($values) . "):</div>";
        echo "<div class='code'>";
        foreach ($values as $i => $value) {
            echo ($i + 1) . ". " . $value . "\n";
        }
        echo "</div>";
        
        if (count($columns) === count($values)) {
            echo "<div class='success'>✅ Column count (" . count($columns) . ") matches value count (" . count($values) . ")</div>";
        } else {
            echo "<div class='error'>❌ Column count (" . count($columns) . ") does NOT match value count (" . count($values) . ")</div>";
        }
    } else {
        echo "<div class='error'>❌ Could not parse SQL query</div>";
    }
    
    // Step 6: Try to Execute the Query
    echo "<h3>Step 6: Execute SQL Query</h3>";
    
    try {
        $db->setQuery($query);
        $db->execute();
        $insertId = $db->insertid();
        
        echo "<div class='success'>✅ SQL Query executed successfully!</div>";
        echo "<div class='info'>Insert ID: $insertId</div>";
        
        // Clean up
        $deleteQuery = "DELETE FROM joomla_ordenproduccion_ordenes WHERE id = $insertId";
        $db->setQuery($deleteQuery);
        $db->execute();
        echo "<div class='info'>✅ Test record cleaned up</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ SQL Query execution failed</div>";
        echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>🎯 Summary</h2>";
    echo "<p>This test replicates the exact logic from WebhookModel without instantiating the model class.</p>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace:</div>";
    echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
}
?>
