<?php
/**
 * Webhook Debug Script
 * Access via: /components/com_ordenproduccion/debug_webhook.php
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use Joomla\CMS\Factory;

echo "<h1>Webhook Debug Script</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

try {
    echo "<h2>1. Testing Joomla Bootstrap</h2>";
    $app = Factory::getApplication('site');
    echo "<div class='success'>✓ Joomla application loaded successfully</div>";
    
    echo "<h2>2. Testing Webhook Controller</h2>";
    $controllerPath = JPATH_BASE . '/components/com_ordenproduccion/src/Controller/WebhookController.php';
    if (file_exists($controllerPath)) {
        echo "<div class='success'>✓ Webhook controller file exists</div>";
        
        // Try to load the controller
        require_once $controllerPath;
        $controller = new \Grimpsa\Component\Ordenproduccion\Site\Controller\WebhookController();
        echo "<div class='success'>✓ Webhook controller instantiated successfully</div>";
        
    } else {
        echo "<div class='error'>✗ Webhook controller file not found at: $controllerPath</div>";
    }
    
    echo "<h2>3. Testing Database Connection</h2>";
    try {
        $db = Factory::getDbo();
        echo "<div class='success'>✓ Database connection successful</div>";
        
        // Test webhook logs table
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_webhook_logs'));
        
        $db->setQuery($query);
        $count = $db->loadResult();
        echo "<div class='success'>✓ Webhook logs table exists (count: $count)</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>✗ Database error: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>4. Testing Webhook Methods</h2>";
    try {
        // Test the process method exists
        if (method_exists($controller, 'process')) {
            echo "<div class='success'>✓ process() method exists</div>";
        } else {
            echo "<div class='error'>✗ process() method not found</div>";
        }
        
        // Test the validateWebhookData method exists
        if (method_exists($controller, 'validateWebhookData')) {
            echo "<div class='success'>✓ validateWebhookData() method exists</div>";
        } else {
            echo "<div class='error'>✗ validateWebhookData() method not found</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>✗ Method test error: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>5. Testing Sample Payload Validation</h2>";
    try {
        $samplePayload = [
            'request_title' => 'Solicitud Ventas a Produccion',
            'form_data' => [
                'client_id' => '7',
                'cliente' => 'Grupo Impre S.A.',
                'nit' => '114441782',
                'valor_factura' => '2500',
                'descripcion_trabajo' => '1000 Flyers Full Color con acabados especiales',
                'color_impresion' => 'Full Color',
                'tiro_retiro' => 'Tiro/Retiro',
                'medidas' => '8.5 x 11',
                'fecha_entrega' => '15/10/2025',
                'material' => 'Husky 250 grms'
            ]
        ];
        
        // Test validation
        $isValid = $controller->validateWebhookData($samplePayload);
        if ($isValid) {
            echo "<div class='success'>✓ Sample payload validation passed</div>";
        } else {
            echo "<div class='error'>✗ Sample payload validation failed</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>✗ Payload validation error: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>6. Testing Component Registration</h2>";
    try {
        $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
        if ($component) {
            echo "<div class='success'>✓ Component booted successfully</div>";
        } else {
            echo "<div class='error'>✗ Component boot failed</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Component boot error: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>7. Testing Webhook URL Access</h2>";
    $baseUrl = $app->get('live_site');
    $webhookUrl = rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.process&format=json';
    
    echo "<div class='info'>Webhook URL: <a href='$webhookUrl' target='_blank'>$webhookUrl</a></div>";
    
    // Test with a simple GET request first
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Content-Type: application/json',
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($webhookUrl, false, $context);
    
    if ($response !== false) {
        echo "<div class='info'>✓ Webhook URL is accessible</div>";
        echo "<div class='info'>Response: " . htmlspecialchars(substr($response, 0, 200)) . "...</div>";
    } else {
        echo "<div class='error'>✗ Webhook URL not accessible or returned error</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Fatal error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h2>Debug Complete</h2>";
echo "<p>If you see any errors above, those are likely causing the 500 error.</p>";
echo "<p>Make sure to run the deployment script to get the latest error display code.</p>";
?>
