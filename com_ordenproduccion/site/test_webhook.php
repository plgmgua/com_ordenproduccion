<?php
/**
 * Webhook Test Script
 * Access via: /components/com_ordenproduccion/test_webhook.php
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Webhook Endpoint Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

try {
    $app = Factory::getApplication('site');
    $baseUrl = $app->get('live_site');
    
    echo "<h2>Webhook Endpoints</h2>";
    
    $endpoints = [
        'Main Webhook' => rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.process&format=json',
        'Test Webhook' => rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.test&format=json',
        'Health Check' => rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.health&format=json'
    ];
    
    foreach ($endpoints as $name => $url) {
        echo "<div class='info'><strong>$name:</strong><br>";
        echo "<a href='$url' target='_blank'>$url</a></div><br>";
    }
    
    echo "<h2>Test Webhook Health</h2>";
    
    // Test health endpoint
    $healthUrl = $endpoints['Health Check'];
    echo "<div class='info'>Testing health endpoint: <a href='$healthUrl' target='_blank'>$healthUrl</a></div>";
    
    // Make a simple test request
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json',
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($healthUrl, false, $context);
    
    if ($response !== false) {
        echo "<div class='success'>✓ Health endpoint responded successfully</div>";
        echo "<div class='info'>Response: " . htmlspecialchars($response) . "</div>";
    } else {
        echo "<div class='error'>✗ Health endpoint failed to respond</div>";
    }
    
    echo "<h2>Sample Webhook Payload</h2>";
    echo "<div class='info'>Use this JSON payload to test the main webhook endpoint:</div>";
    echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;'>";
    echo htmlspecialchars(json_encode([
        'request_title' => 'Solicitud Ventas a Produccion',
        'form_data' => [
            'cliente' => 'Test Client',
            'descripcion_trabajo' => 'Test work order description',
            'fecha_entrega' => '15/10/2025',
            'tipo_trabajo' => 'Impresión',
            'cantidad' => '100',
            'observaciones' => 'Test webhook order'
        ]
    ], JSON_PRETTY_PRINT));
    echo "</pre>";
    
    echo "<h2>Test Instructions</h2>";
    echo "<div class='info'>";
    echo "<ol>";
    echo "<li>Copy the main webhook URL above</li>";
    echo "<li>Use a tool like Postman, curl, or your application</li>";
    echo "<li>Send a POST request with the sample JSON payload</li>";
    echo "<li>Check the webhook logs in the admin panel</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h2>curl Command Example</h2>";
    echo "<div class='info'>";
    echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;'>";
    echo "curl -X POST \\<br>";
    echo "  '" . $endpoints['Main Webhook'] . "' \\<br>";
    echo "  -H 'Content-Type: application/json' \\<br>";
    echo "  -d '{\"request_title\":\"Test Order\",\"form_data\":{\"cliente\":\"Test Client\",\"descripcion_trabajo\":\"Test Description\",\"fecha_entrega\":\"15/10/2025\"}}'";
    echo "</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

echo "<h2>Test Complete</h2>";
echo "<p>If you can access the URLs above without login, the webhook is properly configured as a public endpoint.</p>";
?>
