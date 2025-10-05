<?php
/**
 * Simple Insert Test Script
 * Access via: /components/com_ordenproduccion/test_simple_insert.php
 * This script tests a minimal insert to isolate the column count issue
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Simple Insert Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

try {
    $db = Factory::getDbo();
    
    echo "<h2>🧪 Testing Minimal Insert</h2>";
    
    // Test 1: Minimal required fields only
    echo "<h3>Test 1: Minimal Required Fields</h3>";
    $testData1 = [
        'order_number' => 'TEST-MIN-' . date('Ymd-His'),
        'client_name' => 'Test Client',
        'work_description' => 'Test Description',
        'state' => 1,
        'created' => date('Y-m-d H:i:s'),
        'created_by' => 0
    ];
    
    echo "<div class='code'>";
    foreach ($testData1 as $key => $value) {
        echo "$key => $value\n";
    }
    echo "</div>";
    
    $query1 = $db->getQuery(true)
        ->insert($db->quoteName('joomla_ordenproduccion_ordenes'))
        ->columns(array_keys($testData1))
        ->values(array_map([$db, 'quote'], $testData1));
    
    echo "<div class='info'>SQL Query:</div>";
    echo "<div class='code'>" . $query1 . "</div>";
    
    try {
        $db->setQuery($query1);
        $db->execute();
        $insertId1 = $db->insertid();
        echo "<div class='success'>✅ Test 1 successful! Insert ID: $insertId1</div>";
        
        // Clean up
        $deleteQuery1 = "DELETE FROM joomla_ordenproduccion_ordenes WHERE id = $insertId1";
        $db->setQuery($deleteQuery1);
        $db->execute();
        echo "<div class='info'>✅ Test 1 cleaned up</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Test 1 failed: " . $e->getMessage() . "</div>";
    }
    
    // Test 2: Add more fields gradually
    echo "<h3>Test 2: More Fields</h3>";
    $testData2 = [
        'order_number' => 'TEST-MORE-' . date('Ymd-His'),
        'client_id' => '7',
        'client_name' => 'Test Client',
        'nit' => '123456789',
        'invoice_value' => 1000.00,
        'work_description' => 'Test Description',
        'delivery_date' => '2025-10-15',
        'state' => 1,
        'created' => date('Y-m-d H:i:s'),
        'created_by' => 0,
        'modified' => date('Y-m-d H:i:s'),
        'modified_by' => 0,
        'version' => '1.0.0'
    ];
    
    echo "<div class='code'>";
    foreach ($testData2 as $key => $value) {
        echo "$key => $value\n";
    }
    echo "</div>";
    
    $query2 = $db->getQuery(true)
        ->insert($db->quoteName('joomla_ordenproduccion_ordenes'))
        ->columns(array_keys($testData2))
        ->values(array_map([$db, 'quote'], $testData2));
    
    echo "<div class='info'>SQL Query:</div>";
    echo "<div class='code'>" . $query2 . "</div>";
    
    try {
        $db->setQuery($query2);
        $db->execute();
        $insertId2 = $db->insertid();
        echo "<div class='success'>✅ Test 2 successful! Insert ID: $insertId2</div>";
        
        // Clean up
        $deleteQuery2 = "DELETE FROM joomla_ordenproduccion_ordenes WHERE id = $insertId2";
        $db->setQuery($deleteQuery2);
        $db->execute();
        echo "<div class='info'>✅ Test 2 cleaned up</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Test 2 failed: " . $e->getMessage() . "</div>";
    }
    
    // Test 3: Test with the exact WebhookModel data structure
    echo "<h3>Test 3: WebhookModel Structure</h3>";
    
    // Simulate the WebhookModel createOrder data
    $formData = [
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
        'detalles_corte' => 'Corte recto en guillotina',
        'blocado' => 'SI',
        'detalles_blocado' => 'Blocado de 50 unidades',
        'doblado' => 'SI',
        'detalles_doblado' => 'Doblez a la mitad',
        'laminado' => 'SI',
        'detalles_laminado' => 'Laminado brillante tiro y retiro',
        'lomo' => 'NO',
        'pegado' => 'NO',
        'numerado' => 'SI',
        'detalles_numerado' => 'Numerado consecutivo del 1 al 1000',
        'sizado' => 'NO',
        'engrapado' => 'NO',
        'troquel' => 'SI',
        'detalles_troquel' => 'Troquel circular en esquinas',
        'barniz' => 'SI',
        'detalles_barniz' => 'Barniz UV en logo',
        'impresion_blanco' => 'NO',
        'despuntado' => 'SI',
        'detalles_despuntado' => 'Despunte en esquinas superiores',
        'ojetes' => 'NO',
        'perforado' => 'SI',
        'detalles_perforado' => 'Perforado horizontal para calendario',
        'instrucciones' => 'Entregar en caja de 50 unidades. Cliente recogerá personalmente.',
        'agente_de_ventas' => 'Peter Grant',
        'fecha_de_solicitud' => '2025-10-01 17:00:00'
    ];
    
    $orderNumber = 'TEST-WEBHOOK-' . date('Ymd-His');
    $now = date('Y-m-d H:i:s');
    
    // Create order data exactly like WebhookModel does
    $orderData = [
        'order_number' => $orderNumber,
        'client_id' => $formData['client_id'] ?? '0',
        'client_name' => $formData['cliente'],
        'nit' => $formData['nit'] ?? '',
        'invoice_value' => $formData['valor_factura'] ?? 0,
        'work_description' => $formData['descripcion_trabajo'],
        'print_color' => $formData['color_impresion'] ?? '',
        'dimensions' => $formData['medidas'] ?? '',
        'delivery_date' => '2025-10-15', // Format the date
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
    
    echo "<div class='info'>Order Data (" . count($orderData) . " fields):</div>";
    echo "<div class='code'>";
    foreach ($orderData as $key => $value) {
        echo "$key => $value\n";
    }
    echo "</div>";
    
    $query3 = $db->getQuery(true)
        ->insert($db->quoteName('joomla_ordenproduccion_ordenes'))
        ->columns(array_keys($orderData))
        ->values(array_map([$db, 'quote'], $orderData));
    
    echo "<div class='info'>SQL Query:</div>";
    echo "<div class='code'>" . $query3 . "</div>";
    
    try {
        $db->setQuery($query3);
        $db->execute();
        $insertId3 = $db->insertid();
        echo "<div class='success'>✅ Test 3 successful! Insert ID: $insertId3</div>";
        
        // Clean up
        $deleteQuery3 = "DELETE FROM joomla_ordenproduccion_ordenes WHERE id = $insertId3";
        $db->setQuery($deleteQuery3);
        $db->execute();
        echo "<div class='info'>✅ Test 3 cleaned up</div>";
        
        echo "<h2>✅ SUCCESS!</h2>";
        echo "<p>The WebhookModel data structure works correctly. The issue must be elsewhere.</p>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Test 3 failed: " . $e->getMessage() . "</div>";
        echo "<div class='error'>This confirms the WebhookModel has an issue.</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}
?>
