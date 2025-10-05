<?php
/**
 * Production Webhook Debug Script
 * Access via: /components/com_ordenproduccion/debug_production_webhook.php
 * This script tests the exact payload you provided to identify the issue
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Production Webhook Debug Script</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;white-space:pre-wrap;}</style>";

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

echo "<h2>🔍 Step-by-Step Webhook Debugging</h2>";

try {
    echo "<h3>Step 1: Testing WebhookModel Loading</h3>";
    $modelPath = JPATH_BASE . '/components/com_ordenproduccion/src/Model/WebhookModel.php';
    if (file_exists($modelPath)) {
        echo "<div class='success'>✓ WebhookModel file exists</div>";
        require_once $modelPath;
        
        $model = new \Grimpsa\Component\Ordenproduccion\Site\Model\WebhookModel();
        echo "<div class='success'>✓ WebhookModel instantiated successfully</div>";
    } else {
        echo "<div class='error'>✗ WebhookModel file not found at: $modelPath</div>";
        exit;
    }
    
    echo "<h3>Step 2: Testing Database Connection</h3>";
    $db = Factory::getDbo();
    echo "<div class='success'>✓ Database connection established</div>";
    
    echo "<h3>Step 3: Testing Order Number Generation</h3>";
    $orderNumber = $model->generateOrderNumber($testPayload['form_data']);
    echo "<div class='info'>Generated order number: <strong>$orderNumber</strong></div>";
    
    echo "<h3>Step 4: Testing Existing Order Check</h3>";
    $existingOrder = $model->findExistingOrder($testPayload);
    if ($existingOrder) {
        echo "<div class='warning'>⚠️ Order already exists with ID: " . $existingOrder->id . "</div>";
        echo "<div class='info'>This means we'll try to UPDATE instead of CREATE</div>";
    } else {
        echo "<div class='success'>✓ No existing order found, will CREATE new order</div>";
    }
    
    echo "<h3>Step 5: Testing Order Creation/Update Process</h3>";
    
    if ($existingOrder) {
        echo "<div class='info'>Attempting to UPDATE existing order...</div>";
        $result = $model->updateOrder($existingOrder->id, $testPayload);
    } else {
        echo "<div class='info'>Attempting to CREATE new order...</div>";
        $result = $model->createOrder($testPayload);
    }
    
    if ($result) {
        echo "<div class='success'>✓ Order processing completed successfully!</div>";
        echo "<div class='info'>Result: " . json_encode($result) . "</div>";
        
        $orderNumber = $model->getLastOrderNumber();
        if ($orderNumber) {
            echo "<div class='info'>Last generated order number: <strong>$orderNumber</strong></div>";
        }
        
        // Test EAV data storage
        echo "<h3>Step 6: Testing EAV Data Storage</h3>";
        $eavResult = $model->storeEAVData($result, $testPayload['form_data']);
        if ($eavResult) {
            echo "<div class='success'>✓ EAV data stored successfully</div>";
        } else {
            echo "<div class='error'>✗ EAV data storage failed</div>";
            echo "<div class='error'>Error: " . $model->getError() . "</div>";
        }
        
    } else {
        echo "<div class='error'>✗ Order processing failed!</div>";
        echo "<div class='error'>Error: " . $model->getError() . "</div>";
        
        // Get detailed errors
        $errors = $model->getErrors();
        if (!empty($errors)) {
            echo "<div class='error'>Detailed errors:</div>";
            echo "<div class='code'>" . json_encode($errors, JSON_PRETTY_PRINT) . "</div>";
        }
    }
    
    echo "<h3>Step 7: Database Verification</h3>";
    
    // Check if order was created in main table
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('orden_de_trabajo') . ' = ' . $db->quote($orderNumber));
    
    $db->setQuery($query);
    $orderRecord = $db->loadObject();
    
    if ($orderRecord) {
        echo "<div class='success'>✓ Order found in database</div>";
        echo "<div class='info'>Order ID: " . $orderRecord->id . "</div>";
        echo "<div class='info'>Client: " . $orderRecord->nombre_del_cliente . "</div>";
        echo "<div class='info'>Description: " . $orderRecord->descripcion_de_trabajo . "</div>";
    } else {
        echo "<div class='error'>✗ Order not found in database</div>";
    }
    
    // Check EAV data
    $eavQuery = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_info'))
        ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($orderNumber));
    
    $db->setQuery($eavQuery);
    $eavRecords = $db->loadObjectList();
    
    if (!empty($eavRecords)) {
        echo "<div class='success'>✓ Found " . count($eavRecords) . " EAV records</div>";
        echo "<div class='info'>EAV attributes stored:</div>";
        echo "<ul>";
        foreach ($eavRecords as $record) {
            echo "<li>" . $record->tipo_de_campo . " = " . substr($record->valor, 0, 50) . "...</li>";
        }
        echo "</ul>";
    } else {
        echo "<div class='warning'>⚠️ No EAV records found</div>";
    }
    
    echo "<h3>Step 8: Testing WebhookController Integration</h3>";
    
    // Test the actual webhook controller
    $controllerPath = JPATH_BASE . '/components/com_ordenproduccion/src/Controller/WebhookController.php';
    if (file_exists($controllerPath)) {
        echo "<div class='success'>✓ WebhookController file exists</div>";
        
        require_once $controllerPath;
        
        // Simulate the webhook controller process
        try {
            $controller = new \Grimpsa\Component\Ordenproduccion\Site\Controller\WebhookController();
            echo "<div class='success'>✓ WebhookController instantiated</div>";
            
            // Use reflection to test the protected method
            $reflection = new ReflectionClass($controller);
            $method = $reflection->getMethod('processOrderData');
            $method->setAccessible(true);
            
            $result = $method->invoke($controller, $testPayload);
            
            echo "<div class='info'>WebhookController result:</div>";
            echo "<div class='code'>" . json_encode($result, JSON_PRETTY_PRINT) . "</div>";
            
            if ($result['success']) {
                echo "<div class='success'>✓ WebhookController processing successful!</div>";
            } else {
                echo "<div class='error'>✗ WebhookController processing failed</div>";
                if (isset($result['debug_info'])) {
                    echo "<div class='error'>Debug info:</div>";
                    echo "<div class='code'>" . json_encode($result['debug_info'], JSON_PRETTY_PRINT) . "</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>✗ WebhookController error: " . $e->getMessage() . "</div>";
            echo "<div class='error'>Stack trace:</div>";
            echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
        }
        
    } else {
        echo "<div class='error'>✗ WebhookController file not found</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Fatal error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace:</div>";
    echo "<div class='code'>" . $e->getTraceAsString() . "</div>";
}

echo "<h2>🎯 Summary</h2>";
echo "<p>This debug script has tested your exact payload step-by-step to identify where the production webhook is failing.</p>";
echo "<p>Check the results above to see which step is causing the issue.</p>";
echo "<p><strong>Next steps:</strong> Based on the results, we can fix the specific issue identified.</p>";
?>
