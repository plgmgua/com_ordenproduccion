<?php
/**
 * Test Production Webhook Endpoint
 * Access via: /components/com_ordenproduccion/test_production_webhook.php
 * This script tests the actual webhook endpoint with your payload
 */

echo "<h1>Production Webhook Endpoint Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

// The exact payload from your request
$testPayload = [
    "request_title" => "Solicitud Ventas a Produccion",
    "form_data" => [
        "client_id" => "7",
        "cliente" => "Grupo Impre S.A.",
        "nit" => "114441782",
        "valor_factura" => "2500",
        "descripcion_trabajo" => "1000 Flyers Full Color con acabados especiales",
        "color_impresion" => "Full Color",
        "tiro_retiro" => "Tiro/Retiro",
        "medidas" => "8.5 x 11",
        "fecha_entrega" => "15/10/2025",
        "material" => "Husky 250 grms",
        "cotizacion" => ["/media/com_convertforms/uploads/cotizacion_001.pdf"],
        "arte" => ["/media/com_convertforms/uploads/arte_001.pdf"],
        "corte" => "SI",
        "detalles_corte" => "Corte recto en guillotina",
        "blocado" => "SI",
        "detalles_blocado" => "Blocado de 50 unidades",
        "doblado" => "SI",
        "detalles_doblado" => "Doblez a la mitad",
        "laminado" => "SI",
        "detalles_laminado" => "Laminado brillante tiro y retiro",
        "lomo" => "NO",
        "pegado" => "NO",
        "numerado" => "SI",
        "detalles_numerado" => "Numerado consecutivo del 1 al 1000",
        "sizado" => "NO",
        "engrapado" => "NO",
        "troquel" => "SI",
        "detalles_troquel" => "Troquel circular en esquinas",
        "barniz" => "SI",
        "detalles_barniz" => "Barniz UV en logo",
        "impresion_blanco" => "NO",
        "despuntado" => "SI",
        "detalles_despuntado" => "Despunte en esquinas superiores",
        "ojetes" => "NO",
        "perforado" => "SI",
        "detalles_perforado" => "Perforado horizontal para calendario",
        "instrucciones" => "Entregar en caja de 50 unidades. Cliente recogerá personalmente.",
        "agente_de_ventas" => "Peter Grant",
        "fecha_de_solicitud" => "2025-10-01 17:00:00"
    ]
];

echo "<h2>🌐 Testing Webhook Endpoints</h2>";

// Test both production and test endpoints
$endpoints = [
    'Production Webhook' => 'https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&task=webhook.process&format=json',
    'Test Webhook' => 'https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&task=webhook.test&format=json'
];

foreach ($endpoints as $name => $url) {
    echo "<h3>Testing: $name</h3>";
    echo "<div class='info'>URL: $url</div>";
    
    // Prepare the request
    $postData = json_encode($testPayload);
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_VERBOSE => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    echo "<div class='info'>Sending payload...</div>";
    echo "<div class='code'>" . json_encode($testPayload, JSON_PRETTY_PRINT) . "</div>";
    
    // Execute the request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<div class='info'>HTTP Code: $httpCode</div>";
    
    if ($error) {
        echo "<div class='error'>cURL Error: $error</div>";
    } else {
        echo "<div class='success'>Response received:</div>";
        echo "<div class='code'>$response</div>";
        
        // Try to decode JSON response
        $decodedResponse = json_decode($response, true);
        if ($decodedResponse) {
            if (isset($decodedResponse['success']) && $decodedResponse['success']) {
                echo "<div class='success'>✅ Webhook processed successfully!</div>";
                if (isset($decodedResponse['data'])) {
                    echo "<div class='info'>Data: " . json_encode($decodedResponse['data'], JSON_PRETTY_PRINT) . "</div>";
                }
            } else {
                echo "<div class='error'>❌ Webhook processing failed</div>";
                if (isset($decodedResponse['message'])) {
                    echo "<div class='error'>Message: " . $decodedResponse['message'] . "</div>";
                }
                if (isset($decodedResponse['debug_info'])) {
                    echo "<div class='error'>Debug Info:</div>";
                    echo "<div class='code'>" . json_encode($decodedResponse['debug_info'], JSON_PRETTY_PRINT) . "</div>";
                }
            }
        } else {
            echo "<div class='warning'>⚠️ Response is not valid JSON</div>";
        }
    }
    
    echo "<hr>";
}

echo "<h2>🔧 Manual cURL Commands</h2>";
echo "<p>You can also test these endpoints manually using cURL:</p>";

echo "<h3>Production Webhook:</h3>";
echo "<div class='code'>curl -X POST \\<br>";
echo "  'https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&task=webhook.process&format=json' \\<br>";
echo "  -H 'Content-Type: application/json' \\<br>";
echo "  -d '" . json_encode($testPayload) . "'</div>";

echo "<h3>Test Webhook:</h3>";
echo "<div class='code'>curl -X POST \\<br>";
echo "  'https://grimpsa_webserver.grantsolutions.cc/index.php?option=com_ordenproduccion&task=webhook.test&format=json' \\<br>";
echo "  -H 'Content-Type: application/json' \\<br>";
echo "  -d '" . json_encode($testPayload) . "'</div>";

echo "<h2>📝 Notes</h2>";
echo "<ul>";
echo "<li>Compare the responses between Production and Test webhooks</li>";
echo "<li>Look for differences in error messages or debug info</li>";
echo "<li>Check if the issue is specific to the production endpoint</li>";
echo "<li>Use the debug script above for more detailed analysis</li>";
echo "</ul>";
?>
